// Crockford base32 (excluye I, L, O, U) — coincide con HasPublicUlid del backend.
const ENCODING = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'

/**
 * Genera un ULID válido (`^[0-9A-HJKMNP-TV-Z]{26}$`) para el id de una sección
 * nueva. La aleatoriedad no es criptográfica (basta para ids de sección).
 */
export function newUlid(): string {
  let time = Date.now()
  const timeChars: string[] = []
  for (let i = 0; i < 10; i++) {
    timeChars.unshift(ENCODING[time % 32]!)
    time = Math.floor(time / 32)
  }

  let random = ''
  for (let i = 0; i < 16; i++) {
    random += ENCODING[Math.floor(Math.random() * 32)]!
  }

  return timeChars.join('') + random
}
