import { describe, expect, it } from 'vitest'
import { parseInput } from '~/utils/inputEditor'
import { describeError } from '~/utils/errors'

describe('parseInput', () => {
  it('accepts two JSON arrays', () => {
    const result = parseInput('[{"employeeId":1,"skillLevel":1,"hourlyRate":10}]', '[]')

    expect(result).toEqual({ ok: true, input: { employees: [{ employeeId: 1, skillLevel: 1, hourlyRate: 10 }], tasks: [] } })
  })

  it('reports invalid JSON and non-arrays for both fields', () => {
    const result = parseInput('not json', '{"taskId": 1}')

    expect(result.ok).toBe(false)
    expect(!result.ok && result.errors).toEqual([
      expect.stringMatching(/^employees: invalid JSON/),
      'tasks: must be a JSON array',
    ])
  })
})

describe('describeError', () => {
  it('reads the server route error payload (backend 400 with details)', () => {
    const error = describeError({
      statusCode: 400,
      data: { statusMessage: 'Invalid input', message: 'The backend rejected the input.', data: { details: ['tasks[0].estimation must be an integer >= 1'] } },
    })

    expect(error).toEqual({
      statusCode: 400,
      title: 'Invalid input',
      message: 'The backend rejected the input.',
      details: ['tasks[0].estimation must be an integer >= 1'],
    })
  })

  it('falls back to generic text when there is no payload', () => {
    expect(describeError(undefined)).toMatchObject({ statusCode: 500, title: 'Request failed', details: [] })
  })
})
