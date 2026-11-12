<script setup lang="ts">
import type { DisplayError } from '~/utils/errors'

defineProps<{ error: DisplayError }>()
defineEmits<{ retry: [] }>()
</script>

<template>
  <section class="rounded-xl border border-rose-300 bg-rose-50 p-5" role="alert">
    <h2 class="font-semibold text-rose-900">{{ error.title }} <span class="font-normal text-rose-700">({{ error.statusCode }})</span></h2>
    <p class="mt-1 text-sm text-rose-800">{{ error.message }}</p>
    <ul v-if="error.details.length > 0" class="mt-2 list-inside list-disc text-sm text-rose-800">
      <li v-for="detail in error.details" :key="detail"><code>{{ detail }}</code></li>
    </ul>
    <p v-if="error.statusCode === 400" class="mt-3 text-sm text-rose-800">Fix the input above and run the optimization again.</p>
    <button
      v-else
      type="button"
      class="mt-4 rounded-md bg-rose-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-rose-700"
      @click="$emit('retry')"
    >
      Try again
    </button>
  </section>
</template>
