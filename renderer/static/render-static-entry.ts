// Entry del BUILD del CLI (ADR-019). Separado de render-static.ts para que el módulo
// testeable no importe CSS: aquí se incluye tokens.css (Vite lo extrae junto con los
// estilos de los site-components a un único CSS) y se ejecuta el CLI.
import '@sass-blog/design-tokens/tokens.css'
import { main } from './render-static'

main(process.argv.slice(2)).catch((error) => {
  console.error(error)
  process.exitCode = 1
})
