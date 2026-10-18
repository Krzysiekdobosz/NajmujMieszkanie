<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import { contractService } from '@/services/contractService'
import { propertyService } from '@/services/propertyService'
import { useAuthStore } from '@/stores/auth'
import { useApiErrors } from '@/composables/useApiErrors'
import { formatPrice, formatDate } from '@/utils/formatters'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import BaseBadge from '@/components/base/BaseBadge.vue'
import BaseModal from '@/components/base/BaseModal.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseInput from '@/components/base/BaseInput.vue'
import BaseSelect from '@/components/base/BaseSelect.vue'
import BaseTextarea from '@/components/base/BaseTextarea.vue'

const toast = useToast()
const auth = useAuthStore()
const { setErrors, clearErrors, fieldError } = useApiErrors()

const contracts = ref([])
const properties = ref([])
const loading = ref(false)
const showForm = ref(false)
const submitting = ref(false)
const downloadingId = ref(null)

const statusBadge = { draft: 'gray', generated: 'blue', signed: 'green' }

const emptyForm = () => ({
  property_id: '',
  tenant_email: '',
  tenant_name: '',
  tenant_address: '',
  tenant_id_number: '',
  owner_name: auth.user?.name ?? '',
  owner_address: '',
  owner_id_number: '',
  start_date: new Date().toISOString().slice(0, 10),
  rental_months: 12,
  conditions: '',
})
const form = reactive(emptyForm())

async function fetch() {
  loading.value = true
  try {
    const { data } = await contractService.list()
    contracts.value = data.data
  } finally {
    loading.value = false
  }
}

async function openForm() {
  Object.assign(form, emptyForm())
  clearErrors()
  showForm.value = true
  if (!properties.value.length) {
    const { data } = await propertyService.ownerList({ per_page: 100 })
    properties.value = data.data.map(p => ({ value: p.id, label: `${p.title} (${p.city})` }))
  }
}

async function submit() {
  submitting.value = true
  clearErrors()
  try {
    await contractService.create({ ...form, rental_months: form.rental_months || null })
    toast.success('Umowa została wygenerowana.')
    showForm.value = false
    fetch()
  } catch (e) {
    setErrors(e)
    toast.error(e.response?.data?.message || 'Nie udało się utworzyć umowy.')
  } finally {
    submitting.value = false
  }
}

async function markSigned(contract) {
  await contractService.updateStatus(contract.id, 'signed')
  toast.success('Umowa oznaczona jako podpisana.')
  fetch()
}

async function download(contract) {
  downloadingId.value = contract.id
  try {
    const { data } = await contractService.download(contract.id)
    const url = URL.createObjectURL(new Blob([data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href = url
    link.download = `umowa-najmu-${contract.id}.pdf`
    link.click()
    URL.revokeObjectURL(url)
  } catch {
    toast.error('Nie udało się pobrać umowy.')
  } finally {
    downloadingId.value = null
  }
}

onMounted(fetch)
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Umowy najmu</h1>
      <BaseButton @click="openForm">+ Nowa umowa</BaseButton>
    </div>

    <div v-if="loading" class="flex justify-center py-12"><BaseSpinner /></div>

    <div v-else-if="!contracts.length" class="card p-10 text-center text-gray-500">
      <p class="mb-4">Nie masz jeszcze żadnych umów.</p>
      <BaseButton @click="openForm">Wygeneruj pierwszą umowę</BaseButton>
    </div>

    <div v-else class="space-y-3">
      <div v-for="c in contracts" :key="c.id" class="card p-4 flex flex-col md:flex-row md:items-center gap-4">
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 mb-1">
            <BaseBadge :variant="statusBadge[c.status]">{{ c.status_label }}</BaseBadge>
            <span class="text-xs text-gray-400">Nr {{ c.id }}</span>
          </div>
          <p class="font-medium text-gray-900 truncate">{{ c.property?.title }}</p>
          <p class="text-sm text-gray-500">
            Najemca: <strong>{{ c.tenant?.name }}</strong> · {{ c.tenant?.email }}
          </p>
          <p class="text-sm text-gray-600 mt-1">
            {{ formatDate(c.start_date) }} – {{ c.end_date ? formatDate(c.end_date) : 'bezterminowo' }}
            · {{ formatPrice(c.rental_price) }} / mies.
          </p>
        </div>
        <div class="flex gap-2 shrink-0">
          <BaseButton
            v-if="c.status !== 'signed'"
            variant="secondary"
            size="sm"
            @click="markSigned(c)"
          >Oznacz jako podpisaną</BaseButton>
          <BaseButton size="sm" :loading="downloadingId === c.id" @click="download(c)">Pobierz PDF</BaseButton>
        </div>
      </div>
    </div>

    <BaseModal :show="showForm" title="Nowa umowa najmu" size="lg" @close="showForm = false">
      <form id="contract-form" class="space-y-4" @submit.prevent="submit">
        <BaseSelect
          v-model="form.property_id"
          label="Oferta"
          :options="properties"
          placeholder="Wybierz ofertę"
          :error="fieldError('property_id')"
          required
        />
        <p class="text-xs text-gray-500 -mt-2">Czynsz, kaucja i opłaty zostaną pobrane z ogłoszenia.</p>

        <div class="grid sm:grid-cols-2 gap-4">
          <BaseInput v-model="form.start_date" type="date" label="Data rozpoczęcia" :error="fieldError('start_date')" required />
          <BaseInput v-model="form.rental_months" type="number" label="Okres (miesiące, puste = bezterminowo)" :error="fieldError('rental_months')" />
        </div>

        <fieldset class="border border-gray-200 rounded-lg p-4 space-y-3">
          <legend class="text-sm font-semibold text-gray-700 px-1">Najemca</legend>
          <BaseInput v-model="form.tenant_email" type="email" label="E-mail konta najemcy" :error="fieldError('tenant_email')" required />
          <div class="grid sm:grid-cols-2 gap-3">
            <BaseInput v-model="form.tenant_name" label="Imię i nazwisko" placeholder="Z konta, jeśli puste" />
            <BaseInput v-model="form.tenant_id_number" label="Nr dowodu" />
          </div>
          <BaseInput v-model="form.tenant_address" label="Adres zamieszkania" />
        </fieldset>

        <fieldset class="border border-gray-200 rounded-lg p-4 space-y-3">
          <legend class="text-sm font-semibold text-gray-700 px-1">Wynajmujący</legend>
          <div class="grid sm:grid-cols-2 gap-3">
            <BaseInput v-model="form.owner_name" label="Imię i nazwisko" :error="fieldError('owner_name')" required />
            <BaseInput v-model="form.owner_id_number" label="Nr dowodu" />
          </div>
          <BaseInput v-model="form.owner_address" label="Adres" />
        </fieldset>

        <BaseTextarea v-model="form.conditions" label="Dodatkowe warunki (opcjonalnie)" :rows="3" />
      </form>
      <template #footer>
        <BaseButton variant="secondary" @click="showForm = false">Anuluj</BaseButton>
        <BaseButton type="submit" form="contract-form" :loading="submitting">Wygeneruj umowę</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
