import { Outlet, Link, useLocation } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { useState } from 'react'
import lspdLogo from '../assets/logos/lspd.png'
import lssdLogo from '../assets/logos/lssd.png'
import lsfdLogo from '../assets/logos/lsfd.png'
import lsnLogo from '../assets/logos/lsn.png'

const departments = [
  { code: 'LSPD', name: 'Police Department', color: '#2563EB', logo: lspdLogo },
  { code: 'LSSD', name: "Sheriff's Department", color: '#92400E', logo: lssdLogo },
  { code: 'LSFD', name: 'Fire Department', color: '#DC2626', logo: lsfdLogo },
  { code: 'LSN', name: 'News', color: '#EA580C', logo: lsnLogo },
]

export default function Layout() {
  const { user, logout } = useAuth()
  const location = useLocation()
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)

  const isActive = (path) => location.pathname === path

  return (
    <div className="min-h-screen bg-dark">
      {/* Header */}
      <header className="sticky top-0 z-50 bg-dark/95 backdrop-blur-md border-b border-dark-200">
        <div className="max-w-7xl mx-auto px-4">
          <div className="flex items-center justify-between h-16">
            {/* Logo */}
            <Link to="/" className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-500 to-purple-700 flex items-center justify-center text-white font-bold text-xl">
                LS
              </div>
              <div>
                <h1 className="font-heading font-bold text-lg text-white tracking-wide">Department Portal</h1>
                <p className="text-xs text-gray-400">Los Santos Community</p>
              </div>
            </Link>

            {/* Desktop Nav */}
            <nav className="hidden md:flex items-center gap-1">
              <Link
                to="/"
                className={`px-4 py-2 rounded-lg font-medium transition-all ${
                  isActive('/') ? 'bg-purple-500/20 text-purple-400' : 'text-gray-400 hover:text-white hover:bg-dark-100'
                }`}
              >
                Home
              </Link>
              {departments.map((dept) => (
                <Link
                  key={dept.code}
                  to={`/${dept.code.toLowerCase()}`}
                  className="px-4 py-2 rounded-lg font-medium text-gray-400 hover:text-white hover:bg-dark-100 transition-all flex items-center gap-2"
                >
                  <img src={dept.logo} alt={dept.code} className="w-5 h-5 object-contain" />
                  <span>{dept.code}</span>
                </Link>
              ))}
            </nav>

            {/* Auth */}
            <div className="flex items-center gap-3">
              {user ? (
                <>
                  <Link
                    to="/notifications"
                    className="relative p-2 rounded-lg text-gray-400 hover:text-white hover:bg-dark-100 transition-all"
                  >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                  </Link>
                  <Link
                    to="/messages"
                    className="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-dark-100 transition-all"
                  >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                  </Link>
                  <Link
                    to={`/profile/${user.username}`}
                    className="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-dark-100 hover:bg-dark-200 transition-all"
                  >
                    <div className="w-7 h-7 rounded-full bg-gradient-to-br from-purple-500 to-blue-500 flex items-center justify-center text-white text-sm font-medium">
                      {user.username?.[0]?.toUpperCase()}
                    </div>
                    <span className="text-sm text-white">{user.username}</span>
                  </Link>
                  <button
                    onClick={logout}
                    className="px-3 py-1.5 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-dark-100 transition-all"
                  >
                    Logout
                  </button>
                </>
              ) : (
                <>
                  <Link
                    to="/login"
                    className="px-4 py-2 rounded-lg text-sm font-medium text-gray-300 hover:text-white transition-all"
                  >
                    Login
                  </Link>
                  <Link
                    to="/register"
                    className="px-4 py-2 rounded-lg text-sm font-bold bg-purple-600 hover:bg-purple-500 text-white transition-all"
                  >
                    Register
                  </Link>
                </>
              )}
            </div>

            {/* Mobile menu button */}
            <button
              onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              className="md:hidden p-2 rounded-lg text-gray-400 hover:text-white hover:bg-dark-100"
            >
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                {mobileMenuOpen ? (
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                ) : (
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                )}
              </svg>
            </button>
          </div>
        </div>

        {/* Mobile menu */}
        {mobileMenuOpen && (
          <div className="md:hidden border-t border-dark-200 bg-dark-100 animate-fade-in">
            <div className="px-4 py-3 space-y-1">
              <Link to="/" className="block px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-dark-200">
                Home
              </Link>
              {departments.map((dept) => (
                <Link
                  key={dept.code}
                  to={`/${dept.code.toLowerCase()}`}
                  className="block px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-dark-200 flex items-center gap-2"
                >
                  <img src={dept.logo} alt={dept.code} className="w-5 h-5 object-contain" />
                  <span>{dept.code} - {dept.name}</span>
                </Link>
              ))}
            </div>
          </div>
        )}
      </header>

      {/* Main Content */}
      <main className="max-w-7xl mx-auto px-4 py-8">
        <Outlet />
      </main>

      {/* Footer */}
      <footer className="border-t border-dark-200 mt-16">
        <div className="max-w-7xl mx-auto px-4 py-8">
          <div className="flex flex-col md:flex-row items-center justify-between gap-4">
            <div className="text-center md:text-left">
              <p className="font-heading font-bold text-purple-400">Los Santos Roleplay Community</p>
              <p className="text-sm text-gray-500">To Protect and Serve</p>
            </div>
            <p className="text-sm text-gray-600">© 2026 LSPD Community. All rights reserved.</p>
          </div>
        </div>
      </footer>
    </div>
  )
}
