<template>
	<NcAppContent>
		<div class="view-page">
			<h1 class="page-title">Timer</h1>

			<form class="top-bar" @submit.prevent="onSubmitWorkInput">
				<NcTextField v-model="workInput" style="width: 320px;" label="What have you done?" placeholder="What have you done?" />
				<NcSelect v-model="startProject"
					:options="projects"
					label="name"
					placeholder="Project"
					aria-label-combobox="Project for the new timer"
					style="min-width: 180px;"
					@update:model-value="startSelectionTouched = true" />
				<NcSelect v-model="startTags"
					:options="tags"
					label="name"
					multiple
					placeholder="Tags"
					aria-label-combobox="Tags for the new timer"
					style="min-width: 180px;"
					@update:model-value="startSelectionTouched = true" />
				<NcButton type="primary" native-type="submit">Start</NcButton>
				<NcButton type="button" variant="secondary" :disabled="!isRunning" @click="stopTimer(true)">Stop</NcButton>
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

			<div v-if="pins.length" class="day-group pinned-group">
				<h2>Pinned</h2>
				<table class="entity-table">
					<thead>
						<tr>
							<th class="col-actions"></th>
							<th>Name</th>
							<th>Project</th>
							<th>Tags</th>
							<th>Today</th>
							<th></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="(pin, index) in pins"
							:key="pin.id"
							class="pin-row"
							:class="{ 'row--running': isPinRunning(pin), 'row--drop-target': dropIndex === index && dragId !== pin.id }"
							draggable="true"
							@dragstart="onPinDragStart($event, pin)"
							@dragover.prevent="dropIndex = index"
							@drop.prevent="onPinDrop(index)"
							@dragend="dragId = null; dropIndex = null">
							<td class="entry-actions">
								<NcButton type="tertiary"
									:aria-label="`Start ${pin.name}`"
									title="Start"
									:disabled="isPinRunning(pin)"
									@click="startPin(pin)">
									<template #icon><NcIconSvgWrapper :path="icons.mdiPlay" /></template>
								</NcButton>
								<NcButton type="tertiary"
									:aria-label="`Stop ${pin.name}`"
									title="Stop"
									:disabled="!isPinRunning(pin)"
									@click="stopTimer(false)">
									<template #icon><NcIconSvgWrapper :path="icons.mdiStop" /></template>
								</NcButton>
							</td>
							<td><span class="wi-name">{{ pin.name }}</span></td>
							<td>
								<span v-if="pinProject(pin)" class="project-label">
									<span class="color-dot" :style="{ background: pinProject(pin).color }" />
									{{ pinProject(pin).name }}
								</span>
							</td>
							<td>{{ pinTagNames(pin) }}</td>
							<td class="duration">{{ formatDuration(pinTodaySeconds(pin)) }}</td>
							<td></td>
							<td>
								<NcActions :aria-label="`Actions for ${pin.name}`">
									<NcActionButton close-after-click @click="openEditPin(pin)">
										<template #icon><NcIconSvgWrapper :path="icons.mdiPencil" /></template>
										Edit
									</NcActionButton>
									<NcActionButton :disabled="index === 0" @click="movePin(index, -1)">
										<template #icon><NcIconSvgWrapper :path="icons.mdiChevronUp" /></template>
										Move up
									</NcActionButton>
									<NcActionButton :disabled="index === pins.length - 1" @click="movePin(index, 1)">
										<template #icon><NcIconSvgWrapper :path="icons.mdiChevronDown" /></template>
										Move down
									</NcActionButton>
									<NcActionButton close-after-click @click="unpin(pin)">
										<template #icon><NcIconSvgWrapper :path="icons.mdiPinOff" /></template>
										Unpin
									</NcActionButton>
								</NcActions>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div v-for="day in bundledDays" :key="day.label" class="day-group">
				<h2>{{ day.label }}</h2>
				<table class="entity-table">
					<thead>
						<tr>
							<th class="col-actions"></th>
							<th>Name / Details</th>
							<th>Project</th>
							<th>Tags</th>
							<th>Duration</th>
							<th>Cost</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<template v-for="bundle in day.bundles" :key="bundle.key">
							<tr class="bundle-row" :class="{ 'row--running': isBundleRunning(bundle) }">
								<td class="entry-actions">
									<NcButton type="tertiary"
										:aria-label="`Start ${bundle.name}`"
										title="Start"
										:disabled="isBundleRunning(bundle)"
										@click="startBundle(bundle)">
										<template #icon><NcIconSvgWrapper :path="icons.mdiPlay" /></template>
									</NcButton>
									<NcButton type="tertiary"
										:aria-label="`Stop ${bundle.name}`"
										title="Stop"
										:disabled="!isBundleRunning(bundle)"
										@click="stopTimer(false)">
										<template #icon><NcIconSvgWrapper :path="icons.mdiStop" /></template>
									</NcButton>
									<NcButton type="tertiary"
										:aria-label="isPinned(bundle) ? 'Already pinned' : 'Pin'"
										:title="isPinned(bundle) ? 'Already pinned' : 'Pin'"
										:disabled="isPinned(bundle)"
										@click="pinEntry(bundle)">
										<template #icon><NcIconSvgWrapper :path="isPinned(bundle) ? icons.mdiPin : icons.mdiPinOutline" /></template>
									</NcButton>
								</td>
								<td v-if="bundle.blocks.length === 1" class="editable" @click="openEditNameDetails(bundle.blocks[0])">
									<div class="wi-name">{{ bundle.name }}</div>
									<div v-if="bundle.blocks[0].details" class="wi-details">{{ bundle.blocks[0].details }}</div>
								</td>
								<td v-else>
									<button type="button"
										class="bundle-toggle"
										:aria-expanded="isExpanded(day, bundle) ? 'true' : 'false'"
										@click="toggleExpanded(day, bundle)">
										<NcIconSvgWrapper :path="isExpanded(day, bundle) ? icons.mdiChevronDown : icons.mdiChevronRight" :size="20" />
										<span class="wi-name">{{ bundle.name }}</span>
										<span class="wi-count">{{ bundle.blocks.length }} entries</span>
									</button>
								</td>
								<td>
									<NcSelect :model-value="projectFor(bundle)"
										:options="projects"
										label="name"
										placeholder="Project"
										style="width: 100%; min-width: 0;"
										@update:model-value="(p) => updateBundleProject(bundle, p)" />
								</td>
								<td>
									<NcSelect :model-value="bundle.tags"
										:options="tags"
										label="name"
										multiple
										taggable
										push-tags
										placeholder="Tags"
										style="width: 100%; min-width: 0;"
										@update:model-value="(t) => updateBundleTags(bundle, t)" />
								</td>
								<td v-if="bundle.blocks.length === 1" class="editable duration" @click="openEditTime(bundle.blocks[0])">
									{{ formatDuration(blockSeconds(bundle.blocks[0])) }}
								</td>
								<td v-else class="duration">{{ formatDuration(bundleSeconds(bundle)) }}</td>
								<td>
									<NcTextField v-if="bundle.blocks.length === 1"
										:model-value="costDraft(bundle.blocks[0])"
										:success="costStatus[bundle.blocks[0].id] === 'success'"
										:error="costStatus[bundle.blocks[0].id] === 'error'"
										style="width: 90px;"
										@update:model-value="(v) => (costDrafts[bundle.blocks[0].id] = v)"
										@blur="commitCost(bundle.blocks[0])" />
									<span v-else>{{ formatCost(bundleCost(bundle)) }}</span>
								</td>
								<td>
									<NcButton type="tertiary"
										:aria-label="bundle.blocks.length === 1 ? 'Delete' : `Delete all ${bundle.blocks.length} entries`"
										@click="bundle.blocks.length === 1 ? removeInterval(bundle.blocks[0]) : removeBundle(day, bundle)">
										<template #icon><span class="icon-close" /></template>
									</NcButton>
								</td>
							</tr>
							<template v-if="bundle.blocks.length > 1 && isExpanded(day, bundle)">
								<tr v-for="block in bundle.blocks" :key="block.id" class="block-row" :class="{ 'row--running': block.running == 1 }">
									<td></td>
									<td class="editable" @click="openEditNameDetails(block)">
										<div class="wi-time">{{ blockTimeRange(block) }}</div>
										<div v-if="block.details" class="wi-details">{{ block.details }}</div>
									</td>
									<td></td>
									<td></td>
									<td class="editable duration" @click="openEditTime(block)">{{ formatDuration(blockSeconds(block)) }}</td>
									<td>
										<NcTextField :model-value="costDraft(block)"
											:success="costStatus[block.id] === 'success'"
											:error="costStatus[block.id] === 'error'"
											style="width: 90px;"
											@update:model-value="(v) => (costDrafts[block.id] = v)"
											@blur="commitCost(block)" />
									</td>
									<td>
										<NcButton type="tertiary" aria-label="Delete" @click="removeInterval(block)">
											<template #icon><span class="icon-close" /></template>
										</NcButton>
									</td>
								</tr>
							</template>
						</template>
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
import { mdiChevronDown, mdiChevronRight, mdiChevronUp, mdiPencil, mdiPin, mdiPinOff, mdiPinOutline, mdiPlay, mdiStop } from '@mdi/js'
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
			icons: { mdiChevronDown, mdiChevronRight, mdiChevronUp, mdiPencil, mdiPin, mdiPinOff, mdiPinOutline, mdiPlay, mdiStop },
			// Expanded bundles, keyed by day label and bundle key
			expanded: {},
			// Current server time in seconds, ticking while a timer runs
			nowSec: Math.floor(Date.now() / 1000),
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
		/**
		 * Days, newest first, each with its entries bundled by name, project
		 * and tags: every Start still records its own block, but the list
		 * shows one row per timer and day, with the blocks underneath.
		 */
		bundledDays() {
			return Object.entries(this.flattenedDays).map(([label, day]) => {
				const bundles = new Map()
				for (const block of day.children) {
					const key = this.timerKey(block.name, block.projectId, (block.tags || []).map((t) => t.id))
					if (!bundles.has(key)) {
						bundles.set(key, {
							key,
							name: block.name,
							projectId: block.projectId,
							tags: block.tags || [],
							blocks: [],
						})
					}
					bundles.get(key).blocks.push(block) // children are sorted newest first
				}
				return { label, latest: day.children[0]?.start || 0, bundles: [...bundles.values()] }
			}).sort((a, b) => b.latest - a.latest)
		},
		// The running block, if it's in the loaded range
		runningBlock() {
			const running = this.running[0]
			if (!running) return null
			return this.recentEntries.find((e) => e.id === running.id) || null
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
				this.nowSec = Math.floor(Date.now() / 1000 - skew)
				this.liveTimerText = formatDuration(this.nowSec - start)
			}
			tick()
			this.liveTimerInterval = setInterval(tick, 1000)
		},
		async onSubmitWorkInput() {
			// Read the start bar first: stopping a running timer clears the description
			const name = this.workInput.trim() || 'no description'
			const projectId = this.startProject?.id
			const tagIds = this.startTags.map((t) => t.id).join(',')
			if (this.isRunning) {
				await this.stopTimer()
			}
			const started = await this.startTimer(name, projectId, tagIds)
			if (started) {
				this.startSelectionTouched = false
				this.suggestStartSelection()
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
		/**
		 * Stop the running timer. With rename, the description typed in the
		 * start bar becomes its name (start first, describe when stopping);
		 * otherwise the timer keeps its name and the start bar is left alone.
		 */
		async stopTimer(rename = false) {
			const name = rename === true ? (this.workInput.trim() || 'no description') : 'no description'
			try {
				await apiPost(`/stop-timer/${encodeURIComponent(encodeURIComponent(name))}`)
				if (rename === true) {
					this.workInput = ''
				}
				await this.loadWorkIntervals()
			} catch (e) {
				showError(e.message)
			}
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
		pinTagNames(pin) {
			return this.tags.filter((t) => pin.tagIds.includes(t.id)).map((t) => t.name).join(', ')
		},
		timerKey(name, projectId, tagIds) {
			return [name, projectId ?? '', [...tagIds].sort((a, b) => a - b).join(',')].join('\u0000')
		},
		pinKey(pin) {
			return this.timerKey(pin.name, pin.projectId, pin.tagIds)
		},
		// Booked seconds of a block, counting a running block up to now
		blockSeconds(block) {
			if (block.running == 1) {
				return Math.max(0, this.nowSec - block.start)
			}
			return block.duration || 0
		},
		bundleSeconds(bundle) {
			return bundle.blocks.reduce((sum, b) => sum + this.blockSeconds(b), 0)
		},
		bundleCost(bundle) {
			return bundle.blocks.reduce((sum, b) => sum + (b.cost || 0), 0)
		},
		formatCost(cents) {
			return (cents / 100).toFixed(2)
		},
		blockTimeRange(block) {
			const time = (sec) => new Date(sec * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
			const end = block.running == 1 ? 'now' : time(block.start + (block.duration || 0))
			return `${time(block.start)} – ${end}`
		},
		expandKey(day, bundle) {
			return `${day.label}\u0000${bundle.key}`
		},
		isExpanded(day, bundle) {
			return !!this.expanded[this.expandKey(day, bundle)]
		},
		toggleExpanded(day, bundle) {
			const key = this.expandKey(day, bundle)
			this.expanded[key] = !this.expanded[key]
		},
		isBundleRunning(bundle) {
			return bundle.blocks.some((b) => b.running == 1)
		},
		// Today's booked seconds of a pin: all of today's blocks with its name, project and tags
		pinTodaySeconds(pin) {
			const today = new Date().toDateString()
			const key = this.pinKey(pin)
			return this.recentEntries
				.filter((e) => new Date(e.start * 1000).toDateString() === today
					&& this.timerKey(e.name, e.projectId, (e.tags || []).map((t) => t.id)) === key)
				.reduce((sum, e) => sum + this.blockSeconds(e), 0)
		},
		async startBundle(bundle) {
			if (this.isRunning) {
				await this.stopTimer()
			}
			await this.startTimer(bundle.name, bundle.projectId, bundle.tags.map((t) => t.id).join(','))
		},
		async startPin(pin) {
			if (this.isRunning) {
				await this.stopTimer()
			}
			await this.startTimer(pin.name, pin.projectId, pin.tagIds.join(','))
		},
		async updateBundleProject(bundle, project) {
			try {
				for (const block of bundle.blocks) {
					await apiPost(`/update-work-interval/${block.id}`, { projectId: project?.id ?? '' })
				}
			} catch (e) {
				showError(e.message)
			}
			await this.loadWorkIntervals()
		},
		async updateBundleTags(bundle, tags) {
			try {
				const tagId = tags.map((t) => t.id ?? t.name).join(',')
				for (const block of bundle.blocks) {
					await apiPost(`/update-work-interval/${block.id}`, { tagId })
				}
			} catch (e) {
				showError(e.message)
			}
			await Promise.all([this.loadWorkIntervals(), this.loadTags()])
		},
		async removeBundle(day, bundle) {
			if (!(await this.confirm(`Delete all ${bundle.blocks.length} entries of "${bundle.name}" on ${day.label}?`))) return
			try {
				for (const block of bundle.blocks) {
					await apiPost(`/delete-work-interval/${block.id}`)
				}
			} catch (e) {
				showError(e.message)
			}
			await this.loadWorkIntervals()
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
			const block = this.runningBlock
			if (block) {
				return this.timerKey(block.name, block.projectId, (block.tags || []).map((t) => t.id)) === this.pinKey(pin)
			}
			const running = this.running[0]
			return !!running && running.name === pin.name && (running.projectId ?? null) === (pin.projectId ?? null)
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
.row--running td {
	background-color: var(--color-primary-element-light);
}
.row--drop-target td {
	box-shadow: inset 0 2px 0 var(--color-primary-element);
}
.pin-row {
	cursor: grab;
}
.col-actions {
	width: 1%;
}
.project-label {
	display: inline-flex;
	align-items: center;
	gap: 6px;
}
.color-dot {
	flex-shrink: 0;
	width: 10px;
	height: 10px;
	border-radius: 50%;
}
.duration {
	font-variant-numeric: tabular-nums;
}
.bundle-toggle {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	margin: 0;
	padding: 0;
	min-height: auto;
	border: none;
	background: transparent;
	color: var(--color-main-text);
	font: inherit;
	cursor: pointer;
}
.wi-count {
	color: var(--color-text-maxcontrast);
	font-size: 0.85em;
}
.block-row td {
	padding-top: 4px;
	padding-bottom: 4px;
}
.block-row .wi-time {
	padding-inline-start: 26px;
	color: var(--color-text-maxcontrast);
}
.block-row .wi-details {
	padding-inline-start: 26px;
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
	/* narrow screens: scroll the table, not the page */
	overflow-x: auto;
}
.entity-table {
	width: 100%;
	border-collapse: collapse;
	/* the same column widths in every group (Pinned and each day) */
	table-layout: fixed;
	min-width: 850px;
}
.entity-table th:nth-child(1) { width: 140px; }
.entity-table th:nth-child(3) { width: 220px; }
.entity-table th:nth-child(4) { width: 200px; }
.entity-table th:nth-child(5) { width: 110px; }
.entity-table th:nth-child(6) { width: 120px; }
.entity-table th:nth-child(7) { width: 60px; }
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
