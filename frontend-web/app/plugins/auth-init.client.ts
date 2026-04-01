export default defineNuxtPlugin(async () => {
  // Access auth helpers and reactive auth state
  const { token, user, fetchMe, clearSession } = useAuth()

  // Read any previously saved session data from browser storage
  const savedToken = localStorage.getItem('auth.token')
  const savedUser = localStorage.getItem('auth.user')

  if (savedToken) {
    // Restore the saved token into the reactive auth state
    token.value = savedToken
  }

  if (savedUser) {
    try {
      // Restore the saved user profile into the reactive auth state
      user.value = JSON.parse(savedUser)
    } catch {
      // Remove invalid saved user data if parsing fails
      localStorage.removeItem('auth.user')
    }
  }

  if (token.value) {
    try {
      // Validate and refresh the authenticated user data with the backend
      await fetchMe()
    } catch {
      // Clear the local session if the token is no longer valid
      clearSession()
    }
  }
})
