<template>
	<NcAppContent>
		<div class="view-page">
			<h1 class="page-title">Absences</h1>

			<div v-if="balance" class="balance">
				<template v-if="balance.hasEntitlement">
					<strong>{{ formatDays(balance.remaining) }}</strong> of {{ formatDays(balance.entitlement + balance.carriedOver) }} vacation days left in {{ year }}
					<span v-if="balance.carriedOver > 0" class="hint">
						(incl. {{ formatDays(balance.carriedOver) }} carried over<template v-if="balance.carryOverExpiry">, valid until {{ formatDate(balance.carryOverExpiry) }}</template><template v-if="balance.carriedOverExpired > 0">, {{ formatDays(balance.carriedOverExpired) }} expired</template>)
					</span>
					<span class="hint">· {{ formatDays(balance.approved) }} approved · {{ formatDays(balance.requested) }} requested</span>
				</template>
				<span v-else class="hint">No vacation entitlement set for {{ year }}. Please ask an admin.</span>
			</div>

			<form class="new-item-form" @submit.prevent="addAbsence">
				<NcSelect v-model="form.type"
					:options="activeTypes"
					label="name"
					input-label="Type"
					placeholder="Select type"
					style="width: 220px;" />
				<label class="field">
					<span>From</span>
					<input v-model="form.startDate" type="date" required>
				</label>
				<label class="field">
					<span>To</span>
					<input v-model="form.endDate" type="date" :min="form.startDate" required>
				</label>
				<div class="half-days">
					<NcCheckboxRadioSwitch v-model="form.halfDayStart">Half day at start</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch v-if="form.endDate !== form.startDate" v-model="form.halfDayEnd">Half day at end</NcCheckboxRadioSwitch>
				</div>
				<NcTextField v-model="form.note" label="Note (optional)" style="width: 220px;" />
				<NcButton type="primary" native-type="submit">
					{{ form.type && form.type.needsApproval ? 'Request' : 'Add' }}
				</NcButton>
			</form>

			<div class="toolbar">
				<NcButton type="tertiary" aria-label="Previous year" @click="changeYear(-1)">‹</NcButton>
				<strong>{{ year }}</strong>
				<NcButton type="tertiary" aria-label="Next year" @click="changeYear(1)">›</NcButton>
				<label class="field state-field">
					<span>Public holidays of</span>
					<select :value="settings.userState" @change="saveUserState($event.target.value)">
						<option value="">Default ({{ stateName(settings.defaultState) }})</option>
						<option v-for="(name, code) in settings.states" :key="code" :value="code">{{ name }}</option>
					</select>
				</label>
				<label v-if="settings.isAdmin" class="field state-field">
					<span>Default for everyone (admin)</span>
					<select :value="settings.defaultState" @change="saveDefaultState($event.target.value)">
						<option value="">Nationwide holidays only</option>
						<option v-for="(name, code) in settings.states" :key="code" :value="code">{{ name }}</option>
					</select>
				</label>
			</div>

			<table class="entity-table">
				<thead>
					<tr>
						<th>Type</th>
						<th>Period</th>
						<th>Working days</th>
						<th>Status</th>
						<th>Note</th>
						<th />
					</tr>
				</thead>
				<tbody>
					<tr v-if="absences.length === 0">
						<td colspan="6" class="empty">No absences in {{ year }}.</td>
					</tr>
					<tr v-for="absence in absences" :key="absence.id" :class="{ inactive: !isActive(absence) }">
						<td>
							<span class="color-dot" :style="{ background: absence.typeColor }" />
							{{ absence.typeName || '–' }}
						</td>
						<td>{{ period(absence) }}</td>
						<td>{{ formatDays(absence.days) }}</td>
						<td>
							<span class="status" :class="'status-' + absence.status">{{ statusLabel(absence.status) }}</span>
							<div v-if="absence.decisionComment" class="decision-comment">{{ absence.decisionComment }}</div>
						</td>
						<td>{{ absence.note }}</td>
						<td>
							<NcButton v-if="isActive(absence)" type="tertiary" @click="cancelAbsence(absence)">Cancel</NcButton>
						</td>
					</tr>
				</tbody>
			</table>

			<details class="holidays">
				<summary>Public holidays {{ year }} ({{ stateName(effectiveState) }})</summary>
				<ul>
					<li v-for="holiday in holidays" :key="holiday.date">
						{{ formatDate(holiday.date) }} – {{ holiday.name }}
					</li>
				</ul>
			</details>
		</div>

		<NcDialog v-if="confirmVisible"
			name="Confirmation required"
			:message="confirmMessage"
			:buttons="[
				{ label: 'No', callback: () => resolveConfirm(false) },
				{ label: 'Yes', variant: 'error', callback: () => resolveConfirm(true) },
			]" />
	</NcAppContent>
</template>

<script>
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { apiGet, apiPost } from '../api.js'
import { formatDate, formatDays, period, statusLabel } from '../absences.js'

