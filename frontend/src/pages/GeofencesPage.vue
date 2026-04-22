<template>
  <div class="container">
    <div class="pageHeader">
      <p class="pageTitle">Geofence List</p>

      <div class="pageHeaderActions">
        <router-link class="btn" :to="{ name: 'device-history' }">Device history</router-link>
        <router-link class="btn" :to="{ name: 'team-view' }">Team View</router-link>
        <button class="btnPrimary" @click="openNew">New Geofence</button>
      </div>
    </div>

    <div v-if="err" class="err">{{ err }}</div>

    <div class="card">
      <div class="dtControls">
        <div class="dtLeft">
          <span>Show</span>
          <select class="dtSelect" v-model.number="pageSize" @change="page = 1">
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>
          <span>entries</span>
        </div>

        <div class="dtRight">
          <label class="dtSearchLabel">Search:</label>
          <input class="dtSearchInput" v-model.trim="q" />
        </div>
      </div>

      <table class="table striped compact">
        <thead>
        <tr>
          <th>Geofence Name</th>
          <th>Center</th>
          <th>Speed Limit MPH</th>
          <th style="text-align: center">Active</th>
          <th>Expire Date</th>
          <th style="text-align: center">Points</th>
          <th class="colGps" style="text-align: center">MAP</th>
        </tr>
        </thead>

        <tbody>
        <tr v-if="!loading && !paged.length">
          <td colspan="7" class="empty">No geofences found.</td>
        </tr>

        <tr v-for="g in paged" :key="g.id">
          <td>
            <a href="#" class="link" @click.prevent="openEdit(g, false)">
              {{ g.name || '—' }}
            </a>
          </td>

          <td>{{ formatCenter(g) }}</td>
          <td>{{ formatSpeedLimitMph(g.speed_limit_kph) }}</td>

          <td style="text-align: center">
            <span class="statusPill statusPillTight" :class="g.is_active ? 'statusPillOn' : 'statusPillOff'">
              {{ g.is_active ? 'Yes' : 'No' }}
            </span>
          </td>

          <td>{{ formatDate(g.expire_date) }}</td>
          <td style="text-align: center">{{ pointCount(g) }}</td>

          <td class="colGps mapCell mapCellTight" style="text-align: center">
            <button
                type="button"
                class="mapLink mapBtn mapBtnTight"
                title="Open geofence map"
                @click.stop="openMap(g)"
            >
              <span class="mapIcon mapIconTight">🗺️</span>
            </button>
          </td>
        </tr>
        </tbody>
      </table>

      <div class="dtFooter">
        <div class="dtInfo">Showing {{ startRow }} to {{ endRow }} of {{ filtered.length }}</div>

        <div class="dtPager">
          <button class="dtPagerBtn" :disabled="page <= 1" @click="page--">Previous</button>
          <button class="dtPagerBtn" :disabled="page >= totalPages" @click="page++">Next</button>
        </div>
      </div>
    </div>

    <GeofenceModal
        :open="modalOpen"
        :geofence="selected"
        :focus-map-token="focusMapToken"
        :map-only="mapOnlyOpen"
        @close="handleClose"
        @saved="onChanged"
        @deleted="onChanged"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import GeofenceModal from '../components/GeofenceModal.vue'
import { listGeofences } from '../api/geofences'

const rows = ref([])
const err = ref('')
const q = ref('')
const loading = ref(false)

const page = ref(1)
const pageSize = ref(25)

const modalOpen = ref(false)
const selected = ref(null)
const focusMapToken = ref(0)
const mapOnlyOpen = ref(false)

onMounted(() => reload())
watch(q, () => {
  page.value = 1
})

async function reload() {
  loading.value = true
  err.value = ''

  try {
    const data = await listGeofences()
    rows.value = Array.isArray(data?.data) ? data.data : (Array.isArray(data) ? data : [])
  } catch (error) {
    rows.value = []
    err.value = error?.message || 'Unable to load geofences.'
  } finally {
    loading.value = false
  }
}

