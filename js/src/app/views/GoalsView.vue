<template>
	<NcAppContent>
		<div class="view-page">
		<h1 class="page-title">Goals</h1>

		<form class="new-item-form" @submit.prevent="addGoal">
			<NcSelect v-model="newGoalProject"
				:options="projects"
				label="name"
				placeholder="Select project"
				style="width: 220px;" />
			<NcTextField v-model="newGoalHours" label="Hours" placeholder="# hours" />
			<select v-model="newGoalInterval" class="interval-select">
				<option value="Weekly">Weekly</option>
				<option value="Monthly">Monthly</option>
			</select>
			<select v-model="newGoalDays" class="interval-select" aria-label="Counts on">
				<option value="1111100">Monday to Friday</option>
				<option value="1111111">Every day</option>
			</select>
			<NcButton type="primary" native-type="submit">Add goal</NcButton>
		</form>

		<div class="table-wrapper">
		<table class="entity-table">
			<thead>
				<tr>
					<th>#</th>
					<th>Project</th>
					<th>Target Hours</th>
					<th>Interval</th>
					<th>Started At</th>
					<th>Hours spent current interval</th>
					<th>Past Debt in Hours</th>
					<th>Remaining Hours</th>
					<th>Total Remaining Hours</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="(goal, index) in goals" :key="goal.id">
					<td>{{ index + 1 }}</td>
					<td>{{ goal.projectName || '–' }}</td>
					<!-- goals with an unknown interval or no hours, which add-goal accepted before -->
					<td v-if="goal.invalid" colspan="7" class="invalid-goal">
						This goal has no valid {{ missingParts(goal) }}. Please delete it and add it again.
					</td>
					<template v-else>
						<td>
							{{ goal.currentTarget }}<span v-if="goal.skippedDaysCurrentPeriod" class="skip-note"> of {{ goal.hours }}</span>
							<div v-if="goal.skippedDaysCurrentPeriod" class="skip-note">
								{{ goal.skippedDaysCurrentPeriod }} of {{ goal.countedDaysCurrentPeriod }} days skipped this {{ periodWord(goal) }}
							</div>
							<div class="skip-note">Counts {{ daysText(goal.days) }}</div>
							<div class="goal-actions">
								<NcButton type="tertiary" size="small" @click="skipToday(goal)">Skip today</NcButton>
								<NcButton type="tertiary" size="small" @click="openSkipDialog(goal)">
									Skip days…<template v-if="goal.skips.length"> ({{ goal.skips.length }})</template>
								</NcButton>
							</div>
						</td>
						<td>{{ goal.interval }}</td>
						<td>{{ new Date(goal.createdAt * 1000).toDateString() }}</td>
						<td>{{ goal.workedHoursCurrentPeriod }}</td>
						<td>{{ goal.debtHours }}</td>
						<td>{{ goal.remainingHours }}</td>
						<td>{{ goal.totalRemainingHours }}</td>
					</template>
					<td>
						<NcButton type="tertiary" aria-label="Delete" @click="removeGoal(goal)">
							<template #icon><span class="icon-close" /></template>
						</NcButton>
					</td>
				</tr>
			</tbody>
		</table>
		</div>
		</div>

		<NcDialog v-if="skipGoal"
			:name="'Skip days'"
			@closing="skipGoal = null">
			<p class="dialog-text">
				<strong>{{ skipGoal.projectName || 'This goal' }}</strong>:
				skipped days only apply to this goal. They reduce its target by their share of the
				{{ periodWord(skipGoal) }}: with {{ skipGoal.hours }} h a {{ periodWord(skipGoal) }} on
				{{ exampleDays(skipGoal) }} days, skipping 2 days leaves {{ exampleTarget(skipGoal) }} h.
				Time you work on skipped days still counts.
			</p>
			<form class="skip-form" @submit.prevent="skipRange(skipGoal)">
				<label>
					From
					<input v-model="skipFrom" type="date" required>
				</label>
				<label>
					To
					<input v-model="skipTo" type="date" :min="skipFrom" required>
				</label>
				<NcButton type="primary" native-type="submit">Skip</NcButton>
			</form>

			<h3>Counts on</h3>
			<div class="weekdays">
				<label v-for="(day, i) in weekdays" :key="day">
					<input type="checkbox"
						:checked="skipGoal.days[i] === '1'"
						@change="toggleDay(skipGoal, i, $event.target.checked)">
					{{ day }}
				</label>
			</div>

			<h3 v-if="skipGoal.skips.length">Skipped</h3>
			<ul class="skipped-list">
				<li v-for="skip in skipGoal.skips" :key="skip.id">
					{{ formatRange(skip) }}
					<NcButton type="tertiary" @click="removeSkip(skipGoal, skip)">Remove</NcButton>
				</li>
			</ul>
		</NcDialog>

		<NcDialog v-if="confirmVisible"
			name="Confirmation required"
			:message="confirmMessage"
			:buttons="[
				{ label: 'Cancel', callback: () => resolveConfirm(false) },
				{ label: 'Confirm', variant: 'error', callback: () => resolveConfirm(true) },
			]" />
	</NcAppContent>
</template>

<script>
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { apiGet, apiPost } from '../api.js'

