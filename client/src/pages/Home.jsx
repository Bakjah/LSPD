import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useAuth } from '../context/AuthContext'
import { portalApi, departmentsApi, topicsApi } from '../services/api'
import { EmptyState, ErrorState } from '../components/UI'
import { LoadingSpinner } from '../components/LoadingSpinner'
import lspdLogo from '../assets/logos/lspd.png'
import lssdLogo from '../assets/logos/lssd.png'
import lsfdLogo from '../assets/logos/lsfd.png'
import lsnLogo from '../assets/logos/lsn.png'

// Department configs with logos
const departmentConfig = {
  lspd: { name: 'LSPD', fullName: 'Los Santos Police Department', color: '#2563EB', logo: lspdLogo },
  lssd: { name: 'LSSD', fullName: 'Los Santos Sheriff Department', color: '#92400E', logo: lssdLogo },
  lsfd: { name: 'LSFD', fullName: 'Los Santos Fire Department', color: '#DC2626', logo: lsfdLogo },
  lsn: { name: 'LSN', fullName: 'Los Santos News', color: '#EA580C', logo: lsnLogo },
}

export default function Home() {
  const { user } = useAuth()
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [stats, setStats] = useState(null)
  const [departments, setDepartments] = useState([])
  const [recentTopics, setRecentTopics] = useState([])

  useEffect(() => {
    fetchHomeData()
  }, [])

  const fetchHomeData = async () => {
    setLoading(true)
    setError(null)

    try {
      // Fetch portal stats and departments in parallel
      const [statsRes, forumsRes, topicsRes] = await Promise.allSettled([
        portalApi.getStats(),
        departmentsApi.getAll(),
        topicsApi.getAll({ per_page: 5, sort: 'latest' }),
      ])

      // Process stats
      if (statsRes.status === 'fulfilled') {
        setStats(statsRes.value.stats || statsRes.value.data)
      }

      // Process departments
      if (forumsRes.status === 'fulfilled') {
        const depts = forumsRes.value.departments || forumsRes.value.data || []
        setDepartments(depts)
      }

      // Process recent topics
      if (topicsRes.status === 'fulfilled') {
        const topics = topicsRes.value.topics || topicsRes.value.data || []
        setRecentTopics(topics)
      }
    } catch (err) {
      console.error('Failed to fetch home data:', err)
      setError('Failed to load portal data. Please try again.')
    } finally {
      setLoading(false)
    }
  }

  // Show loading state
  if (loading) {
    return (
      <div className="min-h-[50vh] flex items-center justify-center">
        <LoadingSpinner size="lg" />
      </div>
    )
  }

  // Show error state
  if (error) {
    return <ErrorState message={error} onRetry={fetchHomeData} />
  }

  return (
    <div className="space-y-8">
      {/* Hero Section */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-purple-900/50 to-indigo-900/50 p-8 md:p-12"
      >
        <div className="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmZmZmYiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PGNpcmNsZSBjeD0iMzAiIGN5PSIzMCIgcj0iMiIvPjwvZz48L2c+PC9zdmc+')] opacity-30" />
        <div className="relative z-10">
          <h1 className="text-4xl md:text-5xl font-bold text-white mb-4">
            Welcome to <span className="text-purple-400">Department Portal</span>
          </h1>
          <p className="text-lg text-gray-300 mb-6 max-w-2xl">
            The official community portal for Los Santos emergency services. Join the conversation, share knowledge, and connect with fellow officers.
          </p>
          {!user ? (
            <div className="flex flex-wrap gap-4">
              <Link
                to="/register"
                className="px-6 py-3 bg-purple-600 hover:bg-purple-500 text-white font-semibold rounded-lg transition-colors"
              >
                Join the Community
              </Link>
              <Link
                to="/login"
                className="px-6 py-3 bg-white/10 hover:bg-white/20 text-white font-semibold rounded-lg backdrop-blur-sm transition-colors"
              >
                Sign In
              </Link>
            </div>
          ) : (
            <p className="text-purple-300 font-medium">Welcome back, {user.username}!</p>
          )}
        </div>
      </motion.div>

      {/* Departments Grid */}
      <section>
        <h2 className="text-2xl font-bold text-white mb-6">Our Departments</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          {departments.length > 0 ? (
            departments.map((dept, index) => {
              const config = departmentConfig[dept.code?.toLowerCase()] || {
                name: dept.code,
                fullName: dept.name,
                color: dept.color || '#2563EB',
                logo: lspdLogo,
              }
              return (
                <motion.div
                  key={dept.id}
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: index * 0.1 }}
                >
                  <Link
                    to={`/${dept.code?.toLowerCase()}`}
                    className="block p-6 rounded-xl bg-gray-800/50 hover:bg-gray-800 border border-gray-700 hover:border-gray-600 transition-all group"
                  >
                    <div
                      className="w-12 h-12 rounded-lg flex items-center justify-center mb-4"
                      style={{ backgroundColor: `${config.color}20` }}
                    >
                      <img src={config.logo} alt={config.name} className="w-8 h-8 object-contain" />
                    </div>
                    <h3 className="text-xl font-bold text-white mb-1 group-hover:text-purple-400 transition-colors">
                      {config.name}
                    </h3>
                    <p className="text-sm text-gray-400">{config.fullName}</p>
                    {dept.stats && (
                      <div className="mt-3 pt-3 border-t border-gray-700 flex justify-between text-xs text-gray-500">
                        <span>{dept.stats.members || 0} members</span>
                        <span>{dept.stats.threads || 0} threads</span>
                      </div>
                    )}
                  </Link>
                </motion.div>
              )
            })
          ) : (
            // Fallback to static departments if API returns empty
            Object.entries(departmentConfig).map(([id, dept], index) => (
              <motion.div
                key={id}
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: index * 0.1 }}
              >
                <Link
                  to={`/${id}`}
                  className="block p-6 rounded-xl bg-gray-800/50 hover:bg-gray-800 border border-gray-700 hover:border-gray-600 transition-all group"
                >
                  <div
                    className="w-12 h-12 rounded-lg flex items-center justify-center mb-4"
                    style={{ backgroundColor: `${dept.color}20` }}
                  >
                    <img src={dept.logo} alt={dept.name} className="w-8 h-8 object-contain" />
                  </div>
                  <h3 className="text-xl font-bold text-white mb-1 group-hover:text-purple-400 transition-colors">
                    {dept.name}
                  </h3>
                  <p className="text-sm text-gray-400">{dept.fullName}</p>
                </Link>
              </motion.div>
            ))
          )}
        </div>
      </section>

      {/* Recent Activity */}
      <section>
        <h2 className="text-2xl font-bold text-white mb-6">Recent Activity</h2>
        {recentTopics.length > 0 ? (
          <div className="space-y-3">
            {recentTopics.map((topic, index) => (
              <motion.div
                key={topic.id}
                initial={{ opacity: 0, x: -20 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ delay: index * 0.05 }}
              >
                <Link
                  to={`/topic/${topic.id}`}
                  className="flex items-center justify-between p-4 rounded-lg bg-gray-800/50 hover:bg-gray-800 border border-gray-700 hover:border-gray-600 transition-all"
                >
                  <div className="flex-1 min-w-0">
                    <h3 className="text-white font-medium truncate">{topic.title}</h3>
                    <p className="text-sm text-gray-400">
                      by <span className="text-purple-400">{topic.author?.username || 'Unknown'}</span>
                      {topic.forum && (
                        <> in <span className="uppercase">{topic.forum}</span></>
                      )}
                    </p>
                  </div>
                  <div className="flex items-center gap-6 text-sm text-gray-400 ml-4">
                    <span className="flex items-center gap-1">
                      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                      </svg>
                      {topic.reply_count || 0}
                    </span>
                    <span className="flex items-center gap-1">
                      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                      {topic.view_count || 0}
                    </span>
                  </div>
                </Link>
              </motion.div>
            ))}
          </div>
        ) : (
          <div className="p-8 rounded-lg bg-gray-800/50 border border-gray-700 text-center">
            <p className="text-gray-400">No recent activity yet. Be the first to start a discussion!</p>
          </div>
        )}
      </section>

      {/* Stats Section */}
      <section className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {[
          { label: 'Total Members', value: stats?.total_users?.toLocaleString() || stats?.members?.toLocaleString() || '0' },
          { label: 'Online Now', value: stats?.online_users?.toLocaleString() || stats?.online?.toLocaleString() || '0' },
          { label: 'Total Threads', value: stats?.total_topics?.toLocaleString() || stats?.threads?.toLocaleString() || '0' },
          { label: 'Total Replies', value: stats?.total_posts?.toLocaleString() || stats?.posts?.toLocaleString() || '0' },
        ].map((stat, index) => (
          <motion.div
            key={stat.label}
            initial={{ opacity: 0, scale: 0.9 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ delay: 0.3 + index * 0.05 }}
            className="p-4 rounded-xl bg-gray-800/50 border border-gray-700 text-center"
          >
            <div className="text-2xl md:text-3xl font-bold text-purple-400 mb-1">{stat.value}</div>
            <div className="text-sm text-gray-400">{stat.label}</div>
          </motion.div>
        ))}
      </section>
    </div>
  )
}