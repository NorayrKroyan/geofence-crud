<template>
  <div class="container">
    <div class="pageHeader">
      <div>
        <p class="pageTitle">Device History</p>
      </div>

      <div class="pageHeaderActions">
        <router-link class="btn" :to="{ name: 'drivers' }">Driver Edit</router-link>
        <router-link class="btn" :to="{ name: 'geofences' }">Geofences</router-link>
      </div>
    </div>

    <div v-if="err" class="err">{{ err }}</div>

    <div class="card historyFiltersCard">
      <div class="historyFilters">
        <div class="historyField historyFieldDevice">
          <label class="lblCompact">Driver</label>
          <select
              v-model="filters.device_id"
              class="input"
              :disabled="loadingDevices || !devices.length"
          >
            <option value="">
              {{ loadingDevices ? 'Loading devices...' : 'Choose device' }}
            </option>

            <option
                v-for="device in devices"
                :key="device.device_id"
                :value="device.device_id"
            >
              {{ device.label || device.device_id }}
            </option>
          </select>
        </div>

        <div class="historyField">
          <label class="lblCompact">Mode</label>
          <select v-model="filters.mode" class="input">
            <option value="standard">Standard (click bullet)</option>
            <option value="flag">Flag mode</option>
          </select>
        </div>

        <div class="historyField">
          <label class="lblCompact">From</label>
          <input
              v-model="filters.started_at"
              class="input"
              type="datetime-local"
              step="60"
          />
        </div>

        <div class="historyField">
          <label class="lblCompact">To</label>
          <input
              v-model="filters.ended_at"
              class="input"
              type="datetime-local"
              step="60"
          />
        </div>

        <div class="historyField">
          <label class="lblCompact">&nbsp;</label>
          <button
              class="btnPrimary historyRefreshBtn"
              :disabled="!canRefresh || loadingHistory"
              @click="refreshHistory"
          >
            {{ loadingHistory ? 'Refreshing...' : 'Refresh' }}
          </button>
        </div>
      </div>
    </div>

    <div class="card historyMapCard">
      <div class="historyMapToolbar">
        <div class="historyStatGrid">
          <div class="historyStat">
            <span class="historyStatLabel">Device</span>
            <span class="historyStatValue">
              {{ filters.device_id || '—' }}
            </span>
          </div>

          <div class="historyStat">
            <span class="historyStatLabel">Mode</span>
            <span class="historyStatValue">
              {{ filters.mode === 'flag' ? 'Flag' : 'Standard' }}
            </span>
          </div>

          <div class="historyStat">
            <span class="historyStatLabel">Points</span>
            <span class="historyStatValue">
              {{ rows.length }}
            </span>
          </div>

          <div class="historyStat">
            <span class="historyStatLabel">Last seen</span>
            <span class="historyStatValue">
              {{ selectedDeviceLastSeen ? formatDateTime(selectedDeviceLastSeen) : '—' }}
            </span>
          </div>
        </div>

        <div class="historyLegend">
          <span class="historyLegendItem">
            <span class="historyLegendSwatch historyLegendSwatchPath"></span>
            Path
          </span>

          <span class="historyLegendItem">
            <span class="historyLegendSwatch historyLegendSwatchPoint"></span>
            Points
          </span>
        </div>
      </div>

      <div ref="mapEl" class="historyMap"></div>

      <div v-if="!rows.length && !loadingHistory" class="historyEmpty">
        No history points found for the selected device and range.
      </div>
    </div>

    <div class="card" style="margin-top: 12px;">
      <div class="historyTableHeader">
        <div>
          <p class="modalTitle">History Points</p>
        </div>
      </div>

      <div class="dtControls">
        <div class="dtLeft">
          <span>Show</span>
          <select v-model.number="tablePageSize" class="dtSelect">
            <option
                v-for="size in tablePageSizeOptions"
                :key="size"
                :value="size"
            >
              {{ size }}
            </option>
          </select>
          <span>rows</span>
        </div>

        <div class="dtRight">
          <span class="dtInfo">
            Showing {{ tableStartRow }} to {{ tableEndRow }} of {{ totalTableRows }} rows
          </span>
        </div>
      </div>

      <div class="historyTableWrap">
        <table class="table striped">
          <thead>
          <tr>
            <th>Time</th>
            <th>GPS</th>
            <th>Speed</th>
            <th>Bearing</th>
            <th>Sources</th>
          </tr>
          </thead>

          <tbody>
          <tr v-if="loadingHistory">
            <td class="empty" colspan="5">Loading history...</td>
          </tr>

          <tr v-else-if="!totalTableRows">
            <td class="empty" colspan="5">No rows</td>
          </tr>

          <template v-else>
            <tr
                v-for="row in paginatedRows"
                :key="row.id"
                class="historyClickable historyTableRowTight"
                @click="focusRow(row)"
            >
              <td class="historyTableCellOneLine">
                {{ formatDateTime(row.display_time) }}
              </td>

              <td class="historyTableCellOneLine">
                {{ formatCoordinate(row.latitude) }}, {{ formatCoordinate(row.longitude) }}
              </td>

              <td class="historyTableCellOneLine">
                {{ formatSpeed(row.display_speed) }}
              </td>

              <td class="historyTableCellOneLine">
                {{ formatBearing(row.display_bearing, row.display_bearing_cardinal) }}
              </td>

              <td class="historyTableCellOneLine">
                <div class="historySourceGroup historySourceGroupCompact">
                  <span :class="sourceClass(row.speed_source)">
                    Speed {{ sourceLabel(row.speed_source) }}
                  </span>

                  <span :class="sourceClass(row.bearing_source)">
                    Bearing {{ sourceLabel(row.bearing_source) }}
                  </span>
                </div>
              </td>
            </tr>
          </template>
          </tbody>
        </table>
      </div>

      <div class="dtFooter">
        <div class="dtInfo">
          Page {{ totalTableRows ? tablePage : 0 }} of {{ totalTablePages }}
        </div>

        <div class="dtPager">
          <button
              class="dtPagerBtn"
              :disabled="tablePage <= 1"
              @click="setTablePage(1)"
          >
            First
          </button>

          <button
              class="dtPagerBtn"
              :disabled="tablePage <= 1"
              @click="setTablePage(tablePage - 1)"
          >
            Prev
          </button>

          <button
              v-for="pageNumber in visiblePageNumbers"
              :key="pageNumber"
              class="dtPagerBtn"
              :disabled="pageNumber === tablePage"
              @click="setTablePage(pageNumber)"
          >
            {{ pageNumber }}
          </button>

          <button
              class="dtPagerBtn"
              :disabled="tablePage >= totalTablePages"
              @click="setTablePage(tablePage + 1)"
          >
            Next
          </button>

          <button
              class="dtPagerBtn"
              :disabled="tablePage >= totalTablePages"
              @click="setTablePage(totalTablePages)"
          >
            Last
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { getDriverLocationHistory, listDriverDevices } from '../api/driverLocations'
import { listGeofences } from '../api/geofences'
import { loadGoogleMaps } from '../lib/loadGoogleMaps'