function openEdit(geofence, focusMap = false) {
  selected.value = geofence
  mapOnlyOpen.value = false
  focusMapToken.value = focusMap ? (focusMapToken.value + 1) : 0
  modalOpen.value = true
}

function openMap(geofence) {
  selected.value = geofence
  mapOnlyOpen.value = true
  focusMapToken.value = focusMapToken.value + 1
  modalOpen.value = true
}

function openNew() {
  selected.value = null
  mapOnlyOpen.value = false
  focusMapToken.value = 0
  modalOpen.value = true
}

const filtered = computed(() => {
  if (!q.value) return rows.value
  const needle = q.value.toLowerCase()

  return rows.value.filter((row) => {
    return [
      row.id,
      row.name,
      row.entry_action,
      row.exit_action,
      row.notes,
      row.speed_limit_kph,
      formatSpeedLimitMph(row.speed_limit_kph),
      row.center_point_lat,
      row.center_point_lng,
    ]
        .join(' ')
        .toLowerCase()
        .includes(needle)
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / pageSize.value)))
const paged = computed(() => {
  const start = (page.value - 1) * pageSize.value
  return filtered.value.slice(start, start + pageSize.value)
})

const startRow = computed(() => (filtered.value.length ? (page.value - 1) * pageSize.value + 1 : 0))
const endRow = computed(() => Math.min(page.value * pageSize.value, filtered.value.length))

function parseJsonish(value) {
  if (!value) return null
  if (typeof value !== 'string') return value
  try {
    return JSON.parse(value)
  } catch {
    return null
  }
}

function pointCount(row) {
  const geometry = parseJsonish(row.geometry_json)
  if (Array.isArray(geometry?.paths)) return geometry.paths.length

  const polygon = parseJsonish(row.polygon_points)
  if (Array.isArray(polygon)) return polygon.length
  if (Array.isArray(polygon?.paths)) return polygon.paths.length
  return 0
}

function formatCenter(row) {
  if (row.center_point_lat == null || row.center_point_lng == null) return '—'
  return `${Number(row.center_point_lat).toFixed(5)}, ${Number(row.center_point_lng).toFixed(5)}`
}

function formatSpeedLimitMph(value) {
  if (value === null || value === undefined || value === '') return '—'
  const mph = Number(value) * 0.621371
  if (!Number.isFinite(mph)) return '—'
  return `${Math.round(mph)} MPH`
}

function formatDate(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  const pad = (part) => String(part).padStart(2, '0')
  return `${pad(date.getDate())}-${pad(date.getMonth() + 1)}-${date.getFullYear()} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function handleClose() {
  modalOpen.value = false
  mapOnlyOpen.value = false
}

async function onChanged() {
  modalOpen.value = false
  mapOnlyOpen.value = false
  await reload()
}
</script>

<style scoped>
.table.striped.compact tbody td {
  padding-top: 4px !important;
  padding-bottom: 4px !important;
  vertical-align: middle;
}

.table.striped.compact thead th {
  padding-top: 8px !important;
  padding-bottom: 8px !important;
}

.table.striped.compact tbody tr {
  height: 28px;
}

.statusPillTight {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 1px 8px !important;
  min-height: 20px !important;
  line-height: 1 !important;
  font-size: 12px !important;
  border-radius: 999px;
}

.mapCellTight {
  padding-top: 2px !important;
  padding-bottom: 2px !important;
}

.mapBtnTight {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 !important;
  margin: 0 !important;
  min-width: 18px !important;
  min-height: 18px !important;
  width: 18px;
  height: 18px;
  line-height: 1 !important;
  border: 0 !important;
  background: transparent !important;
}

.mapIconTight {
  display: block;
  font-size: 13px !important;
  line-height: 1 !important;
}
</style>