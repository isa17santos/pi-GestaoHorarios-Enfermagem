export default defineNuxtPlugin(async () => {
  const { token, user, fetchMe, clearSession } = useAuth()

  const savedToken = localStorage.getItem('auth.token')
  const savedUser = localStorage.getItem('auth.user')

  if (savedToken) {
    token.value = savedToken
  }

  if (savedUser) {
    try {
      user.value = JSON.parse(savedUser)
    } catch {
      localStorage.removeItem('auth.user')
    }
  }

  if (token.value) {
    try {
      await fetchMe()
    } catch {
      clearSession()
    }
  }
})
