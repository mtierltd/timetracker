<template>
	<NcAppContent>
		<div class="view-page">
			<h1 class="page-title">Timer</h1>

			<form class="top-bar" @submit.prevent="onSubmitWorkInput">
				<NcTextField v-model="workInput" style="width: 320px;" label="What have you done?" placeholder="What have you done?" />
				<NcButton type="primary" native-type="submit">{{ isRunning ? 'Stop' : 'Start' }}</NcButton>
				<span v-if="isRunning" class="live-timer">{{ liveTimerText }}</span>
				<NcButton type="secondary" @click="openManualEntry">Manual entry</NcButton>
			</form>

			<div class="date-range">
				<select class="native-select" @change="applyPreset($event.target.value); $event.target.value = ''">
					<option value="" disabled selected>Quick range...</option>
					<option v-for="p in presets" :key="p.key" :value="p.key">{{ p.label }}</option>
				</select>
				<NcDateTimePicker v-model="rangeFrom" type="date" :clearable="false" />
				<span>to</span>
				<NcDateTimePicker v-model="rangeTo" type="date" :clearable="false" />
				<NcButton @click="loadWorkIntervals">Refresh</NcButton>
			</div>

			<div v-for="(dayGroup, dayLabel) in flattenedDays" :key="dayLabel" class="day-group">
				<h2>{{ dayLabel }}</h2>
				<table class="entity-table">
					<thead>
						<tr>
							<th></th>
							<th>Name / Details</th>
							<th>Project</th>
							<th>Tags</th>
							<th>Duration</th>
							<th>Cost</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="item in dayGroup.children" :key="item.id">
							<td>
								<NcButton type="tertiary" aria-label="Resume" @click="resume(item)">
									<template #icon><span class="icon-play" /></template>
								</NcButton>
							</td>
							<td class="editable" @click="openEditNameDetails(item)">
								<div class="wi-name">{{ item.name }}</div>
								<div v-if="item.details" class="wi-details">{{ item.details }}</div>
							</td>
							<td>
								<NcSelect :model-value="projectFor(item)"
									:options="projects"
									label="name"
									placeholder="Project"
									style="min-width: 160px;"
									@update:model-value="(p) => updateProject(item, p)" />
							</td>
							<td>
								<NcSelect :model-value="item.tags"
									:options="tags"
									label="name"
									multiple
									placeholder="Tags"
									style="min-width: 160px;"
									@update:model-value="(t) => updateTags(item, t)" />
							</td>
							<td class="editable" @click="openEditTime(item)">{{ formatDuration(item.duration) }}</td>
							<td>
								<NcTextField :model-value="(item.cost / 100).toFixed(2)"
									style="width: 90px;"
									@update:model-value="(v) => updateCost(item, v)" />
							</td>
							<td>
								<NcButton type="tertiary" aria-label="Delete" @click="removeInterval(item)">
									<template #icon><span class="icon-close" /></template>
								</NcButton>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<NcDialog v-if="confirmVisible"
			name="Confirmation required"
			:message="confirmMessage"
			:buttons="[
				{ label: 'Cancel', callback: () => resolveConfirm(false) },
				{ label: 'Confirm', variant: 'error', callback: () => resolveConfirm(true) },
			]" />

		<NcModal v-if="editingItem" @close="editingItem = null">
			<div class="edit-form">
				<h2>Edit entry</h2>
				<NcTextField v-model="editForm.name" label="Name" />
				<NcTextField v-model="editForm.details" label="Details" />
				<NcButton type="primary" @click="saveNameDetails">Save</NcButton>
			</div>
		</NcModal>

		<NcModal v-if="editingTimeItem" size="normal" @close="editingTimeItem = null">
			<div class="edit-form">
				<h2>Edit time</h2>
				<NcDateTimePicker v-model="timeForm.start" type="datetime" label="Start" append-to-body />
				<NcDateTimePicker v-model="timeForm.end" type="datetime" label="End" append-to-body />
				<NcButton type="primary" @click="saveEditTime">Save</NcButton>
			</div>
		</NcModal>

		<NcModal v-if="manualEntryVisible" size="normal" @close="manualEntryVisible = false">
			<div class="edit-form">
				<h2>Manual entry</h2>
				<NcTextField v-model="manualForm.name" label="Name" />
				<NcTextField v-model="manualForm.details" label="Details" />
				<NcDateTimePicker v-model="manualForm.start" type="datetime" label="Start" append-to-body />
				<NcDateTimePicker v-model="manualForm.end" type="datetime" label="End" append-to-body />
				<NcButton type="primary" @click="saveManualEntry">Add entry</NcButton>
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
import { showError } from '@nextcloud/dialogs'
import { apiGet, apiPost } from '../api.js'
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
function formatForApi(d) {
	const dd = String(d.getDate()).padStart(2, '0')
	const mm = String(d.getMonth() + 1).padStart(2, '0')
	const yy = String(d.getFullYear()).slice(-2)
	const h = String(d.getHours()).padStart(2, '0')
	const mi = String(d.getMinutes()).padStart(2, '0')
	return `${dd}/${mm}/${yy} ${h}:${mi}`
}

