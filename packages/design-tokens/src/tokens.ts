/**
 * Design tokens de un sitio. Fuente de verdad; `tokens.css` se GENERA de aquí.
 *
 * Los componentes de site-components leen `var(--st-*, fallback)`; el override
 * por-sitio (sites.settings.branding.tokens) se emite como estilo inline sobre
 * `.st-site-root`. Ver ADR-008.
 */
export interface SiteTokens {
  colors: {
    primary: string
    secondary: string
    accent: string
    surface: string
    surfaceMuted: string
    text: string
    textMuted: string
    onPrimary: string
    dark: string
    onDark: string
  }
  typography: {
    heading: string
    body: string
  }
  radius: {
    sm: string
    md: string
    lg: string
  }
  spacing: {
    none: string
    sm: string
    md: string
    lg: string
    xl: string
  }
  container: {
    default: string
    wide: string
  }
}

export const defaultTokens: SiteTokens = {
  colors: {
    primary: '#2563eb',
    secondary: '#4b5563',
    accent: '#f59e0b',
    surface: '#ffffff',
    surfaceMuted: '#f3f4f6',
    text: '#111827',
    textMuted: '#6b7280',
    onPrimary: '#ffffff',
    dark: '#0b1220',
    onDark: '#f9fafb',
  },
  typography: {
    heading: "'Inter', system-ui, sans-serif",
    body: "'Inter', system-ui, sans-serif",
  },
  radius: {
    sm: '4px',
    md: '8px',
    lg: '16px',
  },
  spacing: {
    none: '0',
    sm: '0.5rem',
    md: '1rem',
    lg: '2rem',
    xl: '4rem',
  },
  container: {
    default: '72rem',
    wide: '90rem',
  },
}
