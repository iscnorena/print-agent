# Print Agent — impresión automática para papelería

Sistema para recibir archivos de clientes (web y, próximamente, WhatsApp) e imprimirlos solos en la impresora del local. Está pensado desde el inicio para varias sucursales, cada una con su propio agente de impresión.

- **`backend/`**: Laravel 13 (PHP 8.5) + MySQL. Cola de trabajos, panel de mostrador, página para clientes y API para los agentes.
- **`agent/`**: Python 3. Corre junto a la impresora (Windows hoy; Raspberry Pi/Linux después), pregunta al backend si hay trabajos y los imprime en silencio.
- **`docs/`**: el plan completo por fases ([prompt-impresion-whatsapp.md](docs/prompt-impresion-whatsapp.md)) y cómo levantar todo en local ([desarrollo-local.md](docs/desarrollo-local.md)).

## Estado (24 de octubre de 2026)

| Fase | Estado |
| --- | --- |
| 1. Agente local de impresión | ✅ Terminada y aceptada |
| 2. Backend base y API del agente | ✅ Terminada y aceptada |
| Extra: página de mostrador (subir e imprimir) | ✅ Terminada y probada en la Brother |
| Extra: página para clientes + botón Imprimir | ✅ Terminada y probada desde el celular vía ngrok |
| 3. Webhook de WhatsApp | ⏳ Siguiente. Esperando datos de Meta |
| 4. Conversión de Word, Excel e imágenes a PDF | Pendiente |
| 5. Conversación, cotización y vista previa | Pendiente |
| 6. Panel de administración completo | Pendiente (ya hay una vista de mostrador básica) |
| 7. Privacidad y limpieza | Pendiente (ya expiran los trabajos en "Listo") |
| 8–10. Pagos, multi-sucursal, portal/app, kiosco | Futuro |

Tests: **101 del backend** (Pest) y **32 del agente** (pytest); todos pasan.

## Qué funciona hoy

### Agente de impresión (Fase 1 y 2)
- Imprime PDF en silencio: SumatraPDF en Windows y `lp`/CUPS en Linux. Maneja copias, color/B/N, doble cara, rango de páginas, tamaño carta y ajuste a la hoja.
- `python agent.py --list-printers`, `--test-print archivo.pdf` y `--run` (modo agente).
- En modo agente pregunta al backend cada 5 s, avisa que está vivo cada 60 s y reintenta con espera creciente si el backend no responde.
- No imprime dos veces el mismo trabajo (`state.json`).
- Logs con rotación en `logs/agent.log`.
- Probado en la **Brother DCP-T720DW**. El nombre de la impresora va en `config.yaml`, nunca en el código.

### Backend (Fase 2)
- Tablas: `locations` (sucursales), `printers`, `customers` y `print_jobs`.
- Estados de un trabajo: `received → processing → quoted → confirmed → ready → queued → printing → printed | failed | cancelled | expired`. Todos los cambios de estado pasan por `PrintJobService`, que bloquea la fila para evitar carreras.
- `print_mode` por sucursal: `manual` (por defecto; el trabajo espera en **Listo**), `auto_if_paid` o `auto`.
- API del agente con token Bearer por impresora (se guarda solo su hash):
  - `GET /api/agent/jobs/next`
  - `GET /api/agent/jobs/{id}/file`
  - `POST /api/agent/jobs/{id}/status`
  - `POST /api/agent/heartbeat`
- Comandos:
  - `printer:create`: registra la impresora y muestra su token.
  - `job:create-test`: manda un PDF directo a imprimir.
  - `user:create`: crea un operador o cambia su contraseña.
  - `jobs:expire`: vence los trabajos en Listo después de N días y borra sus archivos. Está programado a diario, pero en local solo corre si está activo `php artisan schedule:work`.

