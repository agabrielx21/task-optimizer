import { describe, expect, it } from 'vitest'
import type { OptimizationResult, OptimizeRequest, Variant } from '#shared/types/api'
import employees from '#shared/data/employees.json'
import tasks from '#shared/data/tasks.json'
import mock from '../server/mock/mock-results.json'
import {
  compareVariants,
  employeeOptions,
  filterRows,
  ganttLanes,
  joinAssignments,
  sumCost,
  sumHours,
  timeTicks,
  workloadBars,
} from '~/utils/dashboard'

const input: OptimizeRequest = { employees, tasks }
const results = mock as Record<Variant, OptimizationResult>

const infeasible: OptimizationResult = {
  variant: 'cost',
  status: 'infeasible',
  assignments: [],
  totalPrice: 0,
  makespan: 0,
  employeeSummary: [],
  unassignableTaskIds: [31],
  meta: {},
}

describe('joinAssignments (data join + cost calculation)', () => {
  it.each(['cost', 'makespan'] as const)('%s: one row per task, cost = hourlyRate × hours, totals match the backend', (variant) => {
    const rows = joinAssignments(results[variant], input)

    expect(rows).toHaveLength(30)
    expect(rows.map(r => r.taskId)).toEqual(tasks.map(t => t.taskId)) // sorted by task id
    for (const row of rows) {
      expect(row.cost).toBe(row.hourlyRate * row.hours)
      expect(row.employeeSkill).toBeGreaterThanOrEqual(row.requiredSkill)
    }
    expect(sumCost(rows)).toBe(results[variant].totalPrice)
    expect(sumHours(rows)).toBe(78)
  })

  it('joins the right employee and task fields', () => {
    const row = joinAssignments(results.cost, input).find(r => r.taskId === 19)!

    // task 19: skill 2, 6 h -> cost variant gives it to employee 4 (skill 3, 300/h)
    expect(row).toEqual({ taskId: 19, requiredSkill: 2, hours: 6, employeeId: 4, employeeSkill: 3, hourlyRate: 300, cost: 1800 })
  })

  it('throws when the result does not belong to the input', () => {
    const otherInput: OptimizeRequest = { employees, tasks: tasks.slice(0, 5) }

    expect(() => joinAssignments(results.cost, otherInput)).toThrow(/does not match the input/)
  })
})

describe('filterRows', () => {
  const rows = joinAssignments(results.makespan, input)

  it('null keeps everything, an id keeps that employee only', () => {
    expect(filterRows(rows, null)).toHaveLength(30)
    expect(filterRows(rows, 2).map(r => r.taskId)).toEqual([19, 24, 25])
    expect(sumHours(filterRows(rows, 2))).toBe(11)
  })
})

describe('workloadBars', () => {
  it('cost variant: employee 4 is critical (51 h), 8 employees idle', () => {
    const bars = workloadBars(results.cost)

    expect(bars).toHaveLength(10)
    expect(bars.find(b => b.employeeId === 4)).toMatchObject({ hours: 51, percent: 100, isCritical: true, isIdle: false })
    expect(bars.find(b => b.employeeId === 8)!.percent).toBeCloseTo((27 / 51) * 100)
    expect(bars.filter(b => b.isIdle).map(b => b.employeeId)).toEqual([1, 2, 3, 5, 6, 7, 9, 10])
  })

  it('makespan variant: only employee 2 reaches 11 h', () => {
    expect(workloadBars(results.makespan).filter(b => b.isCritical).map(b => b.employeeId)).toEqual([2])
  })
})

describe('ganttLanes', () => {
  it('blocks are back to back from 0 and end at the employee load', () => {
    const lanes = ganttLanes(results.makespan, joinAssignments(results.makespan, input))
    const lane = lanes.find(l => l.employeeId === 2)!

    expect(lane.blocks.map(b => [b.taskId, b.start, b.end])).toEqual([[19, 0, 6], [24, 6, 8], [25, 8, 11]])
    for (const l of lanes) {
      const last = l.blocks.at(-1)
      expect(last?.end ?? 0).toBe(l.hours)
    }
  })
})

describe('compareVariants', () => {
  it('price and makespan difference', () => {
    const comparison = compareVariants(results.cost, results.makespan)!

    expect(comparison.priceDifference).toBe(26400 - 17460)
    expect(comparison.hoursSaved).toBe(51 - 11)
    expect(comparison.priceDifferencePercent).toBeCloseTo(51.2, 1)
    expect(comparison.cost.activeEmployees).toBe(2)
    expect(comparison.makespan.activeEmployees).toBe(10)
  })

  it('null when a variant is infeasible', () => {
    expect(compareVariants(infeasible, results.makespan)).toBeNull()
  })
})

describe('timeTicks', () => {
  it('hourly up to 24 h, coarser above, always ending at the makespan', () => {
    expect(timeTicks(11)).toEqual([0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11])
    expect(timeTicks(51)).toEqual([0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 51]) // no 50 crammed next to 51
    expect(timeTicks(0)).toEqual([0])
  })
})

describe('employeeOptions', () => {
  it('lists every employee with its task count', () => {
    const options = employeeOptions(input, joinAssignments(results.cost, input))

    expect(options).toHaveLength(10)
    expect(options.find(o => o.employeeId === 4)!.taskCount).toBe(20) // all 20 skill-2/3 tasks
    expect(options.find(o => o.employeeId === 8)!.taskCount).toBe(10) // all 10 skill-1 tasks
    expect(options.find(o => o.employeeId === 1)!.taskCount).toBe(0)
  })
})
