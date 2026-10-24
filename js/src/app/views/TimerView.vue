<template>
	<NcAppContent>
		<div class="view-page">
			<h1 class="page-title">Timer</h1>

			<div v-if="pins.length" class="pinned-row" role="list" aria-label="Pinned timers">
				<div v-for="(pin, index) in pins"
					:key="pin.id"
					role="listitem"
					class="pin"
					:class="{ 'pin--active': isPinRunning(pin), 'pin--drop-target': dropIndex === index && dragId !== pin.id }"
					draggable="true"
					@dragstart="onPinDragStart($event, pin)"
					@dragover.prevent="dropIndex = index"
					@drop.prevent="onPinDrop(index)"
					@dragend="dragId = null; dropIndex = null">
					<button type="button"
						class="pin__start"
						:aria-pressed="isPinRunning(pin) ? 'true' : 'false'"
						:title="pinTitle(pin)"
						@click="onPinClick(pin)">
						<span class="pin__color" :style="{ background: pinColor(pin) }" />
						<span class="pin__name">{{ pin.name }}</span>
						<span v-if="pinProject(pin)" class="pin__project">{{ pinProject(pin).name }}</span>
					</button>
					<NcActions :aria-label="`Actions for ${pin.name}`">
						<NcActionButton close-after-click @click="openEditPin(pin)">
							<template #icon><NcIconSvgWrapper :path="icons.mdiPencil" /></template>
							Edit
						</NcActionButton>
						<NcActionButton :disabled="index === 0" @click="movePin(index, -1)">
							<template #icon><NcIconSvgWrapper :path="icons.mdiChevronLeft" /></template>
							Move left
						</NcActionButton>
						<NcActionButton :disabled="index === pins.length - 1" @click="movePin(index, 1)">
							<template #icon><NcIconSvgWrapper :path="icons.mdiChevronRight" /></template>
							Move right
						</NcActionButton>
						<NcActionButton close-after-click @click="unpin(pin)">
							<template #icon><NcIconSvgWrapper :path="icons.mdiPinOff" /></template>
							Unpin
						</NcActionButton>
					</NcActions>
				</div>
			</div>

			<form class="top-bar" @submit.prevent="onSubmitWorkInput">
				<NcTextField v-model="workInput" style="width: 320px;" label="What have you done?" placeholder="What have you done?" />
				<NcSelect v-model="startProject"
					:options="projects"
					label="name"
					placeholder="Project"
					aria-label-combobox="Project for the new timer"
					:disabled="isRunning"
					style="min-width: 180px;"
					@update:model-value="startSelectionTouched = true" />
				<NcSelect v-model="startTags"
					:options="tags"
					label="name"
					multiple
					placeholder="Tags"
					aria-label-combobox="Tags for the new timer"
					:disabled="isRunning"
					style="min-width: 180px;"
					@update:model-value="startSelectionTouched = true" />
				<NcButton type="primary" native-type="submit">{{ isRunning ? 'Stop' : 'Start' }}</NcButton>
				<span v-if="isRunning" class="live-timer">{{ liveTimerText }}</span>
			</form>

			<div class="manual-entry-bar">
				<NcButton type="secondary" @click="openManualEntry">Manual entry</NcButton>
			</div>

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
							<td class="entry-actions">
								<NcButton type="tertiary" aria-label="Resume" @click="resume(item)">
									<template #icon><span class="icon-play" /></template>
								</NcButton>
								<NcButton type="tertiary"
									:aria-label="isPinned(item) ? 'Already pinned' : 'Pin'"
									:title="isPinned(item) ? 'Already pinned' : 'Pin for one-click start'"
									:disabled="isPinned(item)"
									@click="pinEntry(item)">
									<template #icon><NcIconSvgWrapper :path="isPinned(item) ? icons.mdiPin : icons.mdiPinOutline" /></template>
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
							<td class="editable" @click="openEditTime(item)">{{ formatDuration(item.duration) }}</td>
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

		<NcModal v-if="editingPin" size="normal" @close="editingPin = null">
			<div class="edit-form">
				<h2>Edit pinned timer</h2>
				<NcTextField v-model="pinForm.name" label="Name" />
				<NcSelect v-model="pinForm.project"
					:options="projects"
					label="name"
					input-label="Project"
					placeholder="No project" />
				<NcSelect v-model="pinForm.tags"
					:options="tags"
					label="name"
					multiple
					input-label="Tags"
					placeholder="No tags" />
				<NcButton type="primary" @click="savePin">Save</NcButton>
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
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiChevronLeft, mdiChevronRight, mdiPencil, mdiPin, mdiPinOff, mdiPinOutline } from '@mdi/js'
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
	components: { NcAppContent, NcButton, NcTextField, NcSelect, NcDateTimePicker, NcDialog, NcModal, NcActions, NcActionButton, NcIconSvgWrapper },
	data() {
		const now = new Date()
		const from = new Date(now)
		from.setDate(from.getDate() - 30)
		return {
			workInput: '',
			// Project and tags for the next timer. Pre-filled by
			// suggestStartSelection() until the user changes them.
			startProject: null,
			startTags: [],
			startSelectionTouched: false,
			// Pinned timers, in display order
			pins: [],
			editingPin: null,
			pinForm: { name: '', project: null, tags: [] },
			dragId: null,
			dropIndex: null,
			icons: { mdiChevronLeft, mdiChevronRight, mdiPencil, mdiPin, mdiPinOff, mdiPinOutline },
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
			costDrafts: {},
			costStatus: {},
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
		// All loaded entries, newest first
		recentEntries() {
			return Object.values(this.flattenedDays)
				.flatMap((day) => day.children)
				.sort((a, b) => b.start - a.start)
		},
	},
	watch: {
		workInput() {
			this.suggestStartSelection()
		},
	},
	async mounted() {
		await Promise.all([this.loadWorkIntervals(), this.loadProjects(), this.loadTags(), this.loadPins()])
		this.suggestStartSelection()
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
			// The end date picker gives a moment on that day (page load time, or
			// midnight when picked), so include the whole day: otherwise entries
			// started later that day, like a timer started just now, are missing.
			const to = new Date(this.rangeTo)
			to.setHours(23, 59, 59, 999)
			const data = await apiGet('/work-intervals', {
				from: Math.floor(this.rangeFrom.getTime() / 1000),
				to: Math.floor(to.getTime() / 1000),
				tzoffset: new Date().getTimezoneOffset(),
			})
			this.days = data.days || {}
			this.running = data.running || []
			this.setupLiveTimer(data.now)
			this.suggestStartSelection()
		},
		/**
		 * Pre-fill project and tags for the next timer, unless the user
		 * already changed them:
		 * - an earlier entry with the same name: its project and tags, which
		 *   is what the server would copy anyway
		 * - otherwise: the project of the latest entry that has one
		 */
		suggestStartSelection() {
			if (this.startSelectionTouched) return
			const name = this.workInput.trim()
			const sameName = name ? this.recentEntries.find((e) => e.name === name) : null
			if (sameName) {
				this.startProject = this.projectFor(sameName)
				const tagIds = (sameName.tags || []).map((t) => t.id)
				this.startTags = this.tags.filter((t) => tagIds.includes(t.id))
				return
			}
			const lastWithProject = this.recentEntries.find((e) => e.projectId)
			this.startProject = lastWithProject ? this.projectFor(lastWithProject) : null
			this.startTags = []
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
				const started = await this.startTimer(
					this.workInput.trim() || 'no description',
					this.startProject?.id,
					this.startTags.map((t) => t.id).join(','),
				)
				if (started) {
					this.startSelectionTouched = false
					this.suggestStartSelection()
				}
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
				return true
			} catch (e) {
				showError(e.message)
				return false
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
		// --- Pinned timers ---
		async loadPins() {
			try {
				const data = await apiGet('/pinned-timers')
				this.pins = data.PinnedTimers || []
			} catch (e) {
				showError(e.message)
			}
		},
		pinProject(pin) {
			return this.projects.find((p) => p.id === pin.projectId) || null
		},
		pinColor(pin) {
			return this.pinProject(pin)?.color || 'var(--color-border-maxcontrast)'
		},
		pinTitle(pin) {
			const parts = [`${this.isPinRunning(pin) ? 'Stop' : 'Start'} "${pin.name}"`]
			const project = this.pinProject(pin)
			if (project) parts.push(`Project: ${project.name}`)
			const tagNames = this.tags.filter((t) => pin.tagIds.includes(t.id)).map((t) => t.name)
			if (tagNames.length) parts.push(`Tags: ${tagNames.join(', ')}`)
			return parts.join('\n')
		},
		sameIds(a, b) {
			const x = [...a].sort((m, n) => m - n)
			const y = [...b].sort((m, n) => m - n)
			return x.length === y.length && x.every((v, i) => v === y[i])
		},
		isPinned(item) {
			const tagIds = (item.tags || []).map((t) => t.id)
			return this.pins.some((pin) => pin.name === item.name
				&& (pin.projectId ?? null) === (item.projectId ?? null)
				&& this.sameIds(pin.tagIds, tagIds))
		},
		isPinRunning(pin) {
			const running = this.running[0]
			return !!running && running.name === pin.name
				&& (running.projectId ?? null) === (pin.projectId ?? null)
		},
		async onPinClick(pin) {
			// A pin of the running timer stops it; any other pin switches to it
			if (this.isPinRunning(pin)) {
				await this.stopTimer()
				return
			}
			if (this.isRunning) {
				await this.stopTimer()
			}
			await this.startTimer(pin.name, pin.projectId, pin.tagIds.join(','))
		},
		async pinEntry(item) {
			try {
				const data = await apiPost('/add-pinned-timer', {
					name: item.name,
					projectId: item.projectId ?? '',
					tagIds: (item.tags || []).map((t) => t.id).join(','),
				})
				this.pins = data.PinnedTimers || []
			} catch (e) {
				showError(e.message)
			}
		},
		openEditPin(pin) {
			this.editingPin = pin
			this.pinForm = {
				name: pin.name,
				project: this.pinProject(pin),
				tags: this.tags.filter((t) => pin.tagIds.includes(t.id)),
			}
		},
		async savePin() {
			try {
				const data = await apiPost(`/edit-pinned-timer/${this.editingPin.id}`, {
					name: this.pinForm.name,
					projectId: this.pinForm.project?.id ?? '',
					tagIds: this.pinForm.tags.map((t) => t.id).join(','),
				})
				this.pins = data.PinnedTimers || []
				this.editingPin = null
			} catch (e) {
				showError(e.message)
			}
		},
		async unpin(pin) {
			try {
				const data = await apiPost(`/delete-pinned-timer/${pin.id}`)
				this.pins = data.PinnedTimers || []
			} catch (e) {
				showError(e.message)
			}
		},
		onPinDragStart(event, pin) {
			this.dragId = pin.id
			event.dataTransfer.effectAllowed = 'move'
			// Firefox only starts dragging when some data is set
			event.dataTransfer.setData('text/plain', String(pin.id))
		},
		onPinDrop(index) {
			const from = this.pins.findIndex((p) => p.id === this.dragId)
			this.dragId = null
			this.dropIndex = null
			if (from < 0 || from === index) return
			const pins = [...this.pins]
			const [moved] = pins.splice(from, 1)
			pins.splice(index, 0, moved)
			this.savePinOrder(pins)
		},
		movePin(index, delta) {
			const target = index + delta
			if (target < 0 || target >= this.pins.length) return
			const pins = [...this.pins]
			const [moved] = pins.splice(index, 1)
			pins.splice(target, 0, moved)
			this.savePinOrder(pins)
		},
		async savePinOrder(pins) {
			this.pins = pins // show the new order right away
			try {
				const data = await apiPost('/reorder-pinned-timers', { ids: pins.map((p) => p.id).join(',') })
				this.pins = data.PinnedTimers || []
			} catch (e) {
				showError(e.message)
				await this.loadPins()
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
.pinned-row {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	padding-bottom: 12px;
}
.pin {
	display: flex;
	align-items: center;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius-element, var(--border-radius-large));
	background-color: var(--color-main-background);
	cursor: grab;
}
.pin--active {
	border-color: var(--color-primary-element);
	background-color: var(--color-primary-element-light);
}
.pin--drop-target {
	outline: 2px dashed var(--color-primary-element);
	outline-offset: 2px;
}
.pin__start {
	display: flex;
	align-items: center;
	gap: 8px;
	margin: 0;
	padding: 0 4px 0 12px;
	min-height: var(--default-clickable-area);
	border: none;
	background: transparent;
	color: var(--color-main-text);
	font: inherit;
	cursor: pointer;
}
.pin__color {
	flex-shrink: 0;
	width: 10px;
	height: 10px;
	border-radius: 50%;
}
.pin__name {
	font-weight: bold;
}
.pin__project {
	color: var(--color-text-maxcontrast);
}
.entry-actions {
	white-space: nowrap;
}
.entry-actions > * {
	display: inline-flex;
}
.top-bar {
	display: flex;
	align-items: center;
	gap: 12px;
	padding-bottom: 12px;
	flex-wrap: wrap;
}
.manual-entry-bar {
	display: flex;
	padding-bottom: 12px;
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
