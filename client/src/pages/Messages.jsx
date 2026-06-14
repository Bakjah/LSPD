import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useAuth } from '../context/AuthContext'
import { messagesApi } from '../services/api'
import { EmptyState, ErrorState, Avatar } from '../components/UI'
import { LoadingSpinner } from '../components/LoadingSpinner'

export default function Messages() {
  const { user } = useAuth()

  const [conversations, setConversations] = useState([])
  const [selectedConversation, setSelectedConversation] = useState(null)
  const [messages, setMessages] = useState([])
  const [loading, setLoading] = useState(true)
  const [messagesLoading, setMessagesLoading] = useState(false)
  const [error, setError] = useState(null)
  const [newMessage, setNewMessage] = useState('')
  const [sending, setSending] = useState(false)
  const [searchQuery, setSearchQuery] = useState('')

  useEffect(() => {
    if (user) {
      fetchConversations()
    }
  }, [user])

  useEffect(() => {
    if (selectedConversation) {
      fetchMessages(selectedConversation.id)
    }
  }, [selectedConversation])

  const fetchConversations = async () => {
    setLoading(true)
    setError(null)

    try {
      const response = await messagesApi.getConversations()
      setConversations(response.conversations || response.data || [])
    } catch (err) {
      console.error('Failed to fetch conversations:', err)
      setError('Failed to load conversations.')
    } finally {
      setLoading(false)
    }
  }

  const fetchMessages = async (conversationId) => {
    setMessagesLoading(true)
    try {
      const response = await messagesApi.getConversation(conversationId)
      setMessages(response.messages || response.data || [])
    } catch (err) {
      console.error('Failed to fetch messages:', err)
    } finally {
      setMessagesLoading(false)
    }
  }

  const sendMessage = async (e) => {
    e.preventDefault()
    if (!newMessage.trim() || !selectedConversation) return

    setSending(true)
    try {
      const response = await messagesApi.send({
        recipient_id: selectedConversation.participant?.id || selectedConversation.user_id,
        content: newMessage,
      })

      // Add new message to list
      const sentMessage = response.message || response
      setMessages((prev) => [...prev, sentMessage])
      setNewMessage('')

      // Update conversation last message
      setConversations((prev) =>
        prev.map((c) =>
          c.id === selectedConversation.id
            ? { ...c, last_message: newMessage, updated_at: new Date().toISOString() }
            : c
        )
      )
    } catch (err) {
      console.error('Failed to send message:', err)
    } finally {
      setSending(false)
    }
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
    if (hours < 24) return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    if (days < 7) return `${days}d ago`
    return date.toLocaleDateString()
  }

  const filteredConversations = conversations.filter((conv) =>
    conv.participant?.username?.toLowerCase().includes(searchQuery.toLowerCase()) ||
    conv.last_message?.toLowerCase().includes(searchQuery.toLowerCase())
  )

  // Guest state
  if (!user) {
    return (
      <div className="min-h-[50vh] flex items-center justify-center">
        <EmptyState
          icon="💬"
          title="Sign in required"
          description="Please sign in to view your messages."
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
    <div className="space-y-4">
      <h1 className="text-2xl md:text-3xl font-bold text-white">Messages</h1>

      {/* Main chat container */}
      <div
        className="bg-gray-800/50 backdrop-blur-sm rounded-xl border border-gray-700 overflow-hidden"
        style={{ height: 'calc(100vh - 250px)', minHeight: '500px' }}
      >
        <div className="flex h-full">
          {/* Conversations List */}
          <div className="w-full md:w-80 border-r border-gray-700 flex flex-col">
            {/* Search */}
            <div className="p-4 border-b border-gray-700">
              <div className="relative">
                <input
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Search conversations..."
                  className="w-full px-4 py-2 pl-10 rounded-lg bg-gray-900/50 border border-gray-700 text-white placeholder-gray-500 focus:outline-none focus:border-purple-500"
                />
                <svg className="w-5 h-5 text-gray-500 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </div>
            </div>

            {/* Conversations */}
            <div className="flex-1 overflow-y-auto">
              {loading ? (
                <div className="p-4 flex items-center justify-center">
                  <LoadingSpinner size="md" />
                </div>
              ) : error ? (
                <div className="p-4 text-center text-red-400">{error}</div>
              ) : filteredConversations.length === 0 ? (
                <div className="p-4 text-center text-gray-400">
                  {searchQuery ? 'No conversations found' : 'No conversations yet'}
                </div>
              ) : (
                filteredConversations.map((conv) => (
                  <button
                    key={conv.id}
                    onClick={() => setSelectedConversation(conv)}
                    className={`w-full p-4 text-left hover:bg-gray-700/30 transition-colors border-b border-gray-700 ${
                      selectedConversation?.id === conv.id ? 'bg-gray-700/50' : ''
                    }`}
                  >
                    <div className="flex items-center gap-3">
                      <div className="relative flex-shrink-0">
                        <Avatar
                          src={conv.participant?.avatar}
                          name={conv.participant?.username}
                          size="sm"
                        />
                        {conv.participant?.is_online && (
                          <span className="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full border-2 border-gray-800" />
                        )}
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between">
                          <span className="text-white font-medium truncate">
                            {conv.participant?.username || 'Unknown'}
                          </span>
                          <span className="text-xs text-gray-500">
                            {formatTime(conv.updated_at || conv.last_message_at)}
                          </span>
                        </div>
                        <p className="text-sm text-gray-400 truncate">
                          {conv.last_message || conv.lastMessage || 'No messages yet'}
                        </p>
                      </div>
                      {(conv.unread_count || conv.unread) > 0 && (
                        <span className="w-5 h-5 rounded-full bg-purple-500 text-white text-xs flex items-center justify-center flex-shrink-0">
                          {conv.unread_count || conv.unread}
                        </span>
                      )}
                    </div>
                  </button>
                ))
              )}
            </div>
          </div>

          {/* Chat Area */}
          <div className="hidden md:flex flex-1 flex-col">
            {selectedConversation ? (
              <>
                {/* Chat Header */}
                <div className="p-4 border-b border-gray-700 flex items-center gap-3">
                  <Avatar
                    src={selectedConversation.participant?.avatar}
                    name={selectedConversation.participant?.username}
                    size="sm"
                  />
                  <div>
                    <Link
                      to={`/profile/${selectedConversation.participant?.username}`}
                      className="text-white font-medium hover:text-purple-400 transition-colors"
                    >
                      {selectedConversation.participant?.username || 'Unknown'}
                    </Link>
                    {selectedConversation.participant?.is_online && (
                      <p className="text-xs text-green-400">Online</p>
                    )}
                  </div>
                </div>

                {/* Messages */}
                <div className="flex-1 overflow-y-auto p-4 space-y-4">
                  {messagesLoading ? (
                    <div className="flex items-center justify-center h-full">
                      <LoadingSpinner size="md" />
                    </div>
                  ) : messages.length === 0 ? (
                    <div className="flex items-center justify-center h-full text-gray-400">
                      Start a conversation!
                    </div>
                  ) : (
                    messages.map((msg, index) => {
                      const isOwn = msg.sender_id === user.id || msg.is_own || msg.sender === 'me'
                      return (
                        <motion.div
                          key={msg.id || index}
                          initial={{ opacity: 0, y: 10 }}
                          animate={{ opacity: 1, y: 0 }}
                          transition={{ delay: index * 0.02 }}
                          className={`flex ${isOwn ? 'justify-end' : 'justify-start'}`}
                        >
                          <div className={`max-w-[70%] ${isOwn ? 'order-2' : 'order-1'}`}>
                            {!isOwn && (
                              <Avatar
                                src={msg.sender?.avatar}
                                name={msg.sender?.username}
                                size="sm"
                                className="mb-1"
                              />
                            )}
                            <div
                              className={`px-4 py-2 rounded-lg ${
                                isOwn
                                  ? 'bg-purple-600 text-white rounded-br-none'
                                  : 'bg-gray-700 text-white rounded-bl-none'
                              }`}
                            >
                              <p className="whitespace-pre-wrap">{msg.content}</p>
                            </div>
                            <p className={`text-xs text-gray-500 mt-1 ${isOwn ? 'text-right' : 'text-left'}`}>
                              {formatTime(msg.created_at)}
                              {msg.edited_at && ' (edited)'}
                            </p>
                          </div>
                        </motion.div>
                      )
                    })
                  )}
                </div>

                {/* Message Input */}
                <form onSubmit={sendMessage} className="p-4 border-t border-gray-700">
                  <div className="flex gap-2">
                    <input
                      type="text"
                      value={newMessage}
                      onChange={(e) => setNewMessage(e.target.value)}
                      placeholder="Type a message..."
                      className="flex-1 px-4 py-2 rounded-lg bg-gray-900/50 border border-gray-700 text-white placeholder-gray-500 focus:outline-none focus:border-purple-500"
                      disabled={sending}
                    />
                    <button
                      type="submit"
                      disabled={sending || !newMessage.trim()}
                      className="px-4 py-2 bg-purple-600 hover:bg-purple-500 disabled:bg-purple-600/50 disabled:cursor-not-allowed text-white rounded-lg transition-colors flex items-center gap-2"
                    >
                      {sending ? (
                        <LoadingSpinner size="sm" />
                      ) : (
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                        </svg>
                      )}
                    </button>
                  </div>
                </form>
              </>
            ) : (
              <div className="flex-1 flex items-center justify-center text-gray-400">
                <div className="text-center">
                  <svg className="w-16 h-16 mx-auto mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                  </svg>
                  <p>Select a conversation to start messaging</p>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
