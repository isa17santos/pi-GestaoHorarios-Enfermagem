type SwapStatus = 'pending' | 'accepted' | 'rejected' | 'cancelled'

type SwapDirection = 'sent' | 'received'

type ShiftOption = {
  id: number
  date: string
  shift_type: {
    id: number
    name: string
    color: string
    start_time: string
    end_time: string
  }
  users: { id: number; name: string }[]
}

type ShiftListResponse = {
  data: ShiftOption[]
}

type SwapParticipant = {
  id: number
  name: string
  role: 'requester' | 'target'
}

type SwapShiftType = {
  id: number
  name: string
}

type SwapShift = {
  id: number
  date: string
  shift_type: SwapShiftType
  owner: {
    id: number
    name: string
  }
}

type SwapRequest = {
  id: number
  status: SwapStatus
  notes: string | null
  created_at: string
  creator: {
    id: number
    name: string
  }
  participants: SwapParticipant[]
  offered_shifts: SwapShift[]
  requested_shifts: SwapShift[]
}

type SwapListResponse = {
  data: SwapRequest[]
}

type SwapSingleResponse = {
  data: SwapRequest
}

type CreateSwapPayload = {
  offered_shift_ids: number[]
  requested_shift_ids: number[]
  target_user_id: number
  notes?: string
}

export const useSwap = () => {
  const config = useRuntimeConfig()
  const { token, user } = useAuth()

  const swapRequests = useState<SwapRequest[]>('swap.requests', () => [])

  const loadingSwaps = useState<boolean>('swap.loadingSwaps', () => false)

  const errorSwaps = useState<string | null>('swap.errorSwaps', () => null)

  const requireAuthToken = () => {
    if (!token.value) {
      throw new Error('Authentication required. Please sign in again.')
    }

    return token.value
  }

  const extractApiErrorMessage = (error: unknown, fallback: string) => {
    const apiError = error as {
      data?: {
        message?: string
        error?: string
        errors?: Record<string, string[] | string>
      }
      statusMessage?: string
      message?: string
    }

    if (apiError?.data?.message) return apiError.data.message
    if (apiError?.data?.error) return apiError.data.error

    const errorEntries = apiError?.data?.errors
    if (errorEntries && typeof errorEntries === 'object') {
      const firstErrorValue = Object.values(errorEntries)[0]
      if (Array.isArray(firstErrorValue) && firstErrorValue.length > 0) {
        return firstErrorValue[0] || fallback
      }

      if (typeof firstErrorValue === 'string' && firstErrorValue.trim()) {
        return firstErrorValue
      }
    }

    if (apiError?.statusMessage) return apiError.statusMessage
    return fallback
  }

  const fetchSwaps = async (direction?: SwapDirection, status?: SwapStatus) => {
    loadingSwaps.value = true
    errorSwaps.value = null

    try {
      const authToken = requireAuthToken()
      const query = new URLSearchParams()

      // Add optional filters only when provided.
      if (direction) query.set('direction', direction)
      if (status) query.set('status', status)

      const queryString = query.toString()
      const endpoint = queryString
        ? `${config.public.apiBase}/swaps?${queryString}`
        : `${config.public.apiBase}/swaps`

      const response = await $fetch<SwapListResponse | SwapRequest[]>(endpoint, {
        headers: {
          Authorization: `Bearer ${authToken}`,
        },
      })

      const fetchedSwaps = Array.isArray(response)
        ? response
        : (response.data ?? [])

      swapRequests.value = fetchedSwaps
      return fetchedSwaps
    } catch (error) {
      errorSwaps.value = extractApiErrorMessage(error, 'Failed to fetch swaps')
      console.error('Error fetching swaps:', error)
      throw error
    } finally {
      loadingSwaps.value = false
    }
  }

  const fetchShifts = async (params: {
    mine?: boolean
    user_id?: number
    exclude_mine?: boolean
    future?: boolean
  }): Promise<ShiftOption[]> => {
    const authToken = requireAuthToken()
    const query = new URLSearchParams()
    if (params.mine) query.set('mine', 'true')
    if (params.user_id) query.set('user_id', String(params.user_id))
    if (params.exclude_mine) query.set('exclude_mine', 'true')
    if (params.future) query.set('future', 'true')

    const response = await $fetch<ShiftListResponse>(
      `${config.public.apiBase}/shifts?${query.toString()}`,
      { headers: { Authorization: `Bearer ${authToken}` } }
    )
    return response.data ?? []
  }

  const createSwap = async (payload: CreateSwapPayload): Promise<SwapRequest> => {
    loadingSwaps.value = true
    errorSwaps.value = null

    try {
      const authToken = requireAuthToken()

      const response = await $fetch<SwapSingleResponse>(
        `${config.public.apiBase}/swaps`,
        {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${authToken}`,
          },
          body: payload,
        }
      )

      return response.data
    } catch (error) {
      errorSwaps.value = extractApiErrorMessage(error, 'Failed to create swap')
      console.error('Error creating swap:', error)
      throw error
    } finally {
      loadingSwaps.value = false
    }
  }

  const acceptSwap = async (id: number): Promise<SwapRequest> => {
    loadingSwaps.value = true
    errorSwaps.value = null

    try {
      const authToken = requireAuthToken()

      const response = await $fetch<SwapSingleResponse>(
        `${config.public.apiBase}/swaps/${id}/accept`,
        {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${authToken}`,
          },
        }
      )

      return response.data
    } catch (error) {
      errorSwaps.value = extractApiErrorMessage(error, 'Failed to accept swap')
      console.error('Error accepting swap:', error)
      throw error
    } finally {
      loadingSwaps.value = false
    }
  }

  const rejectSwap = async (id: number): Promise<SwapRequest> => {
    loadingSwaps.value = true
    errorSwaps.value = null

    try {
      const authToken = requireAuthToken()

      const response = await $fetch<SwapSingleResponse>(
        `${config.public.apiBase}/swaps/${id}/reject`,
        {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${authToken}`,
          },
        }
      )

      return response.data
    } catch (error) {
      errorSwaps.value = extractApiErrorMessage(error, 'Failed to reject swap')
      console.error('Error rejecting swap:', error)
      throw error
    } finally {
      loadingSwaps.value = false
    }
  }

  const cancelSwap = async (id: number): Promise<SwapRequest> => {
    loadingSwaps.value = true
    errorSwaps.value = null

    try {
      const authToken = requireAuthToken()

      const response = await $fetch<SwapSingleResponse>(
        `${config.public.apiBase}/swaps/${id}/cancel`,
        {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${authToken}`,
          },
        }
      )

      return response.data
    } catch (error) {
      errorSwaps.value = extractApiErrorMessage(error, 'Failed to cancel swap')
      console.error('Error cancelling swap:', error)
      throw error
    } finally {
      loadingSwaps.value = false
    }
  }

  return {
    swapRequests,
    loadingSwaps,
    errorSwaps,
    fetchSwaps,
    fetchShifts,
    createSwap,
    acceptSwap,
    rejectSwap,
    cancelSwap,
  }
}
