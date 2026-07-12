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
					<td>{{ goal.projectName }}</td>
					<td>{{ goal.hours }}</td>
					<td>{{ goal.interval }}</td>
					<td>{{ new Date(goal.createdAt * 1000).toDateString() }}</td>
					<td>{{ goal.workedHoursCurrentPeriod }}</td>
					<td>{{ goal.debtHours }}</td>
					<td>{{ goal.remainingHours }}</td>
					<td>{{ goal.totalRemainingHours }}</td>
					<td>
						<NcButton type="tertiary" aria-label="Delete" @click="removeGoal(goal)">
							<template #icon><span class="icon-close" /></template>
						</NcButton>
					</td>
				</tr>
			</tbody>
		</table>
		</div>

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
		async removeGoal(goal) {
			if (!(await this.confirm(`Are you sure you want to delete the goal for "${goal.projectName}"?`))) return
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
	padding: 8px 16px;
	text-align: left;
	border-bottom: 1px solid var(--color-border);
}
</style>
