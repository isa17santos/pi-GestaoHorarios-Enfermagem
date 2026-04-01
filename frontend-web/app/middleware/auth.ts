export default defineNuxtRouteMiddleware(() => {
  // Read the global auth state to protect private pages
  const { isLoggedIn } = useAuth()

  if (!isLoggedIn.value) {
    // Redirect unauthenticated users back to the login page
    return navigateTo('/')
  }
})
