<template>
  <teleport to="body">
    <div v-if="open" class="modalOverlay" @mousedown.self="emit('close')">
      <div class="modalCard wide geofenceModal" :class="{ mapOnlyMode: props.mapOnly }" :style="modalStyle">
        <div class="modalHeader">
          <div class="modalTitle">
            <span>{{ props.mapOnly ? (form.name || `Geofence #${form.id || ''}`) : (isEditMode ? `Edit Geofence #${form.id}` : 'New Geofence') }}</span>
            <span v-if="form.is_delete" class="pillDeleted">Deleted Flag</span>
          </div>

          <div class="actions">
            <button class="btn" type="button" @click="emit('close')">Close</button>
          </div>
        </div>

        <div v-if="message" class="err modalErr">{{ message }}</div>

        <div class="modalBody splitBody" :class="{ stacked: !isDesktop }">
          <div v-if="!props.mapOnly" class="splitLeft" :style="leftPaneStyle">
            <div class="splitLeftInner">
              <div class="formTwoCol dbForm">
                <div class="formCol">
                  <div class="row">
                    <div class="lbl">ID</div>
                    <div class="ctl">
                      <input class="input" :value="form.id || ''" type="text" disabled />
                    </div>
                  </div>

                  <div class="row">
                    <div class="lbl">Event ID</div>
                    <div class="ctl">
                      <input v-model="form.event_id" class="input" type="number" min="1" />
                    </div>
                  </div>

                  <div class="row">
                    <div class="lbl">Name</div>
                    <div class="ctl">
                      <input v-model.trim="form.name" class="input-xl" type="text" />
                      <div v-if="fieldError('name')" class="fieldErr">{{ fieldError('name') }}</div>
                    </div>
                  </div>

                  <div class="row">
                    <div class="lbl">Speed Limit KPH</div>
                    <div class="ctl">
                      <input v-model="form.speed_limit_kph" class="input" type="number" min="0" />
                    </div>
                  </div>

                  <div class="row">
                    <div class="lbl">Entry Action</div>
                    <div class="ctl">
                      <input v-model.trim="form.entry_action" class="input-xl" type="text" maxlength="40" />
                    </div>
                  </div>

                  <div class="row">
                    <div class="lbl">Exit Action</div>
                    <div class="ctl">
                      <input v-model.trim="form.exit_action" class="input-xl" type="text" maxlength="40" />
                    </div>
                  </div>

                  <div class="row">
                    <div class="lbl">Color</div>
                    <div class="ctl colorRow">
                      <input v-model="form.color" type="color" class="colorSwatch" />
                      <input v-model.trim="form.color" class="input" type="text" />
                    </div>
                  </div>

                  <div class="row">
                    <div class="lbl">Is Active</div>
                    <div class="ctl">
                      <label class="checkRow"><input v-model="form.is_active" type="checkbox" /> <span>Enabled</span></label>
                    </div>
                  </div>

                  <div class="row">
                    <div class="lbl">Expire Date</div>
                    <div class="ctl">
                      <input v-model="form.expire_date" class="input" type="datetime-local" />
                    </div>
                  </div>

                  <div class="row">
                    <div class="lbl">Center Lat</div>
                    <div class="ctl">
                      <input v-model="form.center_point_lat" class="input" type="number" step="0.0000001" />
                    </div>
                  </div>

                  <div class="row">
                    <div class="lbl">Center Lng</div>
                    <div class="ctl">
                      <input v-model="form.center_point_lng" class="input" type="number" step="0.0000001" />
                    </div>
                  </div>

                  <div class="row notesRow">
                    <div class="lbl">Notes</div>
                    <div class="ctl">
                      <textarea v-model="form.notes" class="textarea-xl" rows="4"></textarea>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-if="!props.mapOnly && isDesktop" class="splitDivider" @mousedown.prevent="startSplitResize"></div>

          <div class="splitRight" :class="{ mapOnlyPane: props.mapOnly }">
            <div v-if="!props.mapOnly" class="mapTools">
              <button class="btn" type="button" @click="startDrawingPolygon">Draw / Redraw</button>
              <button class="btn" type="button" @click="clearPolygon">Clear</button>
              <button class="btn" type="button" @click="fitPolygon">Fit</button>
              <button class="btn" type="button" @click="syncFormFromPolygon(false)">Refresh Fields</button>
            </div>

            <div class="mapMeta">
              <div><strong>Polygon points:</strong> {{ currentPointCount }}</div>
              <div><strong>Center:</strong> {{ displayCoord(form.center_point_lat) }}, {{ displayCoord(form.center_point_lng) }}</div>
            </div>

            <div v-if="mapError" class="err mapErr">{{ mapError }}</div>
            <div ref="mapCanvasRef" class="mapCanvas"></div>
          </div>
        </div>

        <div v-if="!props.mapOnly" class="modalFooter">
          <div class="footerActions">
            <button
                v-if="isEditMode"
                class="btnDanger"
                type="button"
                :disabled="saving || deleting"
                @click="handleDelete"
            >
              {{ deleting ? 'Deleting...' : 'Delete' }}
            </button>

            <div class="footerRight">
              <button class="btnPrimary" type="button" :disabled="saving || deleting" @click="handleSave">
                {{ saving ? 'Saving...' : (isEditMode ? 'Update Geofence' : 'Create Geofence') }}
              </button>
            </div>
          </div>
        </div>

        <div v-if="!props.mapOnly" class="modalResizeHandle" @mousedown.prevent="startModalResize" title="Resize"></div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { createGeofence, deleteGeofence, getGeofence, updateGeofence } from '../api/geofences'
