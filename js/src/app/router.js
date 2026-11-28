import { createRouter, createWebHistory } from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import TagsView from './views/TagsView.vue'
import ClientsView from './views/ClientsView.vue'
import GoalsView from './views/GoalsView.vue'
import AbsencesView from './views/AbsencesView.vue'
import AbsencesAdminView from './views/AbsencesAdminView.vue'
import WorkingTimeView from './views/WorkingTimeView.vue'
import TeamAbsencesView from './views/TeamAbsencesView.vue'
import ProjectsView from './views/ProjectsView.vue'
import TimerView from './views/TimerView.vue'
import DashboardView from './views/DashboardView.vue'
import ReportsView from './views/ReportsView.vue'
import TimelinesView from './views/TimelinesView.vue'
import TimelinesAdminView from './views/TimelinesAdminView.vue'

const views = [
	{ path: '', name: 'timer', title: 'Timer', component: TimerView },
	{ path: 'dashboard', name: 'dashboard', title: 'Dashboard', component: DashboardView },
	{ path: 'goals', name: 'goals', title: 'Goals', component: GoalsView },
	{ path: 'absences', name: 'absences', title: 'Absences', component: AbsencesView },
	{ path: 'team-absences', name: 'team-absences', title: 'Team calendar', component: TeamAbsencesView },
	{ path: 'working-time', name: 'working-time', title: 'Working time', component: WorkingTimeView },
	{ path: 'absences-admin', name: 'absences-admin', title: 'Absences Admin', component: AbsencesAdminView, adminOnly: true },
	{ path: 'reports', name: 'reports', title: 'Reports', component: ReportsView },
	{ path: 'timelines', name: 'timelines', title: 'Timelines', component: TimelinesView },
	{ path: 'timelines-admin', name: 'timelines-admin', title: 'Timelines Admin', component: TimelinesAdminView, adminOnly: true },
	{ path: 'projects', name: 'projects', title: 'Projects', component: ProjectsView },
	{ path: 'clients', name: 'clients', title: 'Clients', component: ClientsView },
	{ path: 'tags', name: 'tags', title: 'Tags', component: TagsView },
]

const routes = views.map(({ path, name, component }) => ({
	path: `/${path}`,
	name,
	component,
}))

export default createRouter({
	history: createWebHistory(generateUrl('/apps/timetracker')),
	routes,
})

export { views }
