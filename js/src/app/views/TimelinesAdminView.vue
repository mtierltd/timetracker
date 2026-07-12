<template>
	<NcAppContent>
		<div class="view-page">
			<h1 class="page-title">Timelines Admin</h1>

			<table class="entity-table">
				<thead>
					<tr>
						<th>#</th>
						<th>Id</th>
						<th>User</th>
						<th>Status</th>
						<th>When</th>
						<th>Total Duration</th>
						<th>Created At</th>
						<th></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="(tl, index) in timelines" :key="tl.id">
						<td>{{ index + 1 }}</td>
						<td>{{ tl.id }}</td>
						<td>{{ tl.userUid }}</td>
						<td>{{ tl.status }}</td>
						<td>{{ tl.timeInterval }}</td>
						<td>{{ formatDuration(tl.totalDuration) }}</td>
						<td>{{ new Date(tl.createdAt * 1000).toLocaleString() }}</td>
						<td><a :href="downloadUrl(tl.id)">Download</a></td>
						<td>
							<NcButton type="tertiary" aria-label="Edit status" @click="openEdit(tl)">
								<template #icon><span class="icon-rename" /></template>
							</NcButton>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<NcModal v-if="editing" @close="editing = null">
			<div class="edit-form">
				<h2>Edit timeline status</h2>
				<select v-model="editStatus" class="native-select">
					<option value="pending">Pending</option>
					<option value="approved">Approved</option>
					<option value="rejected">Rejected</option>
				</select>
				<NcButton type="primary" @click="saveStatus">Edit timeline</NcButton>
			</div>
		</NcModal>
	</NcAppContent>
</template>

<script>
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcModal from '@nextcloud/vue/components/NcModal'
import { showError } from '@nextcloud/dialogs'
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
	name: 'TimelinesAdminView',
	components: { NcAppContent, NcButton, NcModal },
	data() {
		return {
			timelines: [],
			editing: null,
			editStatus: 'pending',
		}
	},
	async mounted() {
		await this.loadTimelines()
	},
	methods: {
		formatDuration,
		downloadUrl(id) {
			return generateUrl(`/apps/timetracker/ajax/download-timeline/${id}`)
		},
		async loadTimelines() {
			const data = await apiGet('/timelines-admin')
			this.timelines = data.Timelines || []
		},
		openEdit(tl) {
			this.editing = tl
			this.editStatus = tl.status
		},
		async saveStatus() {
			try {
				await apiPost(`/edit-timeline/${this.editing.id}`, { status: this.editStatus })
				this.editing = null
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
.native-select {
	height: 34px;
}
.edit-form {
	padding: 20px;
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: 280px;
}
</style>
