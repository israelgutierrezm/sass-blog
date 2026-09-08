<script setup lang="ts">
import { PageRenderer } from '@sass-blog/site-components'
import { useBuilderStore } from '../../stores/builder'

const builder = useBuilderStore()
</script>

<template>
  <div class="flex-1 overflow-y-auto bg-gray-100 p-6">
    <div class="mx-auto max-w-4xl overflow-hidden rounded-lg bg-white shadow-sm" data-testid="canvas">
      <p
        v-if="builder.schema.sections.length === 0"
        class="p-16 text-center text-gray-400"
      >
        La página está vacía. Agrega una sección desde la izquierda.
      </p>
      <PageRenderer v-else :schema="builder.schema" editable />
    </div>
  </div>
</template>
