// Endpoint de salud para readiness checks (Playwright webServer, orquestación).
export default defineEventHandler(() => ({ status: 'ok' }))
