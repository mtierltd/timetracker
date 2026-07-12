<template>
	<NcAppContent>
		<div class="view-page">
			<h1 class="page-title">Timelines</h1>

			<div class="controls">
				<select v-model="group1" class="native-select" @change="loadReport">
					<option value="project">Group by: Project</option>
					<option value="client">Group by: Client</option>
				</select>
				<select v-model="group2" class="native-select" @change="loadReport">
					<option value="">Then by: None</option>
					<option value="name">Then by: Time Entry</option>
				</select>
				<select v-model="timegroup" class="native-select" @change="loadReport">
					<option value="day">Interval: Daily</option>
					<option value="week">Interval: Weekly</option>
					<option value="month">Interval: Monthly</option>
					<option value="year">Interval: Yearly</option>
				</select>
				<NcSelect v-model="filterProjects" :options="projects" label="name" multiple placeholder="Filter projects" style="min-width: 200px;" @update:model-value="loadReport" />
				<NcSelect v-model="filterClients" :options="clients" label="name" multiple placeholder="Filter clients" style="min-width: 200px;" @update:model-value="loadReport" />
			</div>

			<div class="date-range">
				<NcDateTimePicker v-model="rangeFrom" type="date" :clearable="false" @update:model-value="loadReport" />
				<span>to</span>
				<NcDateTimePicker v-model="rangeTo" type="date" :clearable="false" @update:model-value="loadReport" />
				<NcButton type="primary" @click="exportTimeline">Export Timeline CSV</NcButton>
			</div>

			<table class="entity-table">
				<thead>
					<tr>
						<th>#</th>
						<th>Name</th>
						<th>User</th>
						<th>Project</th>
						<th>Client</th>
						<th>When</th>
						<th>Total Duration</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="(item, index) in items" :key="index">
						<td>{{ index + 1 }}</td>
						<td>{{ item.name }}</td>
						<td>{{ item.userUid }}</td>
						<td>{{ item.project }}</td>
						<td>{{ item.client }}</td>
						<td>{{ item.ftime || item.time }}</td>
						<td>{{ formatDuration(item.totalDuration) }}</td>
					</tr>
				</tbody>
			</table>

			<h2 class="history-title">Exported timelines</h2>
			<table class="entity-table">
				<thead>
					<tr>
						<th>#</th>
						<th>Id</th>
						<th>Status</th>
						<th>When</th>
						<th>Total Duration</th>
						<th>Created At</th>
						<th></th>
						<th></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="(tl, index) in timelines" :key="tl.id">
						<td>{{ index + 1 }}</td>
						<td>{{ tl.id }}</td>
						<td>{{ tl.status }}</td>
						<td>{{ tl.timeInterval }}</td>
						<td>{{ formatDuration(tl.totalDuration) }}</td>
						<td>{{ new Date(tl.createdAt * 1000).toLocaleString() }}</td>
						<td><a :href="downloadUrl(tl.id)">Download</a></td>
						<td>
							<NcButton type="tertiary" aria-label="Email" @click="openEmail(tl)">
								<template #icon><span class="icon-mail" /></template>
							</NcButton>
						</td>
						<td>
							<NcButton type="tertiary" aria-label="Delete" @click="removeTimeline(tl)">
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

		<NcModal v-if="emailing" @close="emailing = null">
			<div class="edit-form">
				<h2>Email timeline</h2>
				<NcTextField v-model="emailForm.email" label="Email address(es), separated by ;" />
				<NcTextField v-model="emailForm.subject" label="Subject" />
				<NcTextField v-model="emailForm.content" label="Message" />
				<NcButton type="primary" @click="sendEmail">Send</NcButton>
			</div>
		</NcModal>
	</NcAppContent>
</template>

<script>
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcModal from '@nextcloud/vue/components/NcModal'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import { apiGet, apiPost } from '../api.js'

function pad(n) {
	return String(n).padStart(2, '0')
}
function formatDuration(seconds) {
	seconds = Math.max(0, Math.floor(seconds || 0))
	const h = Math.floor(seconds / 3600)
	const m = Math.floor((seconds % 3600) / 60)
	const s = seconds % 60
	return `${pad(h)}:${pad(m)}:${pad(s)}`
}

export default {
	name: 'TimelinesView',
	components: { NcAppContent, NcButton, NcTextField, NcSelect, NcDateTimePicker, NcDialog, NcModal },
	data() {
		const now = new Date()
		const from = new Date(now)
		from.setDate(from.getDate() - 29)
		return {
			items: [],
			timelines: [],
			projects: [],
			clients: [],
			filterProjects: [],
			filterClients: [],
			group1: 'project',
			group2: '',
			timegroup: 'day',
			rangeFrom: from,
			rangeTo: now,
			emailing: null,
			emailForm: { email: '', subject: '', content: '' },
			confirmVisible: false,
			confirmMessage: '',
			confirmResolver: null,
		}
	},
	async mounted() {
		await Promise.all([this.loadProjects(), this.loadClients(), this.loadReport(), this.loadTimelines()])
	},
	methods: {
		formatDuration,
		downloadUrl(id) {
			return generateUrl(`/apps/timetracker/ajax/download-timeline/${id}`)
		},
		async loadProjects() {
			const data = await apiGet('/projects')
			this.projects = data.Projects || []
		},
		async loadClients() {
			const data = await apiGet('/clients')
			this.clients = data.Clients || []
		},
		async loadTimelines() {
			const data = await apiGet('/timelines')
			this.timelines = data.Timelines || []
		},
		reportParams() {
			return {
				name: '',
				from: Math.floor(this.rangeFrom.getTime() / 1000),
				to: Math.floor(this.rangeTo.getTime() / 1000),
				group1: this.group1,
				group2: this.group2,
				timegroup: this.timegroup,
				filterProjectId: this.filterProjects.map((p) => p.id).join(','),
				filterClientId: this.filterClients.map((c) => c.id).join(','),
			}
		},
		async loadReport() {
			const data = await apiGet('/report', this.reportParams())
			this.items = data.items || []
		},
		async exportTimeline() {
			try {
				await apiPost('/timeline', this.reportParams())
				await this.loadTimelines()
				showSuccess('Timeline exported')
			} catch (e) {
				showError(e.message)
			}
		},
		openEmail(tl) {
			this.emailing = tl
			this.emailForm = { email: '', subject: `Timeline #${tl.id}`, content: '' }
		},
		async sendEmail() {
			try {
				await apiPost(`/email-timeline/${this.emailing.id}`, this.emailForm)
				this.emailing = null
				showSuccess('Email sent')
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
		async removeTimeline(tl) {
			if (!(await this.confirm(`Are you sure you want to delete timeline #${tl.id}?`))) return
			try {
				await apiPost(`/delete-timeline/${tl.id}`)
				await this.loadTimelines()
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
.controls {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
	padding-bottom: 12px;
}
.native-select {
	height: 34px;
}
.date-range {
	display: flex;
	align-items: center;
	gap: 8px;
	padding-bottom: 20px;
}
.history-title {
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
.edit-form {
	padding: 20px;
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: 340px;
}
</style>
