type AuthUser = {
  name: string
  email: string
  role: string
}

type LoginResponse = {
  message: string
  token: string
  user: AuthUser
}

export const useAuth = () => {
  const token = useState<string | null>('auth.token', () => null)
  const user = useState<AuthUser | null>('auth.user', () => null)
  const isLoggedIn = computed(() => Boolean(token.value))

  const setSession = (newToken: string, newUser: AuthUser) => {
    token.value = newToken
    user.value = newUser

    if (process.client) {
      localStorage.setItem('auth.token', newToken)
      localStorage.setItem('auth.user', JSON.stringify(newUser))
    }
  }

  const clearSession = () => {
    token.value = null
    user.value = null

    if (process.client) {
      localStorage.removeItem('auth.token')
      localStorage.removeItem('auth.user')
    }
  }

  const login = async (email: string, password: string) => {
    const config = useRuntimeConfig()

    const response = await $fetch<LoginResponse>(`${config.public.apiBase}/login`, {
      method: 'POST',
      body: { email, password },
    })

    setSession(response.token, response.user)
    return response
  }

  const fetchMe = async () => {
    const config = useRuntimeConfig()

    if (!token.value) return null

    const response = await $fetch<{ user: AuthUser }>(`${config.public.apiBase}/me`, {
      headers: {
        Authorization: `Bearer ${token.value}`,
      },
    })

    user.value = response.user

    if (process.client) {
      localStorage.setItem('auth.user', JSON.stringify(response.user))
    }

    return response.user
  }

  const logout = async () => {
    const config = useRuntimeConfig()

    try {
      if (token.value) {
        await $fetch(`${config.public.apiBase}/logout`, {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${token.value}`,
          },
        })
      }
    } finally {
      clearSession()
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
