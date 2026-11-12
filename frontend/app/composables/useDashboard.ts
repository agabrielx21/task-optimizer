import type { Ref } from 'vue'
import type { OptimizeResponse, Variant } from '#shared/types/api'
import {
  compareVariants,
  employeeOptions,
  figures,
  filterRows,
  ganttLanes,
  joinAssignments,
  sumCost,
  sumHours,
  workloadBars,
} from '~/utils/dashboard'

export function useDashboard(
  response: Ref<OptimizeResponse | null | undefined>,
  variant: Ref<Variant>,
  employeeFilter: Ref<number | null>,
) {
  const result = computed(() => response.value?.results[variant.value] ?? null)
  const input = computed(() => response.value?.input ?? null)

  const isInfeasible = computed(() => result.value?.status === 'infeasible')

  const rows = computed(() =>
    result.value && input.value && !isInfeasible.value ? joinAssignments(result.value, input.value) : [],
  )
  const filteredRows = computed(() => filterRows(rows.value, employeeFilter.value))

  const filteredTotals = computed(() => ({
    hours: sumHours(filteredRows.value),
    cost: sumCost(filteredRows.value),
  }))

  const summary = computed(() =>
    result.value && !isInfeasible.value
      ? { ...figures(result.value), taskCount: result.value.assignments.length }
      : null,
  )

  const bars = computed(() => (result.value && !isInfeasible.value ? workloadBars(result.value) : []))
  const lanes = computed(() => (result.value && !isInfeasible.value ? ganttLanes(result.value, rows.value) : []))

  const options = computed(() => (input.value ? employeeOptions(input.value, rows.value) : []))

  const comparison = computed(() =>
    response.value ? compareVariants(response.value.results.cost, response.value.results.makespan) : null,
  )

  return {
    result,
    input,
    isInfeasible,
    rows,
    filteredRows,
    filteredTotals,
    summary,
    bars,
    lanes,
    options,
    comparison,
  }
}
