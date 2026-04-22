<template>
  <div class="container">
    <div class="pageHeader">
      <div>
        <p class="pageTitle">Team View</p>
      </div>

      <div class="pageHeaderActions">
        <router-link class="btn" to="/drivers">Driver Edit</router-link>
        <router-link class="btn" to="/geofences">Geofences</router-link>
        <router-link class="btn" to="/device-history">Device History</router-link>
      </div>
    </div>

    <div v-if="err" class="err">{{ err }}</div>

    <div class="card historyFiltersCard teamFiltersCard">
      <div class="historyFilters teamHistoryFilters">
        <div class="historyField teamDriverField">
          <label class="lblCompact">Drivers</label>

          <div ref="driverDropdownRef" class="teamDropdown">
            <button
                type="button"
                class="input teamDropdownTrigger"
                :class="{ teamDropdownTriggerOpen: driverDropdownOpen }"
                :disabled="loadingDevices || !devices.length"
                @click.stop="toggleDriverDropdown"
            >
              <span class="teamDropdownText">{{ selectedDriversLabel }}</span>
              <span class="teamDropdownArrow">{{ driverDropdownOpen ? '▴' : '▾' }}</span>
            </button>

            <div v-if="driverDropdownOpen" class="teamDropdownMenu" @click.stop>
              <div class="teamDropdownList">
                <label
                    v-for="device in devices"
                    :key="device.device_id"
                    class="teamDropdownRow"
                >
                  <input
                      v-model="filters.device_ids"
                      type="checkbox"
                      :value="device.device_id"
                  />
                  <span
                      class="teamDropdownDot"
                      :style="{ backgroundColor: colorForDevice(device.device_id) }"
                  ></span>
                  <span class="teamDropdownName">{{ device.label || device.device_id }}</span>
                </label>
              </div>
            </div>
          </div>
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
      </div>
    </div>

    <div class="card historyMapCard">
      <div class="historyMapToolbar">
        <div class="historyStatGrid">
          <div class="historyStat">
            <span class="historyStatLabel">Drivers</span>
            <span class="historyStatValue">{{ filters.device_ids.length || '—' }}</span>
          </div>

          <div class="historyStat">
            <span class="historyStatLabel">Mode</span>
            <span class="historyStatValue">{{ filters.mode === 'flag' ? 'Flag' : 'Standard' }}</span>
          </div>

          <div class="historyStat">
            <span class="historyStatLabel">Points</span>
            <span class="historyStatValue">{{ rows.length }}</span>
          </div>

          <div class="historyStat">
            <span class="historyStatLabel">Last seen</span>
            <span class="historyStatValue">
              {{ latestHistoryRow ? formatDateTime(latestHistoryRow.display_time || latestHistoryRow.device_dtm || latestHistoryRow.timestamp) : '—' }}
            </span>
          </div>
        </div>

        <div class="historyLegend teamLegend">
          <span class="historyLegendItem">
            <span class="historyLegendSwatch historyLegendSwatchPath"></span>
            Path
          </span>

          <span class="historyLegendItem">
            <span class="historyLegendSwatch historyLegendSwatchPoint"></span>
            Points
          </span>

          <span
              v-for="device in selectedDevices"
              :key="device.device_id"
              class="historyLegendItem"
          >
            <span
                class="historyLegendSwatch teamLegendSwatch"
                :style="{ backgroundColor: colorForDevice(device.device_id) }"
            ></span>
            {{ device.label || device.device_id }}
          </span>
        </div>
      </div>

      <div ref="mapEl" class="historyMap"></div>

      <div v-if="loadingHistory" class="historyEmpty">
        Loading history...
      </div>

      <div v-else-if="!rows.length" class="historyEmpty">
        No history points found for the selected drivers and range.
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

          <span class="dtSpacer"></span>

          <span>Driver</span>
          <select v-model="tableDriverFilter" class="dtSelect historyDriverFilterSelect">
            <option value="">All selected drivers</option>
            <option
                v-for="device in selectedDevices"
                :key="device.device_id"
                :value="device.device_id"
            >
              {{ device.label || device.device_id }}
            </option>
          </select>
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
            <th>Driver</th>
            <th>Time</th>
            <th>GPS</th>
            <th>Speed</th>
            <th>Bearing</th>
            <th>Sources</th>
            <th>Action</th>
            <th>Geofence</th>
          </tr>
          </thead>

          <tbody>
          <tr v-if="loadingHistory">
            <td class="empty" colspan="8">Loading history...</td>
          </tr>

          <tr v-else-if="!totalTableRows">
            <td class="empty" colspan="8">No rows</td>
          </tr>

          <template v-else>
            <tr
                v-for="row in paginatedRows"
                :key="row._team_row_key"
                :ref="(el) => setRowRef(row._team_row_key, el)"
                :class="[
                  'historyClickable',
                  'historyTableRowTight',
                  isOverSpeedInsideGeofence(row) ? 'historyTableRowAlert' : '',
                  rowHasMatchedEvent(row) ? 'historyTableRowEvent' : '',
                  activeRowKey === row._team_row_key ? 'historySelectedRow' : '',
                ]"
                @click="selectRow(row, { pan: true, zoom: true, openInfoWindow: true, scroll: false })"
            >
              <td class="historyTableCellOneLine">
                  <span class="teamDriverCell">
                    <span
                        class="teamDriverDot"
                        :style="{ backgroundColor: row._team_color }"
                    ></span>
                    {{ row._team_driver_label }}
                  </span>
              </td>

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

              <td class="historyTableCellOneLine">
                {{ formatMatchedEventActions(row) }}
              </td>

              <td class="historyTableCellOneLine">
                {{ formatMatchedEventGeofences(row) }}
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
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { getDriverLocationHistory, listDriverDevices } from '../api/driverLocations'
import { listEventLogs } from '../api/eventLogs'
import { listGeofences } from '../api/geofences'
import { loadGoogleMaps } from '../lib/loadGoogleMaps'

