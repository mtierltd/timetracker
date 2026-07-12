<template>
	<NcContent app-name="timetracker">
		<NcAppNavigation aria-label="Time Tracker navigation">
			<template #list>
				<NcAppNavigationItem v-for="item in navItems"
					:key="item.name"
					:name="item.title"
					:to="{ name: item.name }" />
			</template>
		</NcAppNavigation>
		<router-view />
	</NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import { getCurrentUser } from '@nextcloud/auth'
import { views } from './router.js'

export default {
	name: 'App',
	components: {
		NcContent,
		NcAppNavigation,
		NcAppNavigationItem,
	},
	data() {
		return {
			isAdmin: !!getCurrentUser()?.isAdmin,
		}
	},
	computed: {
		navItems() {
			return views.filter((item) => item.name !== 'timelines-admin' || this.isAdmin)
		},
	},
}
</script>