const mapEl = ref(null)

const devices = ref([])
const rows = ref([])
const geofences = ref([])
const err = ref('')

const loadingDevices = ref(false)
const loadingHistory = ref(false)

const filters = ref({
  device_id: '',
  mode: 'standard',
  started_at: '',
  ended_at: '',
})

const historyMeta = ref({
  device_id: null,
  started_at: null,
  ended_at: null,
  count: 0,
})

const tablePage = ref(1)
const tablePageSize = ref(25)
const tablePageSizeOptions = [25, 50, 100, 250]

let googleMaps = null
let map = null
let infoWindow = null
let polyline = null
let markers = []
let flagOverlays = []
let geofencePolygons = []
let geofenceLabelMarkers = []
let FlagOverlayClass = null

const canRefresh = computed(() => {
  return Boolean(
      filters.value.device_id &&
      filters.value.started_at &&
      filters.value.ended_at
  )
})

const selectedDevice = computed(() => {
  return devices.value.find((device) => device.device_id === filters.value.device_id) || null
})

const latestHistoryRow = computed(() => {
  return rows.value.length ? rows.value[rows.value.length - 1] : null
})

const selectedDeviceLastSeen = computed(() => {
  if (latestHistoryRow.value) {
    return latestHistoryRow.value?.display_time || latestHistoryRow.value?.device_dtm || latestHistoryRow.value?.timestamp || null
  }

  return selectedDevice.value?.last_seen_at || null
})

const totalTableRows = computed(() => rows.value.length)

const totalTablePages = computed(() => {
  return Math.max(1, Math.ceil(totalTableRows.value / tablePageSize.value))
})