const mapEl = ref(null)

const devices = ref([])
const rows = ref([])
const geofences = ref([])
const eventLogs = ref([])
const err = ref('')
const activeRowKey = ref(null)

const loadingDevices = ref(false)
const loadingHistory = ref(false)

const driverDropdownOpen = ref(false)
const driverDropdownRef = ref(null)

const filters = ref({
  device_ids: [],
  mode: 'standard',
  started_at: '',
  ended_at: '',
})

const tablePage = ref(1)
const tablePageSize = ref(25)
const tablePageSizeOptions = [25, 50, 100, 250]
const tableDriverFilter = ref('')

let googleMaps = null
let map = null
let infoWindow = null
let polylines = []
let markers = []
let flagOverlays = []
let geofencePolygons = []
let geofenceLabelMarkers = []
let FlagOverlayClass = null
let refreshTimer = null
let refreshSequence = 0
let isInitializing = true

const rowRefs = new Map()

const teamPalette = [
  '#40a9ff',
  '#16a34a',
  '#f59e0b',
  '#ef4444',
  '#8b5cf6',
  '#06b6d4',
  '#ec4899',
  '#84cc16',
]

const canRefresh = computed(() => {
  return Boolean(
      filters.value.device_ids.length &&
      filters.value.started_at &&
      filters.value.ended_at
  )
})

const selectedDevices = computed(() => {
  const selected = new Set(filters.value.device_ids)
  return devices.value.filter((device) => selected.has(device.device_id))
})

const selectedDriverIdsSignature = computed(() => {
  return JSON.stringify(selectedDevices.value.map((item) => item.device_id))
})

const selectedDriversLabel = computed(() => {
  const selectedCount = filters.value.device_ids.length

  if (!selectedCount) return 'Select drivers'

  if (selectedCount === 1) {
    const selected = devices.value.find((item) => item.device_id === filters.value.device_ids[0])
    return selected?.label || filters.value.device_ids[0]
  }

  return `${selectedCount} drivers selected`
})

const latestHistoryRow = computed(() => {
  return rows.value.length ? rows.value[rows.value.length - 1] : null
})

const displayRows = computed(() => {
  return [...rows.value].reverse()
})

const filteredTableRows = computed(() => {
  if (!tableDriverFilter.value) {
    return displayRows.value
  }

  return displayRows.value.filter((row) => row._team_device_id === tableDriverFilter.value)
})

const totalTableRows = computed(() => filteredTableRows.value.length)

const totalTablePages = computed(() => {
  return Math.max(1, Math.ceil(totalTableRows.value / tablePageSize.value))
})