import { loadGoogleMaps } from '../lib/loadGoogleMaps'

const props = defineProps({
  open: { type: Boolean, default: false },
  geofence: { type: Object, default: null },
  focusMapToken: { type: Number, default: 0 },
  mapOnly: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'saved', 'deleted'])

const STORAGE_KEY_SPLIT = 'geofence-crud-modal-split-v3'
const STORAGE_KEY_W = 'geofence-crud-modal-width'
const STORAGE_KEY_H = 'geofence-crud-modal-height'

const SPLIT_MIN = 22
const SPLIT_MAX = 55
const LEFT_PANE_MIN_PX = 390

const saving = ref(false)
const deleting = ref(false)
const loading = ref(false)
const message = ref('')
const mapError = ref('')
const validationErrors = ref({})
const viewportWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1280)
const viewportHeight = ref(typeof window !== 'undefined' ? window.innerHeight : 1024)
const splitPercent = ref(clamp(readStoredNumber(STORAGE_KEY_SPLIT, 22), SPLIT_MIN, SPLIT_MAX))
const modalWidth = ref(readStoredNumber(STORAGE_KEY_W, 1260))
const modalHeight = ref(readStoredNumber(STORAGE_KEY_H, 860))
const mapCanvasRef = ref(null)

let googleMaps = null
let map = null
let drawingManager = null
let polygon = null
let polygonPathListeners = []
let mapListeners = []

const form = reactive(blankForm())

const isDesktop = computed(() => viewportWidth.value >= 1024)
const isEditMode = computed(() => Boolean(form.id))
const currentPointCount = computed(() => extractBestAvailablePoints().length)

const modalStyle = computed(() => {
  if (props.mapOnly) {
    return {
      width: `${Math.max(320, viewportWidth.value - 24)}px`,
      height: `${Math.max(320, viewportHeight.value - 24)}px`,
    }
  }

  return {
    width: `${clamp(modalWidth.value, 960, viewportWidth.value - 24)}px`,
    height: `${clamp(modalHeight.value, 720, viewportHeight.value - 24)}px`,
  }
})

const leftPaneStyle = computed(() => {
  if (!isDesktop.value) return {}
  const percent = clamp(splitPercent.value, SPLIT_MIN, SPLIT_MAX)
  return { width: `max(${LEFT_PANE_MIN_PX}px, ${percent}%)` }
})

