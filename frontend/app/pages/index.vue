<script setup lang="ts">
import type { DataResponse, OptimizeResponse, OptimizeRequest, Variant } from '#shared/types/api'
import defaultEmployees from '#shared/data/employees.json'
import defaultTasks from '#shared/data/tasks.json'
import type { View } from '~/components/VariantTabs.vue'
import { describeError } from '~/utils/errors'
import type { SaveState } from '~/utils/inputEditor'

const BUNDLED_INPUT: OptimizeRequest = { employees: defaultEmployees, tasks: defaultTasks }

const route = useRoute()
const router = useRouter()

const view = computed<View>({
  get: () => {
    const value = route.query.variant
    return value === 'makespan' || value === 'compare' ? value : 'cost'
  },
  set: variant => router.replace({ query: { ...route.query, variant } }),
})
const variant = computed<Variant>(() => (view.value === 'makespan' ? 'makespan' : 'cost'))

// Default data = the backend's data/*.json (bundled copy only as a fallback).
const { data: saved } = await useFetch<DataResponse>('/api/data')
const defaultInput = computed<OptimizeRequest>(() => saved.value?.input ?? BUNDLED_INPUT)

const input = useState<OptimizeRequest>('optimize-input', () => structuredClone(defaultInput.value))

// body is reactive: a new input from the editor re-runs the request.
const { data, status, error, refresh } = await useFetch<OptimizeResponse>('/api/optimize', {
  method: 'POST',
  body: input,
})

const isFirstLoad = computed(() => status.value === 'pending' && !data.value)
const isRefreshing = computed(() => status.value === 'pending' && !!data.value)
const displayError = computed(() => (error.value ? describeError(error.value) : null))
const isMock = computed(() => data.value?.source === 'mock' || saved.value?.source === 'mock')

const saveState = ref<SaveState>({ status: 'idle' })

async function saveDefaults(next: OptimizeRequest) {
  saveState.value = { status: 'saving' }
  try {
    const response = await $fetch<DataResponse>('/api/data', { method: 'PUT', body: next })
    saved.value = response
    input.value = response.input
    saveState.value = { status: 'saved' }
  }
  catch (e) {
    const error = describeError(e)
    saveState.value = { status: 'error', message: `${error.title}: ${error.message}`, details: error.details }
  }
}

const employeeFilter = ref<number | null>(null)
const dashboard = useDashboard(data, variant, employeeFilter)
const { result, isInfeasible, filteredRows, filteredTotals, summary, bars, lanes, options, comparison } = dashboard
const totalEmployees = computed(() => dashboard.input.value?.employees.length ?? 0)
</script>

<template>
  <div class="min-h-screen bg-slate-100 text-slate-800">
    <div class="mx-auto max-w-6xl space-y-5 px-4 py-6 sm:px-6">
      <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 class="text-2xl font-semibold text-slate-900">Task Assignment Optimizer</h1>
          <p class="text-sm text-slate-500">Assign tasks to employees by minimum total cost or minimum completion time.</p>
        </div>
        <span
          v-if="data"
          class="rounded-full px-2.5 py-1 text-xs font-medium"
          :class="isMock ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
        >
          {{ isMock ? 'Mock data' : 'Live backend' }}
        </span>
      </header>

      <InputEditor
        v-model="input"
        :default-input="defaultInput"
        :is-mock="isMock"
        :save-state="saveState"
        @save="saveDefaults"
      />

      <div class="flex flex-wrap items-center gap-3">
        <VariantTabs v-model="view" />
        <span v-if="isRefreshing" class="text-sm text-slate-500">Updating…</span>
      </div>

      <LoadingState v-if="isFirstLoad" />

      <ErrorState v-else-if="displayError" :error="displayError" @retry="refresh()" />

      <template v-else-if="data && result">
        <div :class="{ 'opacity-60 transition-opacity': isRefreshing }" class="space-y-5">
          <ComparisonPanel
            v-if="view === 'compare'"
            :comparison="comparison"
            :unassignable-task-ids="data.results.cost.unassignableTaskIds"
          />

          <InfeasibleState v-else-if="isInfeasible" :unassignable-task-ids="result.unassignableTaskIds" />

          <template v-else-if="summary">
            <SummaryCards v-bind="summary" :total-employees="totalEmployees" :meta="result.meta" />
            <WorkloadChart :bars="bars" :makespan="result.makespan" />
            <GanttTimeline :lanes="lanes" :makespan="result.makespan" />
            <AssignmentsTable v-model="employeeFilter" :rows="filteredRows" :options="options" :totals="filteredTotals" />
          </template>
        </div>
      </template>
    </div>
  </div>
</template>
