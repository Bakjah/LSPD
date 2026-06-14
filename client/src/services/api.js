import axios from 'axios'

// API Base URL - adjust based on environment
const API_BASE = import.meta.env.VITE_API_URL || '/api'

// Create axios instance
const api = axios.create({
  baseURL: API_BASE,
  headers: {
    'Content-Type': 'application/json',
  },
  withCredentials: true,
})

// Token storage keys
const TOKEN_KEY = 'access_token'
const REFRESH_KEY = 'refresh_token'
const USER_KEY = 'lspd_user'

// ============================================================
// TOKEN MANAGEMENT
// ============================================================

export const tokenStorage = {
  getAccessToken: () => localStorage.getItem(TOKEN_KEY),
  getRefreshToken: () => localStorage.getItem(REFRESH_KEY),
  setTokens: (accessToken, refreshToken) => {
    localStorage.setItem(TOKEN_KEY, accessToken)
    localStorage.setItem(REFRESH_KEY, refreshToken)
  },
  clearTokens: () => {
    localStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(REFRESH_KEY)
    localStorage.removeItem(USER_KEY)
  },
  getUser: () => {
    const user = localStorage.getItem(USER_KEY)
    return user ? JSON.parse(user) : null
  },
  setUser: (user) => {
    localStorage.setItem(USER_KEY, JSON.stringify(user))
  },
}

// ============================================================
// REQUEST INTERCEPTOR - Add Auth Token
// ============================================================

api.interceptors.request.use(
  (config) => {
    const token = tokenStorage.getAccessToken()
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

// ============================================================
// RESPONSE INTERCEPTOR - Handle Errors & Auto Refresh
// ============================================================

let isRefreshing = false
let refreshSubscribers = []

// Subscribe to token refresh
const subscribeTokenRefresh = (callback) => {
  refreshSubscribers.push(callback)
}

// Notify all subscribers when token is refreshed
const onTokenRefreshed = (token) => {
  refreshSubscribers.forEach((callback) => callback(token))
  refreshSubscribers = []
}

// Reset refresh state
const resetRefreshState = () => {
  isRefreshing = false
  refreshSubscribers = []
}

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config

    // Handle 401 Unauthorized
    if (error.response?.status === 401 && !originalRequest._retry) {
      // If already refreshing, queue the request
      if (isRefreshing) {
        return new Promise((resolve) => {
          subscribeTokenRefresh((token) => {
            originalRequest.headers.Authorization = `Bearer ${token}`
            resolve(api(originalRequest))
          })
        })
      }

      originalRequest._retry = true
      isRefreshing = true

      try {
        const refreshToken = tokenStorage.getRefreshToken()

        if (!refreshToken) {
          throw new Error('No refresh token')
        }

        // Call refresh endpoint
        const response = await axios.post(`${API_BASE}/auth/refresh`, {
          refresh_token: refreshToken,
        })

        const { tokens, user } = response.data

        // Update stored tokens
        tokenStorage.setTokens(tokens.access_token, tokens.refresh_token)
        if (user) {
          tokenStorage.setUser(user)
        }

        // Retry original request
        onTokenRefreshed(tokens.access_token)
        resetRefreshState()

        originalRequest.headers.Authorization = `Bearer ${tokens.access_token}`
        return api(originalRequest)
      } catch (refreshError) {
        // Refresh failed - logout user
        resetRefreshState()
        tokenStorage.clearTokens()
        window.location.href = '/login?session=expired'
        return Promise.reject(refreshError)
      }
    }

    // Handle 403 Forbidden - no permission
    if (error.response?.status === 403) {
      const message = error.response?.data?.error || 'Access denied'
      console.error('Permission denied:', message)
    }

    // Handle 429 Rate Limited
    if (error.response?.status === 429) {
      const message = error.response?.data?.error || 'Too many requests. Please try again later.'
      console.error('Rate limited:', message)
    }

    return Promise.reject(error)
  }
)

// ============================================================
// API SERVICES
// ============================================================

