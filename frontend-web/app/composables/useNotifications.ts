import { useState } from '#imports'

export const useNotifications = () => {
  const { token } = useAuth()
  const config = useRuntimeConfig()

  const notifications = useState<any[]>('notifications.list', () => [])
  const unreadCount = useState<number>('notifications.unreadCount', () => 0)
  const loading = useState<boolean>('notifications.loading', () => false)

  const fetchNotifications = async () => {
    if (!token.value) return
    loading.value = true
    try {
      const response = await $fetch<{ data: any[] }>(`${config.public.apiBase}/notifications`, {
        headers: {
          Authorization: `Bearer ${token.value}`,
          Accept: 'application/json',
        },
      })
      notifications.value = response.data || []
      await fetchUnreadCount()
    } catch (e) {
      console.error('Error fetching notifications:', e)
    } finally {
      loading.value = false
    }
  }

  const fetchUnreadCount = async () => {
    if (!token.value) return
    try {
      const response = await $fetch<{ unread_count: number }>(`${config.public.apiBase}/notifications/unread-count`, {
        headers: {
          Authorization: `Bearer ${token.value}`,
          Accept: 'application/json',
        },
      })
      unreadCount.value = response.unread_count || 0
    } catch (e) {
      console.error('Error fetching unread count:', e)
    }
  }

  const markAsRead = async (id: number) => {
    if (!token.value) return
    try {
      await $fetch(`${config.public.apiBase}/notifications/${id}/read`, {
        method: 'PATCH',
        headers: {
          Authorization: `Bearer ${token.value}`,
          Accept: 'application/json',
        },
      })

      const index = notifications.value.findIndex((n) => n.id === id)
      if (index !== -1 && !notifications.value[index].read) {
        notifications.value[index].read = true
        unreadCount.value = Math.max(0, unreadCount.value - 1)
      }
    } catch (e) {
      console.error(`Error marking notification ${id} as read:`, e)
    }
  }

  const markAllAsRead = async () => {
    if (!token.value) return
    try {
      await $fetch(`${config.public.apiBase}/notifications/read-all`, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${token.value}`,
          Accept: 'application/json',
        },
      })

      notifications.value.forEach((n) => {
        n.read = true
      })
      unreadCount.value = 0
    } catch (e) {
      console.error('Error marking all notifications as read:', e)
    }
  }

  return {
    notifications,
    unreadCount,
    loading,
    fetchNotifications,
    fetchUnreadCount,
    markAsRead,
    markAllAsRead,
  }
}