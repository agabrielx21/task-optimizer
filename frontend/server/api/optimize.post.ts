import { VARIANTS } from '#shared/types/api'
import type { OptimizationResult, OptimizeRequest, OptimizeResponse, Variant } from '#shared/types/api'
import { isOptimizationResult, isOptimizeRequest } from '#shared/utils/contract'
import mockResults from '../mock/mock-results.json'
import { BACKEND_TIMEOUT_MS, BUNDLED_INPUT, backendUrl, isMockMode, toHttpError, unexpectedResponse } from '../utils/backend'

// The browser calls this route, never PHP. Both variants run in parallel.
export default defineEventHandler(async (event): Promise<OptimizeResponse> => {
  if (isMockMode(event)) {
    return { source: 'mock', input: BUNDLED_INPUT, results: mockResults as Record<Variant, OptimizationResult> }
  }

  const url = backendUrl(event)
  const body = await readBody<Partial<OptimizeRequest> | undefined>(event)
  // No body: the backend's data files, fetched first so the returned input is exactly what was optimized.
  const input: OptimizeRequest = body?.employees !== undefined && body?.tasks !== undefined
    ? { employees: body.employees, tasks: body.tasks }
    : await fetchDefaultInput(url)

  const [cost, makespan] = await Promise.all(
    VARIANTS.map(variant => callBackend(url, variant, input)),
  )

  return { source: 'backend', input, results: { cost: cost!, makespan: makespan! } }
})

async function fetchDefaultInput(url: string): Promise<OptimizeRequest> {
  let response: unknown
  try {
    response = await $fetch(`${url}/api/data`, { timeout: BACKEND_TIMEOUT_MS })
  }
  catch (error) {
    throw toHttpError(error, url)
  }
  if (!isOptimizeRequest(response)) {
    throw unexpectedResponse('/api/data')
  }
  return response
}

async function callBackend(url: string, variant: Variant, input: OptimizeRequest): Promise<OptimizationResult> {
  let response: unknown
  try {
    response = await $fetch(`${url}/api/optimize/${variant}`, {
      method: 'POST',
      body: input,
      timeout: BACKEND_TIMEOUT_MS,
    })
  }
  catch (error) {
    throw toHttpError(error, url)
  }

  if (!isOptimizationResult(response)) {
    throw unexpectedResponse(`/api/optimize/${variant}`)
  }

  return response
}
