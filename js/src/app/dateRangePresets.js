export const DATE_RANGE_PRESETS = [
	{ key: 'today', label: 'Today' },
	{ key: 'yesterday', label: 'Yesterday' },
	{ key: 'last7', label: 'Last 7 days' },
	{ key: 'last30', label: 'Last 30 days' },
	{ key: 'last90', label: 'Last 90 days' },
	{ key: 'last365', label: 'Last 365 days' },
	{ key: 'thisMonth', label: 'This month' },
	{ key: 'lastMonth', label: 'Last month' },
	{ key: 'thisYear', label: 'This year' },
	{ key: 'lastYear', label: 'Last year' },
]

function startOfDay(d) {
	const x = new Date(d)
	x.setHours(0, 0, 0, 0)
	return x
}
function endOfDay(d) {
	const x = new Date(d)
	x.setHours(23, 59, 59, 999)
	return x
}

export function getPresetRange(key) {
	const now = new Date()
	switch (key) {
		case 'today':
			return { from: startOfDay(now), to: endOfDay(now) }
		case 'yesterday': {
			const y = new Date(now)
			y.setDate(y.getDate() - 1)
			return { from: startOfDay(y), to: endOfDay(y) }
		}
		case 'last7': {
			const from = new Date(now)
			from.setDate(from.getDate() - 6)
			return { from: startOfDay(from), to: endOfDay(now) }
		}
		case 'last30': {
			const from = new Date(now)
			from.setDate(from.getDate() - 29)
			return { from: startOfDay(from), to: endOfDay(now) }
		}
		case 'last90': {
			const from = new Date(now)
			from.setDate(from.getDate() - 89)
			return { from: startOfDay(from), to: endOfDay(now) }
		}
		case 'last365': {
			const from = new Date(now)
			from.setDate(from.getDate() - 364)
			return { from: startOfDay(from), to: endOfDay(now) }
		}
		case 'thisMonth': {
			const from = new Date(now.getFullYear(), now.getMonth(), 1)
			return { from: startOfDay(from), to: endOfDay(now) }
		}
		case 'lastMonth': {
			const from = new Date(now.getFullYear(), now.getMonth() - 1, 1)
			const to = new Date(now.getFullYear(), now.getMonth(), 0)
			return { from: startOfDay(from), to: endOfDay(to) }
		}
		case 'thisYear': {
			const from = new Date(now.getFullYear(), 0, 1)
			return { from: startOfDay(from), to: endOfDay(now) }
		}
		case 'lastYear': {
			const from = new Date(now.getFullYear() - 1, 0, 1)
			const to = new Date(now.getFullYear() - 1, 11, 31)
			return { from: startOfDay(from), to: endOfDay(to) }
		}
		default:
			return null
	}
}
