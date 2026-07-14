<template>
	<NcAppContent>
		<div class="view-page">
			<h1 class="page-title">Clients</h1>

			<form class="new-item-form" @submit.prevent="addClient">
				<NcTextField v-model="newClientName" style="width: 260px;" label="New client name" placeholder="New client name..." />
				<NcButton type="primary" native-type="submit">Add client</NcButton>
			</form>

			<table class="entity-table">
				<thead>
					<tr>
						<th>#</th>
						<th>Name</th>
						<th></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="(client, index) in clients" :key="client.id">
						<td>{{ index + 1 }}</td>
						<td>{{ client.name }}</td>
						<td>
							<NcButton type="tertiary" aria-label="Edit" @click="openEdit(client)">
								<template #icon><span class="icon-rename" /></template>
							</NcButton>
						</td>
						<td>
							<NcButton type="tertiary" aria-label="Delete" @click="removeClient(client)">
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

		<NcModal v-if="editing" @close="editing = null">
			<div class="edit-form">
				<h2>Edit client</h2>
				<NcTextField v-model="editName" label="Name" />
				<NcButton type="primary" @click="saveEdit">Edit client</NcButton>
			</div>
		</NcModal>
	</NcAppContent>
</template>

<script>
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcModal from '@nextcloud/vue/components/NcModal'
import { showError } from '@nextcloud/dialogs'
import { apiGet, apiPost } from '../api.js'

export default {
	name: 'ClientsView',
	components: { NcAppContent, NcButton, NcTextField, NcDialog, NcModal },
	data() {
		return {
			clients: [],
			newClientName: '',
			editing: null,
			editName: '',
			confirmVisible: false,
			confirmMessage: '',
			confirmResolver: null,
		}
	},
	async mounted() {
		await this.loadClients()
	},
	methods: {
		async loadClients() {
			const data = await apiGet('/clients')
			this.clients = data.Clients || []
		},
		async addClient() {
			if (!this.newClientName.trim()) return
			try {
				await apiPost(`/add-client/${encodeURIComponent(this.newClientName)}`)
				this.newClientName = ''
				await this.loadClients()
			} catch (e) {
				showError(e.message)
			}
		},
		openEdit(client) {
			this.editing = client
			this.editName = client.name
		},
		async saveEdit() {
			try {
				await apiPost(`/edit-client/${this.editing.id}`, { name: this.editName })
				this.editing = null
				await this.loadClients()
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
		async removeClient(client) {
			if (!(await this.confirm(`Are you sure you want to delete the client "${client.name}"?`))) return
			try {
				await apiPost(`/delete-client/${client.id}`)
				await this.loadClients()
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
}
.new-item-form :deep(button) {
	flex-shrink: 0;
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
.edit-form {
	padding: 20px;
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: 300px;
}
</style>