const displayRows = computed(() => {
  return [...rows.value].reverse()
})

const paginatedRows = computed(() => {
  const start = (tablePage.value - 1) * tablePageSize.value
  const end = start + tablePageSize.value
  return displayRows.value.slice(start, end)
})

const tableStartRow = computed(() => {
  if (!totalTableRows.value) return 0
  return ((tablePage.value - 1) * tablePageSize.value) + 1
})

const tableEndRow = computed(() => {
  if (!totalTableRows.value) return 0
  return Math.min(tablePage.value * tablePageSize.value, totalTableRows.value)
})

const visiblePageNumbers = computed(() => {
  const total = totalTablePages.value
  const current = tablePage.value

  let start = Math.max(1, current - 2)
  let end = Math.min(total, start + 4)

  start = Math.max(1, end - 4)

  const pages = []

  for (let page = start; page <= end; page += 1) {
    pages.push(page)
  }

  return pages
})

watch(
    () => filters.value.mode,
    () => {
      renderMap()
    }
)

watch(tablePageSize, () => {
  tablePage.value = 1
})

watch(totalTablePages, (value) => {
  if (tablePage.value > value) {
    tablePage.value = value
  }

  if (tablePage.value < 1) {
    tablePage.value = 1
  }
})

onMounted(async () => {
  applyDefaultRange()

  try {
    await Promise.all([
      ensureMap(),
      fetchDevices(),
      fetchGeofences(),
    ])

    if (!filters.value.device_id && devices.value.length) {
      filters.value.device_id = devices.value[0].device_id
      await refreshHistory()
    }
  } catch (error) {
    err.value = error?.message || 'Failed to initialize the page.'
  }
})

onBeforeUnmount(() => {
  clearMapObjects()
})

async function ensureMap() {
  if (map || !mapEl.value) return

  googleMaps = await loadGoogleMaps(import.meta.env.VITE_GOOGLE_MAPS_API_KEY)

  map = new googleMaps.Map(mapEl.value, {
    center: { lat: 39.8283, lng: -98.5795 },
    zoom: 4,
    mapTypeId: 'satellite',
    streetViewControl: false,
    fullscreenControl: true,
    mapTypeControl: true,
    zoomControl: true,
    zoomControlOptions: {
      position: googleMaps.ControlPosition.RIGHT_CENTER,
    },
    gestureHandling: 'greedy',
  })

  infoWindow = new googleMaps.InfoWindow()
  FlagOverlayClass = buildFlagOverlayClass(googleMaps)
}

async function fetchDevices() {
  loadingDevices.value = true

  try {
    const payload = await listDriverDevices()

    devices.value = Array.isArray(payload?.data)
        ? payload.data.filter((item) => String(item?.device_id || '').trim().length >= 9)
        : []
  } finally {
    loadingDevices.value = false
  }
}

async function fetchGeofences() {
  try {
    const payload = await listGeofences()
    geofences.value = Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : [])
  } catch {
    geofences.value = []
  }
}

async function refreshHistory() {
  if (!canRefresh.value) return

  loadingHistory.value = true
  err.value = ''

  try {
    const [payload] = await Promise.all([
      getDriverLocationHistory({
        device_id: filters.value.device_id,
        started_at: toApiDateTime(filters.value.started_at),
        ended_at: toApiDateTime(filters.value.ended_at),
      }),
      fetchGeofences(),
    ])

    historyMeta.value = payload?.data || {
      device_id: filters.value.device_id,
      started_at: null,
      ended_at: null,
      count: 0,
    }

    rows.value = Array.isArray(payload?.data?.rows) ? payload.data.rows : []
    tablePage.value = 1
    renderMap()
  } catch (error) {
    rows.value = []
    tablePage.value = 1
    clearMapObjects()
    err.value = error?.message || 'Failed to load device history.'
  } finally {
    loadingHistory.value = false
  }
}

