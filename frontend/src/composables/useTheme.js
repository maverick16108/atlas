import { ref, watch, onMounted } from 'vue'

const theme = ref(localStorage.getItem('theme') || 'dark') // Default to dark as per original design

const applyTheme = (newTheme) => {
    if (newTheme === 'dark') {
        document.documentElement.classList.add('dark')
    } else {
        document.documentElement.classList.remove('dark')
    }
    localStorage.setItem('theme', newTheme)
    theme.value = newTheme
}

const toggleTheme = () => {
    const newTheme = theme.value === 'dark' ? 'light' : 'dark'
    applyTheme(newTheme)
}

export function useTheme() {
    onMounted(() => {
        // Initial application
        const storedTheme = localStorage.getItem('theme')
        if (storedTheme) {
            applyTheme(storedTheme)
        } else {
            // Check system preference if no stored theme
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                applyTheme('dark')
            } else {
                applyTheme('light')
            }
        }
    })

    return {
        theme,
        toggleTheme,
        setTheme: applyTheme
    }
}
