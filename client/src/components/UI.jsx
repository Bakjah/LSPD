import { Link } from 'react-router-dom'

/**
 * EmptyState - Display when no data is available
 */
export function EmptyState({
  icon = '📭',
  title = 'No Data',
  description = 'Nothing to show here yet.',
  action = null,
  actionLabel = 'Go Back',
  actionTo = '/'
}) {
  return (
    <div className="flex flex-col items-center justify-center py-12 px-4 text-center">
      <div className="text-6xl mb-4">{icon}</div>
      <h3 className="text-xl font-semibold text-white mb-2">{title}</h3>
      <p className="text-gray-400 mb-6 max-w-md">{description}</p>
      {action ? (
        action
      ) : (
        <Link
          to={actionTo}
          className="px-6 py-2 bg-purple-600 hover:bg-purple-500 text-white font-medium rounded-lg transition-colors"
        >
          {actionLabel}
        </Link>
      )}
    </div>
  )
}

/**
 * ErrorState - Display when an error occurs
 */
export function ErrorState({
  icon = '⚠️',
  title = 'Something went wrong',
  message = 'An error occurred while loading this content.',
  onRetry = null
}) {
  return (
    <div className="flex flex-col items-center justify-center py-12 px-4 text-center">
      <div className="text-6xl mb-4">{icon}</div>
      <h3 className="text-xl font-semibold text-white mb-2">{title}</h3>
      <p className="text-gray-400 mb-6 max-w-md">{message}</p>
      {onRetry && (
        <button
          onClick={onRetry}
          className="px-6 py-2 bg-purple-600 hover:bg-purple-500 text-white font-medium rounded-lg transition-colors"
        >
          Try Again
        </button>
      )}
    </div>
  )
}

/**
 * PageHeader - Standard page header
 */
export function PageHeader({
  title,
  subtitle = null,
  actions = null,
  breadcrumbs = []
}) {
  return (
    <div className="mb-6">
      {breadcrumbs.length > 0 && (
        <nav className="flex items-center gap-2 text-sm text-gray-400 mb-4">
          {breadcrumbs.map((crumb, index) => (
            <span key={index} className="flex items-center gap-2">
              {index > 0 && <span>/</span>}
              {crumb.to ? (
                <Link to={crumb.to} className="hover:text-purple-400 transition-colors">
                  {crumb.label}
                </Link>
              ) : (
                <span className={index === breadcrumbs.length - 1 ? 'text-white' : ''}>
                  {crumb.label}
                </span>
              )}
            </span>
          ))}
        </nav>
      )}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 className="text-2xl md:text-3xl font-bold text-white">{title}</h1>
          {subtitle && <p className="text-gray-400 mt-1">{subtitle}</p>}
        </div>
        {actions && <div className="flex items-center gap-3">{actions}</div>}
      </div>
    </div>
  )
}

/**
 * Card - Reusable card component
 */
export function Card({ children, className = '', hover = false, onClick = null }) {
  const baseClasses = 'bg-gray-800/50 backdrop-blur-sm rounded-xl border border-gray-700 overflow-hidden'
  const hoverClasses = hover ? 'hover:bg-gray-800 hover:border-gray-600 transition-all cursor-pointer' : ''

  return (
    <div
      className={`${baseClasses} ${hoverClasses} ${className}`}
      onClick={onClick}
    >
      {children}
    </div>
  )
}

/**
 * CardHeader - Card header section
 */
export function CardHeader({ children, className = '' }) {
  return (
    <div className={`px-6 py-4 border-b border-gray-700 ${className}`}>
      {children}
    </div>
  )
}

/**
 * CardBody - Card body section
 */
export function CardBody({ children, className = '' }) {
  return (
    <div className={`px-6 py-4 ${className}`}>
      {children}
    </div>
  )
}

/**
 * Badge - Reusable badge component
 */
export function Badge({ children, variant = 'default', size = 'md', className = '' }) {
  const variants = {
    default: 'bg-gray-700 text-gray-300',
    primary: 'bg-purple-500/20 text-purple-400',
    success: 'bg-green-500/20 text-green-400',
    warning: 'bg-yellow-500/20 text-yellow-400',
    danger: 'bg-red-500/20 text-red-400',
    info: 'bg-blue-500/20 text-blue-400',
  }

  const sizes = {
    sm: 'px-2 py-0.5 text-xs',
    md: 'px-2.5 py-1 text-sm',
    lg: 'px-3 py-1.5 text-base',
  }

  return (
    <span className={`inline-flex items-center font-medium rounded ${variants[variant]} ${sizes[size]} ${className}`}>
      {children}
    </span>
  )
}

