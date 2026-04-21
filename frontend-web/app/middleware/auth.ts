export default defineNuxtRouteMiddleware(() => {
  if (process.server) return;

  // Read the global auth state to protect private pages
  const { token } = useAuth();

  if (process.client && !token.value) {
    const saved = localStorage.getItem("auth.token");
    if (saved) {
      token.value = saved;
    }
  }

  if (!token.value) {
    return navigateTo("/");
  }
});