### Página de mostrador — `/print` (con usuario y contraseña)
- Subes un PDF, eliges impresora, copias, caras, páginas y color, y se **imprime de inmediato**. El folio es solo el número del trabajo.
- "Últimos trabajos" se actualiza sola cada 4 s (En cola → Imprimiendo → Impreso) y muestra cliente, opciones y errores.
- Indica si cada impresora está **conectada** o **sin conexión**, según el último aviso de vida de su agente.
- Los trabajos de clientes muestran el botón **Imprimir**. Abre una ventana con las opciones del cliente, que el operador puede ajustar antes de mandar.
- Se bloquea tras 5 intentos fallidos de inicio de sesión en un minuto.

### Página para clientes — `/subir` (pública, por link o QR)
- El cliente sube su PDF con su **celular a 10 dígitos** (acepta espacios, guiones o +52) y, si quiere, su nombre. También elige copias, caras, páginas y color; el color solo aparece si alguna impresora lo tiene.
- El trabajo sigue el `print_mode` de la sucursal: en `manual` queda en **Listo** hasta que el operador da Imprimir.
- Folio: **`<número del trabajo>-<últimos 4 dígitos del celular>`**, por ejemplo `125-4521`. Se le muestra al cliente con el mensaje de pasar a pagar.
- Se aceptan máximo 10 envíos por minuto desde la misma conexión.

### Exponer a internet (pruebas)
- ngrok con dominio fijo gratuito. Instrucciones en [docs/desarrollo-local.md](docs/desarrollo-local.md#4-probar-desde-el-celular-ngrok).
- Laravel confía en encabezados de proxy solo si vienen de `127.0.0.1`, para que las ligas usen `https://` detrás del túnel.

## Decisiones tomadas

- **Solo PDF por ahora.** Word, Excel, PowerPoint, Publisher e imágenes llegan en la Fase 4 (LibreOffice, Ghostscript, poppler), que servirá a todos los canales.
- **El cliente se identifica por su celular a 10 dígitos** (`customers.phone`, único). El identificador de WhatsApp (`wa_id`) es opcional, así que la misma persona queda como un solo cliente en web y en WhatsApp.
- **Un solo servicio para todos los canales.** La página de mostrador, la del cliente, el comando de prueba y (después) WhatsApp usan `PrintJobService`. Las opciones de impresión se validan en una sola clase base (`PrintOptionsRequest`).
- **Lo que sube el operador imprime directo**, sin importar el `print_mode`, porque el operador está presente.
- **Los archivos de clientes llegan a la primera sucursal activa.** El link o QR por negocio llega en la Fase 8.
- **Sin Node/Vite.** Las vistas usan CSS integrado para no requerir compilación en la PC del local.

## Pendientes y siguientes pasos

1. **Fase 3, WhatsApp.** Faltan del lado de Meta el Phone Number ID, el Business Account ID, el token de acceso y el App Secret, que van en `backend/.env` (`WHATSAPP_*`). La Callback URL será `https://<tu-dominio>.ngrok-free.dev/webhook/whatsapp`.
2. **Fase 4, conversión de archivos.** Requiere instalar LibreOffice, Ghostscript y poppler en WSL.
3. **Página de clientes.** Más adelante: inicio de sesión con código por WhatsApp, precios y vista previa (Fases 5 y 9a).
4. **Operación.** El agente en Windows corre desde una copia en `C:\agente\agent` que hay que actualizar a mano tras cambios en `agent/`. Conviene automatizarlo o instalarlo como servicio.
5. Cambiar `APP_NAME` en `backend/.env` por el nombre de la papelería, que aparece en el encabezado de las páginas.

## Historial

| Commit | Cambio |
| --- | --- |
| `3d60f7e` | Fases 1 y 2: agente local y API del backend |
| `4ec9936` | Página de mostrador para subir e imprimir PDF |
| `88e9b24` | Página para clientes, folio con teléfono y botón Imprimir |
| `c36f7ea` | Proxies locales de confianza para ngrok (https) |
| `681f755` | Documentación de desarrollo local y liga de ngrok |
