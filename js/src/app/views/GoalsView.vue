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
							{{ goal.currentPeriodSkipped ? 0 : goal.hours }}
							<div v-if="goal.currentPeriodSkipped" class="skip-note">Skipped this {{ periodWord(goal) }}</div>
							<div v-else-if="goal.skippedPeriods.length" class="skip-note">{{ skippedText(goal) }}</div>
							<div class="goal-actions">
								<NcButton type="tertiary" size="small" @click="toggleCurrentSkip(goal)">
									{{ goal.currentPeriodSkipped ? 'Undo skip' : `Skip this ${periodWord(goal)}` }}
								</NcButton>
								<NcButton type="tertiary" size="small" @click="openSkipDialog(goal)">Skip…</NcButton>
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
			:name="`Skip ${skipGoal.projectName || 'goal'}`"
			@closing="skipGoal = null">
			<p class="dialog-text">
				In a skipped {{ periodWord(skipGoal) }} the goal's target is 0, e.g. during vacation.
				Time you work in it still counts.
			</p>
			<form class="skip-form" @submit.prevent="skipDate(skipGoal, skipDateValue)">
				<label>
					A day in the {{ periodWord(skipGoal) }}
					<input v-model="skipDateValue" type="date" required>
				</label>
				<NcButton type="primary" native-type="submit">Skip</NcButton>
			</form>
			<h3 v-if="skipGoal.skippedPeriods.length">Skipped</h3>
			<ul class="skipped-list">
				<li v-for="period in skipGoal.skippedPeriods" :key="period">
					{{ formatPeriod(skipGoal, period) }}
					<NcButton type="tertiary" @click="unskip(skipGoal, period)">Undo</NcButton>
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
import { showError } from '@nextcloud/dialogs'
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
			skipGoal: null,
			skipDateValue: '',
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
		skippedText(goal) {
			const n = goal.skippedPeriods.length
			return `${n} ${this.periodWord(goal)}${n === 1 ? '' : 's'} skipped`
		},
		// "2026-11-23" (a week's Monday) → "Week of Nov 23, 2026"; "2026-12" → "December 2026"
		formatPeriod(goal, period) {
			const [y, m, d] = period.split('-').map(Number)
			const date = new Date(Date.UTC(y, m - 1, d || 1))
			if (goal.interval === 'Monthly') {
				return date.toLocaleDateString(undefined, { timeZone: 'UTC', month: 'long', year: 'numeric' })
			}
			return 'Week of ' + date.toLocaleDateString(undefined, { timeZone: 'UTC', day: 'numeric', month: 'short', year: 'numeric' })
		},
		openSkipDialog(goal) {
			this.skipGoal = goal
			this.skipDateValue = ''
		},
		async sendSkip(goal, body) {
			try {
				const data = await apiPost(`/skip-goal/${goal.id}`, body)
				this.goals = data.Goals || []
				if (this.skipGoal) {
					this.skipGoal = this.goals.find((g) => g.id === goal.id) || null
				}
			} catch (e) {
				showError(e.message)
			}
		},
		async toggleCurrentSkip(goal) {
			await this.sendSkip(goal, { skip: !goal.currentPeriodSkipped })
		},
		async skipDate(goal, date) {
			await this.sendSkip(goal, { date, skip: true })
			this.skipDateValue = ''
		},
		async unskip(goal, period) {
			// the period key is the week's Monday or the month; any day in it works
			await this.sendSkip(goal, { date: period.length === 7 ? `${period}-01` : period, skip: false })
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
