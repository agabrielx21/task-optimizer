<script setup lang="ts">
defineProps<{
  unassignableTaskIds: number[]
}>()
</script>

<template>
  <section class="rounded-xl border border-amber-300 bg-amber-50 p-5" role="alert">
    <h2 class="font-semibold text-amber-900">No feasible assignment</h2>
    <p class="mt-1 text-sm text-amber-800">
      {{ unassignableTaskIds.length === 1
        ? 'This task requires a higher skill level than any employee has, so it cannot be assigned:'
        : 'These tasks require a higher skill level than any employee has, so they cannot be assigned:' }}
    </p>
    <ul class="mt-3 flex flex-wrap gap-2">
      <li
        v-for="taskId in unassignableTaskIds"
        :key="taskId"
        class="rounded-md bg-white px-2.5 py-1 text-sm font-medium text-amber-900 ring-1 ring-amber-300 tabular-nums"
      >
        Task #{{ taskId }}
      </li>
    </ul>
  </section>
</template>