export default {
	name: 'GoalsView',
	components: { NcAppContent, NcButton, NcTextField, NcSelect, NcDialog },
	data() {
		return {
			goals: [],
			projects: [],
			newGoalProject: null,
			newGoalHours: '',
			newGoalInterval: 'Weekly',
			confirmVisible: false,
			confirmMessage: '',
			confirmResolver: null,
			newGoalDays: '1111100',
			skipGoal: null,
			skipFrom: '',
			skipTo: '',
			weekdays: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
		}
	},
	async mounted() {
		await Promise.all([this.loadGoals(), this.loadProjects()])
	},
	methods: {
		async loadGoals() {
			const data = await apiGet('/goals')
			this.goals = data.Goals || []
		},
		async loadProjects() {
			const data = await apiGet('/projects')
			this.projects = data.Projects || []
		},
		async addGoal() {
			if (!this.newGoalHours.trim()) return
			try {
				await apiPost('/add-goal', {
					projectId: this.newGoalProject?.id ?? '',
					hours: this.newGoalHours,
					interval: this.newGoalInterval,
					days: this.newGoalDays,
				})
				this.newGoalHours = ''
				await this.loadGoals()
			} catch (e) {
				showError(e.message)
			}
		},
		periodWord(goal) {
			return goal.interval === 'Monthly' ? 'month' : 'week'
		},
		daysText(days) {
			if (days === '1111111') return 'every day'
			if (days === '1111100') return 'Monday to Friday'
			return this.weekdays.filter((day, i) => days[i] === '1').join(', ')
		},
		// counted days of a typical week or month, for the example in the dialog
		exampleDays(goal) {
			const perWeek = goal.days.split('').filter((d) => d === '1').length
			return goal.interval === 'Monthly' ? Math.round(perWeek * 52 / 12) : perWeek
		},
		exampleTarget(goal) {
			const days = this.exampleDays(goal)
			return days <= 2 ? 0 : Math.round(goal.hours * (days - 2) / days * 10) / 10
		},
		// "2026-12-21" – "2026-12-24" → "Dec 21 – Dec 24, 2026"
		formatRange(skip) {
			const format = (date, options) => {
				const [y, m, d] = date.split('-').map(Number)
				return new Date(Date.UTC(y, m - 1, d)).toLocaleDateString(undefined, { timeZone: 'UTC', ...options })
			}
			const full = { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }
			if (skip.startDate === skip.endDate) {
				return format(skip.startDate, full)
			}
			return `${format(skip.startDate, { weekday: 'short', day: 'numeric', month: 'short' })} – ${format(skip.endDate, full)}`
		},
		openSkipDialog(goal) {
			this.skipGoal = goal
			this.skipFrom = ''
			this.skipTo = ''
		},
		async send(path, body, goalId) {
			try {
				const data = await apiPost(path, body)
				this.goals = data.Goals || []
				if (this.skipGoal) {
					this.skipGoal = this.goals.find((g) => g.id === goalId) || null
				}
				return true
			} catch (e) {
				showError(e.message)
				return false
			}
		},
		async skipToday(goal) {
			if (await this.send(`/skip-goal/${goal.id}`, {}, goal.id)) {
				showSuccess(`Today is skipped for ${goal.projectName || 'this goal'}`)
			}
		},
		async skipRange(goal) {
			if (await this.send(`/skip-goal/${goal.id}`, { startDate: this.skipFrom, endDate: this.skipTo || this.skipFrom }, goal.id)) {
				this.skipFrom = ''
				this.skipTo = ''
			}
		},
		async removeSkip(goal, skip) {
			await this.send(`/delete-goal-skip/${goal.id}/${skip.id}`, {}, goal.id)
		},
		async toggleDay(goal, index, checked) {
			const days = goal.days.split('')
			days[index] = checked ? '1' : '0'
			if (!(await this.send(`/goal-days/${goal.id}`, { days: days.join('') }, goal.id))) {
				// refused (e.g. no day left): show the saved days again
				this.skipGoal = { ...goal }
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
		// What an invalid goal lacks, e.g. "hours and interval"
		missingParts(goal) {
			const parts = []
			if (!goal.projectId) parts.push('project')
			if (!(goal.hours >= 1)) parts.push('hours')
			if (!['Weekly', 'Monthly'].includes(goal.interval)) parts.push('interval')
			return parts.join(' and ').replace(/ and (?=.* and )/, ', ')
		},
		async removeGoal(goal) {
			const what = goal.projectName ? `the goal for "${goal.projectName}"` : 'this goal'
			if (!(await this.confirm(`Are you sure you want to delete ${what}?`))) return
			try {
				await apiPost(`/delete-goal/${goal.id}`)
				await this.loadGoals()
			} catch (e) {
				showError(e.message)
			}
		},
	},
}
</script>

<style scoped>
.skip-note {
	font-size: 0.9em;
	color: var(--color-text-maxcontrast);
}
.table-wrapper {
	overflow-x: auto;
}
.goal-actions {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
	margin-top: 4px;
	margin-left: -8px;
}
.dialog-text {
	margin-bottom: 12px;
}
.skip-form {
	display: flex;
	align-items: flex-end;
	gap: 8px;
	margin-bottom: 12px;
}
.skip-form label {
	display: flex;
	flex-direction: column;
}
.weekdays {
	display: flex;
	flex-wrap: wrap;
	gap: 4px 12px;
	margin-bottom: 12px;
}
.weekdays label {
	display: inline-flex;
	align-items: center;
	gap: 4px;
}
.weekdays input[type="checkbox"] {
	margin: 0;
	min-height: 0;
}
.skipped-list li {
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.invalid-goal {
	color: var(--color-text-maxcontrast);
	font-style: italic;
}
.view-page {
	padding: 50px 20px 20px;
}
.new-item-form {
	display: flex;
	align-items: flex-end;
	gap: 8px;
	padding-bottom: 16px;
	flex-wrap: wrap;
}
.interval-select {
	height: 34px;
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
</style>
