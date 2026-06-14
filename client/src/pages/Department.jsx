import { useState, useEffect } from 'react'
import { useParams, Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useAuth } from '../context/AuthContext'
import { departmentsApi, forumsApi } from '../services/api'
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

// Default forums if API doesn't return any
const defaultForums = {
  lspd: [
    { id: 1, name: 'General Discussion', description: 'Talk about anything LSPD related', thread_count: 0, post_count: 0 },
    { id: 2, name: 'Training & Certifications', description: 'Training sessions and certification tracking', thread_count: 0, post_count: 0 },
    { id: 3, name: 'Operations & Patrols', description: 'Discuss patrol routes and operations', thread_count: 0, post_count: 0 },
    { id: 4, name: 'Policy & Procedures', description: 'Department policies and procedures', thread_count: 0, post_count: 0 },
    { id: 5, name: 'Complaints & Internal Affairs', description: 'Formal complaints and IA investigations', thread_count: 0, post_count: 0 },
  ],
  lssd: [
    { id: 6, name: 'General Discussion', description: 'Sheriff department community discussions', thread_count: 0, post_count: 0 },
    { id: 7, name: 'Training Academy', description: 'Training schedules and academy info', thread_count: 0, post_count: 0 },
    { id: 8, name: 'Patrol & Warrants', description: 'Patrol operations and warrant requests', thread_count: 0, post_count: 0 },
  ],
  lsfd: [
    { id: 9, name: 'Station Announcements', description: 'Official LSFD announcements', thread_count: 0, post_count: 0 },
    { id: 10, name: 'EMS Operations', description: 'Emergency medical services coordination', thread_count: 0, post_count: 0 },
    { id: 11, name: 'Fire Suppression', description: 'Fire response and suppression tactics', thread_count: 0, post_count: 0 },
  ],
  lsn: [
    { id: 12, name: 'Newsroom', description: 'Breaking news and article drafts', thread_count: 0, post_count: 0 },
    { id: 13, name: 'Tips & Tricks', description: 'Reporting and journalism tips', thread_count: 0, post_count: 0 },
  ],
}

export default function Department() {
  const { department } = useParams()
  const { user } = useAuth()
  const config = departmentConfig[department?.toLowerCase()] || departmentConfig.lspd
  const defaultDeptForums = defaultForums[department?.toLowerCase()] || defaultForums.lspd

  const [departmentData, setDepartmentData] = useState(null)
  const [forums, setForums] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    fetchDepartmentData()
  }, [department])

  const fetchDepartmentData = async () => {
    setLoading(true)
    setError(null)

    try {
      // Fetch department info and forums in parallel
      const [deptRes, forumsRes] = await Promise.allSettled([
        departmentsApi.getById(department),
        departmentsApi.getForums(department),
      ])

      // Process department data
      if (deptRes.status === 'fulfilled') {
        const data = deptRes.value.department || deptRes.value
        if (data) {
          setDepartmentData(data)
          // Override config with API data if available
          if (data.name) {
            config.name = data.code || data.name
            config.fullName = data.name
            config.color = data.color || config.color
          }
        }
      }

      // Process forums data
      if (forumsRes.status === 'fulfilled') {
        const forumsData = forumsRes.value.forums || forumsRes.value.data || []
        if (forumsData.length > 0) {
          setForums(forumsData)
        } else {
          setForums(defaultDeptForums)
        }
      } else {
        setForums(defaultDeptForums)
      }
    } catch (err) {
      console.error('Failed to fetch department data:', err)
      setForums(defaultDeptForums)
    } finally {
      setLoading(false)
    }
  }

  // Loading state
  if (loading) {
    return (
      <div className="min-h-[50vh] flex items-center justify-center">
        <LoadingSpinner size="lg" />
      </div>
    )
  }

  // Error state
  if (error) {
    return <ErrorState message={error} onRetry={fetchDepartmentData} />
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="relative overflow-hidden rounded-2xl p-6 md:p-8"
        style={{ background: `linear-gradient(135deg, ${config.color}30 0%, ${config.color}10 100%)` }}
      >
        <div className="flex items-center gap-4">
          <div
            className="w-16 h-16 md:w-20 md:h-20 rounded-xl flex items-center justify-center overflow-hidden"
            style={{ backgroundColor: `${config.color}40` }}
          >
            <img src={config.logo} alt={config.name} className="w-12 h-12 md:w-16 md:h-16 object-contain" />
          </div>
          <div>
            <h1 className="text-2xl md:text-3xl font-bold text-white">{config.name}</h1>
            <p className="text-gray-300">{config.fullName}</p>
            {departmentData?.description && (
              <p className="text-sm text-gray-400 mt-2 max-w-2xl">{departmentData.description}</p>
            )}
          </div>
        </div>
      </motion.div>

      {/* Forums List */}
      <div className="bg-gray-800/50 backdrop-blur-sm rounded-xl border border-gray-700 overflow-hidden">
        <div className="p-4 border-b border-gray-700">
          <h2 className="text-lg font-semibold text-white">Forums</h2>
        </div>
        <div className="divide-y divide-gray-700">
          {forums.length > 0 ? (
            forums.map((forum, index) => (
              <motion.div
                key={forum.id}
                initial={{ opacity: 0, x: -20 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ delay: index * 0.05 }}
              >
                <Link
                  to={`/forum/${forum.slug || forum.id}`}
                  className="flex items-center justify-between p-4 hover:bg-gray-700/30 transition-colors"
                >
                  <div className="flex-1 min-w-0">
                    <h3 className="text-white font-medium group-hover:text-purple-400 transition-colors">
                      {forum.name}
                    </h3>
                    <p className="text-sm text-gray-400 truncate">{forum.description}</p>
                  </div>
                  <div className="hidden md:flex items-center gap-8 text-sm text-gray-400 ml-4">
                    <div className="text-center">
                      <div className="text-white font-medium">{forum.thread_count || forum.threads || 0}</div>
                      <div className="text-xs">Threads</div>
                    </div>
                    <div className="text-center">
                      <div className="text-white font-medium">{forum.post_count || forum.posts || 0}</div>
                      <div className="text-xs">Posts</div>
                    </div>
                  </div>
                  <svg className="w-5 h-5 text-gray-500 ml-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                  </svg>
                </Link>
              </motion.div>
            ))
          ) : (
            <EmptyState
              icon="📋"
              title="No forums yet"
              description="This department hasn't set up any forums yet."
            />
          )}
        </div>
      </div>
    </div>
  )
}