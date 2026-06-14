import { useState, useEffect } from 'react'
import { useParams, Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useAuth } from '../context/AuthContext'
import { topicsApi, postsApi } from '../services/api'
import { EmptyState, ErrorState, Avatar } from '../components/UI'
import { LoadingSpinner } from '../components/LoadingSpinner'

export default function Topic() {
  const { topicId } = useParams()
  const { user } = useAuth()

  const [topic, setTopic] = useState(null)
  const [posts, setPosts] = useState([])
  const [pagination, setPagination] = useState({})
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [replyContent, setReplyContent] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [replyError, setReplyError] = useState('')

  // Fetch topic and posts
  useEffect(() => {
    fetchTopic()
  }, [topicId])

  const fetchTopic = async (page = 1) => {
    setLoading(true)
    setError(null)

    try {
      const [topicRes, postsRes] = await Promise.allSettled([
        topicsApi.getById(topicId),
        postsApi.getAll(topicId, { page }),
      ])

      if (topicRes.status === 'fulfilled') {
        setTopic(topicRes.value.topic || topicRes.value)
      } else {
        throw new Error('Failed to load topic')
      }

      if (postsRes.status === 'fulfilled') {
        setPosts(postsRes.value.posts || postsRes.value.data || [])
        setPagination(postsRes.value.pagination || {})
      }
    } catch (err) {
      console.error('Failed to fetch topic:', err)
      setError('Failed to load topic. Please try again.')
    } finally {
      setLoading(false)
    }
  }

  const handleSubmitReply = async (e) => {
    e.preventDefault()
    setReplyError('')

    if (!replyContent.trim()) {
      setReplyError('Reply content is required')
      return
    }

    setSubmitting(true)

    try {
      const response = await postsApi.create(topicId, {
        content: replyContent,
      })

      // Add new post to list
      const newPost = response.post || response
      setPosts((prev) => [...prev, newPost])
      setReplyContent('')

      // Update topic reply count
      if (topic) {
        setTopic((prev) => ({
          ...prev,
          reply_count: (prev.reply_count || 0) + 1,
        }))
      }
    } catch (err) {
      setReplyError(err.response?.data?.error || 'Failed to post reply')
    } finally {
      setSubmitting(false)
    }
  }

  const formatDate = (dateString) => {
    if (!dateString) return ''
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
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
    return <ErrorState message={error} onRetry={() => fetchTopic()} />
  }

  // Topic not found
  if (!topic) {
    return (
      <EmptyState
        icon="❌"
        title="Topic not found"
        description="This topic may have been deleted or doesn't exist."
        actionTo="/"
      />
    )
  }

  return (
    <div className="space-y-6">
      {/* Back Link */}
      <Link
        to="/"
        className="text-purple-400 hover:text-purple-300 text-sm inline-flex items-center gap-2 transition-colors"
      >
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
        </svg>
        Back to Forum
      </Link>

      {/* Topic Header */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="bg-gray-800/50 backdrop-blur-sm rounded-xl border border-gray-700 overflow-hidden"
      >
        <div className="p-6 border-b border-gray-700">
          <div className="flex items-start justify-between gap-4 mb-4">
            <h1 className="text-2xl font-bold text-white">{topic.title}</h1>
            {topic.is_locked && (
              <span className="px-3 py-1 bg-red-500/20 text-red-400 rounded-lg text-sm font-medium flex-shrink-0">
                🔒 Locked
              </span>
            )}
          </div>
          <div className="flex flex-wrap items-center gap-4 text-sm text-gray-400">
            <Link
              to={`/profile/${topic.author?.username || topic.username}`}
              className="flex items-center gap-2 text-purple-400 hover:text-purple-300 transition-colors"
            >
              <Avatar
                src={topic.author?.avatar}
                name={topic.author?.username || topic.username}
                size="sm"
              />
              <span>{topic.author?.username || topic.username}</span>
            </Link>
            {topic.author_role && (
              <span
                className="px-2 py-1 rounded text-xs font-medium"
                style={{ backgroundColor: `${topic.author_role.color || '#94A3B8'}20`, color: topic.author_role.color || '#94A3B8' }}
              >
                {topic.author_role.name}
              </span>
            )}
            <span>{formatDate(topic.created_at || topic.createdAt)}</span>
            <span className="flex items-center gap-1">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              {topic.view_count || topic.views || 0} views
            </span>
          </div>
        </div>
        <div className="p-6">
          <div className="prose prose-invert max-w-none text-gray-300 whitespace-pre-wrap">
            {topic.content}
          </div>
        </div>
      </motion.div>

      {/* Replies */}
      <div className="space-y-4">
        <h2 className="text-lg font-semibold text-white">
          {posts.length} {posts.length === 1 ? 'Reply' : 'Replies'}
        </h2>

        {posts.length === 0 ? (
          <div className="p-8 rounded-lg bg-gray-800/50 border border-gray-700 text-center">
            <p className="text-gray-400">No replies yet. Be the first to respond!</p>
          </div>
        ) : (
          posts.map((post, index) => (
            <motion.div
              key={post.id}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: index * 0.05 }}
              className="bg-gray-800/50 backdrop-blur-sm rounded-xl border border-gray-700 p-6"
            >
              <div className="flex gap-4">
                <Link to={`/profile/${post.author?.username || post.username}`} className="flex-shrink-0">
                  <Avatar
                    src={post.author?.avatar}
                    name={post.author?.username || post.username}
                    size="md"
                  />
                </Link>
                <div className="flex-1 min-w-0">
                  <div className="flex flex-wrap items-center gap-2 mb-2">
                    <Link
                      to={`/profile/${post.author?.username || post.username}`}
                      className="text-purple-400 hover:text-purple-300 font-medium transition-colors"
                    >
                      {post.author?.username || post.username}
                    </Link>
                    {post.author_role && (
                      <span
                        className="px-2 py-0.5 rounded text-xs font-medium"
                        style={{ backgroundColor: `${post.author_role.color || '#94A3B8'}20`, color: post.author_role.color || '#94A3B8' }}
                      >
                        {post.author_role.name}
                      </span>
                    )}
                    <span className="text-sm text-gray-500">
                      {formatDate(post.created_at || post.createdAt)}
                    </span>
                    {post.edited_at && (
                      <span className="text-xs text-gray-600">(edited)</span>
                    )}
                  </div>
                  <div className="text-gray-300 whitespace-pre-wrap">{post.content}</div>
                </div>
              </div>
            </motion.div>
          ))
        )}
      </div>

      {/* Pagination */}
      {pagination.total_pages > 1 && (
        <div className="flex items-center justify-center gap-2">
          {Array.from({ length: pagination.total_pages }, (_, i) => i + 1).map((page) => (
            <button
              key={page}
              onClick={() => fetchTopic(page)}
              className={`px-3 py-1 rounded ${
                page === pagination.page
                  ? 'bg-purple-600 text-white'
                  : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
              } transition-colors`}
            >
              {page}
            </button>
          ))}
        </div>
      )}

      {/* Reply Form */}
      {user ? (
        topic.is_locked ? (
          <div className="bg-gray-800/50 backdrop-blur-sm rounded-xl border border-gray-700 p-6 text-center">
            <p className="text-gray-400 flex items-center justify-center gap-2">
              <span>🔒</span> This topic is locked and cannot receive new replies.
            </p>
          </div>
        ) : (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className="bg-gray-800/50 backdrop-blur-sm rounded-xl border border-gray-700 p-6"
          >
            <h3 className="text-lg font-semibold text-white mb-4">Post a Reply</h3>

            {replyError && (
              <div className="mb-4 p-3 rounded-lg bg-red-500/20 border border-red-500/50 text-red-400 text-sm">
                {replyError}
              </div>
            )}

            <form onSubmit={handleSubmitReply}>
              <textarea
                value={replyContent}
                onChange={(e) => setReplyContent(e.target.value)}
                rows={5}
                className="w-full px-4 py-3 rounded-lg bg-gray-900/50 border border-gray-700 text-white placeholder-gray-500 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500 resize-none mb-4"
                placeholder="Write your reply..."
                disabled={submitting}
              />
              <button
                type="submit"
                disabled={submitting || !replyContent.trim()}
                className="px-6 py-3 bg-purple-600 hover:bg-purple-500 disabled:bg-purple-600/50 disabled:cursor-not-allowed text-white font-semibold rounded-lg transition-colors flex items-center gap-2"
              >
                {submitting ? (
                  <>
                    <LoadingSpinner size="sm" />
                    Posting...
                  </>
                ) : (
                  'Post Reply'
                )}
              </button>
            </form>
          </motion.div>
        )
      ) : (
        <div className="bg-gray-800/50 backdrop-blur-sm rounded-xl border border-gray-700 p-6 text-center">
          <p className="text-gray-400">
            <Link to="/login" className="text-purple-400 hover:text-purple-300 font-medium transition-colors">
              Sign in
            </Link>{' '}
            to reply to this topic.
          </p>
        </div>
      )}
    </div>
  )
}