/**
 * Avatar - User avatar component
 */
export function Avatar({ src, alt, name = '', size = 'md', className = '' }) {
  const sizes = {
    xs: 'w-6 h-6 text-xs',
    sm: 'w-8 h-8 text-sm',
    md: 'w-10 h-10 text-base',
    lg: 'w-12 h-12 text-lg',
    xl: 'w-16 h-16 text-xl',
    '2xl': 'w-20 h-20 text-2xl',
  }

  const initials = name
    ? name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .substring(0, 2)
        .toUpperCase()
    : '?'

  if (src) {
    return (
      <img
        src={src}
        alt={alt || name}
        className={`${sizes[size]} rounded-full object-cover ${className}`}
      />
    )
  }

  return (
    <div
      className={`${sizes[size]} rounded-full bg-gradient-to-br from-purple-500 to-blue-500 flex items-center justify-center text-white font-medium ${className}`}
    >
      {initials}
    </div>
  )
}

/**
 * Button - Reusable button component
 */
export function Button({
  children,
  variant = 'primary',
  size = 'md',
  loading = false,
  disabled = false,
  icon = null,
  className = '',
  ...props
}) {
  const variants = {
    primary: 'bg-purple-600 hover:bg-purple-500 text-white',
    secondary: 'bg-gray-700 hover:bg-gray-600 text-white',
    danger: 'bg-red-600 hover:bg-red-500 text-white',
    success: 'bg-green-600 hover:bg-green-500 text-white',
    ghost: 'bg-transparent hover:bg-gray-700 text-gray-300',
    outline: 'border border-gray-600 hover:bg-gray-700 text-gray-300',
  }

  const sizes = {
    sm: 'px-3 py-1.5 text-sm',
    md: 'px-4 py-2 text-base',
    lg: 'px-6 py-3 text-lg',
  }

  return (
    <button
      disabled={disabled || loading}
      className={`inline-flex items-center justify-center gap-2 font-medium rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed ${variants[variant]} ${sizes[size]} ${className}`}
      {...props}
    >
      {loading ? (
        <svg className="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      ) : icon ? (
        icon
      ) : null}
      {children}
    </button>
  )
}

/**
 * Input - Reusable input component
 */
export function Input({
  label,
  error,
  className = '',
  containerClassName = '',
  ...props
}) {
  return (
    <div className={containerClassName}>
      {label && (
        <label className="block text-sm font-medium text-gray-300 mb-2">
          {label}
        </label>
      )}
      <input
        className={`w-full px-4 py-3 rounded-lg bg-gray-900/50 border ${
          error ? 'border-red-500' : 'border-gray-700'
        } text-white placeholder-gray-500 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500 transition-colors ${className}`}
        {...props}
      />
      {error && <p className="mt-1 text-sm text-red-400">{error}</p>}
    </div>
  )
}

/**
 * Textarea - Reusable textarea component
 */
export function Textarea({
  label,
  error,
  className = '',
  containerClassName = '',
  ...props
}) {
  return (
    <div className={containerClassName}>
      {label && (
        <label className="block text-sm font-medium text-gray-300 mb-2">
          {label}
        </label>
      )}
      <textarea
        className={`w-full px-4 py-3 rounded-lg bg-gray-900/50 border ${
          error ? 'border-red-500' : 'border-gray-700'
        } text-white placeholder-gray-500 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500 transition-colors resize-none ${className}`}
        {...props}
      />
      {error && <p className="mt-1 text-sm text-red-400">{error}</p>}
    </div>
  )
}

/**
 * Select - Reusable select component
 */
export function Select({
  label,
  error,
  options = [],
  placeholder = 'Select...',
  className = '',
  containerClassName = '',
  ...props
}) {
  return (
    <div className={containerClassName}>
      {label && (
        <label className="block text-sm font-medium text-gray-300 mb-2">
          {label}
        </label>
      )}
      <select
        className={`w-full px-4 py-3 rounded-lg bg-gray-900/50 border ${
          error ? 'border-red-500' : 'border-gray-700'
        } text-white focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500 transition-colors ${className}`}
        {...props}
      >
        <option value="" disabled className="text-gray-500">{placeholder}</option>
        {options.map((option) => (
          <option key={option.value} value={option.value} className="bg-gray-900">
            {option.label}
          </option>
        ))}
      </select>
      {error && <p className="mt-1 text-sm text-red-400">{error}</p>}
    </div>
  )
}

export default {
  EmptyState,
  ErrorState,
  PageHeader,
  Card,
  CardHeader,
  CardBody,
  Badge,
  Avatar,
  Button,
  Input,
  Textarea,
  Select,
}