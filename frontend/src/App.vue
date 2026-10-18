<script setup>
import { RouterView } from 'vue-router'

const isDemo = import.meta.env.VITE_DEMO === 'true'

async function resetDemo() {
  const { resetDb } = await import('@/demo/db')
  resetDb()
  window.location.reload()
}
</script>

<template>
  <div v-if="isDemo" class="bg-gray-900 text-white text-xs sm:text-sm text-center px-4 py-2">
    Wersja demonstracyjna na danych testowych – zmiany zapisują się tylko w Twojej przeglądarce.
    <button type="button" class="underline text-primary-200 ml-1" @click="resetDemo">Przywróć dane</button>
  </div>
  <RouterView />
</template>
