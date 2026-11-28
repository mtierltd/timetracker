<template>
	<NcAppContent>
		<div class="view-page">
			<h1 class="page-title">Team calendar</h1>

			<div class="toolbar">
				<NcButton type="tertiary" aria-label="Previous month" @click="changeMonth(-1)">‹</NcButton>
				<strong class="month">{{ monthName }}</strong>
				<NcButton type="tertiary" aria-label="Next month" @click="changeMonth(1)">›</NcButton>
				<span class="hint">Colleagues who share a group with you. Requested absences are striped.</span>
			</div>

			<div class="calendar-wrapper">
				<table class="calendar">
					<thead>
						<tr>
							<th class="user-col" />
							<th v-for="day in days"
								:key="day.date"
								:class="{ weekend: day.weekend, holiday: day.holiday, today: day.date === today }"
								:title="day.holiday || ''">
								<div class="weekday">{{ day.weekday }}</div>
								<div>{{ day.number }}</div>
							</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="user in users" :key="user.userUid">
							<th class="user-col">{{ user.displayName }}</th>
							<td v-for="day in days"
								:key="day.date"
								:class="{ weekend: day.weekend, holiday: day.holiday, today: day.date === today }">
								<div v-if="cell(user.userUid, day.date)"
									class="absence"
									:class="{ requested: cell(user.userUid, day.date).absence.status === 'requested', half: cell(user.userUid, day.date).share < 1 }"
									:style="{ background: cell(user.userUid, day.date).absence.typeColor }"
									:title="tooltip(user, cell(user.userUid, day.date).absence)" />
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="legend">
				<span v-for="type in legend" :key="type.name" class="legend-item">
					<span class="color-dot" :style="{ background: type.color }" /> {{ type.name }}
				</span>
			</div>
		</div>
	</NcAppContent>
</template>

<script>
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import { showError } from '@nextcloud/dialogs'
import { apiGet } from '../api.js'
import { period, statusLabel } from '../absences.js'

function pad(n) {
	return String(n).padStart(2, '0')
}

function currentMonth() {
	const d = new Date()
	return `${d.getFullYear()}-${pad(d.getMonth() + 1)}`
}

export default {
	name: 'TeamAbsencesView',
	components: { NcAppContent, NcButton },
	data() {
		const d = new Date()
		return {
			month: currentMonth(),
			today: `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`,
			users: [],
			absences: [],
			holidays: {},
		}
	},
	computed: {
		monthName() {
			const [y, m] = this.month.split('-').map(Number)
			return new Date(Date.UTC(y, m - 1, 1)).toLocaleDateString(undefined, { timeZone: 'UTC', month: 'long', year: 'numeric' })
		},
		days() {
			const [y, m] = this.month.split('-').map(Number)
			const count = new Date(Date.UTC(y, m, 0)).getUTCDate()
			const days = []
			for (let day = 1; day <= count; day++) {
				const date = new Date(Date.UTC(y, m - 1, day))
				const key = `${this.month}-${pad(day)}`
				days.push({
					date: key,
					number: day,
					weekday: date.toLocaleDateString(undefined, { timeZone: 'UTC', weekday: 'narrow' }),
					weekend: [0, 6].includes(date.getUTCDay()),
					holiday: this.holidays[key] || null,
				})
			}
			return days
		},
		// "user|date" → { absence, share }, for the working days of each absence
		cells() {
			const cells = {}
			for (const absence of this.absences) {
				for (const [date, share] of Object.entries(absence.workingDays)) {
					cells[`${absence.userUid}|${date}`] = { absence, share }
				}
			}
			return cells
		},
		legend() {
			const types = new Map()
			for (const absence of this.absences) {
				types.set(absence.typeName, absence.typeColor)
			}
			return [...types].map(([name, color]) => ({ name, color }))
		},
	},
	async mounted() {
		await this.load()
	},
	methods: {
		cell(userUid, date) {
			return this.cells[`${userUid}|${date}`]
		},
		tooltip(user, absence) {
			const note = absence.note ? `\n${absence.note}` : ''
			return `${user.displayName}: ${absence.typeName} (${statusLabel(absence.status)})\n${period(absence)}${note}`
		},
		async load() {
			try {
				const data = await apiGet('/team-absences', { month: this.month })
				this.users = data.Users || []
				this.absences = data.Absences || []
				this.holidays = Array.isArray(data.Holidays) ? {} : (data.Holidays || {})
			} catch (e) {
				showError(e.message)
			}
		},
		async changeMonth(delta) {
			const [y, m] = this.month.split('-').map(Number)
			const d = new Date(Date.UTC(y, m - 1 + delta, 1))
			this.month = `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}`
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
	flex-wrap: wrap;
}
.month {
	min-width: 140px;
	text-align: center;
}
.hint {
	color: var(--color-text-maxcontrast);
	margin-left: 16px;
}
.calendar-wrapper {
	overflow-x: auto;
}
.calendar {
	border-collapse: collapse;
	font-size: 0.9em;
}
.calendar th,
.calendar td {
	border: 1px solid var(--color-border);
	min-width: 26px;
	height: 30px;
	padding: 2px;
	text-align: center;
}
.calendar .user-col {
	text-align: left;
	padding: 2px 12px 2px 4px;
	white-space: nowrap;
	font-weight: normal;
	position: sticky;
	left: 0;
	background: var(--color-main-background);
}
.weekday {
	color: var(--color-text-maxcontrast);
	font-size: 0.85em;
}
.weekend,
.holiday {
	background: var(--color-background-dark);
}
.today {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -2px;
}
.absence {
	height: 22px;
	border-radius: 4px;
}
.absence.half {
	width: 50%;
}
.absence.requested {
	background-image: repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.45) 0 4px, transparent 4px 8px) !important;
}
.legend {
	display: flex;
	gap: 16px;
	margin-top: 12px;
	flex-wrap: wrap;
}
.color-dot {
	display: inline-block;
	width: 10px;
	height: 10px;
	border-radius: 50%;
}
</style>
