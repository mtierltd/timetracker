<template>
	<NcAppContent>
		<div class="view-page">
			<h1 class="page-title">Reports</h1>

			<div class="controls">
				<select v-model="group1" class="native-select" @change="loadReport">
					<option value="">Group by: None</option>
					<option value="project">Group by: Project</option>
					<option value="userUid">Group by: User</option>
					<option value="client">Group by: Client</option>
				</select>
				<select v-model="group2" class="native-select" @change="loadReport">
					<option value="">Then by: None</option>
					<option value="name">Then by: Time Entry</option>
					<option value="userUid">Then by: User</option>
					<option value="project">Then by: Project</option>
				</select>
				<select v-model="timegroup" class="native-select" @change="loadReport">
					<option value="">Interval: None</option>
					<option value="day">Interval: Daily</option>
					<option value="week">Interval: Weekly</option>
					<option value="month">Interval: Monthly</option>
					<option value="year">Interval: Yearly</option>
				</select>
				<NcSelect v-model="filterProjects" :options="projects" label="name" multiple placeholder="Filter projects" style="min-width: 200px;" @update:model-value="loadReport" />
				<NcSelect v-model="filterClients" :options="clients" label="name" multiple placeholder="Filter clients" style="min-width: 200px;" @update:model-value="loadReport" />
			</div>

			<div class="date-range">
				<select class="native-select" @change="applyPreset($event.target.value); $event.target.value = ''">
					<option value="" disabled selected>Quick range...</option>
					<option v-for="p in presets" :key="p.key" :value="p.key">{{ p.label }}</option>
				</select>
				<NcDateTimePicker v-model="rangeFrom" type="date" :clearable="false" @update:model-value="loadReport" />
				<span>to</span>
				<NcDateTimePicker v-model="rangeTo" type="date" :clearable="false" @update:model-value="loadReport" />
				<NcButton @click="downloadCsv">Download CSV</NcButton>
				<NcButton @click="downloadJson">Download JSON</NcButton>
			</div>

			<table class="entity-table">
				<thead>
					<tr>
						<th>#</th>
						<th>Name</th>
						<th>Details</th>
						<th>User</th>
						<th>Project</th>
						<th>Client</th>
						<th>When</th>
						<th>Cost</th>
						<th>Total Duration</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="(item, index) in items" :key="index">
						<td>{{ index + 1 }}</td>
						<td>{{ item.name }}</td>
						<td>{{ item.details }}</td>
						<td>{{ item.userUid }}</td>
						<td>{{ item.project }}</td>
						<td>{{ item.client }}</td>
						<td>{{ formatWhen(item) }}</td>
						<td>{{ ((item.cost || 0) / 100).toFixed(2) }}</td>
						<td>{{ formatDuration(item.totalDuration) }}</td>
					</tr>
				</tbody>
				<tfoot>
					<tr>
						<td colspan="7" class="totals-label">Totals</td>
						<td>{{ (totalCost / 100).toFixed(2) }}</td>
						<td>{{ formatDuration(totalDuration) }}</td>
					</tr>
				</tfoot>
			</table>
		</div>
	</NcAppContent>
</template>

<script>
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'
import { apiGet } from '../api.js'
import { DATE_RANGE_PRESETS, getPresetRange } from '../dateRangePresets.js'

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
	name: 'ReportsView',
	components: { NcAppContent, NcButton, NcSelect, NcDateTimePicker },
	data() {
		const now = new Date()
		const from = new Date(now)
		from.setDate(from.getDate() - 29)
		return {
			items: [],
			projects: [],
			clients: [],
			filterProjects: [],
			filterClients: [],
			group1: 'project',
			group2: 'userUid',
			timegroup: 'day',
			rangeFrom: from,
			rangeTo: now,
			presets: DATE_RANGE_PRESETS,
		}
	},
	computed: {
		totalDuration() {
			return this.items.reduce((sum, i) => sum + (i.totalDuration || 0), 0)
		},
		totalCost() {
			return this.items.reduce((sum, i) => sum + (i.cost || 0), 0)
		},
	},
	async mounted() {
		await Promise.all([this.loadProjects(), this.loadClients(), this.loadReport()])
	},
	methods: {
		formatDuration,
		formatWhen(item) {
			if (item.ftime) return item.ftime
			if (!item.time) return ''
			return new Date(item.time * 1000).toLocaleString()
		},
		async loadProjects() {
			const data = await apiGet('/projects')
			this.projects = data.Projects || []
		},
		async loadClients() {
			const data = await apiGet('/clients')
			this.clients = data.Clients || []
		},
		applyPreset(key) {
			const range = getPresetRange(key)
			if (!range) return
			this.rangeFrom = range.from
			this.rangeTo = range.to
			this.loadReport()
		},
		async loadReport() {
			const data = await apiGet('/report', {
				name: '',
				from: Math.floor(this.rangeFrom.getTime() / 1000),
				to: Math.floor(this.rangeTo.getTime() / 1000),
				group1: this.group1,
				group2: this.group2,
				timegroup: this.timegroup,
				filterProjectId: this.filterProjects.map((p) => p.id).join(','),
				filterClientId: this.filterClients.map((c) => c.id).join(','),
			})
			this.items = data.items || []
		},
		ended(item) {
			if (this.group1 || this.group2 || this.timegroup) return '*'
			if (!item.time) return ''
			return new Date((item.time + (item.totalDuration || 0)) * 1000).toLocaleString()
		},
		toCsv() {
			const header = ['Name', 'Details', 'User', 'Project', 'Client', 'When', 'Cost', 'Total Duration', 'Ended']
			const rows = this.items.map((i) => [
				i.name, i.details, i.userUid, i.project, i.client, this.formatWhen(i),
				((i.cost || 0) / 100).toFixed(2), formatDuration(i.totalDuration), this.ended(i),
			])
			return [header, ...rows].map((r) => r.map((v) => `"${String(v ?? '').replace(/"/g, '""')}"`).join(',')).join('\n')
		},
		downloadBlob(content, filename, type) {
			const blob = new Blob([content], { type })
			const url = URL.createObjectURL(blob)
			const a = document.createElement('a')
			a.href = url
			a.download = filename
			a.click()
			URL.revokeObjectURL(url)
		},
		downloadCsv() {
			this.downloadBlob(this.toCsv(), 'report.csv', 'text/csv')
		},
		downloadJson() {
			this.downloadBlob(JSON.stringify(this.items, null, 2), 'report.json', 'application/json')
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
.totals-label {
	text-align: right;
	font-weight: bold;
}
</style>
