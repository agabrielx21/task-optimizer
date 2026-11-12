<script setup lang="ts">
export type View = 'cost' | 'makespan' | 'compare'

const view = defineModel<View>({ required: true })

const tabs: { value: View, label: string, hint: string }[] = [
  { value: 'cost', label: 'Min Cost', hint: 'Variant A' },
  { value: 'makespan', label: 'Min Makespan', hint: 'Variant B' },
  { value: 'compare', label: 'Compare', hint: 'A vs B' },
]
</script>

<template>
  <div role="tablist" aria-label="Optimization variant" class="inline-flex rounded-lg bg-slate-200/70 p-1">
    <button
      v-for="tab in tabs"
      :key="tab.value"
      type="button"
      role="tab"
      :aria-selected="view === tab.value"
      class="rounded-md px-4 py-2 text-sm font-medium transition-colors"
      :class="view === tab.value
        ? 'bg-white text-slate-900 shadow-sm'
        : 'text-slate-600 hover:text-slate-900'"
      @click="view = tab.value"
    >
      {{ tab.label }}
      <span class="ml-1 hidden text-xs font-normal text-slate-400 sm:inline">{{ tab.hint }}</span>
    </button>
  </div>
</template>
