import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

function ajaxUrl(path) {
	return generateUrl('/apps/timetracker/ajax' + path)
}

// An error response's {"Error": "..."} message, instead of axios' generic one
function withServerMessage(error) {
	const message = error?.response?.data?.Error || error?.response?.data?.error
	return message ? new Error(message) : error
}

export async function apiGet(path, params = {}) {
	try {
		const { data } = await axios.get(ajaxUrl(path), { params })
		return data
	} catch (e) {
		throw withServerMessage(e)
	}
}

export async function apiPost(path, body = {}) {
	let data
	try {
		({ data } = await axios.post(ajaxUrl(path), body))
	} catch (e) {
		throw withServerMessage(e)
	}
	if (data && (data.Error || data.error)) {
		throw new Error(data.Error || data.error)
	}
	return data
}
