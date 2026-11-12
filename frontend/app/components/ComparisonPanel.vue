<script setup lang="ts">
import type { Comparison } from '~/utils/dashboard'
import { formatNumber, formatSigned } from '~/utils/dashboard'

const props = defineProps<{
  comparison: Comparison | null
  unassignableTaskIds: number[]
}>()

const sides = computed(() =>
  props.comparison
    ? [
        { key: 'cost', title: 'Min Cost', data: props.comparison.cost },
        { key: 'makespan', title: 'Min Makespan', data: props.comparison.makespan },
      ]
    : [],
)
</script>

<template>
  <InfeasibleState v-if="comparison === null" :unassignable-task-ids="unassignableTaskIds" />

  <section v-else class="space-y-4">
    <div class="grid gap-3 md:grid-cols-2">
      <article v-for="side in sides" :key="side.key" class="rounded-xl border border-slate-200 bg-white p-4">
        <h2 class="font-semibold text-slate-900">{{ side.title }}</h2>
        <dl class="mt-3 grid grid-cols-3 gap-2 text-sm">
          <div>
            <dt class="text-slate-500">Total price</dt>
            <dd class="text-xl font-semibold tabular-nums text-slate-900">{{ formatNumber(side.data.totalPrice) }}</dd>
          </div>
          <div>
            <dt class="text-slate-500">Makespan</dt>
            <dd class="text-xl font-semibold tabular-nums text-slate-900">{{ side.data.makespan }} h</dd>
          </div>
          <div>
            <dt class="text-slate-500">Employees used</dt>
            <dd class="text-xl font-semibold tabular-nums text-slate-900">{{ side.data.activeEmployees }}</dd>
          </div>
        </dl>
      </article>
    </div>

    <div class="grid gap-3 md:grid-cols-2">
      <div class="rounded-xl border border-slate-200 bg-white p-4">
        <p class="text-sm text-slate-500">Price difference (Makespan − Cost)</p>
        <p class="mt-1 text-2xl font-semibold tabular-nums" :class="comparison.priceDifference > 0 ? 'text-rose-600' : 'text-emerald-600'">
          {{ formatSigned(comparison.priceDifference) }}
          <span class="text-base font-normal">({{ formatSigned(comparison.priceDifferencePercent) }}%)</span>
        </p>
        <p class="mt-1 text-xs text-slate-500">What finishing earlier costs.</p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-white p-4">
        <p class="text-sm text-slate-500">Makespan difference (Cost − Makespan)</p>
        <p class="mt-1 text-2xl font-semibold tabular-nums" :class="comparison.hoursSaved > 0 ? 'text-emerald-600' : 'text-slate-700'">
          {{ formatSigned(comparison.hoursSaved) }} h
          <span class="text-base font-normal">({{ formatSigned(comparison.hoursSavedPercent) }}%)</span>
        </p>
        <p class="mt-1 text-xs text-slate-500">How much earlier everything is done with Min Makespan.</p>
      </div>
    </div>
  </section>
</template>
