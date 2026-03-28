const API_BASE = (import.meta.env.VITE_API_BASE_URL || '/api').replace(/\/$/, '')
const BASE_URL = `${API_BASE}/geofences`

function firstValidationMessage(payload) {
  const errors = payload?.errors
  if (!errors || typeof errors !== 'object') return ''

  const firstKey = Object.keys(errors)[0]
  if (!firstKey || !Array.isArray(errors[firstKey]) || !errors[firstKey].length) return ''

  return errors[firstKey][0]
}

async function request(url, options = {}) {
  const response = await fetch(url, {
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(options.headers || {}),
    },
    ...options,
  })

  const payload = await response.json().catch(() => null)

  if (!response.ok) {
    const error = new Error(
        payload?.message ||
        firstValidationMessage(payload) ||
        `Request failed with status ${response.status}`
    )
    error.status = response.status
    error.errors = payload?.errors || null
    error.payload = payload
    throw error
  }

  return payload
}

export function listGeofences() {
  return request(BASE_URL)
}

export function getGeofence(id) {
  return request(`${BASE_URL}/${id}`)
}

export function createGeofence(data) {
  return request(BASE_URL, {
    method: 'POST',
    body: JSON.stringify(data),
  })
}

export function updateGeofence(id, data) {
  return request(`${BASE_URL}/${id}`, {
    method: 'PUT',
    body: JSON.stringify(data),
  })
}

export function deleteGeofence(id) {
  return request(`${BASE_URL}/${id}`, {
    method: 'DELETE',
  })
}