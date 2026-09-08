<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'

const auth = useAuthStore()
const router = useRouter()

function logout(): void {
  auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen">
    <header
      v-if="auth.isAuthenticated"
      class="flex items-center justify-between border-b border-gray-200 bg-white px-6 py-3"
    >
      <RouterLink :to="{ name: 'workspaces' }" class="font-semibold text-gray-900">
        sass-blog <span class="text-gray-400">admin</span>
      </RouterLink>
      <button class="text-sm text-gray-500 hover:text-gray-900" @click="logout">
        Cerrar sesión
      </button>
    </header>
    <main>
      <RouterView />
    </main>
  </div>
</template>
