<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { propertyService } from '@/services/propertyService'
import { viewingService } from '@/services/viewingService'
import { useAuthStore } from '@/stores/auth'
import BaseSpinner from '@/components/base/BaseSpinner.vue'

const auth = useAuthStore()
const loading  = ref(true)
const stats    = ref({ total: 0, published: 0, pending: 0, messages: 0, viewings: 0 })
const viewings = ref([])
const recentProps = ref([])

onMounted(async () => {
  try {
    // Liczniki bierzemy z meta.total, a nie z pierwszej strony wyników.
    const [propsRes, publishedRes, pendingRes, viewRes] = await Promise.all([
      propertyService.ownerList({ per_page: 5 }),
      propertyService.ownerList({ status: 'published', per_page: 1 }),
      propertyService.ownerList({ status: 'pending', per_page: 1 }),
      viewingService.ownerList({ status: 'pending', per_page: 5 }),
    ])

    const allProps = propsRes.data
    recentProps.value = allProps.data?.slice(0, 5) || []
    stats.value.total     = allProps.meta?.total || 0
    stats.value.published = publishedRes.data.meta?.total || 0
    stats.value.pending   = pendingRes.data.meta?.total || 0
    viewings.value = viewRes.data.data || []
    stats.value.viewings = viewRes.data.meta?.total || 0
  } finally {
    loading.value = false
  }
})

function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleString('pl-PL', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Witaj, {{ auth.user?.name }}!</h1>
      <p class="text-gray-500 text-sm mt-1">Panel właściciela</p>
    </div>

    <div v-if="loading" class="flex justify-center py-12"><BaseSpinner /></div>

    <template v-else>
      <!-- Statystyki -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="card p-4 text-center">
          <p class="text-3xl font-bold text-primary-600">{{ stats.total }}</p>
          <p class="text-sm text-gray-500 mt-1">Wszystkich ofert</p>
        </div>
        <div class="card p-4 text-center">
          <p class="text-3xl font-bold text-green-600">{{ stats.published }}</p>
          <p class="text-sm text-gray-500 mt-1">Opublikowanych</p>
        </div>
        <div class="card p-4 text-center">
          <p class="text-3xl font-bold text-yellow-600">{{ stats.pending }}</p>
          <p class="text-sm text-gray-500 mt-1">Oczekuje weryfikacji</p>
        </div>
        <div class="card p-4 text-center">
          <p class="text-3xl font-bold text-blue-600">{{ stats.viewings }}</p>
          <p class="text-sm text-gray-500 mt-1">Nowych rezerwacji</p>
        </div>
      </div>

      <!-- Szybkie linki -->
      <div class="flex flex-wrap gap-3 mb-8">
        <RouterLink to="/wlasciciel/oferty/nowa" class="btn btn-primary btn-sm">+ Dodaj ofertę</RouterLink>
        <RouterLink to="/wlasciciel/rezerwacje" class="btn btn-secondary btn-sm">Rezerwacje</RouterLink>
        <RouterLink to="/wlasciciel/wiadomosci" class="btn btn-secondary btn-sm">Wiadomości</RouterLink>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Oczekujące rezerwacje -->
        <div class="card p-4">
          <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-gray-900">Oczekujące rezerwacje</h2>
            <RouterLink to="/wlasciciel/rezerwacje" class="text-xs text-primary-600 hover:underline">Wszystkie →</RouterLink>
          </div>
          <div v-if="!viewings.length" class="text-sm text-gray-400 py-4 text-center">Brak oczekujących rezerwacji</div>
          <div v-else class="space-y-3">
            <div v-for="v in viewings" :key="v.id" class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">{{ v.property?.title }}</p>
                <p class="text-xs text-gray-500">{{ v.user?.name }} · {{ formatDate(v.proposed_at) }}</p>
              </div>
              <RouterLink to="/wlasciciel/rezerwacje" class="text-xs text-primary-600 hover:underline shrink-0">Zarządzaj</RouterLink>
            </div>
          </div>
        </div>

        <!-- Ostatnie oferty -->
        <div class="card p-4">
          <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-gray-900">Moje oferty</h2>
            <RouterLink to="/wlasciciel/oferty" class="text-xs text-primary-600 hover:underline">Wszystkie →</RouterLink>
          </div>
          <div v-if="!recentProps.length" class="text-sm text-gray-400 py-4 text-center">Nie masz jeszcze ofert</div>
          <div v-else class="space-y-2">
            <div v-for="p in recentProps" :key="p.id" class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded-lg">
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">{{ p.title }}</p>
                <p class="text-xs text-gray-500">{{ p.city }} · {{ p.price }} zł/mies.</p>
              </div>
              <span :class="['badge text-xs', p.status === 'published' ? 'badge-green' : p.status === 'pending' ? 'badge-yellow' : 'badge-gray']">
                {{ p.status_label }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
