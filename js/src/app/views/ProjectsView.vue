<template>
	<NcAppContent>
		<div class="view-page">
			<h1 class="page-title">Projects</h1>

			<form class="new-item-form" @submit.prevent="addProject">
				<NcTextField v-model="newProjectName" style="width: 220px;" label="New project name" placeholder="New project name..." />
				<NcSelect v-model="newProjectClient" :options="clients" label="name" placeholder="Client" style="width: 200px;" />
				<NcColorPicker v-model="newProjectColor" :palette="palette" palette-only>
					<button type="button" class="color-swatch" :style="{ backgroundColor: newProjectColor }" />
				</NcColorPicker>
				<NcButton type="primary" native-type="submit">Add project</NcButton>
			</form>

			<NcCheckboxRadioSwitch :model-value="showArchived" @update:model-value="onToggleArchived">
				Show archived projects
			</NcCheckboxRadioSwitch>

			<table class="entity-table">
				<thead>
					<tr>
						<th>#</th>
						<th>Color</th>
						<th>Name</th>
						<th>Client</th>
						<th>Locked</th>
						<th v-if="showArchived">Archived</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="(project, index) in projects" :key="project.id" class="clickable-row" @click="openEdit(project)">
						<td>{{ index + 1 }}</td>
						<td><span class="color-swatch" :style="{ backgroundColor: project.color }" /></td>
						<td>{{ project.name }}</td>
						<td>{{ project.client }}</td>
						<td>{{ project.locked ? 'Yes' : 'No' }}</td>
						<td v-if="showArchived">{{ project.archived ? 'Yes' : 'No' }}</td>
						<td @click.stop>
							<NcButton v-if="isAdmin" type="tertiary" aria-label="Delete" @click="removeProject(project)">
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

		<NcModal v-if="editing" size="large" @close="editing = null">
			<div class="edit-form">
				<h2>Edit project</h2>
				<NcTextField v-model="editForm.name" label="Name" />
				<NcSelect v-model="editForm.client" :options="clients" label="name" placeholder="Client" />
				<div class="color-row">
					<span>Color</span>
					<NcColorPicker v-model="editForm.color" :palette="palette" palette-only>
						<button type="button" class="color-swatch" :style="{ backgroundColor: editForm.color }" />
					</NcColorPicker>
				</div>

				<template v-if="isAdmin">
					<NcCheckboxRadioSwitch v-model="editForm.locked">Locked</NcCheckboxRadioSwitch>
					<template v-if="editForm.locked">
						<NcSelect v-model="editForm.allowedTags" :options="tags" label="name" multiple placeholder="Allowed tags" />
						<NcSelect v-model="editForm.allowedUsers" :options="users" label="displayname" multiple placeholder="Allowed users" />
					</template>
				</template>
				<NcCheckboxRadioSwitch v-model="editForm.archived">Archived</NcCheckboxRadioSwitch>

				<div class="edit-actions">
					<NcButton v-if="isAdmin" type="error" @click="removeProject(editing)">Delete project</NcButton>
					<NcButton type="primary" @click="saveEdit">Edit project</NcButton>
				</div>
			</div>
		</NcModal>
	</NcAppContent>
</template>

<script>
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcModal from '@nextcloud/vue/components/NcModal'
import { showError } from '@nextcloud/dialogs'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { apiGet, apiPost } from '../api.js'

const PALETTE = ['#1abc9c', '#2ecc71', '#3498db', '#9b59b6', '#34495e', '#16a085', '#27ae60', '#2980b9',
	'#8e44ad', '#2c3e50', '#f1c40f', '#e67e22', '#e74c3c', '#ecf0f1', '#95a5a6', '#f39c12', '#d35400',
	'#c0392b', '#bdc3c7', '#7f8c8d', '#bf678b', '#c98879', '#ddcb55', '#a5b872', '#6ea68f', '#3794ac',
	'#0082c9', '#2d73be', '#5b64b3', '#8855a8']

