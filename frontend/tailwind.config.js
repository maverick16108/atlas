/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  future: {
    hoverOnlyWhenSupported: true,
  },
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        gold: {
          50: '#FBF8EB',
          100: '#F5EECB',
          200: '#EDDC98',
          300: '#E4CA64',
          400: '#D4AF37', // Base Gold
          500: '#B59426',
          600: '#91751A',
          700: '#6D5713',
          800: '#4D3D0F',
          900: '#2F260A',
        },
        dark: {
          900: '#0C0A09', // Rich Black
          800: '#1C1917',
          700: '#292524',
          600: '#44403C',
        },
        skin: {
          primary: 'rgb(var(--color-text-primary) / <alpha-value>)',
          secondary: 'rgb(var(--color-text-secondary) / <alpha-value>)',
          muted: 'rgb(var(--color-text-muted) / <alpha-value>)',
          inverted: 'rgb(var(--color-bg-primary) / <alpha-value>)',
        }
      },
      backgroundColor: {
        skin: {
          primary: 'rgb(var(--color-bg-primary) / <alpha-value>)',
          secondary: 'rgb(var(--color-bg-secondary) / <alpha-value>)',
          accent: 'rgb(var(--color-bg-accent) / <alpha-value>)',
        }
      },
      borderColor: {
        skin: {
          base: 'rgb(var(--color-border) / <alpha-value>)',
          hover: 'rgb(var(--color-border-hover) / <alpha-value>)',
        }
      },
      fontFamily: {
        sans: ['Manrope', 'Inter', 'sans-serif'],
        display: ['Montserrat', 'sans-serif'],
        oswald: ['Oswald', 'sans-serif'],
        russo: ['Russo One', 'sans-serif'],
        kanit: ['Kanit', 'sans-serif'],
      },
      backgroundImage: {
        'gold-gradient': 'linear-gradient(135deg, #F5EECB 0%, #D4AF37 50%, #91751A 100%)',
        'glass': 'linear-gradient(180deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%)',
      },
      backdropBlur: {
        xs: '2px',
      }
    },
  },
  plugins: [],
}
