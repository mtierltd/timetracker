<template>
	<NcAppContent>
		<div class="view-page">
			<h1 class="page-title">Absences Admin</h1>

			<h2>Requests</h2>
			<table class="entity-table">
				<thead>
					<tr>
						<th>User</th>
						<th>Type</th>
						<th>Period</th>
						<th>Working days</th>
						<th>Note</th>
						<th>Comment</th>
						<th />
					</tr>
				</thead>
				<tbody>
					<tr v-if="requests.length === 0">
						<td colspan="7" class="empty">No open requests.</td>
					</tr>
					<tr v-for="absence in requests" :key="absence.id">
						<td>{{ absence.displayName }}</td>
						<td>
							<span class="color-dot" :style="{ background: absence.typeColor }" />
							{{ absence.typeName }}
						</td>
						<td>{{ period(absence) }}</td>
						<td>{{ formatDays(absence.days) }}</td>
						<td>{{ absence.note }}</td>
						<td><input v-model="comments[absence.id]" type="text" placeholder="Optional"></td>
						<td class="actions">
							<NcButton type="primary" @click="decide(absence, true)">Approve</NcButton>
							<NcButton type="error" @click="decide(absence, false)">Reject</NcButton>
						</td>
					</tr>
				</tbody>
			</table>

			<h2>
				Vacation entitlements
				<NcButton type="tertiary" aria-label="Previous year" @click="changeYear(-1)">‹</NcButton>
				{{ year }}
				<NcButton type="tertiary" aria-label="Next year" @click="changeYear(1)">›</NcButton>
			</h2>
			<table class="entity-table">
				<thead>
					<tr>
						<th>User</th>
						<th>Days</th>
						<th>Carried over</th>
						<th>Carried over valid until</th>
						<th>Approved</th>
						<th>Requested</th>
						<th>Left</th>
						<th />
					</tr>
				</thead>
				<tbody>
					<tr v-for="user in users" :key="user.userUid">
						<td>{{ user.displayName }}</td>
						<td><input v-model="user.edit.days" type="number" min="0" step="0.5"></td>
						<td><input v-model="user.edit.carriedOver" type="number" min="0" step="0.5"></td>
						<td><input v-model="user.edit.carryOverExpiry" type="date"></td>
						<td>{{ formatDays(user.balance.approved) }}</td>
						<td>{{ formatDays(user.balance.requested) }}</td>
						<td>{{ user.balance.hasEntitlement ? formatDays(user.balance.remaining) : '–' }}</td>
						<td>
							<NcButton type="secondary" :disabled="!isChanged(user)" @click="saveEntitlement(user)">Save</NcButton>
						</td>
					</tr>
				</tbody>
			</table>

			<h2>Work models</h2>
			<p class="hint">Hours per weekday, the base of the target hours. Users without a model work 8 hours Monday to Friday.</p>
			<table class="entity-table">
				<thead>
					<tr>
						<th>User</th>
						<th v-for="day in weekdays" :key="day">{{ day }}</th>
						<th>Count from</th>
						<th />
					</tr>
				</thead>
				<tbody>
					<tr v-for="user in users" :key="user.userUid">
						<td>
							{{ user.displayName }}
							<span v-if="user.workModel.isDefault" class="hint">(default)</span>
						</td>
						<td v-for="(day, i) in weekdays" :key="day">
							<input v-model="user.model.hours[i + 1]" type="number" min="0" max="24" step="0.25" class="hours-input">
						</td>
						<td><input v-model="user.model.accountStart" type="date"></td>
						<td>
							<NcButton type="secondary" @click="saveWorkModel(user)">Save</NcButton>
						</td>
					</tr>
				</tbody>
			</table>

			<h2>Absence types</h2>
			<table class="entity-table">
				<thead>
					<tr>
						<th>Name</th>
						<th>Colour</th>
						<th>Counts as</th>
						<th>Deducts vacation</th>
						<th>Needs approval</th>
						<th>Colleagues see</th>
						<th>Archived</th>
						<th />
					</tr>
				</thead>
				<tbody>
					<tr v-for="type in typeRows" :key="type.id || 'new'">
						<td><input v-model="type.name" type="text" :placeholder="type.id ? '' : 'New type'"></td>
						<td><input v-model="type.color" type="color"></td>
						<td>
							<select v-model="type.countsAs">
								<option value="target_reduction">Reduces target hours</option>
								<option value="work_time">Working time</option>
							</select>
						</td>
						<td><input v-model="type.deductsVacation" type="checkbox"></td>
						<td><input v-model="type.needsApproval" type="checkbox"></td>
						<td>
							<select v-model="type.visibility">
								<option value="reason">The type</option>
								<option value="busy_only">Only "absent"</option>
							</select>
						</td>
						<td><input v-model="type.archived" type="checkbox" :disabled="!type.id"></td>
						<td>
							<NcButton type="secondary" @click="saveType(type)">{{ type.id ? 'Save' : 'Add' }}</NcButton>
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
import { showError, showSuccess } from '@nextcloud/dialogs'
import { apiGet, apiPost } from '../api.js'
import { formatDays, period } from '../absences.js'

