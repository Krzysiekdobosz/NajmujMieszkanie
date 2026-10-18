<script setup>
defineProps({
  modelValue: { default: '' },
  label:      { type: String, default: '' },
  options:    { type: Array, default: () => [] },
  placeholder:{ type: String, default: 'Wybierz...' },
  error:      { type: String, default: '' },
  required:   { type: Boolean, default: false },
  disabled:   { type: Boolean, default: false },
  id:         { type: String, default: () => `select-${Math.random().toString(36).slice(2)}` },
})

defineEmits(['update:modelValue'])
</script>

<template>
  <div>
    <label v-if="label" :for="id" class="form-label">
      {{ label }} <span v-if="required" class="text-red-500">*</span>
    </label>
    <select
      :id="id"
      :value="modelValue"
      :disabled="disabled"
      :class="['form-input', error ? 'form-input-error' : '']"
      @change="$emit('update:modelValue', $event.target.value)"
    >
      <!-- Gdy lista ma własną opcję z pustą wartością (np. „Wszystkie”), placeholder byłby zdublowany -->
      <option v-if="!options.some(opt => (opt.value ?? opt) === '')" value="" disabled>{{ placeholder }}</option>
      <option v-for="opt in options" :key="opt.value ?? opt" :value="opt.value ?? opt">
        {{ opt.label ?? opt }}
      </option>
    </select>
    <p v-if="error" class="form-error">{{ error }}</p>
  </div>
</template>
