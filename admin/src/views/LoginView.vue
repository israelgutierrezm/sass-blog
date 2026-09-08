<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { ApiError } from '../services/http'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()

const mode = ref<'login' | 'register'>('login')
const name = ref('')
const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function submit(): Promise<void> {
  error.value = ''
  loading.value = true
  try {
    if (mode.value === 'login') {
      await auth.login(email.value, password.value)
    } else {
      await auth.register(name.value, email.value, password.value)
    }
    router.push({ name: 'workspaces' })
  } catch (e) {
    error.value = e instanceof ApiError ? e.first() : 'Error inesperado'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center px-4">
    <form
      class="w-full max-w-sm rounded-xl border border-gray-200 bg-white p-8 shadow-sm"
      @submit.prevent="submit"
    >
      <h1 class="mb-1 text-xl font-semibold">sass-blog admin</h1>
      <p class="mb-6 text-sm text-gray-500">
        {{ mode === 'login' ? 'Inicia sesión' : 'Crea tu cuenta' }}
      </p>

      <label v-if="mode === 'register'" class="mb-3 block">
        <span class="mb-1 block text-sm text-gray-700">Nombre</span>
        <input v-model="name" type="text" data-testid="name" required
          class="w-full rounded-md border border-gray-300 px-3 py-2 outline-none focus:border-blue-500" />
      </label>

      <label class="mb-3 block">
        <span class="mb-1 block text-sm text-gray-700">Correo</span>
        <input v-model="email" type="email" data-testid="email" required
          class="w-full rounded-md border border-gray-300 px-3 py-2 outline-none focus:border-blue-500" />
      </label>

      <label class="mb-4 block">
        <span class="mb-1 block text-sm text-gray-700">Contraseña</span>
        <input v-model="password" type="password" data-testid="password" required
          class="w-full rounded-md border border-gray-300 px-3 py-2 outline-none focus:border-blue-500" />
      </label>

      <p v-if="error" data-testid="error" class="mb-3 text-sm text-red-600">{{ error }}</p>

      <button type="submit" data-testid="submit" :disabled="loading"
        class="w-full rounded-md bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700 disabled:opacity-50">
        {{ loading ? '…' : mode === 'login' ? 'Entrar' : 'Registrarme' }}
      </button>

      <button type="button" class="mt-3 w-full text-center text-sm text-gray-500 hover:text-gray-900"
        @click="mode = mode === 'login' ? 'register' : 'login'">
        {{ mode === 'login' ? '¿No tienes cuenta? Regístrate' : '¿Ya tienes cuenta? Inicia sesión' }}
      </button>
    </form>
  </div>
</template>
