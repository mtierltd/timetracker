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

// "2026-12-24" → short localized date without the weekday, e.g. "Dec 24, 2026"
export function formatShortDate(date) {
	const [y, m, d] = date.split('-').map(Number)
	return new Date(Date.UTC(y, m - 1, d)).toLocaleDateString(undefined, { timeZone: 'UTC', day: 'numeric', month: 'short', year: 'numeric' })
}

export function period(absence) {
	const start = formatShortDate(absence.startDate) + (absence.halfDayStart ? ' (½)' : '')
	if (absence.startDate === absence.endDate) {
		return start
	}
	return `${start} – ${formatShortDate(absence.endDate)}${absence.halfDayEnd ? ' (½)' : ''}`
}

// 450 → "7:30 h"
export function hours(minutes) {
	const abs = Math.abs(minutes)
	return `${minutes < 0 ? '−' : ''}${Math.floor(abs / 60)}:${String(abs % 60).padStart(2, '0')} h`
}

// 90 → "+1:30 h", -90 → "−1:30 h"
export function signedHours(minutes) {
	return (minutes > 0 ? '+' : '') + hours(minutes)
}