// Auth API
export const authApi = {
  // Login with username/email and password
  login: async (credentials) => {
    const response = await api.post('/auth/login', {
      username: credentials.username,
      password: credentials.password,
      remember_me: credentials.remember || false,
    })

    if (response.data.tokens) {
      tokenStorage.setTokens(
        response.data.tokens.access_token,
        response.data.tokens.refresh_token
      )
    }

    if (response.data.user) {
      tokenStorage.setUser(response.data.user)
    }

    return response.data
  },

  // Register new user
  register: async (userData) => {
    const response = await api.post('/auth/register', {
      username: userData.username,
      email: userData.email,
      password: userData.password,
      password_confirm: userData.password_confirm,
    })

    if (response.data.tokens) {
      tokenStorage.setTokens(
        response.data.tokens.access_token,
        response.data.tokens.refresh_token
      )
    }

    if (response.data.user) {
      tokenStorage.setUser(response.data.user)
    }

    return response.data
  },

  // Logout
  logout: async () => {
    try {
      const refreshToken = tokenStorage.getRefreshToken()
      await api.post('/auth/logout', { refresh_token: refreshToken })
    } catch (error) {
      // Continue with logout even if API call fails
    } finally {
      tokenStorage.clearTokens()
    }
  },

  // Get current user
  me: async () => {
    const response = await api.get('/auth/me')
    if (response.data.user) {
      tokenStorage.setUser(response.data.user)
    }
    return response.data
  },

  // Refresh token
  refresh: async (refreshToken) => {
    const response = await api.post('/auth/refresh', {
      refresh_token: refreshToken,
    })
    return response.data
  },
}

// Portal API
export const portalApi = {
  getStats: async () => {
    const response = await api.get('/portal')
    return response.data
  },
  getDepartments: async () => {
    const response = await api.get('/departments')
    return response.data
  },
}

// Departments API
export const departmentsApi = {
  getAll: async () => {
    const response = await api.get('/departments')
    return response.data
  },
  getById: async (id) => {
    const response = await api.get(`/departments/${id}`)
    return response.data
  },
  getForums: async (departmentId) => {
    const response = await api.get('/forums', {
      params: { department: departmentId },
    })
    return response.data
  },
}

// Forums API
export const forumsApi = {
  getAll: async (params = {}) => {
    const response = await api.get('/forums', { params })
    return response.data
  },
  getById: async (id) => {
    const response = await api.get(`/forums/${id}`)
    return response.data
  },
}

// Topics API
export const topicsApi = {
  getAll: async (params = {}) => {
    const response = await api.get('/topics', { params })
    return response.data
  },
  getById: async (id) => {
    const response = await api.get(`/topics/${id}`)
    return response.data
  },
  create: async (data) => {
    const response = await api.post('/topics', data)
    return response.data
  },
  update: async (id, data) => {
    const response = await api.put(`/topics/${id}`, data)
    return response.data
  },
  delete: async (id) => {
    const response = await api.delete(`/topics/${id}`)
    return response.data
  },
  pin: async (id, pinned = true) => {
    const response = await api.put(`/topics/${id}`, { pinned })
    return response.data
  },
  lock: async (id, locked = true) => {
    const response = await api.put(`/topics/${id}`, { locked })
    return response.data
  },
}

// Posts API
export const postsApi = {
  getAll: async (topicId, params = {}) => {
    const response = await api.get(`/topics/${topicId}/posts`, { params })
    return response.data
  },
  create: async (topicId, data) => {
    const response = await api.post(`/topics/${topicId}/posts`, data)
    return response.data
  },
  update: async (id, data) => {
    const response = await api.put(`/posts/${id}`, data)
    return response.data
  },
  delete: async (id) => {
    const response = await api.delete(`/posts/${id}`)
    return response.data
  },
}

// Profile API
export const profileApi = {
  get: async (username) => {
    const response = await api.get(`/profile/${username}`)
    return response.data
  },
  update: async (data) => {
    const response = await api.put('/profile', data)
    return response.data
  },
  getActivity: async (username, params = {}) => {
    const response = await api.get(`/profile/${username}/activity`, { params })
    return response.data
  },
}

// Members API
export const membersApi = {
  getAll: async (params = {}) => {
    const response = await api.get('/members', { params })
    return response.data
  },
  search: async (query) => {
    const response = await api.get('/members', { params: { search: query } })
    return response.data
  },
}

// Notifications API
export const notificationsApi = {
  getAll: async (params = {}) => {
    const response = await api.get('/notifications', { params })
    return response.data
  },
  markRead: async (id) => {
    const response = await api.put(`/notifications/${id}/read`)
    return response.data
  },
  markAllRead: async () => {
    const response = await api.put('/notifications/read-all')
    return response.data
  },
  delete: async (id) => {
    const response = await api.delete(`/notifications/${id}`)
    return response.data
  },
}