function newType() {
	return { id: null, name: '', color: '#757575', countsAs: 'target_reduction', deductsVacation: false, needsApproval: true, visibility: 'reason', archived: false }
}

export default {
	name: 'AbsencesAdminView',
	components: { NcAppContent, NcButton },
	data() {
		return {
			year: new Date().getFullYear(),
			requests: [],
			comments: {},
			users: [],
			typeRows: [],
			weekdays: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
		}
	},
	async mounted() {
		await Promise.all([this.loadRequests(), this.loadUsers(), this.loadTypes()])
	},
	methods: {
		formatDays,
		period,
		async loadRequests() {
			this.requests = (await apiGet('/absences-requested')).Absences || []
		},
		async loadUsers() {
			const data = await apiGet('/vacation-entitlements', { year: this.year })
			this.users = (data.Users || []).map((user) => ({ ...user, edit: this.editValues(user.balance), model: this.modelValues(user.workModel) }))
		},
		async loadTypes() {
			const types = (await apiGet('/absence-types')).AbsenceTypes || []
			this.typeRows = [...types.map((type) => ({ ...type })), newType()]
		},
		editValues(balance) {
			return {
				days: balance.hasEntitlement ? balance.entitlement : '',
				carriedOver: balance.hasEntitlement ? balance.carriedOver : '',
				carryOverExpiry: balance.carryOverExpiry || (balance.hasEntitlement ? '' : `${this.year}-03-31`),
			}
		},
		modelValues(workModel) {
			const hours = {}
			for (let day = 1; day <= 7; day++) {
				hours[day] = workModel.minutes[day] / 60
			}
			return { hours, accountStart: workModel.accountStart || '' }
		},
		async saveWorkModel(user) {
			const minutes = {}
			for (let day = 1; day <= 7; day++) {
				minutes[day] = Math.round(Number(user.model.hours[day] || 0) * 60)
			}
			try {
				user.workModel = await apiPost('/work-models', { userUid: user.userUid, minutes, accountStart: user.model.accountStart })
				user.model = this.modelValues(user.workModel)
				showSuccess('Saved')
			} catch (e) {
				showError(e.message)
			}
		},
		isChanged(user) {
			const original = this.editValues(user.balance)
			return ['days', 'carriedOver', 'carryOverExpiry'].some((key) => String(user.edit[key]) !== String(original[key]))
		},
		async changeYear(delta) {
			this.year += delta
			await this.loadUsers()
		},
		async decide(absence, approve) {
			try {
				await apiPost(`/absences/${absence.id}/decide`, { approve, comment: this.comments[absence.id] || '' })
				showSuccess(approve ? 'Approved' : 'Rejected')
				await Promise.all([this.loadRequests(), this.loadUsers()])
			} catch (e) {
				showError(e.message)
			}
		},
		async saveEntitlement(user) {
			try {
				user.balance = await apiPost(`/vacation-entitlements?year=${this.year}`, {
					userUid: user.userUid,
					days: user.edit.days === '' ? 0 : user.edit.days,
					carriedOver: user.edit.carriedOver === '' ? 0 : user.edit.carriedOver,
					carryOverExpiry: user.edit.carryOverExpiry,
				})
				user.edit = this.editValues(user.balance)
				showSuccess('Saved')
			} catch (e) {
				showError(e.message)
			}
		},
		async saveType(type) {
			try {
				await apiPost('/absence-types', type)
				showSuccess(type.id ? 'Saved' : 'Added')
				await this.loadTypes()
			} catch (e) {
				showError(e.message)
			}
		},
	},
}
</script>

<style scoped>
.view-page {
	padding: 50px 20px 20px;
}
h2 {
	display: flex;
	align-items: center;
	gap: 4px;
	margin-top: 32px;
}
.entity-table {
	width: 100%;
	border-collapse: collapse;
}
.entity-table th,
.entity-table td {
	padding: 8px 12px;
	text-align: left;
	border-bottom: 1px solid var(--color-border);
}
.entity-table input[type="number"] {
	width: 80px;
}
.hint {
	color: var(--color-text-maxcontrast);
	font-weight: normal;
}
.entity-table input.hours-input {
	width: 64px;
}
.actions {
	display: flex;
	gap: 8px;
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
</style>
