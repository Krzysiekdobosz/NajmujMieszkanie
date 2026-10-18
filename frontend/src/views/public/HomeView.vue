<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import PublicLayout from '@/layouts/PublicLayout.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import { propertyService } from '@/services/propertyService'
import { formatPrice } from '@/utils/formatters'

const router = useRouter()
const latest = ref([])
const loading = ref(true)
const city = ref('')
const type = ref('')

const cities = ['Warszawa', 'Kraków', 'Wrocław', 'Gdańsk', 'Poznań', 'Łódź']

const steps = [
  { title: 'Wyszukaj', text: 'Filtruj oferty według miasta, ceny, metrażu i udogodnień.' },
  { title: 'Umów oglądanie', text: 'Zarezerwuj termin lub napisz bezpośrednio do właściciela.' },
  { title: 'Podpisz umowę', text: 'Umowa i płatności czekają w Twoim panelu.' },
]

function search() {
  const query = {}
  if (city.value.trim()) query.city = city.value.trim()
  if (type.value) query.type = type.value
  router.push({ path: '/oferty', query })
}

onMounted(async () => {
  try {
    const { data } = await propertyService.list({ sort: 'newest' })
    latest.value = data.data.slice(0, 6)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <PublicLayout>
    <!-- Hero -->
    <section class="bg-gradient-to-br from-primary-600 to-primary-800 text-white py-20 md:py-28">
      <div class="max-w-4xl mx-auto px-4 text-center">
        <h1 class="text-4xl md:text-5xl font-bold mb-6">Znajdź wymarzone mieszkanie lub pokój</h1>
        <p class="text-xl text-primary-100 mb-10">Oferty z całej Polski, bezpośrednio od właścicieli.</p>

        <form class="bg-white rounded-2xl p-2 flex flex-col sm:flex-row gap-2 shadow-xl max-w-2xl mx-auto" @submit.prevent="search">
          <input
            v-model="city"
            type="search"
            placeholder="Miasto, np. Kraków"
            aria-label="Miasto"
            class="flex-1 rounded-xl px-4 py-3 text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-500"
          />
          <select
            v-model="type"
            aria-label="Typ oferty"
            class="rounded-xl px-4 py-3 text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500"
          >
            <option value="">Wszystko</option>
            <option value="apartment">Mieszkania</option>
            <option value="room">Pokoje</option>
          </select>
          <button type="submit" class="btn-primary btn-lg rounded-xl">Szukaj</button>
        </form>

        <div class="flex flex-wrap justify-center gap-2 mt-6">
          <RouterLink
            v-for="c in cities"
            :key="c"
            :to="{ path: '/oferty', query: { city: c } }"
            class="px-3 py-1 rounded-full bg-white/10 hover:bg-white/20 text-sm transition-colors"
          >{{ c }}</RouterLink>
        </div>
      </div>
    </section>

    <!-- Najnowsze oferty -->
    <section class="max-w-7xl mx-auto px-4 py-16">
      <div class="flex items-end justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Najnowsze oferty</h2>
        <RouterLink to="/oferty" class="text-primary-600 hover:underline font-medium">Zobacz wszystkie →</RouterLink>
      </div>

      <div v-if="loading" class="flex justify-center py-12"><BaseSpinner /></div>
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <RouterLink
          v-for="p in latest"
          :key="p.id"
          :to="`/oferty/${p.slug}`"
          class="card group hover:shadow-md transition-shadow"
        >
          <div class="relative aspect-video bg-gray-100 overflow-hidden">
            <img
              v-if="p.main_image_url"
              :src="p.main_image_url"
              :alt="p.title"
              loading="lazy"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            />
            <span :class="['absolute top-2 left-2 badge', p.property_type === 'apartment' ? 'badge-blue' : 'badge-green']">
              {{ p.property_type_label }}
            </span>
          </div>
          <div class="p-4">
            <h3 class="font-semibold text-gray-900 group-hover:text-primary-600 transition-colors line-clamp-1 text-base">{{ p.title }}</h3>
            <p class="text-sm text-gray-500 mt-1">{{ p.city }}<span v-if="p.district">, {{ p.district }}</span></p>
            <p class="text-lg font-bold text-primary-600 mt-2">{{ formatPrice(p.price) }} <span class="text-sm font-normal text-gray-400">/ mies.</span></p>
          </div>
        </RouterLink>
      </div>
    </section>

    <!-- Jak to działa -->
    <section class="bg-white border-y border-gray-100 py-16">
      <div class="max-w-5xl mx-auto px-4">
        <h2 class="text-2xl font-bold text-gray-900 text-center mb-10">Jak to działa</h2>
        <div class="grid md:grid-cols-3 gap-6">
          <div v-for="(step, i) in steps" :key="step.title" class="text-center">
            <div class="w-12 h-12 rounded-full bg-primary-100 text-primary-700 font-bold text-lg flex items-center justify-center mx-auto mb-4">
              {{ i + 1 }}
            </div>
            <h3 class="text-lg font-semibold mb-2">{{ step.title }}</h3>
            <p class="text-gray-500">{{ step.text }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA właściciel -->
    <section class="py-16">
      <div class="max-w-3xl mx-auto px-4 text-center">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Jesteś właścicielem?</h2>
        <p class="text-gray-600 mb-8">Dodaj ofertę za darmo, umawiaj oglądania i generuj umowy w jednym miejscu.</p>
        <RouterLink to="/auth/rejestracja" class="btn-primary btn-lg">Dodaj ogłoszenie</RouterLink>
      </div>
    </section>
  </PublicLayout>
</template>