watch(
    () => props.open,
    async (open) => {
      if (!open) {
        document.body.style.overflow = ''
        return
      }

      document.body.style.overflow = 'hidden'
      message.value = ''
      validationErrors.value = {}
      mapError.value = ''

      await hydrateForm()
      await nextTick()
      await ensureMap()
      if (props.focusMapToken) fitPolygon()
    },
)

watch(
    () => props.geofence,
    async () => {
      if (!props.open) return
      await hydrateForm()
      await nextTick()
      await ensureMap()
    },
)

watch(
    () => props.focusMapToken,
    async (token) => {
      if (!props.open || !token) return
      await nextTick()
      fitPolygon()
    },
)

watch(
    () => form.color,
    (value) => {
      if (polygon) {
        polygon.setOptions({
          fillColor: value || '#2563eb',
          strokeColor: value || '#2563eb',
        })
      }
    },
)

function blankForm() {
  return {
    id: null,
    event_id: '',
    trigger_zone: '',
    bounding_box: '',
    bounding_box_center: '',
    name: '',
    center_point_lat: '',
    center_point_lng: '',
    speed_limit_kph: '',
    entry_action: '',
    exit_action: '',
    color: '#2563eb',
    is_active: true,
    is_delete: false,
    expire_date: '',
    notes: '',
    geometry_json: '',
    polygon_points: '',
    created_at: '',
    updated_at: '',
  }
}

function resetForm() {
  Object.assign(form, blankForm())
}

async function hydrateForm() {
  resetForm()
  destroyMapObjects()

  if (!props.geofence?.id) return

  loading.value = true
  try {
    const response = await getGeofence(props.geofence.id)
    const item = response?.data ?? response
    applyGeofence(item)
  } catch (error) {
    message.value = error?.message || 'Unable to load geofence.'
  } finally {
    loading.value = false
  }
}

function applyGeofence(item) {
  if (!item) return

  form.id = item.id ?? null
  form.event_id = item.event_id ?? ''
  form.trigger_zone = stringifyIfNeeded(item.trigger_zone)
  form.bounding_box = stringifyIfNeeded(item.bounding_box)
  form.bounding_box_center = stringifyIfNeeded(item.bounding_box_center)
  form.name = item.name ?? ''
  form.center_point_lat = item.center_point_lat ?? ''
  form.center_point_lng = item.center_point_lng ?? ''
  form.speed_limit_kph = item.speed_limit_kph ?? ''
  form.entry_action = item.entry_action ?? ''
  form.exit_action = item.exit_action ?? ''
  form.color = item.color || '#2563eb'
  form.is_active = Boolean(item.is_active)
  form.is_delete = Boolean(item.is_delete)
  form.expire_date = toDatetimeLocal(item.expire_date)
  form.notes = item.notes ?? ''
  form.geometry_json = stringifyIfNeeded(item.geometry_json)
  form.polygon_points = stringifyIfNeeded(item.polygon_points)
  form.created_at = item.created_at ?? ''
  form.updated_at = item.updated_at ?? ''
}

function stringifyIfNeeded(value) {
  if (value == null) return ''
  if (typeof value === 'string') return value
  try {
    return JSON.stringify(value, null, 2)
  } catch {
    return String(value)
  }
}

