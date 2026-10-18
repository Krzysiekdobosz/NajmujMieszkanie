<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import { paymentService } from '@/services/paymentService'
import { propertyService } from '@/services/propertyService'
import { useApiErrors } from '@/composables/useApiErrors'
import { formatPrice, formatDate } from '@/utils/formatters'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import BaseBadge from '@/components/base/BaseBadge.vue'
import BaseModal from '@/components/base/BaseModal.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseInput from '@/components/base/BaseInput.vue'
import BaseSelect from '@/components/base/BaseSelect.vue'

const toast = useToast()
const { setErrors, clearErrors, fieldError } = useApiErrors()

const payments = ref([])
const summary = ref({ pending: 0, paid_this_month: 0, overdue_count: 0 })
const properties = ref([])
const loading = ref(false)
const statusFilter = ref('')
const showForm = ref(false)
const submitting = ref(false)

const statusOptions = [
  { value: '', label: 'Wszystkie' },
  { value: 'pending', label: 'Oczekujące' },
  { value: 'paid', label: 'Opłacone' },
  { value: 'failed', label: 'Nieudane' },
  { value: 'cancelled', label: 'Anulowane' },
]
const typeOptions = [
  { value: 'rent', label: 'Czynsz' },
  { value: 'deposit', label: 'Kaucja' },
  { value: 'extra_fee', label: 'Opłata dodatkowa' },
]
const statusBadge = { pending: 'yellow', paid: 'green', failed: 'red', cancelled: 'gray' }

const emptyForm = () => ({
  property_id: '',
  tenant_email: '',
  type: 'rent',
  amount: '',
  description: '',
  due_date: new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10),
})
const form = reactive(emptyForm())

async function fetch() {
  loading.value = true
  try {
    const params = {}
    if (statusFilter.value) params.status = statusFilter.value
    const { data } = await paymentService.list(params)
    payments.value = data.data
    if (data.summary) summary.value = data.summary
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
    properties.value = data.data.map(p => ({ value: p.id, label: `${p.title} (${p.city})`, price: p.price }))
  }
}

function onPropertyChange(id) {
  const property = properties.value.find(p => p.value === Number(id))
  if (property && form.type === 'rent' && !form.amount) form.amount = property.price
}

async function submit() {
  submitting.value = true
  clearErrors()
  try {
    await paymentService.create(form)
    toast.success('Płatność została dodana.')
    showForm.value = false
    fetch()
  } catch (e) {
    setErrors(e)
    toast.error(e.response?.data?.message || 'Nie udało się dodać płatności.')
  } finally {
    submitting.value = false
  }
}

async function setStatus(payment, status) {
  await paymentService.updateStatus(payment.id, status)
  toast.success(status === 'paid' ? 'Płatność oznaczona jako opłacona.' : 'Status zaktualizowany.')
  fetch()
}

onMounted(fetch)
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Płatności</h1>
      <BaseButton @click="openForm">+ Dodaj płatność</BaseButton>
    </div>

    <div class="grid sm:grid-cols-3 gap-4 mb-6">
      <div class="card p-4">
        <p class="text-sm text-gray-500">Do zapłaty</p>
        <p class="text-2xl font-bold text-gray-900">{{ formatPrice(summary.pending) }}</p>
      </div>
      <div class="card p-4">
        <p class="text-sm text-gray-500">Wpłynęło w tym miesiącu</p>
        <p class="text-2xl font-bold text-green-700">{{ formatPrice(summary.paid_this_month) }}</p>
      </div>
      <div class="card p-4">
        <p class="text-sm text-gray-500">Zaległe płatności</p>
        <p class="text-2xl font-bold" :class="summary.overdue_count ? 'text-red-600' : 'text-gray-900'">
          {{ summary.overdue_count }}
        </p>
      </div>
    </div>

    <div class="mb-4">
      <BaseSelect v-model="statusFilter" :options="statusOptions" class="w-48" @update:modelValue="fetch" />
    </div>

    <div v-if="loading" class="flex justify-center py-12"><BaseSpinner /></div>

    <div v-else-if="!payments.length" class="card p-10 text-center text-gray-500">
      Brak płatności w tej kategorii.
    </div>

    <div v-else class="card overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
          <tr>
            <th class="px-4 py-3">Termin</th>
            <th class="px-4 py-3">Oferta / najemca</th>
            <th class="px-4 py-3">Typ</th>
            <th class="px-4 py-3 text-right">Kwota</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="p in payments" :key="p.id">
            <td class="px-4 py-3 whitespace-nowrap" :class="p.is_overdue ? 'text-red-600 font-medium' : ''">
              {{ formatDate(p.due_date) }}
              <span v-if="p.is_overdue" class="block text-xs">po terminie</span>
            </td>
            <td class="px-4 py-3">
              <p class="font-medium text-gray-900">{{ p.property?.title }}</p>
              <p class="text-gray-500">{{ p.tenant?.name }}<span v-if="p.description"> · {{ p.description }}</span></p>
            </td>
            <td class="px-4 py-3 whitespace-nowrap">{{ p.type_label }}</td>
            <td class="px-4 py-3 text-right font-semibold whitespace-nowrap">{{ formatPrice(p.amount) }}</td>
            <td class="px-4 py-3"><BaseBadge :variant="statusBadge[p.status]">{{ p.status_label }}</BaseBadge></td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <template v-if="p.status === 'pending'">
                <BaseButton size="sm" @click="setStatus(p, 'paid')">Opłacona</BaseButton>
                <BaseButton size="sm" variant="ghost" @click="setStatus(p, 'cancelled')">Anuluj</BaseButton>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <BaseModal :show="showForm" title="Nowa płatność" @close="showForm = false">
      <form id="payment-form" class="space-y-4" @submit.prevent="submit">
        <BaseSelect
          v-model="form.property_id"
          label="Oferta"
          :options="properties"
          placeholder="Wybierz ofertę"
          :error="fieldError('property_id')"
          required
          @update:modelValue="onPropertyChange"
        />
        <BaseInput v-model="form.tenant_email" type="email" label="E-mail najemcy" :error="fieldError('tenant_email')" required />
        <div class="grid grid-cols-2 gap-4">
          <BaseSelect v-model="form.type" label="Typ" :options="typeOptions" :error="fieldError('type')" required />
          <BaseInput v-model="form.amount" type="number" label="Kwota (zł)" :error="fieldError('amount')" required />
        </div>
        <BaseInput v-model="form.due_date" type="date" label="Termin płatności" :error="fieldError('due_date')" required />
        <BaseInput v-model="form.description" label="Opis (opcjonalnie)" placeholder="np. Czynsz za listopad" />
      </form>
      <template #footer>
        <BaseButton variant="secondary" @click="showForm = false">Anuluj</BaseButton>
        <BaseButton type="submit" form="payment-form" :loading="submitting">Dodaj płatność</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
