<script setup lang="ts">
import type { OptimizationMeta } from '#shared/types/api'
import { formatNumber } from '~/utils/dashboard'

defineProps<{
  totalPrice: number
  makespan: number
  taskCount: number
  activeEmployees: number
  totalEmployees: number
  meta: OptimizationMeta
}>()
</script>

<template>
  <div>
    <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
      <div class="rounded-xl border border-slate-200 bg-white p-4">
        <dt class="text-sm text-slate-500">Total price</dt>
        <dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{{ formatNumber(totalPrice) }}</dd>
      </div>
      <div class="rounded-xl border border-slate-200 bg-white p-4">
        <dt class="text-sm text-slate-500">Makespan</dt>
        <dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">
          {{ formatNumber(makespan) }}<span class="ml-1 text-base font-normal text-slate-500">h</span>
        </dd>
      </div>
      <div class="rounded-xl border border-slate-200 bg-white p-4">
        <dt class="text-sm text-slate-500">Tasks</dt>
        <dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{{ taskCount }}</dd>
      </div>
      <div class="rounded-xl border border-slate-200 bg-white p-4">
        <dt class="text-sm text-slate-500">Employees with work</dt>
        <dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">
          {{ activeEmployees }}<span class="ml-1 text-base font-normal text-slate-500">/ {{ totalEmployees }}</span>
        </dd>
      </div>
    </dl>

    <p v-if="meta.lowerBound !== undefined" class="mt-2 text-xs text-slate-500">
      <span v-if="meta.isOptimal" class="font-medium text-emerald-700">Proven optimal</span>
      <span v-else class="font-medium text-amber-700">Best found, optimality not proven</span>
      · lower bound {{ meta.lowerBound }} h · {{ formatNumber(meta.searchNodes ?? 0) }} search nodes
    </p>
    <p v-else-if="meta.isOptimal" class="mt-2 text-xs font-medium text-emerald-700">Optimal (exact algorithm)</p>
  </div>
</template>
