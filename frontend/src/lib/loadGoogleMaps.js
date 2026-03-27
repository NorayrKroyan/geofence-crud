let loaderPromise = null

export function loadGoogleMaps(apiKey) {
  if (typeof window === 'undefined') {
    return Promise.reject(new Error('Google Maps can only be loaded in a browser environment.'))
  }

  if (window.google?.maps?.Map && window.google?.maps?.drawing && window.google?.maps?.geometry) {
    return Promise.resolve(window.google.maps)
  }

  if (loaderPromise) return loaderPromise
  if (!apiKey) return Promise.reject(new Error('Missing VITE_GOOGLE_MAPS_API_KEY.'))

  loaderPromise = new Promise((resolve, reject) => {
    const callbackName = '__geofenceGoogleMapsInit'

    window[callbackName] = () => {
      try {
        resolve(window.google.maps)
      } finally {
        delete window[callbackName]
      }
    }

    const existing = document.querySelector('script[data-google-maps-loader="geofence"]')
    if (existing) {
      existing.addEventListener('error', () => reject(new Error('Failed to load Google Maps.')), { once: true })
      return
    }

    const script = document.createElement('script')
    script.async = true
    script.defer = true
    script.dataset.googleMapsLoader = 'geofence'
    script.onerror = () => {
      loaderPromise = null
      reject(new Error('Failed to load Google Maps.'))
    }
    script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&libraries=drawing,geometry&callback=${callbackName}`
    document.head.appendChild(script)
  })

  return loaderPromise
}
