import type { Employee, OptimizationResult, OptimizeRequest, Task } from '#shared/types/api'

// Pure functions behind useDashboard (unit-tested in tests/dashboard.test.ts).
export interface AssignmentRow {
  taskId: number
  requiredSkill: number
  hours: number
  employeeId: number
  employeeSkill: number
  hourlyRate: number
  cost: number
}

export interface WorkloadBar {
  employeeId: number
  hours: number
  percent: number
  isCritical: boolean
  isIdle: boolean
}

export interface GanttBlock {
  taskId: number
  start: number
  end: number
  leftPercent: number
  widthPercent: number
}

export interface GanttLane {
  employeeId: number
  hours: number
  isCritical: boolean
  blocks: GanttBlock[]
}

export interface VariantFigures {
  totalPrice: number
  makespan: number
  activeEmployees: number
}

export interface Comparison {
  cost: VariantFigures
  makespan: VariantFigures
  priceDifference: number
  priceDifferencePercent: number
  hoursSaved: number
  hoursSavedPercent: number
}

export interface EmployeeOption {
  employeeId: number
  taskCount: number
}

// Throws if the result references an id that is not in the input (results and input from different runs).
export function joinAssignments(result: OptimizationResult, input: OptimizeRequest): AssignmentRow[] {
  const tasks = new Map<number, Task>(input.tasks.map(t => [t.taskId, t]))
  const employees = new Map<number, Employee>(input.employees.map(e => [e.employeeId, e]))

  return result.assignments
    .map(({ taskId, employeeId }) => {
      const task = tasks.get(taskId)
      const employee = employees.get(employeeId)
      if (!task || !employee) {
        throw new Error(`Assignment ${taskId} → ${employeeId} does not match the input data`)
      }
      return {
        taskId,
        requiredSkill: task.skillLevel,
        hours: task.estimation,
        employeeId,
        employeeSkill: employee.skillLevel,
        hourlyRate: employee.hourlyRate,
        cost: employee.hourlyRate * task.estimation,
      }
    })
    .sort((a, b) => a.taskId - b.taskId)
}

export function filterRows(rows: AssignmentRow[], employeeId: number | null): AssignmentRow[] {
  return employeeId === null ? rows : rows.filter(row => row.employeeId === employeeId)
}

export function sumCost(rows: AssignmentRow[]): number {
  return rows.reduce((total, row) => total + row.cost, 0)
}

export function sumHours(rows: AssignmentRow[]): number {
  return rows.reduce((total, row) => total + row.hours, 0)
}

export function figures(result: OptimizationResult): VariantFigures {
  return {
    totalPrice: result.totalPrice,
    makespan: result.makespan,
    activeEmployees: result.employeeSummary.filter(s => s.totalAssignedHours > 0).length,
  }
}

export function workloadBars(result: OptimizationResult): WorkloadBar[] {
  return [...result.employeeSummary]
    .sort((a, b) => a.employeeId - b.employeeId)
    .map(({ employeeId, totalAssignedHours: hours }) => ({
      employeeId,
      hours,
      percent: result.makespan > 0 ? (hours / result.makespan) * 100 : 0,
      isCritical: hours > 0 && hours === result.makespan,
      isIdle: hours === 0,
    }))
}

// Tasks back to back from t = 0 in taskId order (the order doesn't change the makespan).
export function ganttLanes(result: OptimizationResult, rows: AssignmentRow[]): GanttLane[] {
  const span = result.makespan

  return workloadBars(result).map(({ employeeId, hours, isCritical }) => {
    let clock = 0
    const blocks = rows
      .filter(row => row.employeeId === employeeId)
      .map((row) => {
        const block = {
          taskId: row.taskId,
          start: clock,
          end: clock + row.hours,
          leftPercent: span > 0 ? (clock / span) * 100 : 0,
          widthPercent: span > 0 ? (row.hours / span) * 100 : 0,
        }
        clock += row.hours
        return block
      })
    return { employeeId, hours, isCritical, blocks }
  })
}

export function timeTicks(makespan: number): number[] {
  if (makespan <= 0) return [0]
  const step = makespan <= 24 ? 1 : Math.ceil(makespan / 12)
  const ticks: number[] = []
  for (let t = 0; makespan - t >= step / 2; t += step) ticks.push(t)
  ticks.push(makespan)
  return ticks
}

export function compareVariants(cost: OptimizationResult, makespan: OptimizationResult): Comparison | null {
  if (cost.status !== 'feasible' || makespan.status !== 'feasible') return null

  const a = figures(cost)
  const b = figures(makespan)
  const priceDifference = b.totalPrice - a.totalPrice
  const hoursSaved = a.makespan - b.makespan

  return {
    cost: a,
    makespan: b,
    priceDifference,
    priceDifferencePercent: a.totalPrice > 0 ? (priceDifference / a.totalPrice) * 100 : 0,
    hoursSaved,
    hoursSavedPercent: a.makespan > 0 ? (hoursSaved / a.makespan) * 100 : 0,
  }
}

export function employeeOptions(input: OptimizeRequest, rows: AssignmentRow[]): EmployeeOption[] {
  return [...input.employees]
    .sort((a, b) => a.employeeId - b.employeeId)
    .map(({ employeeId }) => ({
      employeeId,
      taskCount: rows.filter(row => row.employeeId === employeeId).length,
    }))
}

// Fixed locale: server and browser render the same text (no hydration mismatch).
const numberFormat = new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 })

export function formatNumber(value: number): string {
  return numberFormat.format(value)
}

export function formatSigned(value: number): string {
  return (value > 0 ? '+' : value < 0 ? '−' : '±') + numberFormat.format(Math.abs(value))
}
