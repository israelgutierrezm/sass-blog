<script setup lang="ts">
import { buildManifest, getComponent } from '@sass-blog/site-schema'
import { ref } from 'vue'
import { useBuilderStore } from '../../stores/builder'

const builder = useBuilderStore()
const manifest = buildManifest()
const adding = ref(false)

function add(type: string, variant: string): void {
  builder.add(type, variant)
  adding.value = false
}

function label(type: string): string {
  return getComponent(type)?.name ?? type
}
</script>

<template>
  <aside class="w-64 shrink-0 overflow-y-auto border-r border-gray-200 bg-white p-4">
    <div class="mb-3 flex items-center justify-between">
      <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Secciones</h2>
      <button
        data-testid="add-section"
        class="rounded bg-blue-600 px-2 py-1 text-xs font-medium text-white hover:bg-blue-700"
        @click="adding = !adding"
      >
        + Agregar
      </button>
    </div>

    <div v-if="adding" class="mb-3 space-y-2 rounded-md border border-gray-200 p-2">
      <div v-for="c in manifest.components" :key="c.type">
        <p class="mb-1 text-xs font-medium text-gray-500">{{ c.name }}</p>
        <button
          v-for="v in c.variants"
          :key="v.type"
          :data-testid="`add-${v.type}`"
          class="mb-1 block w-full rounded bg-gray-50 px-2 py-1 text-left text-xs hover:bg-blue-50"
          @click="add(c.type, v.type)"
        >
          {{ v.name }}
        </button>
      </div>
    </div>

    <ul class="space-y-1">
      <li v-for="(s, i) in builder.schema.sections" :key="s.id">
        <div
          :class="[
            'flex items-center gap-1 rounded-md border px-2 py-1.5 text-sm',
            builder.selectedId === s.id ? 'border-blue-400 bg-blue-50' : 'border-gray-200',
          ]"
        >
          <button
            class="flex-1 truncate text-left"
            :data-testid="`section-${i}`"
            @click="builder.select(s.id)"
          >
            <span :class="{ 'text-gray-400 line-through': !s.visible }">{{ label(s.type) }}</span>
          </button>
          <button class="px-1 text-gray-400 hover:text-gray-700" title="Subir" @click="builder.move(s.id, -1)">↑</button>
          <button class="px-1 text-gray-400 hover:text-gray-700" title="Bajar" @click="builder.move(s.id, 1)">↓</button>
          <button class="px-1 text-gray-400 hover:text-gray-700" title="Mostrar/ocultar" @click="builder.toggle(s.id)">{{ s.visible ? '◉' : '○' }}</button>
          <button class="px-1 text-gray-400 hover:text-red-600" title="Eliminar" @click="builder.remove(s.id)">✕</button>
        </div>
      </li>
    </ul>
    <p v-if="builder.schema.sections.length === 0" class="mt-2 text-xs text-gray-400">Sin secciones aún.</p>
  </aside>
</template>
