// Formatting shared by the absence views

const STATUS_LABELS = {
	requested: 'Requested',
	approved: 'Approved',
	rejected: 'Rejected',
	cancelled: 'Cancelled',
}

export function statusLabel(status) {
	return STATUS_LABELS[status] || status
}

// "2026-12-24" → localized date, independent of the browser's time zone
export function formatDate(date) {
	const [y, m, d] = date.split('-').map(Number)
	return new Date(Date.UTC(y, m - 1, d)).toLocaleDateString(undefined, { timeZone: 'UTC', weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
}

export function formatDays(days) {
	return Number(days).toLocaleString(undefined, { maximumFractionDigits: 1 })
}

export function period(absence) {
	const start = formatDate(absence.startDate) + (absence.halfDayStart ? ' (½)' : '')
	if (absence.startDate === absence.endDate) {
		return start
	}
	return `${start} – ${formatDate(absence.endDate)}${absence.halfDayEnd ? ' (½)' : ''}`
}
