import type { OptimizeRequest } from '#shared/types/api'

export type ParseResult =
  | { ok: true, input: OptimizeRequest }
  | { ok: false, errors: string[] }

// Syntax only (two JSON arrays); the backend validates the fields.
export function parseInput(employeesText: string, tasksText: string): ParseResult {
  const errors: string[] = []
  const employees = parseArray(employeesText, 'employees', errors)
  const tasks = parseArray(tasksText, 'tasks', errors)

  return errors.length > 0 || employees === null || tasks === null
    ? { ok: false, errors }
    : { ok: true, input: { employees, tasks } as OptimizeRequest }
}

function parseArray(text: string, name: string, errors: string[]): unknown[] | null {
  let value: unknown
  try {
    value = JSON.parse(text)
  }
  catch (e) {
    errors.push(`${name}: invalid JSON (${(e as Error).message})`)
    return null
  }
  if (!Array.isArray(value)) {
    errors.push(`${name}: must be a JSON array`)
    return null
  }
  return value
}

export function toPrettyJson(value: unknown): string {
  return JSON.stringify(value, null, 2)
}

export interface SaveState {
  status: 'idle' | 'saving' | 'saved' | 'error'
  message?: string
  details?: string[]
}