// Messages API
export const messagesApi = {
  getConversations: async (params = {}) => {
    const response = await api.get('/messages', { params })
    return response.data
  },
  getConversation: async (id) => {
    const response = await api.get(`/messages/${id}`)
    return response.data
  },
  send: async (data) => {
    const response = await api.post('/messages', data)
    return response.data
  },
  delete: async (id) => {
    const response = await api.delete(`/messages/${id}`)
    return response.data
  },
}

// Staff API
export const staffApi = {
  getAll: async (departmentId = null) => {
    const response = await api.get('/staff', {
      params: departmentId ? { department: departmentId } : {},
    })
    return response.data
  },
}

// Roles API
export const rolesApi = {
  getAll: async (params = {}) => {
    const response = await api.get('/roles', { params })
    return response.data
  },
  getById: async (id) => {
    const response = await api.get(`/roles/${id}`)
    return response.data
  },
  create: async (data) => {
    const response = await api.post('/roles/create', data)
    return response.data
  },
  update: async (id, data) => {
    const response = await api.put(`/roles/${id}`, data)
    return response.data
  },
  delete: async (id) => {
    const response = await api.delete(`/roles/${id}`)
    return response.data
  },
  assign: async (userId, roleId, departmentId = null) => {
    const response = await api.post('/roles/assign', {
      user_id: userId,
      role_id: roleId,
      department_id: departmentId,
    })
    return response.data
  },
  remove: async (userId, roleId, departmentId = null) => {
    const response = await api.post('/roles/remove', {
      user_id: userId,
      role_id: roleId,
      department_id: departmentId,
    })
    return response.data
  },
}

// Admin API
export const adminApi = {
  getDashboard: async () => {
    const response = await api.get('/admin/dashboard')
    return response.data
  },
  getUsers: async (params = {}) => {
    const response = await api.get('/admin/users', { params })
    return response.data
  },
  updateUser: async (id, data) => {
    const response = await api.put(`/admin/users/${id}`, data)
    return response.data
  },
  banUser: async (id, reason) => {
    const response = await api.post('/admin/users/ban', { user_id: id, reason })
    return response.data
  },
  unbanUser: async (id) => {
    const response = await api.post('/admin/users/unban', { user_id: id })
    return response.data
  },
  getMedals: async () => {
    const response = await api.get('/admin/medals')
    return response.data
  },
  awardMedal: async (userId, medalId, reason) => {
    const response = await api.post('/admin/medals/award', {
      user_id: userId,
      medal_id: medalId,
      reason,
    })
    return response.data
  },
  getSettings: async () => {
    const response = await api.get('/admin/settings')
    return response.data
  },
  updateSetting: async (key, value) => {
    const response = await api.post('/admin/settings', { key, value })
    return response.data
  },
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================

// Check if user is authenticated
export const isAuthenticated = () => {
  return !!tokenStorage.getAccessToken()
}

// Check if user has specific permission
export const hasPermission = (permission) => {
  const user = tokenStorage.getUser()
  if (!user) return false

  // Admin always has all permissions
  if (user.is_admin) return true

  // Check if user has the permission in their roles
  if (user.roles) {
    for (const role of user.roles) {
      if (role.permissions && role.permissions.includes(permission)) {
        return true
      }
    }
  }

  return false
}

// Check if user is admin
export const isAdmin = () => {
  const user = tokenStorage.getUser()
  return user?.is_admin || false
}

// Check if user is staff
export const isStaff = () => {
  const user = tokenStorage.getUser()
  return user?.is_staff || false
}

// Check if user is leader of specific department
export const isDepartmentLeader = (departmentId) => {
  const user = tokenStorage.getUser()
  if (!user || !user.departments) return false

  const dept = user.departments.find((d) => d.id === departmentId)
  if (!dept) return false

  return dept.roles?.some((r) => r.is_leader) || false
}

// Get user's primary role
export const getPrimaryRole = () => {
  const user = tokenStorage.getUser()
  return user?.primary_role || null
}

// Get user's primary department
export const getPrimaryDepartment = () => {
  const user = tokenStorage.getUser()
  return user?.primary_department || null
}

// Export default api instance
export default api