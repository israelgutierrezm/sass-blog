<script setup lang="ts">
import type { NuxtError } from '#app'

defineProps<{ error: NuxtError }>()
</script>

<template>
  <main class="st-error">
    <h1>{{ error.statusCode }}</h1>
    <p>{{ error.statusMessage || 'Algo salió mal' }}</p>
  </main>
</template>

<style>
.st-error {
  min-height: 60vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  font-family: system-ui, sans-serif;
  color: #374151;
}
.st-error h1 {
  font-size: 3rem;
  margin: 0;
}
</style>
