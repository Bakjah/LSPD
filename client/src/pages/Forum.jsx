import { useState, useEffect } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useAuth } from '../context/AuthContext'
import { topicsApi } from '../services/api'
import { EmptyState, ErrorState } from '../components/UI'
import { LoadingSpinner } from '../components/LoadingSpinner'

export default function Forum() {
  const { forumSlug } = useParams()
  const navigate = useNavigate()
  const { user } = useAuth()

  const [threads, setThreads] = useState([])
  const [pagination, setPagination] = useState({})
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [showNewThread, setShowNewThread] = useState(false)
  const [creating, setCreating] = useState(false)
  const [newThread, setNewThread] = useState({ title: '', content: '' })
  const [formError, setFormError] = useState('')

  // Fetch threads
  useEffect(() => {
    fetchThreads()
  }, [forumSlug])

  const fetchThreads = async (page = 1) => {
    setLoading(true)
    setError(null)

    try {
      const response = await topicsApi.getAll({
        forum: forumSlug,
        page,
        per_page: 20,
      })

      setThreads(response.topics || response.data || [])
      setPagination(response.pagination || {})
    } catch (err) {
      console.error('Failed to fetch threads:', err)
      setError('Failed to load threads. Please try again.')
    } finally {
      setLoading(false)
    }
  }

  const handleCreateThread = async (e) => {
    e.preventDefault()
    setFormError('')

    if (!newThread.title.trim() || !newThread.content.trim()) {
      setFormError('Title and content are required')
      return
    }

    setCreating(true)

    try {
      const response = await topicsApi.create({
        title: newThread.title,
        content: newThread.content,
        forum_slug: forumSlug,
      })

      // Close modal and reset form
      setShowNewThread(false)
      setNewThread({ title: '', content: '' })

      // Navigate to the new topic
      if (response.topic?.id) {
        navigate(`/topic/${response.topic.id}`)
      } else {
        // Refresh the thread list
        fetchThreads()
      }
    } catch (err) {
      setFormError(err.response?.data?.error || 'Failed to create thread')
    } finally {
      setCreating(false)
    }
  }

  // Format date to relative time
  const formatDate = (dateString) => {
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

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <Link to="/" className="text-purple-400 hover:text-purple-300 text-sm mb-2 inline-block">
            ← Back to Home
          </Link>
          <h1 className="text-2xl md:text-3xl font-bold text-white capitalize">
            {forumSlug.replace(/-/g, ' ')}
          </h1>
        </div>
        {user && (
          <button
            onClick={() => setShowNewThread(true)}
            className="px-6 py-3 bg-purple-600 hover:bg-purple-500 text-white font-semibold rounded-lg transition-colors flex items-center gap-2"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
            </svg>
            New Thread
          </button>
        )}
      </div>

      {/* New Thread Modal */}
      {showNewThread && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
          onClick={() => setShowNewThread(false)}
        >
          <motion.div
            initial={{ scale: 0.9, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            className="bg-gray-800 rounded-xl border border-gray-700 p-6 w-full max-w-2xl"
            onClick={(e) => e.stopPropagation()}
          >
            <h2 className="text-xl font-bold text-white mb-4">Create New Thread</h2>

            {formError && (
              <div className="mb-4 p-3 rounded-lg bg-red-500/20 border border-red-500/50 text-red-400 text-sm">
                {formError}
              </div>
            )}

            <form onSubmit={handleCreateThread} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-300 mb-2">Title</label>
                <input
                  type="text"
                  required
                  value={newThread.title}
                  onChange={(e) => setNewThread({ ...newThread, title: e.target.value })}
                  className="w-full px-4 py-3 rounded-lg bg-gray-900/50 border border-gray-700 text-white placeholder-gray-500 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500"
                  placeholder="Thread title..."
                  disabled={creating}
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-300 mb-2">Content</label>
                <textarea
                  required
                  rows={8}
                  value={newThread.content}
                  onChange={(e) => setNewThread({ ...newThread, content: e.target.value })}
                  className="w-full px-4 py-3 rounded-lg bg-gray-900/50 border border-gray-700 text-white placeholder-gray-500 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500 resize-none"
                  placeholder="Write your thread content..."
                  disabled={creating}
                />
              </div>
              <div className="flex gap-3">
                <button
                  type="button"
                  onClick={() => setShowNewThread(false)}
                  disabled={creating}
                  className="flex-1 py-3 px-4 bg-gray-700 hover:bg-gray-600 disabled:opacity-50 text-white font-semibold rounded-lg transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={creating}
                  className="flex-1 py-3 px-4 bg-purple-600 hover:bg-purple-500 disabled:bg-purple-600/50 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2"
                >
                  {creating ? (
                    <>
                      <LoadingSpinner size="sm" />
                      Creating...
                    </>
                  ) : (
                    'Create Thread'
                  )}
                </button>
              </div>
            </form>
          </motion.div>
        </motion.div>
      )}

      {/* Loading State */}
      {loading && (
        <div className="min-h-[30vh] flex items-center justify-center">
          <LoadingSpinner size="lg" />
        </div>
      )}

      {/* Error State */}
      {error && !loading && (
        <ErrorState message={error} onRetry={fetchThreads} />
      )}

      {/* Threads List */}
      {!loading && !error && (
        <div className="bg-gray-800/50 backdrop-blur-sm rounded-xl border border-gray-700 overflow-hidden">
          {/* Table Header */}
          <div className="grid grid-cols-12 gap-4 p-4 border-b border-gray-700 text-sm font-medium text-gray-400">
            <div className="col-span-12 md:col-span-7 lg:col-span-8">Thread</div>
            <div className="col-span-3 md:col-span-2 text-center hidden md:block">Replies</div>
            <div className="col-span-3 md:col-span-2 text-center hidden md:block">Views</div>
            <div className="col-span-12 md:col-span-3 lg:col-span-2 text-right">Last Post</div>
          </div>

          {/* Table Body */}
          <div className="divide-y divide-gray-700">
            {threads.length === 0 ? (
              <EmptyState
                icon="💬"
                title="No threads yet"
                description="Be the first to start a discussion in this forum!"
                action={
                  user ? (
                    <button
                      onClick={() => setShowNewThread(true)}
                      className="px-6 py-2 bg-purple-600 hover:bg-purple-500 text-white font-medium rounded-lg transition-colors"
                    >
                      Start a Discussion
                    </button>
                  ) : (
                    <Link
                      to="/login"
                      className="px-6 py-2 bg-purple-600 hover:bg-purple-500 text-white font-medium rounded-lg transition-colors"
                    >
                      Login to Post
                    </Link>
                  )
                }
              />
            ) : (
              threads.map((thread, index) => (
                <motion.div
                  key={thread.id}
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: index * 0.03 }}
                >
                  <Link
                    to={`/topic/${thread.id}`}
                    className="grid grid-cols-12 gap-4 p-4 hover:bg-gray-700/30 transition-colors items-center"
                  >
                    <div className="col-span-12 md:col-span-7 lg:col-span-8 flex items-center gap-3">
                      {thread.is_pinned && (
                        <span className="px-2 py-0.5 text-xs bg-purple-500/20 text-purple-400 rounded flex-shrink-0">
                          Pinned
                        </span>
                      )}
                      {thread.is_locked && (
                        <span className="px-2 py-0.5 text-xs bg-gray-500/20 text-gray-400 rounded flex-shrink-0">
                          Locked
                        </span>
                      )}
                      <div className="min-w-0">
                        <h3 className="text-white font-medium truncate">{thread.title}</h3>
                        <p className="text-sm text-gray-400">
                          by <span className="text-purple-400">{thread.author?.username || thread.username || 'Unknown'}</span>
                        </p>
                      </div>
                    </div>
                    <div className="col-span-3 md:col-span-2 text-center hidden md:block text-gray-400">
                      {thread.reply_count || thread.replies || 0}
                    </div>
                    <div className="col-span-3 md:col-span-2 text-center hidden md:block text-gray-400">
                      {thread.view_count || thread.views || 0}
                    </div>
                    <div className="col-span-12 md:col-span-3 lg:col-span-2 text-right text-sm text-gray-400">
                      {formatDate(thread.last_post_at || thread.lastPost)}
                    </div>
                  </Link>
                </motion.div>
              ))
            )}
          </div>

          {/* Pagination */}
          {pagination.total_pages > 1 && (
            <div className="p-4 border-t border-gray-700 flex items-center justify-between">
              <span className="text-sm text-gray-400">
                Page {pagination.page} of {pagination.total_pages}
              </span>
              <div className="flex gap-2">
                <button
                  onClick={() => fetchThreads(pagination.page - 1)}
                  disabled={pagination.page <= 1}
                  className="px-3 py-1 rounded bg-gray-700 hover:bg-gray-600 disabled:opacity-50 text-white text-sm transition-colors"
                >
                  Previous
                </button>
                <button
                  onClick={() => fetchThreads(pagination.page + 1)}
                  disabled={pagination.page >= pagination.total_pages}
                  className="px-3 py-1 rounded bg-gray-700 hover:bg-gray-600 disabled:opacity-50 text-white text-sm transition-colors"
                >
                  Next
                </button>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  )
}