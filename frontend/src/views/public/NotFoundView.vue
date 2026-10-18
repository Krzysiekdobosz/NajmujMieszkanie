<script setup>
import { RouterLink } from 'vue-router'
import PublicLayout from '@/layouts/PublicLayout.vue'
</script>

<template>
  <PublicLayout>
    <section class="max-w-xl mx-auto px-4 py-24 text-center">
      <p class="text-7xl font-bold text-primary-600 mb-4">404</p>
      <h1 class="text-2xl font-bold text-gray-900 mb-3">Nie znaleziono strony</h1>
      <p class="text-gray-500 mb-8">Strona, której szukasz, nie istnieje lub została przeniesiona.</p>
      <div class="flex flex-col sm:flex-row gap-3 justify-center">
        <RouterLink to="/" class="btn-primary">Strona główna</RouterLink>
        <RouterLink to="/oferty" class="btn-secondary">Przeglądaj oferty</RouterLink>
      </div>
    </section>
  </PublicLayout>
</template>
