import { ref, watch } from 'vue'

type Appearance = 'light' | 'dark' | 'system'

const appearance = ref<Appearance>('system')

export function initializeTheme(): void {
    // Get saved preference or default to system
    const saved = localStorage.getItem('appearance') as Appearance | null
    if (saved && ['light', 'dark', 'system'].includes(saved)) {
        appearance.value = saved
    }
    
    applyTheme(appearance.value)
    
    // Watch for system preference changes
    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            if (appearance.value === 'system') {
                applyTheme('system')
            }
        })
    }
}

export function setAppearance(value: Appearance): void {
    appearance.value = value
    localStorage.setItem('appearance', value)
    applyTheme(value)
}

function applyTheme(value: Appearance): void {
    const isDark = value === 'dark' || 
        (value === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)
    
    if (isDark) {
        document.documentElement.classList.add('dark')
    } else {
        document.documentElement.classList.remove('dark')
    }
}

// Watch for changes
watch(appearance, (newValue) => {
    applyTheme(newValue)
})