function renderMap() {
  if (!map || !googleMaps) return

  clearMapObjects()

  if (!rows.value.length) {
    map.setCenter({ lat: 39.8283, lng: -98.5795 })
    map.setZoom(4)
    return
  }

  const bounds = new googleMaps.LatLngBounds()

  const path = rows.value
      .filter((row) => row.latitude !== null && row.longitude !== null)
      .map((row) => {
        const position = {
          lat: Number(row.latitude),
          lng: Number(row.longitude),
        }

        bounds.extend(position)
        return position
      })

  if (!path.length) return

  polyline = new googleMaps.Polyline({
    map,
    path,
    geodesic: true,
    strokeColor: '#40a9ff',
    strokeOpacity: 0.95,
    strokeWeight: 4,
    icons: path.length > 1
        ? [
          {
            icon: {
              path: googleMaps.SymbolPath.FORWARD_CLOSED_ARROW,
              scale: 3.5,
              fillColor: '#40a9ff',
              fillOpacity: 1,
              strokeColor: '#40a9ff',
              strokeWeight: 1,
            },
            offset: '100%',
            repeat: '90px',
          },
        ]
        : [],
  })

  rows.value.forEach((row) => {
    if (row.latitude === null || row.longitude === null) return

    const position = {
      lat: Number(row.latitude),
      lng: Number(row.longitude),
    }

    const marker = new googleMaps.Marker({
      map,
      position,
      title: formatDateTime(row.display_time),
      icon: {
        path: googleMaps.SymbolPath.CIRCLE,
        scale: 4,
        fillColor: '#1d4ed8',
        fillOpacity: 1,
        strokeColor: '#ffffff',
        strokeWeight: 1.2,
      },
      zIndex: 5,
    })

    marker.addListener('click', () => {
      openInfo(row, position)
    })

    markers.push(marker)

    if (filters.value.mode === 'flag' && FlagOverlayClass) {
      const overlay = new FlagOverlayClass({
        map,
        position,
        html: buildFlagHtml(row),
        onClick: () => openInfo(row, position),
      })

      flagOverlays.push(overlay)
    }
  })

  const latestPosition = latestHistoryRow.value && latestHistoryRow.value.latitude !== null && latestHistoryRow.value.longitude !== null
      ? {
        lat: Number(latestHistoryRow.value.latitude),
        lng: Number(latestHistoryRow.value.longitude),
      }
      : null

  if (latestPosition) {
    renderNearbyGeofences(latestPosition)
  }

  if (path.length === 1) {
    map.setCenter(path[0])
    map.setZoom(18)
    return
  }

  map.fitBounds(bounds, 48)
}

function clearMapObjects() {
  markers.forEach((marker) => marker.setMap(null))
  markers = []

  flagOverlays.forEach((overlay) => overlay.setMap(null))
  flagOverlays = []

  geofencePolygons.forEach((shape) => shape.setMap(null))
  geofencePolygons = []

  geofenceLabelMarkers.forEach((marker) => marker.setMap(null))
  geofenceLabelMarkers = []

  if (polyline) {
    polyline.setMap(null)
    polyline = null
  }

  if (infoWindow) {
    infoWindow.close()
  }
}


function renderNearbyGeofences(latestPosition) {
  if (!latestPosition) return

  geofences.value.forEach((geofence) => {
    const center = getGeofenceCenter(geofence)
    if (!center) return

    const distance = haversineMiles(latestPosition, center)
    if (distance > 50) return

    const points = extractGeofencePoints(geofence)
    if (!points.length) return

    const polygonShape = new googleMaps.Polygon({
      map,
      paths: points,
      strokeColor: geofence.color || '#16a34a',
      strokeOpacity: 0.95,
      strokeWeight: 2,
      fillColor: geofence.color || '#16a34a',
      fillOpacity: 0.12,
      clickable: false,
      zIndex: 2,
    })

    geofencePolygons.push(polygonShape)

    const labelMarker = new googleMaps.Marker({
      map,
      position: center,
      clickable: false,
      zIndex: 3,
      icon: {
        path: googleMaps.SymbolPath.CIRCLE,
        scale: 0.01,
        fillOpacity: 0,
        strokeOpacity: 0,
      },
      label: {
        text: String(geofence.name || 'Geofence'),
        color: '#111827',
        fontSize: '18px',
        fontWeight: '700',
      },
    })

    geofenceLabelMarkers.push(labelMarker)
  })
}

function extractGeofencePoints(geofence) {
  const fromGeometryJson = extractPointsFromAny(geofence?.geometry_json)
  if (fromGeometryJson.length) return fromGeometryJson

  const fromGeometryObject = extractPointsFromAny(geofence?.geometry)
  if (fromGeometryObject.length) return fromGeometryObject

  const fromPolygonPoints = extractPointsFromAny(geofence?.polygon_points)
  if (fromPolygonPoints.length) return fromPolygonPoints

  return extractPointsFromAny(geofence?.polygon_points_array)
}

