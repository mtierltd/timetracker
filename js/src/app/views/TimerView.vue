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
				<NcButton v-if="foldableKeys.length"
					type="tertiary"
					class="fold-all"
					@click="setAllFolded(!allFolded)">
					<template #icon><NcIconSvgWrapper :path="allFolded ? chevronRight : chevronDown" /></template>
					{{ allFolded ? 'Unfold all' : 'Fold all' }}
				</NcButton>
			</div>

			<div v-for="day in groupedDays" :key="day.label" class="day-group">
				<h2 class="day-heading">
					<span>{{ day.label }}</span>
					<span class="day-total" title="Total of the day">{{ formatDuration(totalOf(day.entries)) }}</span>
				</h2>
				<div class="table-scroll">
				<table class="entity-table">
					<thead>
						<tr>
							<th></th>
							<th>Name / Details</th>
							<th>Project</th>
							<th>Tags</th>
							<th>Time</th>
							<th>Duration</th>
							<th>Cost</th>
							<th></th>
						</tr>
					</thead>
					<tbody v-for="group in day.groups" :key="group.name" :class="{ 'name-group': group.children.length > 1 }">
						<tr v-if="group.children.length > 1" class="group-row">
							<td>
								<NcButton type="tertiary" aria-label="Resume" @click="resume(group.children[0])">
									<template #icon><span class="icon-play" /></template>
								</NcButton>
							</td>
							<td colspan="3">
								<button type="button"
									class="group-toggle"
									:aria-expanded="isFolded(day, group) ? 'false' : 'true'"
									:title="isFolded(day, group) ? 'Unfold' : 'Fold'"
									@click="toggleFolded(day, group)">
									<span class="group-chevron"><NcIconSvgWrapper :path="isFolded(day, group) ? chevronRight : chevronDown" :size="20" /></span>
									<span class="group-name">{{ group.name }}</span>
									<span class="group-count" :title="`${group.children.length} entries`">{{ group.children.length }}</span>
								</button>
							</td>
							<td></td>
							<td class="group-total">{{ formatDuration(totalOf(group.children)) }}</td>
							<td colspan="2"></td>
						</tr>
							<tr v-for="item in visibleChildren(day, group)" :key="item.id" :class="{ 'in-group': group.children.length > 1 }">
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
										taggable
										push-tags
										placeholder="Tags"
										style="min-width: 160px;"
										@update:model-value="(t) => updateTags(item, t)" />
								</td>
								<td class="editable wi-time" @click="openEditTime(item)">{{ timeRange(item) }}</td>
							<td class="editable wi-duration" @click="openEditTime(item)">{{ formatDuration(elapsed(item)) }}</td>
								<td>
									<NcTextField :model-value="costDraft(item)"
										:success="costStatus[item.id] === 'success'"
										:error="costStatus[item.id] === 'error'"
										style="width: 90px;"
										@update:model-value="(v) => (costDrafts[item.id] = v)"
										@blur="commitCost(item)" />
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
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { showError } from '@nextcloud/dialogs'
import { apiGet, apiPost } from '../api.js'
import { DATE_RANGE_PRESETS, getPresetRange, startOfDay, endOfDay } from '../dateRangePresets.js'

// mdiChevronDown and mdiChevronRight from Material Design Icons (@mdi/js)
const CHEVRON_DOWN = 'M7.41,8.58L12,13.17L16.59,8.58L18,10L12,16L6,10L7.41,8.58Z'
const CHEVRON_RIGHT = 'M8.59,16.58L13.17,12L8.59,7.41L10,6L16,12L10,18L8.59,16.58Z'

