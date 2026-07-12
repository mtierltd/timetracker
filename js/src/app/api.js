import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

function ajaxUrl(path) {
	return generateUrl('/apps/timetracker/ajax' + path)
}

export async function apiGet(path, params = {}) {
	const { data } = await axios.get(ajaxUrl(path), { params })
	return data
}

export async function apiPost(path, body = {}) {
	const { data } = await axios.post(ajaxUrl(path), body)
	if (data && (data.Error || data.error)) {
		throw new Error(data.Error || data.error)
	}
	return data
}
