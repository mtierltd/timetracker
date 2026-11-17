<template>
	<div class="pinned-timers" role="list" aria-label="Pinned timers">
		<div v-for="(item, index) in items"
			:key="item.pin.id"
			role="listitem"
			class="pin"
			:class="{ 'pin--running': item.running, 'pin--drop': dropIndex === index && dragIndex !== index }"
			draggable="true"
			@dragstart="onDragStart($event, index)"
			@dragover.prevent="dropIndex = index"
			@dragleave="dropIndex === index && (dropIndex = null)"
			@drop.prevent="onDrop(index)"
			@dragend="dragIndex = dropIndex = null">
			<button type="button"
				class="pin__start"
				:aria-pressed="item.running ? 'true' : 'false'"
				:title="item.running ? `Stop “${item.pin.name}”` : `Start “${item.pin.name}”`"
				@click="$emit('start', item.pin)">
				<span class="pin__icon"><NcIconSvgWrapper :path="item.running ? mdiStop : mdiPlay" :size="18" /></span>
				<span v-if="item.pin.projectId" class="pin__dot" :style="{ backgroundColor: item.pin.projectColor }" />
				<span class="pin__text">
					<span class="pin__name">{{ item.pin.name }}</span>
					<span v-if="item.pin.projectName" class="pin__project">{{ item.pin.projectName }}</span>
				</span>
				<span v-if="item.seconds >= 60 || item.running" class="pin__time" :title="'Today'">{{ formatShort(item.seconds, item.running) }}</span>
			</button>
			<NcActions class="pin__menu" :aria-label="`Actions for “${item.pin.name}”`">
				<NcActionButton close-after-click @click="$emit('edit', item.pin)">
					<template #icon><NcIconSvgWrapper :path="mdiPencil" /></template>
					Edit
				</NcActionButton>
				<NcActionButton v-if="index > 0" close-after-click @click="move(index, -1)">
					<template #icon><NcIconSvgWrapper :path="mdiChevronLeft" /></template>
					Move left
				</NcActionButton>
				<NcActionButton v-if="index < items.length - 1" close-after-click @click="move(index, 1)">
					<template #icon><NcIconSvgWrapper :path="mdiChevronRight" /></template>
					Move right
				</NcActionButton>
				<NcActionButton close-after-click @click="$emit('remove', item.pin)">
					<template #icon><NcIconSvgWrapper :path="mdiPinOff" /></template>
					Unpin
				</NcActionButton>
			</NcActions>
		</div>
		<NcButton type="tertiary" class="pin-add" aria-label="Add pinned timer" title="Add pinned timer" @click="$emit('add')">
			<template #icon><NcIconSvgWrapper :path="mdiPlus" /></template>
			<template v-if="items.length === 0">Pin a timer</template>
		</NcButton>
	</div>
</template>

<script>
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiChevronLeft, mdiChevronRight, mdiPencil, mdiPinOff, mdiPlay, mdiPlus, mdiStop } from '@mdi/js'

/**
 * The row of pinned timers above the entries. Each pin starts (or, while
 * it runs, stops) a timer in one click and shows today's time for it.
 * Reorder by drag and drop, or with "Move left/right" in the pin's menu.
 */
export default {
	name: 'PinnedTimers',
	components: { NcActions, NcActionButton, NcButton, NcIconSvgWrapper },
	props: {
		/** [{ pin, running: boolean, seconds: number }] in display order */
		items: { type: Array, required: true },
	},
	emits: ['start', 'edit', 'remove', 'reorder', 'add'],
	data() {
		return {
			mdiChevronLeft, mdiChevronRight, mdiPencil, mdiPinOff, mdiPlay, mdiPlus, mdiStop,
			dragIndex: null,
			dropIndex: null,
		}
	},
	methods: {
		// H:MM, or H:MM:SS while running so the live time visibly ticks
		formatShort(seconds, withSeconds) {
			seconds = Math.max(0, Math.floor(seconds))
			const h = Math.floor(seconds / 3600)
			const m = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0')
			return withSeconds ? `${h}:${m}:${String(seconds % 60).padStart(2, '0')}` : `${h}:${m}`
		},
		ids() {
			return this.items.map((i) => i.pin.id)
		},
		move(index, delta) {
			const ids = this.ids()
			const [id] = ids.splice(index, 1)
			ids.splice(index + delta, 0, id)
			this.$emit('reorder', ids)
		},
		onDragStart(event, index) {
			this.dragIndex = index
			event.dataTransfer.effectAllowed = 'move'
			event.dataTransfer.setData('text/plain', String(this.items[index].pin.id))
		},
		onDrop(index) {
			const from = this.dragIndex
			this.dragIndex = this.dropIndex = null
			if (from === null || from === index) return
			const ids = this.ids()
			const [id] = ids.splice(from, 1)
			ids.splice(index, 0, id)
			this.$emit('reorder', ids)
		},
	},
}
</script>

<style scoped>
.pinned-timers {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
	padding-bottom: 16px;
}
.pin {
	display: flex;
	align-items: center;
	max-width: 100%;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius-element, 8px);
	background-color: var(--color-main-background);
}
.pin--running {
	border-color: var(--color-primary-element);
	background-color: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
}
.pin--drop {
	box-shadow: -3px 0 0 var(--color-primary-element);
}
.pin__start {
	display: flex;
	align-items: center;
	gap: 6px;
	min-width: 0;
	min-height: var(--default-clickable-area, 34px);
	margin: 0;
	padding: 2px 4px 2px 8px;
	border: none;
	border-radius: var(--border-radius-element, 8px) 0 0 var(--border-radius-element, 8px);
	background: transparent;
	color: inherit;
	font: inherit;
	text-align: left;
	cursor: pointer;
}
.pin__start:hover,
.pin__start:focus-visible {
	background-color: var(--color-background-hover);
}
.pin--running .pin__start:hover,
.pin--running .pin__start:focus-visible {
	background-color: var(--color-primary-element-light-hover);
}
/* The wrapper keeps the icon from being a direct child of the button, which
   Nextcloud's global "button > span[class^=icon-]" rule would restyle. */
.pin__icon {
	display: flex;
	flex-shrink: 0;
}
.pin__icon :deep(.icon-vue) {
	min-width: 20px;
	min-height: 20px;
}
.pin__dot {
	flex-shrink: 0;
	width: 10px;
	height: 10px;
	border-radius: 50%;
	box-shadow: inset 0 0 0 1px var(--color-border-dark);
}
.pin__text {
	display: flex;
	flex-direction: column;
	min-width: 0;
	line-height: 1.2;
}
.pin__name,
.pin__project {
	overflow: hidden;
	white-space: nowrap;
	text-overflow: ellipsis;
	max-width: 180px;
}
.pin__name {
	font-weight: bold;
}
.pin__project {
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
}
.pin--running .pin__project {
	color: inherit;
	opacity: 0.8;
}
.pin__time {
	flex-shrink: 0;
	padding-left: 4px;
	font-variant-numeric: tabular-nums;
}
.pin__menu {
	flex-shrink: 0;
}
</style>
