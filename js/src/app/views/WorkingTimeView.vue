<template>
	<NcAppContent>
		<div class="view-page">
			<h1 class="page-title">Working time</h1>

			<div class="toolbar">
				<NcButton type="tertiary" aria-label="Previous month" @click="changeMonth(-1)">‹</NcButton>
				<strong class="month">{{ monthName }}</strong>
				<NcButton type="tertiary" aria-label="Next month" @click="changeMonth(1)">›</NcButton>
			</div>

			<div v-if="account" class="summary">
				<div class="tile">
					<span class="label">Target</span>
					<span class="value">{{ hours(account.target) }}</span>
				</div>
				<div class="tile">
					<span class="label">Actual</span>
					<span class="value">{{ hours(account.actual) }}</span>
				</div>
				<div class="tile">
					<span class="label">Balance {{ monthName }}</span>
					<span class="value" :class="sign(account.balance)">{{ signedHours(account.balance) }}</span>
				</div>
				<div class="tile">
					<span class="label">Balance {{ account.month.slice(0, 4) }} until today</span>
					<span class="value" :class="sign(account.yearBalance)">{{ account.yearBalance === null ? '–' : signedHours(account.yearBalance) }}</span>
				</div>
			</div>

			<p v-if="account" class="hint">
				Work model: {{ modelText }}<template v-if="account.workModel.isDefault"> (default, an admin can change it)</template>.
				<template v-if="account.workModel.accountStart">Counted from {{ formatDate(account.workModel.accountStart) }}.</template>
				Days after today don't count towards the balances.
			</p>

			<table v-if="account" class="entity-table">
				<thead>
					<tr>
						<th>Day</th>
						<th>Target</th>
						<th>Recorded</th>
						<th>Absence</th>
						<th>Actual</th>
						<th>Difference</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="day in account.days"
						:key="day.date"
						:class="{ off: day.model === 0 || day.holiday, future: !counts(day) }">
						<td>
							{{ formatDate(day.date) }}
							<span v-if="day.holiday" class="holiday">{{ day.holiday }}</span>
						</td>
						<td>{{ day.target ? hours(day.target) : '' }}</td>
						<td>{{ day.worked ? hours(day.worked) : '' }}</td>
						<td>{{ day.absence ? hours(day.absence) : '' }}</td>
						<td>{{ day.actual ? hours(day.actual) : '' }}</td>
						<td :class="sign(day.actual - day.target)">
							{{ counts(day) && (day.target || day.actual) ? signedHours(day.actual - day.target) : '' }}
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</NcAppContent>
</template>

<script>
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import { showError } from '@nextcloud/dialogs'
import { apiGet } from '../api.js'
import { formatDate, hours, signedHours } from '../absences.js'

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

function currentMonth() {
	const d = new Date()
	return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

export default {
	name: 'WorkingTimeView',
	components: { NcAppContent, NcButton },
	data() {
		return {
			month: currentMonth(),
			account: null,
		}
	},
	computed: {
		monthName() {
			const [y, m] = this.month.split('-').map(Number)
			return new Date(Date.UTC(y, m - 1, 1)).toLocaleDateString(undefined, { timeZone: 'UTC', month: 'long', year: 'numeric' })
		},
		modelText() {
			const minutes = this.account.workModel.minutes
			const parts = WEEKDAYS.map((day, i) => minutes[i + 1] ? `${day} ${hours(minutes[i + 1])}` : null).filter(Boolean)
			const total = Object.values(minutes).reduce((a, b) => a + b, 0)
			return `${parts.join(', ') || 'no working days'} (${hours(total)} per week)`
		},
	},
	async mounted() {
		await this.load()
	},
	methods: {
		formatDate,
		hours,
		signedHours,
		counts(day) {
			return day.date >= this.account.countFrom && day.date <= this.account.today
		},
		sign(minutes) {
			if (minutes === null || minutes === 0) return ''
			return minutes > 0 ? 'positive' : 'negative'
		},
		async load() {
			try {
				this.account = await apiGet('/time-account', { month: this.month })
			} catch (e) {
				showError(e.message)
			}
		},
		async changeMonth(delta) {
			const [y, m] = this.month.split('-').map(Number)
			const d = new Date(Date.UTC(y, m - 1 + delta, 1))
			this.month = `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}`
			await this.load()
		},
	},
}
</script>

<style scoped>
.view-page {
	padding: 50px 20px 20px;
}
.toolbar {
	display: flex;
	align-items: center;
	gap: 8px;
	padding-bottom: 16px;
}
.month {
	min-width: 140px;
	text-align: center;
}
.summary {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
	margin-bottom: 12px;
}
.tile {
	display: flex;
	flex-direction: column;
	padding: 12px 16px;
	min-width: 160px;
	border-radius: var(--border-radius-large, 10px);
	background: var(--color-background-hover);
}
.tile .label {
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}
.tile .value {
	font-size: 1.5em;
	font-weight: bold;
}
.hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
}
.entity-table {
	width: 100%;
	border-collapse: collapse;
}
.entity-table th,
.entity-table td {
	padding: 6px 16px;
	text-align: left;
	border-bottom: 1px solid var(--color-border);
}
.off td {
	background: var(--color-background-dark);
}
.future td {
	color: var(--color-text-maxcontrast);
}
.holiday {
	margin-left: 8px;
	font-size: 0.9em;
	color: var(--color-text-maxcontrast);
}
.positive {
	color: var(--color-success-text, var(--color-success));
}
.negative {
	color: var(--color-error-text, var(--color-error));
}
</style>
