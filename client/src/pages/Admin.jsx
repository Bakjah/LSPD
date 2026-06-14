import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useAuth } from '../context/AuthContext'
import api from '../services/api'

export default function Admin() {
  const { user } = useAuth()
  const [activeTab, setActiveTab] = useState('overview')
  const [stats, setStats] = useState({
    users: 0,
    threads: 0,
    posts: 0,
    reports: 0,
  })
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    setTimeout(() => {
      setStats({
        users: 2847,
        threads: 1203,
        posts: 8456,
        reports: 12,
      })
      setLoading(false)
    }, 300)
  }, [])

  const adminTabs = [
    { id: 'overview', label: 'Overview', icon: '📊' },
    { id: 'users', label: 'Users', icon: '👥' },
    { id: 'threads', label: 'Threads', icon: '📝' },
    { id: 'reports', label: 'Reports', icon: '🚨' },
    { id: 'settings', label: 'Settings', icon: '⚙️' },
  ]

  if (!user) {
    return (
      <div className="flex items-center justify-center min-h-[50vh]">
        <div className="text-center">
          <p className="text-gray-400 mb-4">Please sign in to access admin panel.</p>
          <Link to="/login" className="text-purple-400 hover:text-purple-300 font-medium">
            Sign In
          </Link>
        </div>
      </div>
    )
  }

  // Check if user has admin privileges (simplified check)
  const isAdmin = user.role === 'admin' || user.role === 'moderator'

  if (!isAdmin) {
    return (
      <div className="flex items-center justify-center min-h-[50vh]">
        <div className="text-center">
          <svg className="w-16 h-16 mx-auto mb-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <h2 className="text-xl font-bold text-white mb-2">Access Denied</h2>
          <p className="text-gray-400">You do not have permission to access the admin panel.</p>
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl md:text-3xl font-bold text-white">Admin Panel</h1>
          <p className="text-gray-400">Manage your community</p>
        </div>
        <div className="px-3 py-1 bg-purple-500/20 text-purple-400 rounded-lg text-sm font-medium">
          {user.role === 'admin' ? 'Administrator' : 'Moderator'}
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {[
          { label: 'Total Users', value: stats.users.toLocaleString(), icon: '👥' },
          { label: 'Total Threads', value: stats.threads.toLocaleString(), icon: '📝' },
          { label: 'Total Posts', value: stats.posts.toLocaleString(), icon: '💬' },
          { label: 'Open Reports', value: stats.reports, icon: '🚨' },
        ].map((stat, index) => (
          <motion.div
            key={stat.label}
            initial={{ opacity: 0, scale: 0.9 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ delay: index * 0.05 }}
            className="p-4 rounded-xl bg-gray-800/50 border border-gray-700"
          >
            <div className="flex items-center gap-3 mb-2">
              <span className="text-2xl">{stat.icon}</span>
              <div>
                <div className="text-2xl font-bold text-white">{stat.value}</div>
                <div className="text-sm text-gray-400">{stat.label}</div>
              </div>
            </div>
          </motion.div>
        ))}
      </div>

      {/* Tabs */}
      <div className="bg-gray-800/50 backdrop-blur-sm rounded-xl border border-gray-700 overflow-hidden">
        <div className="flex overflow-x-auto border-b border-gray-700">
          {adminTabs.map((tab) => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={`px-6 py-4 font-medium transition-colors whitespace-nowrap ${
                activeTab === tab.id
                  ? 'text-purple-400 border-b-2 border-purple-400'
                  : 'text-gray-400 hover:text-white'
              }`}
            >
              <span className="mr-2">{tab.icon}</span>
              {tab.label}
            </button>
          ))}
        </div>

        <div className="p-6">
          {activeTab === 'overview' && (
            <div className="space-y-6">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="p-4 rounded-lg bg-gray-900/50 border border-gray-700">
                  <h3 className="text-lg font-semibold text-white mb-4">Recent Activity</h3>
                  <div className="space-y-3">
                    {[
                      { action: 'New user registered', time: '5 min ago' },
                      { action: 'Thread deleted by mod', time: '15 min ago' },
                      { action: 'New report submitted', time: '30 min ago' },
                      { action: 'User warned', time: '1 hour ago' },
                    ].map((item, i) => (
                      <div key={i} className="flex justify-between items-center text-sm">
                        <span className="text-gray-300">{item.action}</span>
                        <span className="text-gray-500">{item.time}</span>
                      </div>
                    ))}
                  </div>
                </div>

                <div className="p-4 rounded-lg bg-gray-900/50 border border-gray-700">
                  <h3 className="text-lg font-semibold text-white mb-4">Quick Actions</h3>
                  <div className="grid grid-cols-2 gap-3">
                    <button className="p-3 rounded-lg bg-gray-700 hover:bg-gray-600 text-white text-sm transition-colors">
                      Create Announcement
                    </button>
                    <button className="p-3 rounded-lg bg-gray-700 hover:bg-gray-600 text-white text-sm transition-colors">
                      Manage Forums
                    </button>
                    <button className="p-3 rounded-lg bg-gray-700 hover:bg-gray-600 text-white text-sm transition-colors">
                      View Logs
                    </button>
                    <button className="p-3 rounded-lg bg-gray-700 hover:bg-gray-600 text-white text-sm transition-colors">
                      Export Data
                    </button>
                  </div>
                </div>
              </div>
            </div>
          )}

          {activeTab === 'users' && (
            <div className="text-center text-gray-400 py-8">
              <svg className="w-12 h-12 mx-auto mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
              </svg>
              <p>User management coming soon</p>
            </div>
          )}

          {activeTab === 'threads' && (
            <div className="text-center text-gray-400 py-8">
              <svg className="w-12 h-12 mx-auto mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <p>Thread management coming soon</p>
            </div>
          )}

          {activeTab === 'reports' && (
            <div className="space-y-4">
              {[1, 2, 3].map((report) => (
                <div key={report} className="p-4 rounded-lg bg-gray-900/50 border border-gray-700">
                  <div className="flex items-center justify-between mb-2">
                    <span className="px-2 py-1 bg-red-500/20 text-red-400 rounded text-xs">Pending</span>
                    <span className="text-sm text-gray-500">2 hours ago</span>
                  </div>
                  <h4 className="text-white font-medium mb-1">Report #{report}</h4>
                  <p className="text-sm text-gray-400 mb-3">User reported for inappropriate content in thread</p>
                  <div className="flex gap-2">
                    <button className="px-3 py-1 bg-red-600 hover:bg-red-500 text-white text-sm rounded transition-colors">
                      Dismiss
                    </button>
                    <button className="px-3 py-1 bg-yellow-600 hover:bg-yellow-500 text-white text-sm rounded transition-colors">
                      Warn User
                    </button>
                    <button className="px-3 py-1 bg-gray-600 hover:bg-gray-500 text-white text-sm rounded transition-colors">
                      View Details
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}

          {activeTab === 'settings' && (
            <div className="text-center text-gray-400 py-8">
              <svg className="w-12 h-12 mx-auto mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              <p>Site settings coming soon</p>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