export default {
	name: 'TimerView',
	components: { NcAppContent, NcButton, NcTextField, NcSelect, NcDateTimePicker, NcDialog, NcModal },
	data() {
		const now = new Date()
		const from = new Date(now)
		from.setDate(from.getDate() - 30)
		return {
			workInput: '',
			days: {},
			running: [],
			projects: [],
			tags: [],
			rangeFrom: from,
			rangeTo: now,
			presets: DATE_RANGE_PRESETS,
			liveTimerText: '00:00:00',
			liveTimerInterval: null,
			editingItem: null,
			editForm: { name: '', details: '' },
			editingTimeItem: null,
			timeForm: { start: null, end: null },
			manualEntryVisible: false,
			manualForm: { name: '', details: '', start: null, end: null },
			confirmVisible: false,
			confirmMessage: '',
			confirmResolver: null,
		}
	},
	computed: {
		isRunning() {
			return this.running.length > 0
		},
		flattenedDays() {
			const result = {}
			for (const [dayLabel, nameGroups] of Object.entries(this.days)) {
				const children = []
				for (const group of Object.values(nameGroups)) {
					children.push(...group.children)
				}
				children.sort((a, b) => b.start - a.start)
				result[dayLabel] = { children }
			}
			return result
		},
	},
	async mounted() {
		await Promise.all([this.loadWorkIntervals(), this.loadProjects(), this.loadTags()])
	},
	beforeUnmount() {
		clearInterval(this.liveTimerInterval)
	},
	methods: {
		formatDuration,
		projectFor(item) {
			return this.projects.find((p) => p.id === item.projectId) || null
		},
		async loadProjects() {
			const data = await apiGet('/projects')
			this.projects = data.Projects || []
		},
		async loadTags() {
			const data = await apiGet('/tags')
			this.tags = data.Tags || []
		},
		applyPreset(key) {
			const range = getPresetRange(key)
			if (!range) return
			this.rangeFrom = range.from
			this.rangeTo = range.to
			this.loadWorkIntervals()
		},
		async loadWorkIntervals() {
			const data = await apiGet('/work-intervals', {
				from: Math.floor(this.rangeFrom.getTime() / 1000),
				to: Math.floor(this.rangeTo.getTime() / 1000),
				tzoffset: new Date().getTimezoneOffset(),
			})
			this.days = data.days || {}
			this.running = data.running || []
			this.setupLiveTimer(data.now)
		},
		setupLiveTimer(serverNow) {
			clearInterval(this.liveTimerInterval)
			if (!this.isRunning) return
			const skew = (Date.now() / 1000) - serverNow
			const start = this.running[0].start
			const tick = () => {
				const elapsed = Math.floor(Date.now() / 1000 - skew - start)
				this.liveTimerText = formatDuration(elapsed)
			}
			tick()
			this.liveTimerInterval = setInterval(tick, 1000)
		},
		async onSubmitWorkInput() {
			if (this.isRunning) {
				await this.stopTimer()
			} else {
				await this.startTimer(this.workInput.trim() || 'no description')
			}
		},
		async startTimer(name, projectId, tagIds) {
			try {
				await apiPost(`/start-timer/${encodeURIComponent(encodeURIComponent(name))}`, {
					projectId: projectId ?? '',
					tags: tagIds ?? '',
				})
				this.workInput = ''
				await this.loadWorkIntervals()
			} catch (e) {
				showError(e.message)
			}
		},
		async stopTimer() {
			try {
				await apiPost(`/stop-timer/${encodeURIComponent(encodeURIComponent(this.workInput.trim() || 'no description'))}`)
				this.workInput = ''
				await this.loadWorkIntervals()
			} catch (e) {
				showError(e.message)
			}
		},
		async resume(item) {
			if (this.isRunning) {
				await this.stopTimer()
			}
			await this.startTimer(item.name, item.projectId, item.tags.map((t) => t.id).join(','))
		},
		openEditNameDetails(item) {
			this.editingItem = item
			this.editForm = { name: item.name, details: item.details || '' }
		},
		async saveNameDetails() {
			try {
				await apiPost(`/update-work-interval/${this.editingItem.id}`, {
					name: this.editForm.name,
					details: this.editForm.details,
				})
				this.editingItem = null
				await this.loadWorkIntervals()
			} catch (e) {
				showError(e.message)
			}
		},
		async updateProject(item, project) {
			try {
				await apiPost(`/update-work-interval/${item.id}`, { projectId: project?.id ?? '' })
				await this.loadWorkIntervals()
			} catch (e) {
				showError(e.message)
			}
		},
		async updateTags(item, tags) {
			try {
				await apiPost(`/update-work-interval/${item.id}`, { tagId: tags.map((t) => t.id).join(',') })
				await this.loadWorkIntervals()
			} catch (e) {
				showError(e.message)
			}
		},
		async updateCost(item, value) {
			try {
				await apiPost(`/add-cost/${item.id}`, { cost: value })
			} catch (e) {
				showError(e.message)
			}
		},
		openManualEntry() {
			const now = new Date()
			this.manualForm = { name: '', details: '', start: now, end: now }
			this.manualEntryVisible = true
		},
		openEditTime(item) {
			this.editingTimeItem = item
			this.timeForm = {
				start: new Date(item.start * 1000),
				end: new Date((item.start + item.duration) * 1000),
			}
		},
		async saveEditTime() {
			try {
				await apiPost(`/update-work-interval/${this.editingTimeItem.id}`, {
					start: formatForApi(this.timeForm.start),
					end: formatForApi(this.timeForm.end),
					tzoffset: new Date().getTimezoneOffset(),
				})
				this.editingTimeItem = null
				await this.loadWorkIntervals()
			} catch (e) {
				showError(e.message)
			}
		},
		async saveManualEntry() {
			try {
				await apiPost(`/add-work-interval/${encodeURIComponent(encodeURIComponent(this.manualForm.name))}`, {
					start: formatForApi(this.manualForm.start),
					end: formatForApi(this.manualForm.end),
					tzoffset: new Date().getTimezoneOffset(),
					details: this.manualForm.details,
				})
				this.manualEntryVisible = false
				await this.loadWorkIntervals()
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
		async removeInterval(item) {
			if (!(await this.confirm(`Are you sure you want to delete "${item.name}"?`))) return
			try {
				await apiPost(`/delete-work-interval/${item.id}`)
				await this.loadWorkIntervals()
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
.top-bar {
	display: flex;
	align-items: center;
	gap: 12px;
	padding-bottom: 12px;
	flex-wrap: wrap;
}
.top-bar :deep(button) {
	flex-shrink: 0;
}
.live-timer {
	font-family: monospace;
	font-size: 1.2em;
}
.date-range {
	display: flex;
	align-items: center;
	gap: 8px;
	padding-bottom: 20px;
}
.native-select {
	height: 34px;
}
.day-group {
	margin-bottom: 24px;
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
	vertical-align: middle;
}
.editable {
	cursor: pointer;
}
.wi-details {
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
}
.edit-form {
	padding: 20px;
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: 340px;
}
</style>