function extractPointsFromAny(value) {
  const parsed = parseJsonish(value)
  return extractPoints(parsed)
}

function extractPoints(value) {
  if (!value) return []

  if (Array.isArray(value)) {
    if (value.length && Array.isArray(value[0]) && !('lat' in (value[0] || {})) && !('lng' in (value[0] || {})) && !('latitude' in (value[0] || {})) && !('longitude' in (value[0] || {}))) {
      return extractPoints(value[0])
    }

    const mapped = value
        .map(normalizePoint)
        .filter(Boolean)

    return mapped.length >= 3 ? mapped : []
  }

  if (typeof value === 'object') {
    if (Array.isArray(value.paths)) return extractPoints(value.paths)
    if (Array.isArray(value.points)) return extractPoints(value.points)

    if (Array.isArray(value.coordinates)) {
      const coordinates = Array.isArray(value.coordinates[0]?.[0]) ? value.coordinates[0] : value.coordinates
      const mapped = coordinates
          .map((pair) => Array.isArray(pair) && pair.length >= 2 ? { lat: Number(pair[1]), lng: Number(pair[0]) } : null)
          .filter(Boolean)

      return mapped.length >= 3 ? mapped : []
    }
  }

  return []
}

function normalizePoint(point) {
  if (!point) return null

  if (Array.isArray(point)) {
    if (point.length < 2) return null

    const first = Number(point[0])
    const second = Number(point[1])

    if (!Number.isFinite(first) || !Number.isFinite(second)) return null

    if (Math.abs(first) <= 90 && Math.abs(second) <= 180) {
      return { lat: first, lng: second }
    }

    return { lat: second, lng: first }
  }

  const lat = Number(point.lat ?? point.latitude)
  const lng = Number(point.lng ?? point.longitude)

  if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null

  return { lat, lng }
}

function getGeofenceCenter(geofence) {
  const lat = Number(geofence?.center_point_lat)
  const lng = Number(geofence?.center_point_lng)

  if (Number.isFinite(lat) && Number.isFinite(lng)) {
    return { lat, lng }
  }

  const points = extractGeofencePoints(geofence)
  if (!points.length) return null

  const totals = points.reduce((carry, point) => {
    carry.lat += point.lat
    carry.lng += point.lng
    return carry
  }, { lat: 0, lng: 0 })

  return {
    lat: totals.lat / points.length,
    lng: totals.lng / points.length,
  }
}

function parseJsonish(value) {
  if (!value) return null
  if (typeof value !== 'string') return value

  try {
    return JSON.parse(value)
  } catch {
    return null
  }
}

function haversineMiles(pointA, pointB) {
  const toRadians = (degrees) => (degrees * Math.PI) / 180
  const earthRadiusMiles = 3958.7613
  const dLat = toRadians(pointB.lat - pointA.lat)
  const dLng = toRadians(pointB.lng - pointA.lng)
  const lat1 = toRadians(pointA.lat)
  const lat2 = toRadians(pointB.lat)

  const a = Math.sin(dLat / 2) ** 2
      + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2

  return 2 * earthRadiusMiles * Math.asin(Math.sqrt(a))
}


function focusRow(row) {
  if (!map || row.latitude === null || row.longitude === null) return

  const position = {
    lat: Number(row.latitude),
    lng: Number(row.longitude),
  }

  map.panTo(position)

  if (map.getZoom() < 17) {
    map.setZoom(17)
  }

  openInfo(row, position)
}

function openInfo(row, position) {
  if (!map || !infoWindow) return

  infoWindow.setPosition(position)
  infoWindow.setContent(buildInfoHtml(row))
  infoWindow.open(map)
}

function buildInfoHtml(row) {
  const gps = `${formatCoordinate(row.latitude)}, ${formatCoordinate(row.longitude)}`
  const speed = `${formatSpeed(row.display_speed)} (${sourceLabel(row.speed_source)})`
  const bearing = `${formatBearing(row.display_bearing, row.display_bearing_cardinal)} (${sourceLabel(row.bearing_source)})`

  return `
    <div class="historyInfoWindow">
      <div class="historyInfoTitle">${escapeHtml(formatDateTime(row.display_time))}</div>
      <div class="historyInfoLine"><strong>GPS:</strong> ${escapeHtml(gps)}</div>
      <div class="historyInfoLine"><strong>Speed:</strong> ${escapeHtml(speed)}</div>
      <div class="historyInfoLine"><strong>Bearing:</strong> ${escapeHtml(bearing)}</div>
      <div class="historyInfoLine"><strong>Record ID:</strong> ${escapeHtml(String(row.id))}</div>
      ${
      row.google_maps_url
          ? `<div class="historyInfoActions"><a href="${row.google_maps_url}" target="_blank" rel="noopener noreferrer">View on Google Maps</a></div>`
          : ''
  }
    </div>
  `
}

