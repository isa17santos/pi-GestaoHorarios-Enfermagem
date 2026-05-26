// Represents the authenticated user data stored in the frontend session state
type AuthUser = {
  id: number
  name: string
  email: string
  role: string
  must_change_password: boolean
}

// Response shape returned by the API when login succeeds normally
type LoginSuccessResponse = {
  message: string
  must_change_password: false
  token: string
  user: AuthUser
}

// Response shape returned by the API when the user must change the password first
type LoginMustChangePasswordResponse = {
  message: string
  must_change_password: true
  email: string
  password_reset_token: string
}

// Union type that allows the login request to handle both backend responses
type LoginResponse = LoginSuccessResponse | LoginMustChangePasswordResponse

export const useAuth = () => {
  // Store the auth token globally across the Nuxt app
  const token = useState<string | null>('auth.token', () => null)

  // Store the authenticated user data globally across the Nuxt app
  const user = useState<AuthUser | null>('auth.user', () => null)

  // Computed helper used to know whether the user is currently authenticated
  const isLoggedIn = computed(() => Boolean(token.value))

  const setSession = (newToken: string, newUser: AuthUser) => {

    // Update reactive auth state in memory
    token.value = newToken
    user.value = newUser

    if (process.client) {
      // Persist auth state so it survives page refreshes in the browser
      localStorage.setItem('auth.token', newToken)
      localStorage.setItem('auth.user', JSON.stringify(newUser))
    }
  }

  const clearSession = () => {
    // Remove the current auth state from memory
    token.value = null
    user.value = null

    if (process.client) {
      // Remove the persisted auth state from browser storage
      localStorage.removeItem('auth.token')
      localStorage.removeItem('auth.user')
    }
  }

  const login = async (email: string, password: string) => {
    const config = useRuntimeConfig()

    // Send the login request to the backend API
    const response = await $fetch<LoginResponse>(`${config.public.apiBase}/login`, {
      method: 'POST',
      body: { email, password },
    })

    // Only create a frontend session if the backend allows the user to log in fully
    if (!response.must_change_password) {
      setSession(response.token, response.user)
    }

    return response
  }

  const fetchMe = async () => {
    const config = useRuntimeConfig()

    // Do nothing if there is no token to authenticate the request
    if (!token.value) return null

    // Fetch the authenticated user's public profile from the backend
    const response = await $fetch<{ user: AuthUser }>(`${config.public.apiBase}/me`, {
      headers: {
        Authorization: `Bearer ${token.value}`,
      },
    })

    // Keep the in-memory user state in sync with the backend
    user.value = response.user

    if (process.client) {
      // Persist the refreshed user data in browser storage
      localStorage.setItem('auth.user', JSON.stringify(response.user))
    }

    return response.user
  }

  const logout = async () => {
    const config = useRuntimeConfig()

    try {
      if (token.value) {
        // Revoke the current token on the backend if the user is authenticated
        await $fetch(`${config.public.apiBase}/logout`, {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${token.value}`,
          },
        })
      }
    } finally {
      // Always clear the local session even if the backend request fails
      clearSession()

      // Send the user back to the login page
      await navigateTo('/')
    }
  }

  return {
    token,
    user,
    isLoggedIn,
    login,
    logout,
    fetchMe,
    setSession,
    clearSession,
  }
}
