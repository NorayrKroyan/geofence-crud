<template>
  <div class="container">
    <div class="pageHeader">
      <p class="pageTitle">Driver Edit</p>

      <div class="pageHeaderActions">
        <router-link class="btn" :to="{ name: 'device-history' }">Device history</router-link>
        <button class="btnPrimary" @click="openNew">New Driver</button>
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
            <th>Name</th>
            <th>Mobile Number</th>
            <th style="text-align: center">Status</th>
          </tr>
        </thead>

        <tbody>
          <tr v-if="loading && !rows.length">
            <td colspan="3" class="empty">Loading drivers...</td>
          </tr>

          <tr v-else-if="!paged.length">
            <td colspan="3" class="empty">No drivers found.</td>
          </tr>

          <tr v-for="driver in paged" :key="driver.id">
            <td>
              <a href="#" class="link" @click.prevent="openEdit(driver)">
                {{ driver.name || '—' }}
              </a>
            </td>

            <td>{{ driver.mobile_number || '—' }}</td>

            <td style="text-align: center">
              <span class="statusPill statusPillTight" :class="statusClass(driver.status)">
                {{ driver.status || '—' }}
              </span>
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

    <div v-if="modalOpen" class="modalOverlay" @click.self="closeModal">
      <div class="modalCard driverModalCard">
        <div class="modalHeader">
          <div>
            <p class="modalTitle">{{ isEditing ? 'Edit Driver' : 'New Driver' }}</p>
          </div>

          <button class="btn" type="button" @click="closeModal">Close</button>
        </div>

        <div class="modalBody">
          <div class="formTwoCol driverFormSingleCol">
            <div class="formCol">
              <div class="row">
                <label class="lbl">Name</label>
                <div class="ctl">
                  <input v-model.trim="form.name" class="input" type="text" />
                </div>
              </div>

              <div class="row">
                <label class="lbl">Mobile Number</label>
                <div class="ctl">
                  <input v-model.trim="form.mobile_number" class="input" type="text" />
                </div>
              </div>

              <div class="row">
                <label class="lbl">Status</label>
                <div class="ctl">
                  <input v-model.trim="form.status" class="input" type="text" placeholder="active" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modalFooter">
          <div class="actions">
            <button
              v-if="isEditing"
              class="btnDanger"
              type="button"
              :disabled="saving || deleting"
              @click="removeDriver"
            >
              {{ deleting ? 'Deleting...' : 'Delete' }}
            </button>
          </div>

          <div class="actions">
            <button class="btn" type="button" :disabled="saving || deleting" @click="closeModal">Cancel</button>
            <button class="btnPrimary" type="button" :disabled="saving || deleting" @click="saveDriver">
              {{ saving ? 'Saving...' : 'Save' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { createDriver, deleteDriver, getDriver, listDrivers, updateDriver } from '../api/drivers'

const rows = ref([])
const err = ref('')
const q = ref('')
const loading = ref(false)

const page = ref(1)
const pageSize = ref(25)

const modalOpen = ref(false)
const saving = ref(false)
const deleting = ref(false)

const selectedId = ref(null)
const form = ref(blankForm())

onMounted(() => reload())

watch(q, () => {
  page.value = 1
})

function blankForm() {
  return {
    name: '',
    mobile_number: '',
    status: 'active',
  }
}

const isEditing = computed(() => selectedId.value !== null)

const filtered = computed(() => {
  if (!q.value) return rows.value

  const needle = q.value.toLowerCase()

  return rows.value.filter((row) => {
    return [
      row.id,
      row.name,
      row.mobile_number,
      row.status,
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

async function reload() {
  loading.value = true
  err.value = ''

  try {
    const payload = await listDrivers()
    rows.value = Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : [])
  } catch (error) {
    rows.value = []
    err.value = error?.message || 'Unable to load drivers.'
  } finally {
    loading.value = false
  }
}

function openNew() {
  selectedId.value = null
  form.value = blankForm()
  err.value = ''
  modalOpen.value = true
}

async function openEdit(driver) {
  selectedId.value = driver?.id ?? null
  form.value = blankForm()
  err.value = ''
  modalOpen.value = true

  if (!selectedId.value) return

  try {
    const payload = await getDriver(selectedId.value)
    const item = payload?.data || driver || {}

    form.value = {
      name: item?.name || '',
      mobile_number: item?.mobile_number || '',
      status: item?.status || 'active',
    }
  } catch (error) {
    modalOpen.value = false
    err.value = error?.message || 'Unable to load driver.'
  }
}

function closeModal() {
  if (saving.value || deleting.value) return
  modalOpen.value = false
  selectedId.value = null
  form.value = blankForm()
}

async function saveDriver() {
  saving.value = true
  err.value = ''

  try {
    const payload = {
      name: form.value.name,
      mobile_number: form.value.mobile_number || null,
      status: form.value.status,
    }

    if (isEditing.value) {
      await updateDriver(selectedId.value, payload)
    } else {
      await createDriver(payload)
    }

    modalOpen.value = false
    selectedId.value = null
    form.value = blankForm()
    await reload()
  } catch (error) {
    err.value = error?.message || 'Unable to save driver.'
  } finally {
    saving.value = false
  }
}

async function removeDriver() {
  if (!isEditing.value || deleting.value) return
  if (!window.confirm('Delete this driver?')) return

  deleting.value = true
  err.value = ''

  try {
    await deleteDriver(selectedId.value)
    modalOpen.value = false
    selectedId.value = null
    form.value = blankForm()
    await reload()
  } catch (error) {
    err.value = error?.message || 'Unable to delete driver.'
  } finally {
    deleting.value = false
  }
}

function statusClass(value) {
  return String(value || '').trim().toLowerCase() === 'active'
    ? 'statusPillOn'
    : 'statusPillOff'
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

.statusPillTight {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 1px 8px !important;
  min-height: 20px !important;
  line-height: 1 !important;
  font-size: 12px !important;
  border-radius: 999px;
  text-transform: capitalize;
}

.driverModalCard {
  width: min(760px, 100%);
}

.driverFormSingleCol {
  grid-template-columns: 1fr;
  gap: 0;
}
</style>