function buildFlagHtml(row) {
  return `
    <div class="historyFlagTime">${escapeHtml(formatTimeOnly(row.display_time))}</div>
    <div class="historyFlagMetric">${escapeHtml(formatSpeed(row.display_speed))}</div>
    <div class="historyFlagSub">${escapeHtml(formatBearing(row.display_bearing, row.display_bearing_cardinal))}</div>
  `
}

function buildFlagOverlayClass(google) {
  return class FlagOverlay extends google.OverlayView {
    constructor({ map, position, html, onClick = null }) {
      super()

      this.position = position instanceof google.LatLng
          ? position
          : new google.LatLng(position)

      this.html = html
      this.onClick = onClick
      this.element = null

      if (map) {
        this.setMap(map)
      }
    }

    onAdd() {
      const element = document.createElement('div')
      element.className = 'historyFlag'
      element.innerHTML = this.html

      if (this.onClick) {
        element.addEventListener('click', this.onClick)
      }

      this.element = element
      this.getPanes().overlayMouseTarget.appendChild(element)
    }

    draw() {
      if (!this.element) return

      const projection = this.getProjection()
      const pixel = projection?.fromLatLngToDivPixel(this.position)

      if (!pixel) return

      this.element.style.left = `${pixel.x}px`
      this.element.style.top = `${pixel.y}px`
    }

    onRemove() {
      if (!this.element) return

      this.element.remove()
      this.element = null
    }
  }
}

function setTablePage(page) {
  const normalized = Math.min(Math.max(1, page), totalTablePages.value)
  tablePage.value = normalized
}

function applyDefaultRange() {
  const end = new Date()
  const start = new Date(end.getTime() - (60 * 60 * 1000))

  filters.value.started_at = toLocalInputValue(start)
  filters.value.ended_at = toLocalInputValue(end)
}

function toLocalInputValue(date) {
  const shifted = new Date(date.getTime() - (date.getTimezoneOffset() * 60000))
  return shifted.toISOString().slice(0, 16)
}

function toApiDateTime(value) {
  if (!value) return ''

  const localDate = new Date(value)

  if (Number.isNaN(localDate.getTime())) return ''

  return localDate.toISOString()
}

function formatDateTime(value) {
  if (!value) return '—'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) return String(value)

  return date.toLocaleString()
}

function formatTimeOnly(value) {
  if (!value) return '—'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) return String(value)

  return date.toLocaleTimeString([], {
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatCoordinate(value) {
  if (value === null || value === undefined || value === '') return '—'
  return Number(value).toFixed(6)
}

function formatSpeed(value) {
  if (value === null || value === undefined || value === '') return '—'

  const mph = Number(value) * 0.621371
  return `${mph.toFixed(1)} MPH`
}

function formatBearing(value, cardinal = null) {
  if (value === null || value === undefined || value === '') return '—'

  const base = `${Math.round(Number(value))}°`
  return cardinal ? `${base} ${cardinal}` : base
}

function sourceLabel(source) {
  if (source === 'table') return 'Table'
  if (source === 'calculated') return 'Calculated'
  return '—'
}

function sourceClass(source) {
  if (source === 'table') return 'historySourcePill historySourcePillTable'
  if (source === 'calculated') return 'historySourcePill historySourcePillCalculated'
  return 'historySourcePill'
}

function escapeHtml(value) {
  return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;')
}
</script>

<style scoped>
.historyTableRowTight td {
  padding-top: 6px !important;
  padding-bottom: 6px !important;
  vertical-align: middle;
}

.historyTableCellOneLine {
  white-space: nowrap;
  line-height: 1.2;
  font-size: 13px;
}

.historySourceGroupCompact {
  display: flex;
  gap: 6px;
  align-items: center;
  flex-wrap: nowrap;
}

.historySourceGroupCompact :deep(.historySourcePill) {
  white-space: nowrap;
}

.historyTableWrap {
  overflow-x: auto;
}
</style>