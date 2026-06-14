/**
 * LoadingSpinner - Reusable loading component
 */
export function LoadingSpinner({ size = 'md', className = '' }) {
  const sizeClasses = {
    sm: 'w-4 h-4 border-2',
    md: 'w-8 h-8 border-2',
    lg: 'w-12 h-12 border-3',
    xl: 'w-16 h-16 border-4',
  }

  return (
    <div className={`${className}`}>
      <div
        className={`${sizeClasses[size]} border-purple-500 border-t-transparent rounded-full animate-spin`}
      />
    </div>
  )
}

/**
 * LoadingOverlay - Full screen loading overlay
 */
export function LoadingOverlay({ message = 'Loading...' }) {
  return (
    <div className="fixed inset-0 bg-dark/80 backdrop-blur-sm z-50 flex items-center justify-center">
      <div className="text-center">
        <LoadingSpinner size="lg" />
        <p className="mt-4 text-gray-400">{message}</p>
      </div>
    </div>
  )
}

/**
 * LoadingButton - Button with loading state
 */
export function LoadingButton({ children, loading, disabled, className = '', ...props }) {
  return (
    <button
      disabled={disabled || loading}
      className={`relative ${className}`}
      {...props}
    >
      {loading && (
        <span className="absolute left-4 top-1/2 -translate-y-1/2">
          <LoadingSpinner size="sm" />
        </span>
      )}
      <span className={loading ? 'pl-8' : ''}>{children}</span>
    </button>
  )
}

export default LoadingSpinner