// Folded groups are a per-browser preference, kept in localStorage as
// "<day label>\u0000<name>" keys. Only the newest ones are kept.
const FOLDED_STORAGE_KEY = 'timetracker.timer.foldedGroups'
const FOLDED_MAX = 500
function readFolded() {
	try {
		const value = JSON.parse(window.localStorage.getItem(FOLDED_STORAGE_KEY) || '[]')
		return Array.isArray(value) ? value.filter((k) => typeof k === 'string') : []
	} catch (e) {
		return []
	}
}
function writeFolded(keys) {
	try {
		window.localStorage.setItem(FOLDED_STORAGE_KEY, JSON.stringify(keys.slice(-FOLDED_MAX)))
	} catch (e) {
		// storage unavailable (private mode, quota): folding still works until reload
	}
}

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
// HH:MM in the browser's local time
function formatClock(ts) {
	const d = new Date(ts * 1000)
	return `${pad(d.getHours())}:${pad(d.getMinutes())}`
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
	components: { NcAppContent, NcButton, NcTextField, NcSelect, NcDateTimePicker, NcDialog, NcModal, NcIconSvgWrapper },
	data() {
		const now = new Date()
		const from = new Date(now)
		from.setDate(from.getDate() - 30)
		return {
			chevronDown: CHEVRON_DOWN,
			chevronRight: CHEVRON_RIGHT,
			// groups are unfolded unless listed here
			foldedKeys: readFolded(),
			workInput: '',
			days: {},
			running: [],
			projects: [],
			tags: [],
			rangeFrom: from,
			rangeTo: now,
			presets: DATE_RANGE_PRESETS,
			liveTimerText: '00:00:00',
			nowTs: Math.floor(Date.now() / 1000),
			liveTimerInterval: null,
			editingItem: null,
			editForm: { name: '', details: '' },
			editingTimeItem: null,
			timeForm: { start: null, end: null },
			manualEntryVisible: false,
			manualForm: { name: '', details: '', start: null, end: null },
			costDrafts: {},
			costStatus: {},
			confirmVisible: false,
			confirmMessage: '',
			confirmResolver: null,
		}
	},
	computed: {
		// Keys of the shown groups that can fold (more than one entry)
		foldableKeys() {
			return this.groupedDays.flatMap((day) => day.groups
				.filter((g) => g.children.length > 1)
				.map((g) => this.groupKey(day, g)))
		},
		allFolded() {
			const folded = new Set(this.foldedKeys)
			return this.foldableKeys.length > 0 && this.foldableKeys.every((k) => folded.has(k))
		},
		isRunning() {
			return this.running.length > 0
		},
		// The server already groups by day and name; order groups by their newest entry.
		groupedDays() {
			return Object.entries(this.days).map(([label, nameGroups]) => {
				const groups = Object.entries(nameGroups).map(([name, group]) => {
					const children = [...group.children].sort((a, b) => b.start - a.start)
					return { name, children }
				})
				groups.sort((a, b) => b.children[0].start - a.children[0].start)
				const entries = groups.flatMap((g) => g.children)
				return { label, groups, entries }
			})
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
		groupKey(day, group) {
			return `${day.label}\u0000${group.name}`
		},
		isFolded(day, group) {
			return group.children.length > 1 && this.foldedKeys.includes(this.groupKey(day, group))
		},
		visibleChildren(day, group) {
			return this.isFolded(day, group) ? [] : group.children
		},
		toggleFolded(day, group) {
			const key = this.groupKey(day, group)
			this.foldedKeys = this.foldedKeys.includes(key)
				? this.foldedKeys.filter((k) => k !== key)
				: [...this.foldedKeys, key]
			writeFolded(this.foldedKeys)
		},
		setAllFolded(folded) {
			const shown = new Set(this.foldableKeys)
			const others = this.foldedKeys.filter((k) => !shown.has(k))
			this.foldedKeys = folded ? [...others, ...this.foldableKeys] : others
			writeFolded(this.foldedKeys)
		},
		// Seconds of an entry; a running entry counts with its live elapsed time.
		elapsed(item) {
			return Number(item.running) ? Math.max(0, this.nowTs - item.start) : (item.duration || 0)
		},
		timeRange(item) {
			const start = formatClock(item.start)
			return Number(item.running) ? `${start} -` : `${start} - ${formatClock(item.start + (item.duration || 0))}`
		},
		totalOf(items) {
			return items.reduce((sum, item) => sum + this.elapsed(item), 0)
		},
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
			// Query whole days: the picked "to" date includes the full day, so entries
			// started after the page was opened (resume, start bar) show up too.
			const data = await apiGet('/work-intervals', {
				from: Math.floor(startOfDay(this.rangeFrom).getTime() / 1000),
				to: Math.floor(endOfDay(this.rangeTo).getTime() / 1000),
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
				this.nowTs = Math.floor(Date.now() / 1000 - skew)
				this.liveTimerText = formatDuration(this.nowTs - start)
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
				await apiPost(`/update-work-interval/${item.id}`, { tagId: tags.map((t) => t.id ?? t.name).join(',') })
				await this.loadWorkIntervals()
			} catch (e) {
				showError(e.message)
			}
		},
		costDraft(item) {
			return this.costDrafts[item.id] ?? (item.cost / 100).toFixed(2)
		},
		async commitCost(item) {
			const value = this.costDrafts[item.id]
			if (value === undefined) return
			try {
				await apiPost(`/add-cost/${item.id}`, { cost: value })
				this.costStatus[item.id] = 'success'
				setTimeout(() => { this.costStatus[item.id] = null }, 3000)
				await this.loadWorkIntervals()
			} catch (e) {
				this.costStatus[item.id] = 'error'
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
					// the offsets on these dates, not today's (#151)
					tzoffset: this.timeForm.start.getTimezoneOffset(),
					tzoffsetEnd: this.timeForm.end.getTimezoneOffset(),
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
					// the offsets on these dates, not today's (#151)
					tzoffset: this.manualForm.start.getTimezoneOffset(),
					tzoffsetEnd: this.manualForm.end.getTimezoneOffset(),
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
.day-heading {
	display: flex;
	align-items: baseline;
	gap: 12px;
}
.day-total {
	font-family: monospace;
	font-size: 0.8em;
	color: var(--color-text-maxcontrast);
}
.wi-time,
.wi-duration,
.group-total {
	white-space: nowrap;
	font-variant-numeric: tabular-nums;
}
.table-scroll {
	overflow-x: auto;
}
.group-row td {
	background-color: var(--color-background-dark);
}
.group-toggle {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	margin: 0;
	padding: 2px 8px 2px 0;
	min-height: 0;
	border: none;
	border-radius: var(--border-radius-element, 8px);
	background: transparent;
	color: inherit;
	font: inherit;
	text-align: left;
	cursor: pointer;
}
.group-toggle:hover,
.group-toggle:focus-visible {
	background-color: var(--color-background-hover);
}
/* The wrapper keeps the icon from being a direct child of the button, which
   Nextcloud's global "button > span[class^=icon-]" rule would restyle. */
.group-chevron {
	display: flex;
}
.group-chevron :deep(.icon-vue) {
	min-width: 20px;
	min-height: 20px;
}
.group-name {
	font-weight: bold;
}
.group-count {
	display: inline-block;
	margin-left: 8px;
	padding: 0 8px;
	border-radius: var(--border-radius-pill, 12px);
	background-color: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
	font-size: 0.85em;
	line-height: 1.6;
}
.group-total {
	font-weight: bold;
}
.in-group td:first-child {
	box-shadow: inset 3px 0 0 var(--color-primary-element-light);
}
.in-group td:nth-child(2) {
	padding-left: 24px;
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
