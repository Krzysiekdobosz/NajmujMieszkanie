<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useApiErrors } from '@/composables/useApiErrors'
import { useToast } from 'vue-toastification'
import { authService } from '@/services/authService'
import BaseInput from '@/components/base/BaseInput.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseAlert from '@/components/base/BaseAlert.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const toast = useToast()
const { message, fieldError, setErrors, clearErrors } = useApiErrors()

const form = ref({ email: '', password: '' })
const loading = ref(false)

const isDemo = import.meta.env.VITE_DEMO === 'true'
const demoAccounts = [
  { label: 'Najemca', email: 'user@example.com' },
  { label: 'Właściciel', email: 'owner@example.com' },
  { label: 'Administrator', email: 'admin@example.com' },
]

function loginAs(account) {
  form.value = { email: account.email, password: 'password' }
  handleSubmit()
}

async function handleSubmit() {
  clearErrors()
  loading.value = true
  try {
    const { data } = await authService.login(form.value)
    auth.setToken(data.data.token)
    auth.user = data.data.user
    toast.success('Zalogowano pomyślnie.')
    const redirect = route.query.redirect
    if (redirect) return router.push(redirect)
    if (auth.isAdmin)  return router.push('/admin/dashboard')
    if (auth.isOwner)  return router.push('/wlasciciel/dashboard')
    router.push('/panel/dashboard')
  } catch (e) {
    setErrors(e)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <RouterLink to="/" class="text-2xl font-bold text-primary-600">NajmujMieszkanie</RouterLink>
        <h1 class="mt-4 text-2xl font-bold text-gray-900">Zaloguj się</h1>
        <p class="mt-1 text-sm text-gray-500">
          Nie masz konta?
          <RouterLink to="/auth/rejestracja" class="text-primary-600 hover:underline font-medium">Zarejestruj się</RouterLink>
        </p>
      </div>

      <div v-if="isDemo" class="card p-4 mb-4 bg-primary-50 border-primary-100">
        <p class="text-sm font-medium text-primary-800 mb-2">Wersja demo – zaloguj się kontem testowym:</p>
        <div class="flex flex-wrap gap-2">
          <BaseButton
            v-for="account in demoAccounts"
            :key="account.email"
            variant="secondary"
            size="sm"
            :disabled="loading"
            @click="loginAs(account)"
          >{{ account.label }}</BaseButton>
        </div>
      </div>

      <div class="card p-8">
        <BaseAlert v-if="message" type="error" :message="message" class="mb-4" />

        <form @submit.prevent="handleSubmit" class="space-y-5">
          <BaseInput
            v-model="form.email"
            label="Adres e-mail"
            type="email"
            placeholder="jan@example.com"
            :error="fieldError('email')"
            required
          />
          <div>
            <BaseInput
              v-model="form.password"
              label="Hasło"
              type="password"
              placeholder="••••••••"
              :error="fieldError('password')"
              required
            />
            <div class="mt-1 text-right">
              <RouterLink to="/auth/reset-hasla" class="text-xs text-primary-600 hover:underline">
                Zapomniałeś hasła?
              </RouterLink>
            </div>
          </div>

          <BaseButton type="submit" class="w-full" :loading="loading">
            Zaloguj się
          </BaseButton>
        </form>
      </div>
    </div>
  </div>
</template>