const paginatedRows = computed(() => {
  const start = (tablePage.value - 1) * tablePageSize.value
  const end = start + tablePageSize.value
  return filteredTableRows.value.slice(start, end)
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

const reactiveFilterSignature = computed(() => {
  return JSON.stringify({
    device_ids: [...filters.value.device_ids].sort(),
    started_at: filters.value.started_at,
    ended_at: filters.value.ended_at,
  })
})

const eventLogByRowKey = computed(() => {
  const mapped = {}

  rows.value.forEach((row) => {
    mapped[row._team_row_key] = null
  })

  eventLogs.value.forEach((log) => {
    const match = findNearestHistoryRow(log.created_at, log._team_device_id)

    if (!match) return

    const rowTimeValue = match?.display_time || match?.device_dtm || match?.timestamp || null
    const rowTime = new Date(rowTimeValue).getTime()
    const logTime = new Date(log.created_at).getTime()

    if (Number.isNaN(rowTime) || Number.isNaN(logTime)) return

    const distance = Math.abs(rowTime - logTime)
    const key = match._team_row_key
    const current = mapped[key]

    if (!current || distance < current.distance) {
      mapped[key] = {
        distance,
        log,
      }
    }
  })

  return mapped
})

watch(
    () => filters.value.mode,
    () => {
      renderMap()
    }
)

watch(
    reactiveFilterSignature,
    () => {
      scheduleReactiveRefresh()
    }
)

watch(tablePageSize, async () => {
  tablePage.value = 1

  if (activeRowKey.value !== null) {
    await nextTick()
    scrollSelectedRowIntoView(activeRowKey.value)
  }
})

watch(totalTablePages, (value) => {
  if (tablePage.value > value) {
    tablePage.value = value
  }

  if (tablePage.value < 1) {
    tablePage.value = 1
  }
})

watch(
    selectedDriverIdsSignature,
    () => {
      const stillExists = selectedDevices.value.some((device) => device.device_id === tableDriverFilter.value)

      if (!stillExists) {
        tableDriverFilter.value = ''
      }

      tablePage.value = 1
    }
)

watch(
    tableDriverFilter,
    () => {
      tablePage.value = 1
    }
)

onMounted(async () => {
  applyDefaultRange()
  document.addEventListener('click', handleOutsideDropdownClick)

  try {
    await Promise.all([
      ensureMap(),
      fetchDevices(),
      fetchGeofences(),
    ])

    if (!filters.value.device_ids.length && devices.value.length) {
      filters.value.device_ids = devices.value
          .slice(0, Math.min(3, devices.value.length))
          .map((item) => item.device_id)
    }

    isInitializing = false

    if (canRefresh.value) {
      await refreshHistory()
    }
  } catch (error) {
    err.value = error?.message || 'Failed to initialize the page.'
    isInitializing = false
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleOutsideDropdownClick)

  if (refreshTimer) {
    clearTimeout(refreshTimer)
    refreshTimer = null
  }

  clearMapObjects()
  rowRefs.clear()
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

    const raw = Array.isArray(payload?.data)
        ? payload.data.filter((item) => String(item?.device_id || '').trim().length >= 9)
        : []

    const seen = new Set()

    devices.value = raw.filter((item) => {
      const key = String(item?.device_id || '').trim()

      if (!key || seen.has(key)) {
        return false
      }

      seen.add(key)
      return true
    })
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

function scheduleReactiveRefresh() {
  if (isInitializing) return

  if (refreshTimer) {
    clearTimeout(refreshTimer)
    refreshTimer = null
  }

  if (!canRefresh.value) {
    clearHistoryData()
    return
  }

  refreshTimer = setTimeout(() => {
    refreshHistory()
  }, 250)
}

async function refreshHistory() {
  if (!canRefresh.value) {
    clearHistoryData()
    return
  }

  const requestId = ++refreshSequence

  loadingHistory.value = true
  err.value = ''
  activeRowKey.value = null

  try {
    await fetchGeofences()

    const selectedIds = [...filters.value.device_ids]

    const payloads = await Promise.all(
        selectedIds.map(async (deviceId) => {
          const [historyPayload, eventPayload] = await Promise.all([
            getDriverLocationHistory({
              device_id: deviceId,
              started_at: toApiDateTime(filters.value.started_at),
              ended_at: toApiDateTime(filters.value.ended_at),
            }),
            listEventLogs({
              device_id: deviceId,
              started_at: toApiDateTime(filters.value.started_at),
              ended_at: toApiDateTime(filters.value.ended_at),
            }).catch(() => ({ data: { rows: [] } })),
          ])

          return {
            deviceId,
            historyPayload,
            eventPayload,
          }
        })
    )

    if (requestId !== refreshSequence) return

    const combinedRows = []
    const combinedEventLogs = []

    payloads.forEach(({ deviceId, historyPayload, eventPayload }) => {
      const driver = devices.value.find((item) => item.device_id === deviceId) || null
      const driverLabel = driver?.label || deviceId
      const color = colorForDevice(deviceId)

      const historyRows = Array.isArray(historyPayload?.data?.rows) ? historyPayload.data.rows : []
      const logRows = Array.isArray(eventPayload?.data?.rows) ? eventPayload.data.rows : []

      historyRows.forEach((row, index) => {
        const baseId = row?.id ?? `idx-${index}`

        combinedRows.push({
          ...row,
          _team_device_id: deviceId,
          _team_driver_label: driverLabel,
          _team_color: color,
          _team_row_key: `${deviceId}:${baseId}:${index}`,
        })
      })

      logRows.forEach((log) => {
        combinedEventLogs.push({
          ...log,
          _team_device_id: deviceId,
        })
      })
    })

    combinedRows.sort((left, right) => {
      return rowTimeMs(left) - rowTimeMs(right)
    })

    rows.value = combinedRows
    eventLogs.value = combinedEventLogs
    tablePage.value = 1
    renderMap()
  } catch (error) {
    if (requestId !== refreshSequence) return

    rows.value = []
    eventLogs.value = []
    tablePage.value = 1
    clearMapObjects()
    err.value = error?.message || 'Failed to load team view.'
  } finally {
    if (requestId === refreshSequence) {
      loadingHistory.value = false
    }
  }
}

function clearHistoryData() {
  rows.value = []
  eventLogs.value = []
  activeRowKey.value = null
  tablePage.value = 1
  tableDriverFilter.value = ''
  loadingHistory.value = false
  clearMapObjects()

  if (map) {
    map.setCenter({ lat: 39.8283, lng: -98.5795 })
    map.setZoom(4)
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
  const rowsByDevice = new Map()
  let totalPathPoints = 0

  rows.value.forEach((row) => {
    const key = row._team_device_id

    if (!rowsByDevice.has(key)) {
      rowsByDevice.set(key, [])
    }

    rowsByDevice.get(key).push(row)
  })

  const latestPositions = []

  rowsByDevice.forEach((deviceRows, deviceId) => {
    const color = deviceRows[0]?._team_color || colorForDevice(deviceId)

    const path = deviceRows
        .filter((row) => row.latitude !== null && row.longitude !== null)
        .map((row) => {
          const position = {
            lat: Number(row.latitude),
            lng: Number(row.longitude),
          }

          bounds.extend(position)
          totalPathPoints += 1
          return position
        })

    if (path.length) {
      const polyline = new googleMaps.Polyline({
        map,
        path,
        geodesic: true,
        strokeColor: color,
        strokeOpacity: 0.95,
        strokeWeight: 4,
        icons: path.length > 1
            ? [
              {
                icon: {
                  path: googleMaps.SymbolPath.FORWARD_CLOSED_ARROW,
                  scale: 3.5,
                  fillColor: color,
                  fillOpacity: 1,
                  strokeColor: color,
                  strokeWeight: 1,
                },
                offset: '100%',
                repeat: '90px',
              },
            ]
            : [],
      })

      polylines.push(polyline)
    }

    deviceRows.forEach((row) => {
      if (row.latitude === null || row.longitude === null) return

      const position = {
        lat: Number(row.latitude),
        lng: Number(row.longitude),
      }

      const marker = new googleMaps.Marker({
        map,
        position,
        title: `${row._team_driver_label} - ${formatDateTime(row.display_time)}`,
        icon: {
          path: googleMaps.SymbolPath.CIRCLE,
          scale: 4,
          fillColor: row._team_color,
          fillOpacity: 1,
          strokeColor: '#ffffff',
          strokeWeight: 1.2,
        },
        zIndex: 5,
        visible: filters.value.mode !== 'flag',
      })

      marker.addListener('click', () => {
        selectRow(row, { pan: false, zoom: false, openInfoWindow: true, scroll: true })
      })

      markers.push(marker)

      if (filters.value.mode === 'flag' && FlagOverlayClass) {
        const overlay = new FlagOverlayClass({
          map,
          position,
          html: buildFlagHtml(row),
          onClick: () => selectRow(row, { pan: false, zoom: false, openInfoWindow: true, scroll: true }),
        })

        flagOverlays.push(overlay)
      }
    })

    const latestRow = [...deviceRows].reverse().find((row) => row.latitude !== null && row.longitude !== null)

    if (latestRow) {
      latestPositions.push({
        lat: Number(latestRow.latitude),
        lng: Number(latestRow.longitude),
      })
    }
  })

  renderNearbyGeofences(latestPositions)

  if (totalPathPoints === 1) {
    map.setCenter(bounds.getCenter())
    map.setZoom(18)
    return
  }

  if (!bounds.isEmpty()) {
    map.fitBounds(bounds, 48)
  }
}

function clearMapObjects() {
  polylines.forEach((shape) => shape.setMap(null))
  polylines = []

  markers.forEach((marker) => marker.setMap(null))
  markers = []

  flagOverlays.forEach((overlay) => overlay.setMap(null))
  flagOverlays = []

  geofencePolygons.forEach((shape) => shape.setMap(null))
  geofencePolygons = []

  geofenceLabelMarkers.forEach((marker) => marker.setMap(null))
  geofenceLabelMarkers = []

  if (infoWindow) {
    infoWindow.close()
  }
}

function renderNearbyGeofences(latestPositions) {
  if (!Array.isArray(latestPositions) || !latestPositions.length) return

  const renderedKeys = new Set()

  geofences.value.forEach((geofence) => {
    const center = getGeofenceCenter(geofence)
    if (!center) return

    const isNearby = latestPositions.some((position) => {
      return haversineMiles(position, center) <= 50
    })

    if (!isNearby) return

    const points = extractGeofencePoints(geofence)
    if (!points.length) return

    const geofenceKey = String(geofence.id ?? geofence.name ?? `${center.lat}:${center.lng}`)

    if (renderedKeys.has(geofenceKey)) return
    renderedKeys.add(geofenceKey)

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
    if (
        value.length &&
        Array.isArray(value[0]) &&
        !('lat' in (value[0] || {})) &&
        !('lng' in (value[0] || {})) &&
        !('latitude' in (value[0] || {})) &&
        !('longitude' in (value[0] || {}))
    ) {
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

async function selectRow(row, {
  pan = true,
  zoom = true,
  openInfoWindow = true,
  scroll = false,
} = {}) {
  if (!row) return

  activeRowKey.value = row._team_row_key

  const isVisibleInTable = ensureRowVisible(row._team_row_key)

  if (scroll || isVisibleInTable) {
    await nextTick()
    scrollSelectedRowIntoView(row._team_row_key)
  }

  if (!map || row.latitude === null || row.longitude === null) return

  const position = {
    lat: Number(row.latitude),
    lng: Number(row.longitude),
  }

  if (pan) {
    map.panTo(position)
  }

  if (zoom && map.getZoom() < 17) {
    map.setZoom(17)
  }

  if (openInfoWindow) {
    openInfo(row, position)
  }
}

function ensureRowVisible(rowKey) {
  const index = filteredTableRows.value.findIndex((row) => row._team_row_key === rowKey)

  if (index === -1) return false

  const neededPage = Math.floor(index / tablePageSize.value) + 1

  if (tablePage.value !== neededPage) {
    tablePage.value = neededPage
  }

  return true
}

function scrollSelectedRowIntoView(rowKey) {
  const element = rowRefs.get(String(rowKey))

  if (!element || typeof element.scrollIntoView !== 'function') return

  element.scrollIntoView({
    behavior: 'smooth',
    block: 'center',
  })
}

function setRowRef(rowKey, el) {
  const key = String(rowKey)

  if (el) {
    rowRefs.set(key, el)
  } else {
    rowRefs.delete(key)
  }
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
      <div class="historyInfoLine"><strong>Driver:</strong> ${escapeHtml(row._team_driver_label || row._team_device_id)}</div>
      <div class="historyInfoLine"><strong>GPS:</strong> ${escapeHtml(gps)}</div>
      <div class="historyInfoLine"><strong>Speed:</strong> ${escapeHtml(speed)}</div>
      <div class="historyInfoLine"><strong>Bearing:</strong> ${escapeHtml(bearing)}</div>
      <div class="historyInfoLine"><strong>Record ID:</strong> ${escapeHtml(String(row.id ?? '—'))}</div>
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

function rowTimeMs(row) {
  const value = row?.display_time || row?.device_dtm || row?.timestamp || null
  const time = new Date(value).getTime()
  return Number.isNaN(time) ? 0 : time
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

function findNearestHistoryRow(eventTime, deviceId = null) {
  if (!eventTime || !rows.value.length) return null

  const target = new Date(eventTime).getTime()

  if (Number.isNaN(target)) return null

  let nearest = null
  let nearestDistance = Number.POSITIVE_INFINITY

  rows.value.forEach((row) => {
    if (deviceId && row._team_device_id !== deviceId) return

    const rowTimeValue = row?.display_time || row?.device_dtm || row?.timestamp || null
    const rowTime = new Date(rowTimeValue).getTime()

    if (Number.isNaN(rowTime)) return

    const distance = Math.abs(rowTime - target)

    if (distance < nearestDistance) {
      nearest = row
      nearestDistance = distance
    }
  })

  return nearest
}

function matchedEventLog(row) {
  return eventLogByRowKey.value[row._team_row_key]?.log || null
}

function rowHasMatchedEvent(row) {
  return Boolean(matchedEventLog(row))
}

function formatMatchedEventActions(row) {
  const log = matchedEventLog(row)
  return log ? formatEventLogAction(log.action) : '—'
}

function formatMatchedEventGeofences(row) {
  const log = matchedEventLog(row)

  if (!log) return '—'

  return log.geofence_name || (log.geofence_id !== null && log.geofence_id !== undefined ? `#${log.geofence_id}` : '—')
}

function formatEventLogAction(action) {
  const normalized = String(action || '').trim().toLowerCase()

  if (normalized === 'entry' || normalized === 'entered') return 'Entry'
  if (normalized === 'exit' || normalized === 'exited') return 'Exit'

  return normalized ? normalized.charAt(0).toUpperCase() + normalized.slice(1) : '—'
}

function formatCoordinate(value) {
  if (value === null || value === undefined || value === '') return '—'
  return Number(value).toFixed(6)
}

function isOverSpeedInsideGeofence(row) {
  const point = normalizeHistoryPoint(row)
  if (!point) return false

  const speedKph = Number(row?.display_speed)
  if (!Number.isFinite(speedKph) || speedKph <= 0) return false

  return geofences.value.some((geofence) => {
    if (!isGeofenceUsable(geofence)) return false

    const speedLimitKph = Number(geofence?.speed_limit_kph)
    if (!Number.isFinite(speedLimitKph) || speedLimitKph <= 0) return false
    if (speedKph <= speedLimitKph) return false

    const polygon = extractGeofencePoints(geofence)
    if (polygon.length < 3) return false

    return isPointInsidePolygon(point, polygon)
  })
}

function normalizeHistoryPoint(row) {
  const lat = Number(row?.latitude)
  const lng = Number(row?.longitude)

  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    return null
  }

  return { lat, lng }
}

function isGeofenceUsable(geofence) {
  if (!geofence) return false

  const isDeleted = Number(geofence.is_delete) === 1
  if (isDeleted) return false

  if (geofence.is_active === false) return false
  if (geofence.is_active !== null && geofence.is_active !== undefined && Number(geofence.is_active) === 0) {
    return false
  }

  return true
}

function isPointInsidePolygon(point, polygon) {
  let inside = false

  for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i, i += 1) {
    const start = polygon[j]
    const end = polygon[i]

    if (isPointOnSegment(point, start, end)) {
      return true
    }

    const intersects = ((end.lng > point.lng) !== (start.lng > point.lng))
        && (
            point.lat
            < ((start.lat - end.lat) * (point.lng - end.lng)) / ((start.lng - end.lng) || Number.EPSILON) + end.lat
        )

    if (intersects) {
      inside = !inside
    }
  }

  return inside
}

function isPointOnSegment(point, start, end, epsilon = 1e-9) {
  const cross = ((point.lng - start.lng) * (end.lat - start.lat))
      - ((point.lat - start.lat) * (end.lng - start.lng))

  if (Math.abs(cross) > epsilon) {
    return false
  }

  const dot = ((point.lat - start.lat) * (end.lat - start.lat))
      + ((point.lng - start.lng) * (end.lng - start.lng))

  if (dot < 0) {
    return false
  }

  const squaredLength = ((end.lat - start.lat) ** 2) + ((end.lng - start.lng) ** 2)

  return dot <= squaredLength
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

function colorForDevice(deviceId) {
  const value = String(deviceId || '')
  let hash = 0

  for (let i = 0; i < value.length; i += 1) {
    hash = ((hash << 5) - hash) + value.charCodeAt(i)
    hash |= 0
  }

  return teamPalette[Math.abs(hash) % teamPalette.length]
}

function toggleDriverDropdown() {
  driverDropdownOpen.value = !driverDropdownOpen.value
}

function handleOutsideDropdownClick(event) {
  const root = driverDropdownRef.value

  if (!root) return

  if (!root.contains(event.target)) {
    driverDropdownOpen.value = false
  }
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
.teamFiltersCard {
  position: relative;
  overflow: visible !important;
  z-index: 30;
}

.teamHistoryFilters {
  display: grid;
  grid-template-columns: minmax(240px, 1.35fr) minmax(180px, 0.9fr) minmax(220px, 1fr) minmax(220px, 1fr);
  gap: 12px;
  align-items: start;
}

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

.historyTableRowAlert td {
  background: #fee2e2 !important;
}

.historyTableRowAlert:hover td {
  background: #fecaca !important;
}

.historyTableRowEvent td {
  box-shadow: inset 0 1px 0 rgba(37, 99, 235, 0.08), inset 0 -1px 0 rgba(37, 99, 235, 0.08);
}

.historySelectedRow td {
  box-shadow: inset 0 0 0 2px #2563eb !important;
}

.teamLegend {
  flex-wrap: wrap;
  gap: 8px 12px;
}

.teamLegendSwatch {
  border: 1px solid rgba(15, 23, 42, 0.12);
}

.teamDriverCell {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.teamDriverDot,
.teamDropdownDot {
  width: 10px;
  height: 10px;
  border-radius: 999px;
  display: inline-block;
  flex: 0 0 10px;
}

.teamDriverField {
  position: relative;
  min-width: 0;
  z-index: 40;
}

.teamDropdown {
  position: relative;
  z-index: 80;
}

.teamDropdownTrigger {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  text-align: left;
  cursor: pointer;
  appearance: none;
  -webkit-appearance: none;
  font: inherit;
  line-height: inherit;
  background: #fff;
}

.teamDropdownTriggerOpen {
  border-color: #2563eb !important;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
}

.teamDropdownText {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.teamDropdownArrow {
  margin-left: 8px;
  color: #64748b;
  flex: 0 0 auto;
  font-size: 11px;
}

.teamDropdownMenu {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  width: 100%;
  min-width: 100%;
  z-index: 120;
  background: #fff;
  border: 1px solid #dbe4ee;
  border-radius: 10px;
  box-shadow: 0 14px 30px rgba(15, 23, 42, 0.14);
  padding: 8px;
  overflow: hidden;
}

.teamDropdownList {
  max-height: 260px;
  overflow-y: auto;
  padding-right: 2px;
}

.teamDropdownRow {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 9px 8px;
  border-radius: 8px;
  cursor: pointer;
}

.teamDropdownRow:hover {
  background: #f8fafc;
}

.teamDropdownName {
  font-size: 13px;
  line-height: 1.25;
}

.dtSpacer {
  display: inline-block;
  width: 12px;
}

.historyDriverFilterSelect {
  min-width: 240px;
}

@media (max-width: 1180px) {
  .teamHistoryFilters {
    grid-template-columns: repeat(2, minmax(220px, 1fr));
  }
}

@media (max-width: 760px) {
  .teamHistoryFilters {
    grid-template-columns: 1fr;
  }
}
</style>