import { useState, useEffect } from 'react'
import { useParams, Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useAuth } from '../context/AuthContext'
import { profileApi } from '../services/api'
import { EmptyState, ErrorState, Avatar, Badge } from '../components/UI'
import { LoadingSpinner } from '../components/LoadingSpinner'

export default function Profile() {
  const { username } = useParams()
  const { user: currentUser } = useAuth()

  const [profile, setProfile] = useState(null)
  const [activity, setActivity] = useState({ threads: [], replies: [] })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [activeTab, setActiveTab] = useState('threads')

  useEffect(() => {
    fetchProfile()
  }, [username])

  const fetchProfile = async () => {
    setLoading(true)
    setError(null)

    try {
      const response = await profileApi.get(username)

      if (response.user) {
        setProfile(response.user)
      } else if (response.profile) {
        setProfile(response.profile)
      } else {
        setProfile(response)
      }

      // Fetch activity if available
      try {
        const activityRes = await profileApi.getActivity(username)
        setActivity({
          threads: activityRes.threads || [],
          replies: activityRes.replies || [],
        })
      } catch {
        // Activity endpoint might not exist yet
      }
    } catch (err) {
      console.error('Failed to fetch profile:', err)
      setError('Failed to load profile. Please try again.')
    } finally {
      setLoading(false)
    }
  }

  const formatDate = (dateString) => {
    if (!dateString) return ''
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    })
  }

  const formatJoinDate = (dateString) => {
    if (!dateString) return ''
    const date = new Date(dateString)
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long' })
  }

  const getRoleBadge = (role) => {
    if (!role) return null
    return (
      <Badge
        variant="primary"
        style={{ backgroundColor: `${role.color || '#94A3B8'}20`, color: role.color || '#94A3B8' }}
      >
        {role.badge && <span className="mr-1">{role.badge}</span>}
        {role.name}
      </Badge>
    )
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
    return <ErrorState message={error} onRetry={fetchProfile} />
  }

  // Profile not found
  if (!profile) {
    return (
      <EmptyState
        icon="👤"
        title="Profile not found"
        description="This user doesn't exist or their profile is private."
        actionTo="/"
      />
    )
  }

  const isOwnProfile = currentUser?.username === profile.username || currentUser?.uuid === profile.uuid

  // Get primary role
  const primaryRole = profile.primary_role || profile.role || null
  const primaryDept = profile.primary_department || profile.department || null

  return (
    <div className="space-y-6">
      {/* Profile Header */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="bg-gray-800/50 backdrop-blur-sm rounded-xl border border-gray-700 overflow-hidden"
      >
        {/* Cover Photo */}
        <div
          className="h-32 md:h-48 bg-gradient-to-r from-purple-600/30 to-indigo-600/30"
          style={{
            backgroundImage: profile.cover_photo ? `url(${profile.cover_photo})` : undefined,
            backgroundSize: 'cover',
            backgroundPosition: 'center',
          }}
        />

        <div className="px-6 pb-6">
          <div className="flex flex-col md:flex-row md:items-end gap-4 -mt-12">
            {/* Avatar */}
            <div className="relative">
              <Avatar
                src={profile.avatar}
                name={profile.username}
                size="2xl"
                className="border-4 border-gray-800"
              />
              {profile.is_online && (
                <span className="absolute bottom-2 right-2 w-4 h-4 bg-green-500 rounded-full border-2 border-gray-800" />
              )}
            </div>

            {/* User Info */}
            <div className="flex-1">
              <div className="flex flex-wrap items-center gap-3 mb-2">
                <h1 className="text-2xl font-bold text-white">{profile.username}</h1>
                {getRoleBadge(primaryRole)}
                {primaryDept && (
                  <Badge variant="default">{primaryDept.name || primaryDept}</Badge>
                )}
              </div>
              <p className="text-gray-400 text-sm">
                Member since {formatJoinDate(profile.created_at || profile.join_date || profile.joinDate)}
              </p>
              {profile.last_seen && (
                <p className="text-gray-500 text-xs mt-1">
                  Last seen: {formatDate(profile.last_seen)}
                </p>
              )}
            </div>

            {/* Actions */}
            {isOwnProfile && (
              <button className="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors">
                Edit Profile
              </button>
            )}
          </div>
        </div>
      </motion.div>

      {/* Stats & Bio */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        {/* Bio Section */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
          className="md:col-span-2 bg-gray-800/50 backdrop-blur-sm rounded-xl border border-gray-700 p-6"
        >
          <h2 className="text-lg font-semibold text-white mb-4">About</h2>
          <p className="text-gray-300 mb-4 whitespace-pre-wrap">
            {profile.biography || profile.bio || 'No biography yet.'}
          </p>

          {/* Discord */}
          {profile.discord && (
            <div className="flex items-center gap-2 text-gray-400">
              <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z" />
              </svg>
              <span>{profile.discord}</span>
            </div>
          )}

          {/* Roles & Departments */}
          {profile.roles && profile.roles.length > 0 && (
            <div className="mt-4 pt-4 border-t border-gray-700">
              <h3 className="text-sm font-medium text-gray-400 mb-2">Roles</h3>
              <div className="flex flex-wrap gap-2">
                {profile.roles.map((role) => getRoleBadge(role))}
              </div>
            </div>
          )}

          {profile.departments && profile.departments.length > 0 && (
            <div className="mt-4 pt-4 border-t border-gray-700">
              <h3 className="text-sm font-medium text-gray-400 mb-2">Departments</h3>
              <div className="flex flex-wrap gap-2">
                {profile.departments.map((dept) => (
                  <Badge key={dept.id || dept} variant="default">
                    {dept.name || dept.code || dept}
                  </Badge>
                ))}
              </div>
            </div>
          )}
        </motion.div>

        {/* Stats Section */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.15 }}
          className="bg-gray-800/50 backdrop-blur-sm rounded-xl border border-gray-700 p-6"
        >
          <h2 className="text-lg font-semibold text-white mb-4">Stats</h2>
          <div className="space-y-4">
            <div className="flex justify-between items-center">
              <span className="text-gray-400">Posts</span>
              <span className="text-white font-medium">
                {(profile.post_count || profile.posts || 0).toLocaleString()}
              </span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-gray-400">Threads</span>
              <span className="text-white font-medium">
                {(profile.thread_count || profile.threads || 0).toLocaleString()}
              </span>
            </div>
            {profile.reputation !== undefined && (
              <div className="flex justify-between items-center">
                <span className="text-gray-400">Reputation</span>
                <span className="text-purple-400 font-medium">
                  {(profile.reputation || 0).toLocaleString()}
                </span>
              </div>
            )}
            {profile.medals_count !== undefined && (
              <div className="flex justify-between items-center">
                <span className="text-gray-400">Medals</span>
                <span className="text-yellow-400 font-medium">
                  {profile.medals_count || 0}
                </span>
              </div>
            )}
          </div>
        </motion.div>
      </div>

      {/* Activity Tabs */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.2 }}
        className="bg-gray-800/50 backdrop-blur-sm rounded-xl border border-gray-700 overflow-hidden"
      >
        <div className="flex border-b border-gray-700">
          <button
            onClick={() => setActiveTab('threads')}
            className={`px-6 py-4 font-medium transition-colors ${
              activeTab === 'threads'
                ? 'text-purple-400 border-b-2 border-purple-400'
                : 'text-gray-400 hover:text-white'
            }`}
          >
            Threads ({activity.threads.length})
          </button>
          <button
            onClick={() => setActiveTab('replies')}
            className={`px-6 py-4 font-medium transition-colors ${
              activeTab === 'replies'
                ? 'text-purple-400 border-b-2 border-purple-400'
                : 'text-gray-400 hover:text-white'
            }`}
          >
            Replies ({activity.replies.length})
          </button>
        </div>

        <div className="p-4">
          {activeTab === 'threads' ? (
            activity.threads.length > 0 ? (
              <div className="space-y-3">
                {activity.threads.map((thread) => (
                  <Link
                    key={thread.id}
                    to={`/topic/${thread.id}`}
                    className="block p-4 rounded-lg bg-gray-900/50 hover:bg-gray-900 border border-gray-700 transition-colors"
                  >
                    <h3 className="text-white font-medium mb-1">{thread.title}</h3>
                    <div className="flex items-center gap-4 text-sm text-gray-400">
                      <span>{formatDate(thread.created_at)}</span>
                      <span>{thread.reply_count || thread.replies || 0} replies</span>
                    </div>
                  </Link>
                ))}
              </div>
            ) : (
              <p className="text-center text-gray-400 py-8">No threads yet.</p>
            )
          ) : activity.replies.length > 0 ? (
            <div className="space-y-3">
              {activity.replies.map((reply) => (
                <Link
                  key={reply.id}
                  to={`/topic/${reply.topic_id || reply.topicId}`}
                  className="block p-4 rounded-lg bg-gray-900/50 hover:bg-gray-900 border border-gray-700 transition-colors"
                >
                  <h3 className="text-white font-medium mb-1">{reply.title || reply.topic_title}</h3>
                  <p className="text-sm text-gray-400 truncate mb-2">{reply.content || reply.excerpt}</p>
                  <span className="text-xs text-gray-500">{formatDate(reply.created_at)}</span>
                </Link>
              ))}
            </div>
          ) : (
            <p className="text-center text-gray-400 py-8">No replies yet.</p>
          )}
        </div>
      </motion.div>
    </div>
  )
}