function today() {
	const d = new Date()
	return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

export default {
	name: 'AbsencesView',
	components: { NcAppContent, NcButton, NcCheckboxRadioSwitch, NcDialog, NcSelect, NcTextField },
	data() {
		return {
			year: new Date().getFullYear(),
			types: [],
			absences: [],
			holidays: [],
			balance: null,
			settings: { states: {}, defaultState: '', userState: '', isAdmin: false },
			form: { type: null, startDate: today(), endDate: today(), halfDayStart: false, halfDayEnd: false, note: '' },
			confirmVisible: false,
			confirmMessage: '',
			confirmResolver: null,
		}
	},
	computed: {
		activeTypes() {
			return this.types.filter((type) => !type.archived)
		},
		effectiveState() {
			return this.settings.userState || this.settings.defaultState
		},
	},
	watch: {
		'form.startDate'(value) {
			if (this.form.endDate < value) {
				this.form.endDate = value
			}
		},
	},
	async mounted() {
		await Promise.all([this.loadTypes(), this.loadSettings()])
		await this.reload()
	},
	methods: {
		formatDate,
		formatDays,
		period,
		statusLabel,
		isActive(absence) {
			return absence.status === 'requested' || absence.status === 'approved'
		},
		stateName(code) {
			return code ? this.settings.states[code] : 'nationwide'
		},
		async loadTypes() {
			const data = await apiGet('/absence-types')
			this.types = data.AbsenceTypes || []
			this.form.type = this.activeTypes[0] || null
		},
		async loadSettings() {
			this.settings = await apiGet('/absence-settings')
		},
		async reload() {
			const [absences, holidays, balance] = await Promise.all([
				apiGet('/absences', { year: this.year }),
				apiGet('/holidays', { year: this.year }),
				apiGet('/vacation-balance', { year: this.year }),
			])
			this.absences = absences.Absences || []
			this.holidays = holidays.Holidays || []
			this.balance = balance
		},
		async changeYear(delta) {
			this.year += delta
			await this.reload()
		},
		async addAbsence() {
			try {
				const absence = await apiPost('/absences', {
					typeId: this.form.type?.id,
					startDate: this.form.startDate,
					endDate: this.form.endDate,
					halfDayStart: this.form.halfDayStart,
					halfDayEnd: this.form.endDate !== this.form.startDate && this.form.halfDayEnd,
					note: this.form.note,
				})
				showSuccess(absence.status === 'requested' ? 'Absence requested' : 'Absence added')
				this.form.note = ''
				this.form.halfDayStart = false
				this.form.halfDayEnd = false
				this.year = Number(absence.startDate.slice(0, 4))
				await this.reload()
			} catch (e) {
				showError(e.message)
			}
		},
		async cancelAbsence(absence) {
			if (!(await this.confirm(`Cancel your ${absence.typeName || 'absence'} ${this.period(absence)}?`))) return
			try {
				await apiPost(`/absences/${absence.id}/cancel`)
				await this.reload()
			} catch (e) {
				showError(e.message)
			}
		},
		async saveUserState(state) {
			await this.saveSettings({ userState: state })
		},
		async saveDefaultState(state) {
			await this.saveSettings({ defaultState: state })
		},
		async saveSettings(body) {
			try {
				Object.assign(this.settings, await apiPost('/absence-settings', body))
				await this.reload()
			} catch (e) {
				showError(e.message)
			}
		},
		confirm(message) {
			this.confirmMessage = message
			this.confirmVisible = true
			return new Promise((resolve) => {
				this.confirmResolver = resolve
			})
		},
		resolveConfirm(value) {
			this.confirmVisible = false
			this.confirmResolver?.(value)
		},
	},
}
</script>

<style scoped>
.view-page {
	padding: 50px 20px 20px;
}
.balance {
	padding: 12px 16px;
	margin-bottom: 16px;
	border-radius: var(--border-radius-large, 10px);
	background: var(--color-background-hover);
}
.hint {
	color: var(--color-text-maxcontrast);
}
.new-item-form,
.toolbar {
	display: flex;
	align-items: flex-end;
	gap: 8px;
	padding-bottom: 16px;
	flex-wrap: wrap;
}
.toolbar {
	align-items: center;
}
.field {
	display: flex;
	flex-direction: column;
	gap: 2px;
	font-size: 0.9em;
	color: var(--color-text-maxcontrast);
}
.field input,
.field select {
	height: 34px;
	color: var(--color-main-text);
}
.state-field {
	margin-left: 16px;
}
.half-days {
	display: flex;
	flex-direction: column;
}
.entity-table {
	width: 100%;
	border-collapse: collapse;
}
.entity-table th,
.entity-table td {
	padding: 8px 16px;
	text-align: left;
	border-bottom: 1px solid var(--color-border);
	vertical-align: top;
}
.inactive td {
	color: var(--color-text-maxcontrast);
}
.inactive td:nth-child(2) {
	text-decoration: line-through;
}
.empty {
	color: var(--color-text-maxcontrast);
	font-style: italic;
}
.color-dot {
	display: inline-block;
	width: 10px;
	height: 10px;
	border-radius: 50%;
	margin-right: 6px;
}
.status {
	padding: 2px 8px;
	border-radius: var(--border-radius-pill, 12px);
	background: var(--color-background-dark);
}
.status-approved {
	background: var(--color-success);
	color: var(--color-primary-element-text, #fff);
}
.status-requested {
	background: var(--color-warning);
	color: var(--color-main-text);
}
.status-rejected {
	background: var(--color-error);
	color: var(--color-primary-element-text, #fff);
}
.decision-comment {
	font-size: 0.9em;
	color: var(--color-text-maxcontrast);
	margin-top: 4px;
}
.holidays {
	margin-top: 24px;
}
.holidays summary {
	cursor: pointer;
	font-weight: bold;
}
</style>
