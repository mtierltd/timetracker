<template>
	<NcAppContent>
		<div class="view-page">
			<h1 class="page-title">Dashboard</h1>

			<div class="date-range">
				<select class="native-select" @change="applyPreset($event.target.value); $event.target.value = ''">
					<option value="" disabled selected>Quick range...</option>
					<option v-for="p in presets" :key="p.key" :value="p.key">{{ p.label }}</option>
				</select>
				<NcDateTimePicker v-model="rangeFrom" type="date" :clearable="false" />
				<span>to</span>
				<NcDateTimePicker v-model="rangeTo" type="date" :clearable="false" />
				<NcButton @click="loadReport">Refresh</NcButton>
			</div>

			<p class="summary">
				Total time: {{ formatHoursMinutes(totalDuration) }} &nbsp;&middot;&nbsp;
				Total costs: {{ (totalCost / 100).toFixed(2) }}
			</p>

			<div class="charts">
				<div class="chart-box">
					<h2>Time by project</h2>
					<Doughnut v-if="timeChartData.labels.length" :data="timeChartData" :options="timeChartOptions" />
					<p v-else>No data to display</p>
				</div>
				<div class="chart-box">
					<h2>Cost by project</h2>
					<Doughnut v-if="costChartData.labels.length" :data="costChartData" :options="costChartOptions" />
					<p v-else>No data to display</p>
				</div>
			</div>
		</div>
	</NcAppContent>
</template>

<script>
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js'
import { Doughnut } from 'vue-chartjs'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'
import { apiGet } from '../api.js'
import { DATE_RANGE_PRESETS, getPresetRange } from '../dateRangePresets.js'

ChartJS.register(ArcElement, Tooltip, Legend)

const COLORS = ['#0082c9', '#e9322d', '#f1c40f', '#27ae60', '#8e44ad', '#e67e22', '#16a085',
	'#2c3e50', '#c0392b', '#2980b9', '#f39c12', '#7f8c8d', '#34495e', '#1abc9c', '#9b59b6']

export default {
	name: 'DashboardView',
	components: { NcAppContent, NcButton, NcDateTimePicker, Doughnut },
	data() {
		const now = new Date()
		const from = new Date(now)
		from.setDate(from.getDate() - 29)
		return {
			rangeFrom: from,
			rangeTo: now,
			presets: DATE_RANGE_PRESETS,
			items: [],
		}
	},
	computed: {
		totalDuration() {
			return this.items.reduce((sum, i) => sum + (i.totalDuration || 0), 0)
		},
		totalCost() {
			return this.items.reduce((sum, i) => sum + (i.cost || 0), 0)
		},
		byProject() {
			const map = new Map()
			for (const item of this.items) {
				const key = item.project || 'Project Not Set'
				if (!map.has(key)) map.set(key, { duration: 0, cost: 0 })
				const entry = map.get(key)
				entry.duration += item.totalDuration || 0
				entry.cost += item.cost || 0
			}
			return map
		},
		timeChartData() {
			const labels = [...this.byProject.keys()]
			return {
				labels,
				datasets: [{
					data: labels.map((l) => this.byProject.get(l).duration),
					backgroundColor: labels.map((_, i) => COLORS[i % COLORS.length]),
				}],
			}
		},
		costChartData() {
			const labels = [...this.byProject.keys()]
			return {
				labels,
				datasets: [{
					data: labels.map((l) => this.byProject.get(l).cost),
					backgroundColor: labels.map((_, i) => COLORS[i % COLORS.length]),
				}],
			}
		},
		timeChartOptions() {
			return {
				plugins: {
					tooltip: {
						callbacks: {
							label: (ctx) => `${ctx.label}: ${this.formatHoursMinutes(ctx.raw)}`,
						},
					},
				},
			}
		},
		costChartOptions() {
			return {
				plugins: {
					tooltip: {
						callbacks: {
							label: (ctx) => `${ctx.label}: ${(ctx.raw / 100).toFixed(2)}`,
						},
					},
				},
			}
		},
	},
	async mounted() {
		await this.loadReport()
	},
	methods: {
		formatHoursMinutes(seconds) {
			const h = Math.floor(seconds / 3600)
			const m = Math.floor((seconds % 3600) / 60)
			return `${h} hours ${m} minutes`
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
				group1: 'client',
				group2: 'project',
				timegroup: '',
				filterProjectId: '',
				filterClientId: '',
			})
			this.items = data.items || []
		},
	},
}
</script>

<style scoped>
.view-page {
	padding: 50px 20px 20px;
}
.date-range {
	display: flex;
	align-items: center;
	gap: 8px;
	padding-bottom: 16px;
}
.native-select {
	height: 34px;
}
.summary {
	font-weight: bold;
	margin-bottom: 20px;
}
.charts {
	display: flex;
	gap: 40px;
	flex-wrap: wrap;
}
.chart-box {
	width: 400px;
}
</style>
