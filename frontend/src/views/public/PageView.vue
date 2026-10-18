<script setup>
import { ref, reactive, watch } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useToast } from 'vue-toastification'
import PublicLayout from '@/layouts/PublicLayout.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import BaseInput from '@/components/base/BaseInput.vue'
import BaseTextarea from '@/components/base/BaseTextarea.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import { pageService } from '@/services/pageService'
import { useAuthStore } from '@/stores/auth'
import { useApiErrors } from '@/composables/useApiErrors'

const route = useRoute()
const toast = useToast()
const auth = useAuthStore()
const { setErrors, clearErrors, fieldError } = useApiErrors()

const page = ref(null)
const loading = ref(true)
const notFound = ref(false)
const sending = ref(false)
const sent = ref(false)

const contact = reactive({ name: '', email: '', subject: '', body: '' })

async function load(slug) {
  loading.value = true
  notFound.value = false
  try {
    const { data } = await pageService.show(slug)
    page.value = data.data
    document.title = `${page.value.meta_title} | NajmujMieszkanie`
  } catch {
    notFound.value = true
  } finally {
    loading.value = false
  }
}

async function sendContact() {
  sending.value = true
  clearErrors()
  try {
    const { data } = await pageService.sendContact(contact)
    toast.success(data.message || 'Wiadomość została wysłana.')
    sent.value = true
    Object.assign(contact, { subject: '', body: '' })
  } catch (e) {
    setErrors(e)
    toast.error(e.response?.data?.message || 'Nie udało się wysłać wiadomości.')
  } finally {
    sending.value = false
  }
}

watch(() => route.params.slug, (slug) => {
  sent.value = false
  if (auth.user) {
    contact.name = contact.name || auth.user.name
    contact.email = contact.email || auth.user.email
  }
  load(slug)
}, { immediate: true })
</script>

<template>
  <PublicLayout>
    <div class="max-w-3xl mx-auto px-4 py-12 sm:px-6">
      <div v-if="loading" class="flex justify-center py-20"><BaseSpinner /></div>

      <div v-else-if="notFound" class="text-center py-20">
        <h1 class="text-2xl font-bold mb-2">Nie znaleziono strony</h1>
        <RouterLink to="/" class="text-primary-600 hover:underline">Wróć na stronę główną</RouterLink>
      </div>

      <article v-else>
        <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-8">{{ page.title }}</h1>
        <!-- Treść stron statycznych edytuje wyłącznie administrator -->
        <div class="page-content" v-html="page.content" />

        <section v-if="page.slug === 'kontakt'" class="card p-6 mt-10">
          <h2 class="text-xl font-semibold mb-4">Napisz do nas</h2>
          <div v-if="sent" class="rounded-lg bg-green-50 border border-green-200 text-green-800 p-4">
            Dziękujemy! Twoja wiadomość trafiła do naszego zespołu.
            <button type="button" class="underline ml-1" @click="sent = false">Wyślij kolejną</button>
          </div>
          <form v-else class="space-y-4" @submit.prevent="sendContact">
            <div class="grid sm:grid-cols-2 gap-4">
              <BaseInput v-model="contact.name" label="Imię i nazwisko" :error="fieldError('name')" required />
              <BaseInput v-model="contact.email" type="email" label="E-mail" :error="fieldError('email')" required />
            </div>
            <BaseInput v-model="contact.subject" label="Temat" :error="fieldError('subject')" required />
            <BaseTextarea v-model="contact.body" label="Wiadomość" :rows="5" :error="fieldError('body')" required />
            <BaseButton type="submit" :loading="sending">Wyślij wiadomość</BaseButton>
          </form>
        </section>
      </article>
    </div>
  </PublicLayout>
</template>
