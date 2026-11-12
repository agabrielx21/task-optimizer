import type { OptimizeErrorData } from '#shared/types/api'

export interface DisplayError {
  statusCode: number
  title: string
  message: string
  details: string[]
}

export function describeError(error: unknown): DisplayError {
  const e = (error ?? {}) as {
    statusCode?: number
    statusMessage?: string
    message?: string
    data?: { statusMessage?: string, message?: string, data?: OptimizeErrorData } & OptimizeErrorData
  }

  const statusCode = e.statusCode ?? 500
  const payload = e.data
  const details = payload?.data?.details ?? payload?.details ?? []

  return {
    statusCode,
    title: payload?.statusMessage ?? e.statusMessage ?? 'Request failed',
    message: payload?.message ?? e.message ?? 'Something went wrong while loading the results.',
    details,
  }
}
