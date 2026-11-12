import { FetchError } from 'ofetch'
import type { H3Event } from 'h3'
import type { OptimizeRequest } from '#shared/types/api'
import defaultEmployees from '#shared/data/employees.json'
import defaultTasks from '#shared/data/tasks.json'

// Mock mode only: there is no backend to read the data from.
export const BUNDLED_INPUT: OptimizeRequest = { employees: defaultEmployees, tasks: defaultTasks }

export const BACKEND_TIMEOUT_MS = 10_000

export function isMockMode(event: H3Event): boolean {
  return String(useRuntimeConfig(event).useMock) === 'true'
}

export function backendUrl(event: H3Event): string {
  return String(useRuntimeConfig(event).backendUrl).replace(/\/+$/, '')
}

export function toHttpError(error: unknown, url: string) {
  const details = error instanceof FetchError
    ? ((error.data as { details?: string[] } | undefined)?.details ?? [])
    : []

  if (error instanceof FetchError && error.response?.status === 400) {
    return createError({
      statusCode: 400,
      statusMessage: 'Invalid input',
      message: 'The backend rejected the employees/tasks input.',
      data: { details },
    })
  }

  if (error instanceof FetchError && error.response) {
    return createError({
      statusCode: 502,
      statusMessage: 'Backend error',
      message: `The backend answered with HTTP ${error.response.status}.`,
      data: { backendUrl: url, details },
    })
  }

  return createError({
    statusCode: 502,
    statusMessage: 'Backend unreachable',
    message: `Could not reach the backend at ${url}. Is it running? (Or start the frontend with NUXT_USE_MOCK=true.)`,
    data: { backendUrl: url },
  })
}

export function unexpectedResponse(path: string) {
  return createError({
    statusCode: 502,
    statusMessage: 'Unexpected backend response',
    message: `The backend answered ${path} with data that does not match the API contract.`,
  })
}
