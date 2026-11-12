import type { DataResponse } from '#shared/types/api'
import { isOptimizeRequest } from '#shared/utils/contract'
import { BACKEND_TIMEOUT_MS, BUNDLED_INPUT, backendUrl, isMockMode, toHttpError, unexpectedResponse } from '../utils/backend'

export default defineEventHandler(async (event): Promise<DataResponse> => {
  if (isMockMode(event)) {
    return { source: 'mock', input: BUNDLED_INPUT }
  }

  const url = backendUrl(event)
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

  return { source: 'backend', input: response }
})
