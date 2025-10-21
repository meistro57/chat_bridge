/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  darkMode: 'media',
  theme: {
    extend: {
      fontFamily: {
        'sans': ['"MS Sans Serif"', 'system-ui', 'sans-serif'],
        'system': ['"MS Sans Serif"', 'sans-serif'],
        'mono': ['"VT323"', 'monospace'],
      },
      colors: {
        'win-gray-100': '#dfdfdf',
        'win-gray-200': '#bfbfbf',
        'win-gray-300': '#a0a0a0',
        'win-gray-400': '#808080',
        'win-gray-500': '#404040',
        'win-gray-600': '#202020',
        'winamp-teal': '#00ccaa',
        'winamp-green': '#00cc00',
        'winamp-blue': '#5577aa',
        'winamp-red': '#aa0000',
        'winamp-orange': '#ee4400',
        'winamp-purple': '#990099',
      },
      boxShadow: {
        'inset-win-ridge': 'inset 2px 2px 4px rgba(255,255,255,0.8), inset -2px -2px 4px rgba(0,0,0,0.5)',
        'inset-win-groove': 'inset -2px -2px 4px rgba(255,255,255,0.8), inset 2px 2px 4px rgba(0,0,0,0.5)',
        'win-outset': '2px 2px 4px rgba(0,0,0,0.8), -1px -1px 2px rgba(255,255,255,0.4)',
        'win-inset': 'inset -1px -1px 2px rgba(255,255,255,0.8), inset 1px 1px 2px rgba(0,0,0,0.6)',
      },
      animation: {
        'pulse-winamp': 'pulse 1.5s ease-in-out infinite',
      },
    },
  },
  plugins: [],
}