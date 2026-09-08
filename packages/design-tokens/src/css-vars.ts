import { defaultTokens, type SiteTokens } from './tokens'

export type DeepPartial<T> = {
  [K in keyof T]?: T[K] extends object ? DeepPartial<T[K]> : T[K]
}

/** Nombre del grupo -> prefijo de la variable CSS. */
const GROUP_PREFIX: Record<string, string> = {
  colors: 'color',
  typography: 'typography',
  radius: 'radius',
  spacing: 'spacing',
  container: 'container',
}

function kebab(value: string): string {
  return value.replace(/[A-Z]/g, (m) => `-${m.toLowerCase()}`)
}

/**
 * Aplana tokens (parciales o completos) a un mapa nombre-de-variable -> valor,
 * con prefijo `--st-` y claves en kebab-case (surfaceMuted -> surface-muted).
 */
export function flattenTokens(tokens: DeepPartial<SiteTokens>): Record<string, string> {
  const out: Record<string, string> = {}

  for (const [group, values] of Object.entries(tokens)) {
    if (!values) {
      continue
    }
    const prefix = GROUP_PREFIX[group] ?? group
    for (const [key, val] of Object.entries(values as Record<string, unknown>)) {
      if (val === undefined || val === null) {
        continue
      }
      out[`--st-${prefix}-${kebab(key)}`] = String(val)
    }
  }

  return out
}

/**
 * Emite SÓLO las variables sobreescritas, como cadena de estilo inline para
 * `.st-site-root` (cascada sobre los defaults de tokens.css).
 */
export function tokensToCssVars(override: DeepPartial<SiteTokens>): string {
  return Object.entries(flattenTokens(override))
    .map(([name, value]) => `${name}: ${value};`)
    .join(' ')
}

/**
 * Deep-merge del override por-sitio sobre los defaults; devuelve tokens completos.
 */
export function resolveSiteTokens(override: DeepPartial<SiteTokens> = {}): SiteTokens {
  const merged = structuredClone(defaultTokens)

  for (const [group, values] of Object.entries(override)) {
    if (!values) {
      continue
    }
    const target = (merged as unknown as Record<string, object>)[group]
    if (target) {
      Object.assign(target, values)
    }
  }

  return merged
}

/**
 * Genera el CSS base de tokens sobre `.st-site-root` (NO `:root`, para no
 * contaminar el chrome del admin). Fuente de `tokens.css`.
 */
export function buildTokensCss(): string {
  const body = Object.entries(flattenTokens(defaultTokens))
    .map(([name, value]) => `  ${name}: ${value};`)
    .join('\n')

  return `.st-site-root {\n${body}\n}\n`
}
