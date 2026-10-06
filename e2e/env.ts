/**
 * Puertos/URLs del stack E2E, aislado del de desarrollo. El backend E2E usa un puerto PROPIO
 * (no el 8000 por defecto de Laravel): así la suite corre aunque haya otro servidor de
 * desarrollo local escuchando en 8000 (de este u otro proyecto). Override con E2E_API_PORT.
 */
export const API_PORT = Number(process.env.E2E_API_PORT ?? 8100)
export const API_ORIGIN = `http://127.0.0.1:${API_PORT}`
export const API_BASE = `${API_ORIGIN}/api/v1`
