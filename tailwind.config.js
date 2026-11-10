/**
 * QR-SAR - Konfigurasi build Tailwind CSS (statis, menggantikan Tailwind Play runtime di browser)
 * Build ulang setiap kali ada kelas baru di view/JS:
 *   npx tailwindcss@3.4.17 -c tailwind.config.js -i assets/css/tailwind.src.css -o assets/css/tailwind.css --minify
 */
module.exports = {
  content: [
    './*.php',
    './views/**/*.php',
    './controllers/**/*.php',
    './helpers/**/*.php',
    './services/**/*.php',
    './models/**/*.php',
    './api/**/*.php',
    './assets/js/**/*.js'
  ],
  darkMode: 'class',
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'monospace']
      },
      boxShadow: {
        'xs': '0 1px 2px 0 rgba(0, 0, 0, 0.05)'
      },
      colors: {
        basarnas: {
          50: '#fff7ed',
          100: '#ffedd5',
          200: '#fed7aa',
          300: '#fdba74',
          400: '#fb923c',
          500: '#f97316',
          600: '#ea580c',
          700: '#c2410c',
          800: '#9a3412',
          900: '#7c2d12',
          orange: '#F97316',
          amber: '#D97706',
          deep: '#C2410C',
          navy: '#0B1120',
          slate: '#1E293B',
          dark: '#060B13'
        }
      },
      animation: {
        'radar-spin': 'radar-spin 12s linear infinite',
        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite'
      },
      keyframes: {
        'radar-spin': {
          from: { transform: 'rotate(0deg)' },
          to: { transform: 'rotate(360deg)' }
        }
      }
    }
  }
};
