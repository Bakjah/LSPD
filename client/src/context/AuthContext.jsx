import { createContext, useContext, useState, useEffect, useCallback } from 'react'
import { authApi, tokenStorage, isAuthenticated } from '../services/api'

const AuthContext = createContext(null)

// ============================================================
// AUTH PROVIDER
// ============================================================

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  // Initialize auth state from localStorage
  useEffect(() => {
    const initializeAuth = async () => {
      const storedUser = tokenStorage.getUser()
      const hasToken = isAuthenticated()

      if (storedUser && hasToken) {
        // Validate token by calling /auth/me
        try {
          const response = await authApi.me()
          setUser(response.user || storedUser)
        } catch (err) {
          // Token invalid, clear storage
          tokenStorage.clearTokens()
          setUser(null)
        }
      }

      setLoading(false)
    }

    initializeAuth()
  }, [])

  // Login
  const login = useCallback(async (credentials) => {
    setLoading(true)
    setError(null)

    try {
      const response = await authApi.login(credentials)

      if (response.user) {
        setUser(response.user)
      }

      return response
    } catch (err) {
      const errorMessage =
        err.response?.data?.error ||
        err.response?.data?.message ||
        'Login failed. Please try again.'
      setError(errorMessage)
      throw new Error(errorMessage)
    } finally {
      setLoading(false)
    }
  }, [])

  // Register
  const register = useCallback(async (userData) => {
    setLoading(true)
    setError(null)

    try {
      const response = await authApi.register(userData)

      if (response.user) {
        setUser(response.user)
      }

      return response
    } catch (err) {
      const errorMessage =
        err.response?.data?.error ||
        err.response?.data?.message ||
        (err.response?.data?.details
          ? err.response.data.details.join(', ')
          : 'Registration failed. Please try again.')
      setError(errorMessage)
      throw new Error(errorMessage)
    } finally {
      setLoading(false)
    }
  }, [])

  // Logout
  const logout = useCallback(async () => {
    setLoading(true)

    try {
      await authApi.logout()
    } finally {
      tokenStorage.clearTokens()
      setUser(null)
      setLoading(false)
    }
  }, [])

  // Update user data
  const updateUser = useCallback((userData) => {
    setUser(userData)
    tokenStorage.setUser(userData)
  }, [])

  // Refresh user data from server
  const refreshUser = useCallback(async () => {
    try {
      const response = await authApi.me()
      if (response.user) {
        setUser(response.user)
        tokenStorage.setUser(response.user)
      }
      return response.user
    } catch (err) {
      console.error('Failed to refresh user:', err)
      return null
    }
  }, [])

  // Clear error
  const clearError = useCallback(() => {
    setError(null)
  }, [])

  // Value object
  const value = {
    user,
    loading,
    error,
    isAuthenticated: !!user,
    login,
    register,
    logout,
    updateUser,
    refreshUser,
    clearError,
    // Permission helpers
    isAdmin: user?.is_admin || false,
    isStaff: user?.is_staff || false,
    hasRole: (roleSlug) => {
      return user?.roles?.some((r) => r.slug === roleSlug) || false
    },
    hasPermission: (permissionKey) => {
      if (!user) return false
      if (user.is_admin) return true

      // Check all roles for the permission
      for (const role of user.roles || []) {
        if (role.permissions?.includes(permissionKey)) {
          return true
        }
      }
      return false
    },
    isDepartmentLeader: (departmentId) => {
      if (!user || !user.departments) return false
      const dept = user.departments.find((d) => d.id === departmentId)
      if (!dept) return false
      return dept.roles?.some((r) => r.is_leader) || false
    },
    getPrimaryRole: () => {
      return user?.primary_role || null
    },
    getPrimaryDepartment: () => {
      return user?.primary_department || null
    },
  }

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

// ============================================================
// USE AUTH HOOK
// ============================================================

export function useAuth() {
  const context = useContext(AuthContext)

  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider')
  }

  return context
}

// Named export for default
export default useAuth