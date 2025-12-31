/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './templates/**/*.html.twig',
    './assets/**/*.js',
  ],
  theme: {
    extend: {
      colors: {
        // Architectural light (page backgrounds)
        paper: '#FFFCF6',
        daylight: '#FAF7F0',

        // Warm neutrals (cards/panels)
        bone: '#F5F1E8',
        putty: '#E8E2D5',
        oatmeal: '#D4CFC0',
        stone: '#9D9786',

        // Anchors
        graphite: '#3A3A3A',
        gunmetal: '#2C2C2E',
        ink: '#1A1A1A',

        // Materials
        walnut: '#5C4033',
        cognac: '#9A6324',

        // Punch accents (ONE per page/section via CSS var)
        signal: '#D32F2F',
        teal: '#00897B',
        mustard: '#F9A825',
        persimmon: '#E64A19',
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        display: ['Sohne', 'Inter', 'sans-serif'],
        mono: ['JetBrains Mono', 'Consolas', 'monospace'],
      },
      borderRadius: {
        'chamfer-card': '0.75rem',   // rounded-xl for cards
        'chamfer-panel': '0.5rem',   // rounded-lg for panels
        'chamfer-control': '0.375rem', // rounded-md for controls
      },
      boxShadow: {
        'inset-panel': 'inset 0 2px 4px rgba(0,0,0,0.06)',
        'elevated-base': '0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06)',
        'elevated-hover': '0 4px 6px rgba(0,0,0,0.08), 0 2px 4px rgba(0,0,0,0.06)',
      },
    },
  },
  plugins: [],
}
