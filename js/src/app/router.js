import { createRouter, createWebHistory } from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import PlaceholderView from './views/PlaceholderView.vue'

const views = [
	{ path: 'timer', name: 'timer', title: 'Timer' },
	{ path: 'dashboard', name: 'dashboard', title: 'Dashboard' },
	{ path: 'goals', name: 'goals', title: 'Goals' },
	{ path: 'reports', name: 'reports', title: 'Reports' },
	{ path: 'timelines', name: 'timelines', title: 'Timelines' },
	{ path: 'timelines-admin', name: 'timelines-admin', title: 'Timelines Admin' },
	{ path: 'projects', name: 'projects', title: 'Projects' },
	{ path: 'clients', name: 'clients', title: 'Clients' },
	{ path: 'tags', name: 'tags', title: 'Tags' },
]

const routes = views.map(({ path, name, title }) => ({
	path: `/${path}`,
	name,
	component: PlaceholderView,
	props: { title },
}))

routes.push({ path: '/', redirect: '/timer' })

export default createRouter({
	history: createWebHistory(generateUrl('/apps/timetracker/spa')),
	routes,
})

export { views }
