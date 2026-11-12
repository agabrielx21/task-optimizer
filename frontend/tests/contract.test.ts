import { describe, expect, it } from 'vitest'
import { isOptimizationResult, isOptimizeRequest } from '#shared/utils/contract'
import employees from '#shared/data/employees.json'
import tasks from '#shared/data/tasks.json'
import mock from '../server/mock/mock-results.json'

// mock-results.json is generated from the real backend (npm run mock:update), so this test
// fails as soon as the PHP response shape and the TypeScript contract drift apart.
describe('API contract', () => {
  it.each(['cost', 'makespan'] as const)('real backend output for %s matches the contract', (variant) => {
    expect(isOptimizationResult(mock[variant])).toBe(true)
  })

  it('accepts the infeasible shape (empty meta object)', () => {
    expect(isOptimizationResult({
      variant: 'makespan',
      status: 'infeasible',
      assignments: [],
      totalPrice: 0,
      makespan: 0,
      employeeSummary: [],
      unassignableTaskIds: [7, 9],
      meta: {},
    })).toBe(true)
  })

  it.each([
    ['missing field', { ...mock.cost, makespan: undefined }],
    ['unknown status', { ...mock.cost, status: 'ok' }],
    ['string id', { ...mock.cost, assignments: [{ taskId: '1', employeeId: 8 }] }],
    ['meta as list (PHP empty array)', { ...mock.cost, meta: [] }],
    ['not an object', null],
  ])('rejects %s', (_label, value) => {
    expect(isOptimizationResult(value)).toBe(false)
  })
})

describe('default-data contract (GET/PUT /api/data)', () => {
  it('accepts the data files', () => {
    expect(isOptimizeRequest({ employees, tasks })).toBe(true)
  })

  it.each([
    ['missing tasks', { employees }],
    ['string rate', { employees: [{ employeeId: 1, skillLevel: 1, hourlyRate: '10' }], tasks: [] }],
    ['fractional estimation', { employees: [], tasks: [{ taskId: 1, skillLevel: 1, estimation: 1.5 }] }],
  ])('rejects %s', (_label, value) => {
    expect(isOptimizeRequest(value)).toBe(false)
  })
})
