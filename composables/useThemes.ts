import { useCookie } from '#app'
import { useColorMode, type UseColorModeOptions } from '@vueuse/core'

type StorageLike = {
  getItem: (key: string) => string | null
  setItem: (key: string, value: string) => void
  removeItem: (key: string) => void
}

function createCookieStorage(key: string): StorageLike {
  const cookie = useCookie<string | null>(key, { sameSite: 'lax', path: '/' })

  return {
    getItem: () => cookie.value ?? null,
    setItem: (_key: string, value: string) => {
      cookie.value = value
    },
    removeItem: () => {
      cookie.value = null
    },
  }
}

export function useThemes(options: UseColorModeOptions = {}) {
  const storageKey = options.storageKey ?? 'color-mode'

  const colorMode = useColorMode({
    storage: createCookieStorage(storageKey),
    ...options,
  })

  return { colorMode }
}

