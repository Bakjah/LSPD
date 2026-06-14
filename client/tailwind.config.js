/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        portal: '#9925EB',
        lspd: '#2563EB',
        lssd: '#92400E',
        lsfd: '#DC2626',
        lsn: '#EA580C',
        dark: {
          DEFAULT: '#0F172A',
          100: '#1E293B',
          200: '#334155',
        },
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        heading: ['Rajdhani', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
