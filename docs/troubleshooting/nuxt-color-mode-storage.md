# Nuxt color mode storage error

When running the Nuxt front-end locally you may see logs similar to:

```
ERROR  storage.getItem is not a function
    at read (.../node_modules/@vueuse/core/index.mjs:2106:55)
    at update (.../node_modules/@vueuse/core/index.mjs:2138:22)
    at useStorage (.../node_modules/@vueuse/core/index.mjs:2073:5)
    at useColorMode (.../node_modules/@vueuse/core/index.mjs:2176:101)
    at useThemes (composables/useThemes.ts:76:59)
```

The project configures `@vueuse/core`'s [`useColorMode`](https://vueuse.org/core/useColorMode/) composable to persist the current
mode using a composable-powered storage reference. The helper that is passed to the `storage`
option returned a `Ref` object rather than a `StorageLike` implementation, so VueUse attempted to
call `storage.getItem(...)` on that ref and crashed.

## Resolution

Update `composables/useThemes.ts` so that it provides a storage adapter matching the
[`StorageLike`](https://vueuse.org/shared/usestorage/#storagelike) contract expected by VueUse. In practice
this means wrapping the cookie helper in an object that exposes `getItem`, `setItem`, and `removeItem`
just like the standard Web Storage API.

```diff [composables/useThemes.ts]
 const colorMode = useColorMode({
-  storage: computedStorage,
+  storage: createCookieStorage(options.storageKey ?? 'color-mode'),
   ...options,
 })
```

And implement `createCookieStorage(...)` as shown below:

```ts
import { useCookie } from '#app'
import { useColorMode, type UseColorModeOptions } from '@vueuse/core'

function createCookieStorage(key: string) {
  const cookie = useCookie<string | null>(key, { sameSite: 'lax', path: '/' })

  return {
    getItem: () => cookie.value ?? null,
    setItem: (_: string, value: string) => { cookie.value = value },
    removeItem: () => { cookie.value = null },
  }
}

export function useThemes(options: UseColorModeOptions = {}) {
  const colorMode = useColorMode({
    storage: createCookieStorage(options.storageKey ?? 'color-mode'),
    ...options,
  })

  return { colorMode }
}
```

## Verification

1. Run `pnpm install` and `pnpm dev` inside the Nuxt project.
2. Load the application in the browser and toggle between light/dark themes.
3. The `storage.getItem is not a function` error no longer appears in the terminal logs and the selected
   theme persists between reloads.

The wrapper keeps the composable SSR-safe while satisfying VueUse's storage contract, so the theme selection
persists correctly between page loads.
