<script setup lang="ts">
import type { WorkloadBar } from '~/utils/dashboard'

defineProps<{
  bars: WorkloadBar[]
  makespan: number
}>()
</script>

<template>
  <section class="rounded-xl border border-slate-200 bg-white p-4">
    <header class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
      <h2 class="font-semibold text-slate-900">Employee workload</h2>
      <p class="flex items-center gap-3 text-xs text-slate-500">
        <span class="flex items-center gap-1"><span class="size-2.5 rounded-sm bg-rose-500" />= makespan ({{ makespan }} h)</span>
        <span class="flex items-center gap-1"><span class="size-2.5 rounded-sm bg-indigo-400" />assigned</span>
        <span class="flex items-center gap-1"><span class="size-2.5 rounded-sm bg-slate-200" />idle</span>
      </p>
    </header>

    <ul class="space-y-1.5">
      <li v-for="bar in bars" :key="bar.employeeId" class="flex items-center gap-3 text-sm">
        <span class="w-24 shrink-0" :class="bar.isIdle ? 'text-slate-400' : 'text-slate-700'">
          Employee {{ bar.employeeId }}
        </span>
        <div class="h-5 flex-1 rounded bg-slate-100">
          <div
            v-if="!bar.isIdle"
            class="h-full rounded"
            :class="bar.isCritical ? 'bg-rose-500' : 'bg-indigo-400'"
            :style="{ width: `${bar.percent}%` }"
          />
        </div>
        <span
          class="w-16 shrink-0 text-right tabular-nums"
          :class="bar.isIdle ? 'text-slate-400' : bar.isCritical ? 'font-semibold text-rose-600' : 'text-slate-700'"
        >
          {{ bar.isIdle ? 'idle' : `${bar.hours} h` }}
        </span>
      </li>
    </ul>
  </section>
</template>
