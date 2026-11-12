import type { OptimizationResult, OptimizeRequest } from '../types/api'

const isInt = (value: unknown): value is number => Number.isInteger(value)
const isNumber = (value: unknown): value is number => typeof value === 'number' && Number.isFinite(value)
const isObject = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null && !Array.isArray(value)

// Types vanish at runtime: this checks every real backend response.
export function isOptimizationResult(value: unknown): value is OptimizationResult {
  if (!isObject(value)) return false

  return (value.variant === 'cost' || value.variant === 'makespan')
    && (value.status === 'feasible' || value.status === 'infeasible')
    && Array.isArray(value.assignments)
    && value.assignments.every(a => isObject(a) && isInt(a.taskId) && isInt(a.employeeId))
    && isNumber(value.totalPrice)
    && isInt(value.makespan)
    && Array.isArray(value.employeeSummary)
    && value.employeeSummary.every(s => isObject(s) && isInt(s.employeeId) && isInt(s.totalAssignedHours))
    && Array.isArray(value.unassignableTaskIds)
    && value.unassignableTaskIds.every(isInt)
    && isObject(value.meta)
}

export function isOptimizeRequest(value: unknown): value is OptimizeRequest {
  if (!isObject(value)) return false

  return Array.isArray(value.employees)
    && value.employees.every(e => isObject(e) && isInt(e.employeeId) && isInt(e.skillLevel) && isNumber(e.hourlyRate))
    && Array.isArray(value.tasks)
    && value.tasks.every(t => isObject(t) && isInt(t.taskId) && isInt(t.skillLevel) && isInt(t.estimation))
}