export default {
	name: 'ProjectsView',
	components: { NcAppContent, NcButton, NcTextField, NcSelect, NcColorPicker, NcCheckboxRadioSwitch, NcDialog, NcModal },
	data() {
		return {
			projects: [],
			clients: [],
			tags: [],
			users: [],
			showArchived: false,
			newProjectName: '',
			newProjectClient: null,
			newProjectColor: '#0082c9',
			editing: null,
			editForm: {},
			isAdmin: !!getCurrentUser()?.isAdmin,
			palette: PALETTE,
			confirmVisible: false,
			confirmMessage: '',
			confirmResolver: null,
		}
	},
	async mounted() {
		await Promise.all([this.loadProjects(), this.loadClients(), this.loadTags(), this.loadUsers()])
	},
	methods: {
		async loadProjects() {
			const data = await apiGet('/projects-table', { archived: this.showArchived ? 1 : 0 })
			this.projects = data.items || []
		},
		async loadClients() {
			const data = await apiGet('/clients')
			this.clients = data.Clients || []
		},
		async loadTags() {
			const data = await apiGet('/tags')
			this.tags = data.Tags || []
		},
		async loadUsers() {
			try {
				const { data } = await axios.get(generateOcsUrl('cloud/users/details'), {
					headers: { 'OCS-APIRequest': 'true' },
				})
				this.users = Object.values(data.ocs?.data?.users || {})
			} catch (e) {
				this.users = []
			}
		},
		async onToggleArchived(value) {
			this.showArchived = value
			await this.loadProjects()
		},
		async addProject() {
			if (!this.newProjectName.trim()) return
			try {
				await apiPost(`/add-project/${encodeURIComponent(this.newProjectName)}`, {
					clientId: this.newProjectClient?.id ?? '',
					color: this.newProjectColor,
				})
				this.newProjectName = ''
				await this.loadProjects()
			} catch (e) {
				showError(e.message)
			}
		},
		openEdit(project) {
			if (project.locked && !this.isAdmin) return
			this.editing = project
			this.editForm = {
				name: project.name,
				client: this.clients.find((c) => c.id === project.clientId) || null,
				color: project.color,
				locked: !!project.locked,
				archived: !!project.archived,
				allowedTags: this.tags.filter((t) => (project.allowedTags || []).some((a) => a.id === t.id)),
				allowedUsers: this.users.filter((u) => (project.allowedUsers || []).includes(u.id)),
			}
		},
		async saveEdit() {
			try {
				await apiPost(`/edit-project/${this.editing.id}`, {
					name: this.editForm.name,
					clientId: this.editForm.client?.id ?? '',
					color: this.editForm.color,
					locked: this.editForm.locked ? '1' : '0',
					archived: this.editForm.archived ? '1' : '0',
					allowedTags: this.editForm.allowedTags.map((t) => t.id).join(','),
					allowedUsers: this.editForm.allowedUsers.map((u) => u.id).join(','),
				})
				this.editing = null
				await this.loadProjects()
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
		async removeProject(project) {
			if (!(await this.confirm(`Are you sure you want to delete the project "${project.name}"? This will permanently delete all its tracked time.`))) return
			try {
				await apiPost(`/delete-project-with-data/${project.id}`)
				this.editing = null
				await this.loadProjects()
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
.new-item-form :deep(button) {
	flex-shrink: 0;
}
.color-swatch {
	width: 34px;
	height: 34px;
	border-radius: 50%;
	border: 1px solid var(--color-border);
	cursor: pointer;
	display: inline-block;
}
.color-row {
	display: flex;
	align-items: center;
	gap: 12px;
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
.clickable-row {
	cursor: pointer;
}
.clickable-row:hover {
	background-color: var(--color-background-hover);
}
.edit-form {
	padding: 20px;
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: 360px;
}
.edit-actions {
	display: flex;
	justify-content: space-between;
	margin-top: 12px;
}
</style>
