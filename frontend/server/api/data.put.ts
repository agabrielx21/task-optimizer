import type { DataResponse, OptimizeRequest } from '#shared/types/api'
import { isOptimizeRequest } from '#shared/utils/contract'
import { BACKEND_TIMEOUT_MS, backendUrl, isMockMode, toHttpError, unexpectedResponse } from '../utils/backend'

export default defineEventHandler(async (event): Promise<DataResponse> => {
  if (isMockMode(event)) {
    throw createError({
      statusCode: 409,
      statusMessage: 'Saving is disabled',
      message: 'Mock mode is on (NUXT_USE_MOCK=true): there is no backend to save to.',
    })
  }

  const body = await readBody<OptimizeRequest>(event)
  const url = backendUrl(event)
  let response: unknown
  try {
    response = await $fetch(`${url}/api/data`, { method: 'PUT', body, timeout: BACKEND_TIMEOUT_MS })
  }
  catch (error) {
    throw toHttpError(error, url)
  }

  if (!isOptimizeRequest(response)) {
    throw unexpectedResponse('/api/data')
  }

  return { source: 'backend', input: response }
})
