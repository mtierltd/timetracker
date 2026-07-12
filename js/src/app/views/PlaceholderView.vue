<template>
	<NcAppContent>
		<NcEmptyContent :name="title" :description="description">
			<template #icon>
				<span :class="icon" />
			</template>
		</NcEmptyContent>
	</NcAppContent>
</template>

<script>
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'

export default {
	name: 'PlaceholderView',
	components: {
		NcAppContent,
		NcEmptyContent,
	},
	props: {
		title: {
			type: String,
			required: true,
		},
		icon: {
			type: String,
			default: 'icon-category-organization',
		},
	},
	computed: {
		description() {
			return `The ${this.title} view has not been migrated to the new interface yet.`
		},
	},
}
</script>
