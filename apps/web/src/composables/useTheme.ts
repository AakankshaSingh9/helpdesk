import { computed, ref } from 'vue'

/**
 * Theme controller. Three preferences — 'light', 'dark', or 'system' (follow
 * the OS). The chosen preference is persisted to localStorage under 'theme'
 * ('system' clears the key); the *resolved* concrete value is written to
 * <html data-theme> so style.css can flip the design tokens. An inline script
 * in index.html applies the same logic before first paint to avoid a flash —
 * keep the two in sync.
 */
type ThemePreference = 'light' | 'dark' | 'system'

const STORAGE_KEY = 'theme'
const media = window.matchMedia('(prefers-color-scheme: dark)')

function load(): ThemePreference {
  const stored = localStorage.getItem(STORAGE_KEY)
  return stored === 'light' || stored === 'dark' ? stored : 'system'
}

const preference = ref<ThemePreference>(load())

function resolve(pref: ThemePreference): 'light' | 'dark' {
  if (pref === 'system') return media.matches ? 'dark' : 'light'
  return pref
}

function apply() {
  document.documentElement.dataset.theme = resolve(preference.value)
}

// Follow the OS in real time while on the 'system' preference.
media.addEventListener('change', () => {
  if (preference.value === 'system') apply()
})

export function useTheme() {
  const isDark = computed(() => resolve(preference.value) === 'dark')

  function setPreference(pref: ThemePreference) {
    preference.value = pref
    if (pref === 'system') localStorage.removeItem(STORAGE_KEY)
    else localStorage.setItem(STORAGE_KEY, pref)
    apply()
  }

  // Explicit light/dark flip for a simple toggle button.
  function toggle() {
    setPreference(isDark.value ? 'light' : 'dark')
  }

  return { preference, isDark, setPreference, toggle }
}