function toDatetimeLocal(value) {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  const pad = (part) => String(part).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function toPayload() {
  return {
    event_id: normalizeNullableInt(form.event_id),
    trigger_zone: blankToNull(form.trigger_zone),
    bounding_box: blankToNull(form.bounding_box),
    bounding_box_center: blankToNull(form.bounding_box_center),
    name: form.name?.trim() || '',
    center_point_lat: normalizeNullableNumber(form.center_point_lat),
    center_point_lng: normalizeNullableNumber(form.center_point_lng),
    speed_limit_kph: normalizeNullableInt(form.speed_limit_kph),
    entry_action: blankToNull(form.entry_action),
    exit_action: blankToNull(form.exit_action),
    color: form.color || '#2563eb',
    is_active: Boolean(form.is_active),
    is_delete: Boolean(form.is_delete),
    expire_date: blankToNull(form.expire_date),
    notes: blankToNull(form.notes),
    geometry_json: blankToNull(form.geometry_json),
    polygon_points: blankToNull(form.polygon_points),
  }
}

async function handleSave() {
  message.value = ''
  validationErrors.value = {}

  if (polygon) syncFormFromPolygon(false)

  saving.value = true
  try {
    const payload = toPayload()
    if (isEditMode.value) {
      await updateGeofence(form.id, payload)
    } else {
      await createGeofence(payload)
    }
    emit('saved')
  } catch (error) {
    validationErrors.value = error?.errors || {}
    message.value = error?.message || 'Unable to save geofence.'
  } finally {
    saving.value = false
  }
}

async function handleDelete() {
  if (!form.id) return

  deleting.value = true
  message.value = ''
  try {
    await deleteGeofence(form.id)
    emit('deleted')
  } catch (error) {
    message.value = error?.message || 'Unable to delete geofence.'
  } finally {
    deleting.value = false
  }
}

function fieldError(field) {
  const errors = validationErrors.value?.[field]
  return Array.isArray(errors) && errors.length ? errors[0] : ''
}

async function ensureMap() {
  if (!mapCanvasRef.value) return

  try {
    googleMaps = await loadGoogleMaps(import.meta.env.VITE_GOOGLE_MAPS_API_KEY)

    if (!map) {
      map = new googleMaps.Map(mapCanvasRef.value, {
        center: getFallbackCenter(),
        zoom: 11,
        streetViewControl: false,
        mapTypeControl: true,
        fullscreenControl: true,
      })

      drawingManager = new googleMaps.drawing.DrawingManager({
        drawingMode: null,
        drawingControl: false,
        polygonOptions: polygonOptions(),
      })
      drawingManager.setMap(map)

      mapListeners.push(
          googleMaps.event.addListener(drawingManager, 'overlaycomplete', (event) => {
            if (event.type !== googleMaps.drawing.OverlayType.POLYGON) return
            drawingManager.setDrawingMode(null)
            replacePolygon(event.overlay)
            syncFormFromPolygon(true)
          }),
      )
    }

    if (!polygon) {
      const points = extractBestAvailablePoints()
      if (points.length >= 3) {
        drawPolygon(points, true)
      } else {
        map.setCenter(getFallbackCenter())
      }
    }

    googleMaps.event.trigger(map, 'resize')
  } catch (error) {
    mapError.value = error?.message || 'Unable to load Google Maps.'
  }
}

function destroyMapObjects() {
  clearPolygonListeners()

  if (polygon) {
    polygon.setMap(null)
    polygon = null
  }

  if (drawingManager) {
    drawingManager.setMap(null)
    drawingManager = null
  }

  mapListeners.forEach((listener) => listener?.remove?.())
  mapListeners = []
  map = null
}

function polygonOptions() {
  return {
    clickable: true,
    editable: !props.mapOnly,
    fillColor: form.color || '#2563eb',
    fillOpacity: 0.18,
    strokeColor: form.color || '#2563eb',
    strokeWeight: 2,
  }
}

function replacePolygon(newPolygon) {
  clearPolygonListeners()
  if (polygon) polygon.setMap(null)

  polygon = newPolygon
  polygon.setOptions(polygonOptions())
  polygon.setEditable(!props.mapOnly)

  const path = polygon.getPath()
  polygonPathListeners = [
    path.addListener('set_at', () => syncFormFromPolygon(false)),
    path.addListener('insert_at', () => syncFormFromPolygon(false)),
    path.addListener('remove_at', () => syncFormFromPolygon(false)),
  ]
}

function clearPolygonListeners() {
  polygonPathListeners.forEach((listener) => listener?.remove?.())
  polygonPathListeners = []
}

function drawPolygon(points, fit = false) {
  if (!googleMaps || !map || points.length < 3) return
  const nextPolygon = new googleMaps.Polygon({ paths: points, ...polygonOptions(), map })
  replacePolygon(nextPolygon)
  syncFormFromPolygon(fit)
}

function startDrawingPolygon() {
  if (!drawingManager || !googleMaps) return
  clearPolygon()
  drawingManager.setOptions({ polygonOptions: polygonOptions() })
  drawingManager.setDrawingMode(googleMaps.drawing.OverlayType.POLYGON)
}

function clearPolygon() {
  if (polygon) {
    clearPolygonListeners()
    polygon.setMap(null)
    polygon = null
  }
  form.geometry_json = ''
  form.polygon_points = ''
  form.bounding_box = ''
  form.bounding_box_center = ''
  form.center_point_lat = ''
  form.center_point_lng = ''
}

function syncFormFromPolygon(fit = false) {
  if (!polygon) return

  const points = polygon.getPath().getArray().map((point) => ({ lat: roundCoord(point.lat()), lng: roundCoord(point.lng()) }))
  const bounds = new googleMaps.LatLngBounds()
  points.forEach((point) => bounds.extend(point))

  const northEast = bounds.getNorthEast()
  const southWest = bounds.getSouthWest()
  const center = bounds.getCenter()

  form.center_point_lat = roundCoord(center.lat())
  form.center_point_lng = roundCoord(center.lng())
  form.bounding_box = JSON.stringify(
      {
        north: roundCoord(northEast.lat()),
        east: roundCoord(northEast.lng()),
        south: roundCoord(southWest.lat()),
        west: roundCoord(southWest.lng()),
      },
      null,
      2,
  )
  form.bounding_box_center = JSON.stringify(
      { lat: roundCoord(center.lat()), lng: roundCoord(center.lng()) },
      null,
      2,
  )
  form.polygon_points = JSON.stringify(points, null, 2)
  form.geometry_json = JSON.stringify({ paths: points }, null, 2)

  if (fit && map) map.fitBounds(bounds)
}

function fitPolygon() {
  if (!googleMaps || !map || !polygon) return
  const bounds = new googleMaps.LatLngBounds()
  polygon.getPath().getArray().forEach((point) => bounds.extend(point))
  map.fitBounds(bounds)
}

function extractBestAvailablePoints() {
  const fromGeometry = extractPointsFromAny(form.geometry_json)
  if (fromGeometry.length) return fromGeometry
  return extractPointsFromAny(form.polygon_points)
}

function extractPointsFromAny(value) {
  const parsed = parseJsonish(value)
  return extractPoints(parsed)
}

function extractPoints(value) {
  if (!value) return []

  if (Array.isArray(value)) {
    if (value.length && Array.isArray(value[0]) && !('lat' in (value[0] || {})) && !('lng' in (value[0] || {}))) {
      return extractPoints(value[0])
    }
    const mapped = value.map(normalizePoint).filter(Boolean)
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

function parseJsonish(value) {
  if (!value) return null
  if (typeof value !== 'string') return value
  try {
    return JSON.parse(value)
  } catch {
    return null
  }
}

function normalizePoint(value) {
  if (!value) return null

  if (Array.isArray(value)) {
    if (value.length < 2) return null
    const first = Number(value[0])
    const second = Number(value[1])
    if (!Number.isFinite(first) || !Number.isFinite(second)) return null
    if (Math.abs(first) <= 90 && Math.abs(second) <= 180) return { lat: first, lng: second }
    return { lat: second, lng: first }
  }

  const lat = Number(value.lat ?? value.latitude)
  const lng = Number(value.lng ?? value.lon ?? value.longitude ?? value.long)
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null
  return { lat, lng }
}

function getFallbackCenter() {
  const rawLat = String(form.center_point_lat ?? '').trim()
  const rawLng = String(form.center_point_lng ?? '').trim()

  if (rawLat !== '' && rawLng !== '') {
    const lat = Number(rawLat)
    const lng = Number(rawLng)

    if (Number.isFinite(lat) && Number.isFinite(lng)) {
      return { lat, lng }
    }
  }

  return { lat: 41.1400, lng: -104.8200 }
}

function normalizeNullableNumber(value) {
  if (value === '' || value == null) return null
  const numeric = Number(value)
  return Number.isFinite(numeric) ? numeric : null
}

function normalizeNullableInt(value) {
  if (value === '' || value == null) return null
  const numeric = Number.parseInt(value, 10)
  return Number.isFinite(numeric) ? numeric : null
}

function blankToNull(value) {
  if (value == null) return null
  const stringValue = String(value).trim()
  return stringValue === '' ? null : stringValue
}

function roundCoord(value) {
  return Number(Number(value).toFixed(7))
}

function displayCoord(value) {
  if (value == null || value === '') return '—'
  const numeric = Number(value)
  return Number.isFinite(numeric) ? numeric.toFixed(5) : String(value)
}

function clamp(value, min, max) {
  return Math.min(Math.max(Number(value), min), max)
}

function readStoredNumber(key, fallback) {
  if (typeof window === 'undefined') return fallback
  const raw = window.localStorage.getItem(key)
  const numeric = Number(raw)
  return Number.isFinite(numeric) ? numeric : fallback
}

function startSplitResize(event) {
  if (!isDesktop.value) return
  const startX = event.clientX
  const startPercent = splitPercent.value
  const bodyWidth = event.currentTarget.parentElement?.getBoundingClientRect().width || 1200

  const onMove = (moveEvent) => {
    const deltaPercent = ((moveEvent.clientX - startX) / bodyWidth) * 100
    splitPercent.value = clamp(startPercent + deltaPercent, SPLIT_MIN, SPLIT_MAX)
  }

  const onUp = () => {
    window.localStorage.setItem(STORAGE_KEY_SPLIT, String(splitPercent.value))
    window.removeEventListener('mousemove', onMove)
    window.removeEventListener('mouseup', onUp)
  }

  window.addEventListener('mousemove', onMove)
  window.addEventListener('mouseup', onUp)
}

function startModalResize(event) {
  const startX = event.clientX
  const startY = event.clientY
  const startW = modalWidth.value
  const startH = modalHeight.value

  const onMove = (moveEvent) => {
    modalWidth.value = clamp(startW + (moveEvent.clientX - startX), 960, viewportWidth.value - 24)
    modalHeight.value = clamp(startH + (moveEvent.clientY - startY), 720, viewportHeight.value - 24)
  }

  const onUp = () => {
    window.localStorage.setItem(STORAGE_KEY_W, String(modalWidth.value))
    window.localStorage.setItem(STORAGE_KEY_H, String(modalHeight.value))
    window.removeEventListener('mousemove', onMove)
    window.removeEventListener('mouseup', onUp)
  }

  window.addEventListener('mousemove', onMove)
  window.addEventListener('mouseup', onUp)
}

function handleWindowResize() {
  viewportWidth.value = window.innerWidth
  viewportHeight.value = window.innerHeight
}

if (typeof window !== 'undefined') {
  window.addEventListener('resize', handleWindowResize)
}

onBeforeUnmount(() => {
  document.body.style.overflow = ''
  destroyMapObjects()
  if (typeof window !== 'undefined') {
    window.removeEventListener('resize', handleWindowResize)
  }
})
</script>

<style scoped>
.geofenceModal {
  display: flex;
  flex-direction: column;
  position: relative;
}

.mapOnlyMode {
  max-width: none;
}

.modalErr {
  margin: 8px 12px 0 12px;
}

.splitBody {
  display: flex;
  gap: 0;
  min-height: 0;
  flex: 1;
  padding: 0;
}

.splitBody.stacked {
  display: block;
  overflow: auto;
}

.splitLeft,
.splitRight {
  min-height: 0;
}

.splitLeft {
  overflow: auto;
  padding: 10px 6px 10px 12px;
  flex: 0 0 auto;
  min-width: 0;
}

.splitLeftInner {
  width: fit-content;
  min-width: 0;
}

.splitRight {
  flex: 1;
  border-left: 1px solid #e5e7eb;
  padding: 10px;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.mapOnlyPane {
  border-left: none;
  padding: 10px;
}

.splitDivider {
  width: 6px;
  cursor: col-resize;
  background: linear-gradient(to right, transparent, #e5e7eb, transparent);
}

.dbForm {
  grid-template-columns: max-content;
  gap: 14px;
  width: fit-content;
}

.formCol {
  display: flex;
  flex-direction: column;
  gap: 6px;
  width: fit-content;
}

.formColEmpty {
  min-height: 1px;
}

.row {
  display: grid;
  grid-template-columns: 132px max-content;
  gap: 8px;
  align-items: center;
  margin: 0;
  width: fit-content;
}

.row.tall,
.notesRow {
  align-items: start;
}

.lbl {
  font-size: 12px;
  font-weight: 700;
  line-height: 1.1;
}

.ctl {
  min-width: 0;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.input,
.textarea,
.input-xl,
.textarea-xl {
  width: auto;
  max-width: 100%;
}

.input {
  min-height: 30px;
  padding-top: 4px;
  padding-bottom: 4px;
}

.textarea {
  padding-top: 6px;
  padding-bottom: 6px;
}

.notesRow .textarea,
.notesRow .textarea-xl {
  min-height: 84px;
}

.fieldErr {
  color: #b91c1c;
  font-size: 11px;
  margin-top: 3px;
  font-weight: 700;
}

.colorRow {
  display: grid;
  grid-template-columns: 54px max-content;
  gap: 6px;
  align-items: center;
  width: fit-content;
}

.colorSwatch {
  width: 54px;
  height: 30px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 2px;
  background: #fff;
}

.checkRow {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-weight: 700;
  min-height: 30px;
}

.mapTools {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-bottom: 8px;
}

.mapMeta {
  display: flex;
  gap: 14px;
  flex-wrap: wrap;
  font-size: 12px;
  color: #374151;
  margin-bottom: 8px;
}

.mapErr {
  margin-bottom: 8px;
}

.mapCanvas {
  flex: 1;
  min-height: 420px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  overflow: hidden;
  background: #f9fafb;
}

.footerActions {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.footerRight {
  margin-left: auto;
  display: flex;
  align-items: center;
  justify-content: flex-end;
}

.modalResizeHandle {
  position: absolute;
  right: 0;
  bottom: 0;
  width: 18px;
  height: 18px;
  cursor: nwse-resize;
  background:
      linear-gradient(135deg, transparent 0 45%, #cbd5e1 45% 55%, transparent 55% 100%),
      linear-gradient(135deg, transparent 0 65%, #94a3b8 65% 75%, transparent 75% 100%);
}

@media (max-width: 1023px) {
  .splitLeft {
    min-width: 0;
    width: auto !important;
  }

  .splitLeftInner,
  .dbForm,
  .formCol,
  .row {
    width: 100%;
  }

  .dbForm {
    grid-template-columns: 1fr;
    gap: 10px;
  }

  .formCol {
    gap: 6px;
  }

  .splitLeft,
  .splitRight {
    padding: 10px;
  }

  .splitRight {
    border-left: none;
    border-top: 1px solid #e5e7eb;
  }

  .mapOnlyPane {
    border-top: none;
    padding: 10px;
  }

  .row {
    grid-template-columns: 110px 1fr;
    gap: 6px;
  }

  .ctl {
    align-items: stretch;
  }

  .input,
  .textarea,
  .input-xl,
  .textarea-xl {
    width: 100%;
    max-width: none;
  }

  .colorRow {
    grid-template-columns: 54px 1fr;
    width: 100%;
  }
}
</style>


