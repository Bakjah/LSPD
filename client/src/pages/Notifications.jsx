import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useAuth } from '../context/AuthContext'
import { notificationsApi } from '../services/api'
import { EmptyState, ErrorState, Avatar } from '../components/UI'
import { LoadingSpinner } from '../components/LoadingSpinner'

export default function Notifications() {
  const { user } = useAuth()

  const [notifications, setNotifications] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [marking, setMarking] = useState(false)

  useEffect(() => {
    if (user) {
      fetchNotifications()
    }
  }, [user])

  const fetchNotifications = async () => {
    setLoading(true)
    setError(null)

    try {
      const response = await notificationsApi.getAll()
      setNotifications(response.notifications || response.data || [])
    } catch (err) {
      console.error('Failed to fetch notifications:', err)
      setError('Failed to load notifications.')
    } finally {
      setLoading(false)
    }
  }

  const markAsRead = async (notificationId) => {
    try {
      await notificationsApi.markRead(notificationId)
      setNotifications((prev) =>
        prev.map((n) =>
          n.id === notificationId ? { ...n, is_read: true, read: true } : n
        )
      )
    } catch (err) {
      console.error('Failed to mark notification as read:', err)
    }
  }

  const markAllAsRead = async () => {
    setMarking(true)
    try {
      await notificationsApi.markAllRead()
      setNotifications((prev) => prev.map((n) => ({ ...n, is_read: true, read: true })))
    } catch (err) {
      console.error('Failed to mark all as read:', err)
    } finally {
      setMarking(false)
    }
  }

  const deleteNotification = async (notificationId) => {
    try {
      await notificationsApi.delete(notificationId)
      setNotifications((prev) => prev.filter((n) => n.id !== notificationId))
    } catch (err) {
      console.error('Failed to delete notification:', err)
    }
  }

  const getNotificationIcon = (type) => {
    const icons = {
      reply: (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
      ),
      mention: (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
        </svg>
      ),
      quote: (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
        </svg>
      ),
      recruitment: (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
        </svg>
      ),
      message: (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
      ),
    }
    return icons[type] || icons.reply
  }

  const getNotificationColor = (type) => {
    const colors = {
      reply: 'bg-blue-500/20 text-blue-400',
      mention: 'bg-purple-500/20 text-purple-400',
      quote: 'bg-green-500/20 text-green-400',
      recruitment: 'bg-yellow-500/20 text-yellow-400',
      message: 'bg-gray-500/20 text-gray-400',
    }
    return colors[type] || colors.reply
  }

  const formatTime = (dateString) => {
    if (!dateString) return ''
    const date = new Date(dateString)
    const now = new Date()
    const diff = now - date
    const minutes = Math.floor(diff / 60000)
    const hours = Math.floor(diff / 3600000)
    const days = Math.floor(diff / 86400000)

    if (minutes < 1) return 'Just now'
    if (minutes < 60) return `${minutes}m ago`
    if (hours < 24) return `${hours}h ago`
    if (days < 7) return `${days}d ago`
    return date.toLocaleDateString()
  }

  const getNotificationLink = (notification) => {
    if (notification.topic_id) return `/topic/${notification.topic_id}`
    if (notification.post_id) return `/topic/${notification.topic_id}#post-${notification.post_id}`
    if (notification.user_id) return `/profile/${notification.username}`
    return '#'
  }

  const unreadCount = notifications.filter((n) => !n.is_read && !n.read).length

  // Guest state
  if (!user) {
    return (
      <div className="min-h-[50vh] flex items-center justify-center">
        <EmptyState
          icon="🔔"
          title="Sign in required"
          description="Please sign in to view your notifications."
          action={
            <Link
              to="/login"
              className="px-6 py-2 bg-purple-600 hover:bg-purple-500 text-white font-medium rounded-lg transition-colors"
            >
              Sign In
            </Link>
          }
        />
      </div>
    )
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl md:text-3xl font-bold text-white">Notifications</h1>
          {unreadCount > 0 && (
            <p className="text-gray-400">{unreadCount} unread notification{unreadCount > 1 ? 's' : ''}</p>
          )}
        </div>
        {unreadCount > 0 && (
          <button
            onClick={markAllAsRead}
            disabled={marking}
            className="px-4 py-2 text-sm bg-gray-700 hover:bg-gray-600 disabled:opacity-50 text-white rounded-lg transition-colors"
          >
            {marking ? 'Marking...' : 'Mark all as read'}
          </button>
        )}
      </div>

      {/* Loading State */}
      {loading && (
        <div className="min-h-[30vh] flex items-center justify-center">
          <LoadingSpinner size="lg" />
        </div>
      )}

      {/* Error State */}
      {error && !loading && (
        <ErrorState message={error} onRetry={fetchNotifications} />
      )}

      {/* Notifications List */}
      {!loading && !error && (
        <div className="bg-gray-800/50 backdrop-blur-sm rounded-xl border border-gray-700 overflow-hidden">
          {notifications.length === 0 ? (
            <EmptyState
              icon="🔔"
              title="No notifications"
              description="You're all caught up! Check back later for updates."
            />
          ) : (
            <div className="divide-y divide-gray-700">
              {notifications.map((notification, index) => (
                <motion.div
                  key={notification.id}
                  initial={{ opacity: 0, x: -20 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: index * 0.03 }}
                  className={`p-4 hover:bg-gray-700/30 transition-colors ${
                    !notification.is_read && !notification.read ? 'bg-purple-500/5' : ''
                  }`}
                >
                  <Link
                    to={getNotificationLink(notification)}
                    onClick={() => {
                      if (!notification.is_read && !notification.read) {
                        markAsRead(notification.id)
                      }
                    }}
                    className="flex items-start gap-4"
                  >
                    <div className={`flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center ${getNotificationColor(notification.type)}`}>
                      {getNotificationIcon(notification.type)}
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2">
                        {notification.actor && (
                          <Avatar src={notification.actor.avatar} name={notification.actor.username} size="xs" />
                        )}
                        <h3 className="text-white font-medium">{notification.title}</h3>
                        {(!notification.is_read && !notification.read) && (
                          <span className="w-2 h-2 rounded-full bg-purple-500 flex-shrink-0" />
                        )}
                      </div>
                      <p className="text-sm text-gray-400 mt-1">{notification.message || notification.content}</p>
                      <p className="text-xs text-gray-500 mt-2">{formatTime(notification.created_at)}</p>
                    </div>
                    <button
                      onClick={(e) => {
                        e.preventDefault()
                        deleteNotification(notification.id)
                      }}
                      className="p-1 text-gray-500 hover:text-red-400 transition-colors"
                    >
                      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </Link>
                </motion.div>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  )
}
