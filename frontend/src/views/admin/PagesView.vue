<script setup>
import { ref, reactive, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useToast } from 'vue-toastification'
import { adminService } from '@/services/adminService'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import BaseBadge from '@/components/base/BaseBadge.vue'
import BaseModal from '@/components/base/BaseModal.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseInput from '@/components/base/BaseInput.vue'
import BaseSelect from '@/components/base/BaseSelect.vue'
import BaseTextarea from '@/components/base/BaseTextarea.vue'

const toast = useToast()
const pages = ref([])
const loading = ref(false)
const editing = ref(null)
const saving = ref(false)
const preview = ref(false)

const form = reactive({ title: '', content: '', meta_title: '', meta_description: '', status: 'published' })

const statusOptions = [
  { value: 'published', label: 'Opublikowana' },
  { value: 'draft', label: 'Szkic' },
]

async function fetch() {
  loading.value = true
  try {
    const { data } = await adminService.pages()
    pages.value = data.data
  } finally {
    loading.value = false
  }
}

async function edit(page) {
  const { data } = await adminService.pageShow(page.id)
  const { title, content, meta_title, meta_description, status } = data.data
  Object.assign(form, { title, content, meta_title: meta_title ?? '', meta_description: meta_description ?? '', status })
  preview.value = false
  editing.value = data.data
}

async function save() {
  saving.value = true
  try {
    await adminService.pageUpdate(editing.value.id, form)
    toast.success('Strona zapisana.')
    editing.value = null
    fetch()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Nie udało się zapisać strony.')
  } finally {
    saving.value = false
  }
}

onMounted(fetch)
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Strony statyczne</h1>
    <p class="text-gray-500 mb-6">Treść stron „O nas”, „FAQ”, „Regulamin” i pozostałych.</p>

    <div v-if="loading" class="flex justify-center py-12"><BaseSpinner /></div>

    <div v-else class="card divide-y divide-gray-100">
      <div v-for="page in pages" :key="page.id" class="p-4 flex items-center gap-4">
        <div class="flex-1 min-w-0">
          <p class="font-medium text-gray-900">{{ page.title }}</p>
          <RouterLink :to="`/${page.slug}`" class="text-sm text-primary-600 hover:underline" target="_blank">/{{ page.slug }}</RouterLink>
        </div>
        <BaseBadge :variant="page.status === 'published' ? 'green' : 'gray'">
          {{ page.status === 'published' ? 'Opublikowana' : 'Szkic' }}
        </BaseBadge>
        <BaseButton variant="secondary" size="sm" @click="edit(page)">Edytuj</BaseButton>
      </div>
    </div>

    <BaseModal :show="!!editing" :title="`Edycja: ${editing?.title ?? ''}`" size="xl" @close="editing = null">
      <form id="page-form" class="space-y-4" @submit.prevent="save">
        <div class="grid sm:grid-cols-3 gap-4">
          <BaseInput v-model="form.title" label="Tytuł" class="sm:col-span-2" required />
          <BaseSelect v-model="form.status" label="Status" :options="statusOptions" />
        </div>

        <div>
          <div class="flex items-center justify-between mb-1">
            <span class="form-label mb-0">Treść (HTML)</span>
            <button type="button" class="text-sm text-primary-600 hover:underline" @click="preview = !preview">
              {{ preview ? 'Edytuj kod' : 'Podgląd' }}
            </button>
          </div>
          <div v-if="preview" class="page-content border border-gray-200 rounded-lg p-4 max-h-96 overflow-y-auto" v-html="form.content" />
          <BaseTextarea v-else v-model="form.content" :rows="14" class="font-mono" />
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <BaseInput v-model="form.meta_title" label="Meta title (SEO)" />
          <BaseInput v-model="form.meta_description" label="Meta description (SEO)" />
        </div>
      </form>
      <template #footer>
        <BaseButton variant="secondary" @click="editing = null">Anuluj</BaseButton>
        <BaseButton type="submit" form="page-form" :loading="saving">Zapisz</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
