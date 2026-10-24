# Proyecto: Impresión de archivos recibidos por WhatsApp

## Contexto
Tengo una papelería en Acapulco. Los clientes me envían por WhatsApp archivos (PDF, Word, Excel, imágenes) para imprimir. Quiero automatizarlo: el cliente manda el archivo, un bot le cotiza, le muestra una vista previa, confirma, y el trabajo se imprime solo en la impresora del local.

A futuro quiero instalar impresoras en otros negocios (multi-sucursal), cada una con un agente local (Raspberry Pi). Diseña desde el inicio pensando en eso, pero implementa solo lo que pide cada fase.

## Stack y restricciones
- **Backend:** Laravel 11 (PHP 8.2+), MySQL, colas con database driver (Redis opcional después).
- **Agente local:** Python 3, multiplataforma (Windows ahora, Raspberry Pi/Linux después).
- **WhatsApp:** WhatsApp Business Cloud API oficial de Meta (no librerías no oficiales).
- **Desarrollo:** todo corre en mi PC con Windows. El webhook se expone con ngrok o Cloudflare Tunnel. Uso el número de prueba gratuito de Meta.
- **Impresoras:** pruebo con una Brother DCP-T720DW en casa (inyección de tinta a color, WiFi y dúplex); producción será una Epson EcoTank L3210 (solo USB). El nombre de la impresora debe ir en configuración, nunca en el código.
- Estructura monorepo: `/backend` (Laravel) y `/agent` (Python).

## Reglas de trabajo
- Trabaja **una fase a la vez**. Al terminar cada fase: corre las pruebas, dame un resumen corto de qué hiciste, cómo probarlo manualmente, y **detente** hasta que yo apruebe.
- Escribe pruebas (PHPUnit/Pest en backend, pytest en el agente) para la lógica principal.
- Secretos en `.env`, nunca en el repo. Incluye `.env.example`.
- Código y comentarios en inglés; mensajes al cliente de WhatsApp en español de México, tono amable y breve.
- Si algo es ambiguo, pregúntame antes de asumir.

---

## Fase 1 — Agente local de impresión (Python)
Objetivo: imprimir un PDF en silencio en la impresora configurada.

- `agent/config.yaml`: `printer_name`, `api_base_url`, `agent_token`, `poll_interval_seconds`, `download_dir`.
- Módulo de impresión con dos backends:
  - **Windows:** SumatraPDF en modo silencioso: `SumatraPDF.exe -print-to "<printer>" -silent -print-settings "<settings>" archivo.pdf` (copias, fit, color/monocromo, dúplex si aplica). Ruta de SumatraPDF configurable.
  - **Linux:** `lp -d <printer> -n <copies> -o fit-to-page ...`.
- Comando de prueba: `python agent.py --test-print archivo.pdf` que imprime un archivo local.
- Comando para listar impresoras disponibles en el sistema.
- Logs a archivo con rotación.
- **Criterio de aceptación:** imprimo un PDF de prueba en mi Brother sin que se abra ninguna ventana.

## Fase 2 — Backend base y API del agente
Objetivo: el agente consulta trabajos pendientes al backend y los imprime.

- Modelos y migraciones:
  - `locations` (sucursal: nombre, dirección, activo).
  - `printers` (location_id, nombre, `agent_token` hasheado, `supports_color`, `last_seen_at`, estado).
  - `customers` (wa_id/teléfono, nombre de perfil).
  - `print_jobs` (folio, `channel` = whatsapp | web | app | kiosk, customer_id, printer_id, archivo original, PDF normalizado, páginas totales, rango de páginas, copias, color/BN, precio, estado, timestamps).
  - Estados de `print_jobs`: `received → processing → quoted → confirmed → ready (esperando al cliente) → (paid) → queued → printing → printed | failed | cancelled | expired`.
  - `locations.print_mode`: `manual` (default) | `auto_if_paid` | `auto`.
    - `manual`: tras la confirmación del cliente el trabajo queda en `ready`; solo pasa a `queued` cuando el operador da clic en **Imprimir** en el dashboard (cuando el cliente llega y paga). Objetivo: evitar merma por clientes que envían y no recogen.
    - `auto_if_paid`: pasa a `queued` automáticamente solo si está pagado; si no, queda en `ready`.
    - `auto`: pasa a `queued` al confirmar.
  - Expiración: trabajos en `ready` sin imprimir después de N días (configurable) → `expired` y se borran sus archivos.
- API para el agente (auth por Bearer token de la impresora):
  - `GET /api/agent/jobs/next` → siguiente trabajo `queued` para esa impresora.
  - `GET /api/agent/jobs/{id}/file` → descarga del PDF (URL firmada o stream autenticado).
  - `POST /api/agent/jobs/{id}/status` → `printing`, `printed`, `failed` (+ mensaje de error).
  - `POST /api/agent/heartbeat` → actualiza `last_seen_at`.
- Agente: loop de polling + heartbeat; reintentos con backoff si el backend no responde; no imprimir dos veces el mismo trabajo (idempotencia).
- Comando artisan para crear un trabajo de prueba manual con un PDF.
- **Criterio:** creo un trabajo con artisan y el agente lo imprime y lo marca `printed`.

## Fase 3 — Webhook de WhatsApp y descarga de archivos
- `GET /webhook/whatsapp`: verificación (`hub.verify_token`, `hub.challenge`).
- `POST /webhook/whatsapp`: validar firma `X-Hub-Signature-256` con el App Secret; responder 200 rápido y procesar en cola.
- Guardar mensajes entrantes (texto, document, image) e idempotencia por `message.id`.
- `DownloadMediaJob`: con el `media_id`, obtener URL de la Graph API y descargar el archivo con el token. Validar tamaño máximo y tipos permitidos (pdf, doc/docx, xls/xlsx, ppt/pptx, pub, jpg/png).
- Servicio `WhatsAppClient` para enviar texto, imagen (subiendo media) y botones interactivos.
- **Criterio:** mando un archivo al número de prueba y se guarda en storage y en `print_jobs` como `received`.

## Fase 4 — Procesamiento del archivo
- Convertir Office → PDF con LibreOffice headless (ruta configurable, funciona en Windows).
- Publisher (`.pub`) → PDF con LibreOffice (lo abre vía libmspub/Draw). La fidelidad no es perfecta (fuentes, posiciones, imágenes), así que la vista previa es obligatoria para estos archivos y conviene sugerir al cliente enviar PDF si la vista previa sale mal. Incluir archivos `.pub` de prueba.
- Imágenes → PDF de una página tamaño carta, ajustado.
- Si llega PDF directo, no se convierte, pero se valida:
  - Que abra (detectar dañado o con contraseña → pedir reenvío o contraseña al cliente).
  - Número de páginas.
  - Tamaño de página: normalizar a carta con "ajustar a hoja" (A4, escaneos de celular, tamaños raros).
  - Orientación: rotar páginas horizontales cuando convenga.
  - Detección de color por página (p. ej. Ghostscript `inkcov`) para sugerir B/N o color y cotizar.
- Herramientas sugeridas: Ghostscript, poppler (`pdfinfo`, `pdftoppm`), qpdf. Documenta cómo instalarlas en Windows y Linux.
- **Criterio:** PDF, Word, imagen y PDF en A4 terminan como PDF carta válido con páginas contadas.

## Fase 5 — Conversación, cotización y vista previa
- Máquina de estados de conversación por cliente (guardada en BD). Opciones a preguntar:
  - Copias.
  - B/N o color (solo si la impresora lo soporta).
  - Rango de páginas si son muchas (ej. "1-5, 8").
- Tabla/config de precios: por página B/N, por página color, mínimos.
- **Vista previa** antes de confirmar:
  - 1–4 páginas: una imagen por página.
  - Más páginas: una imagen con miniaturas en cuadrícula + primera página grande.
  - Mostrar la hoja completa con márgenes; en escala de grises si es B/N.
  - Marca de agua "VISTA PREVIA".
- Mensaje con resumen: páginas, tamaño, B/N/color, copias, precio y botones **Confirmar / Cambiar / Cancelar**.
- Al confirmar: generar folio y aplicar el `print_mode` de la sucursal (en `manual` queda en `ready`).
  - En `manual`, avisar: "Tu folio es #123. Tu archivo está listo; se imprime cuando pases a la papelería y pagues."
  - En automático, avisar "Tu folio es #123" y pasar a `queued`.
- Al imprimir: notificar "Tu impresión #123 está lista".
- Timeouts: si el cliente no responde en X tiempo, cancelar y avisar.
- **[PENDIENTE]** Ajustar textos y pasos según el flujo real de chats con clientes que te pasaré.
- **Criterio:** flujo completo de punta a punta con el número de prueba y la Brother.

## Fase 6 — Panel de administración
- Panel web (Filament) para: ver cola por impresora, reimprimir, cancelar, ver estado/última conexión de cada agente, editar precios.
- **Vista de mostrador** (prioritaria): lista de trabajos en `ready`, búsqueda por folio o teléfono, vista previa del archivo, total a cobrar y botón **Cobrar e imprimir** (marca pagado en mostrador y pasa a `queued`). Debe ser usable desde tablet o celular.
- Configurar `print_mode` y días de expiración por sucursal.
- **Reimpresión** (atascos, hoja manchada, falta de tinta): botón **Reimprimir** en trabajos `printed` o `failed`.
  - Permitir reimprimir todo o solo páginas específicas (ej. "3" o "3-4"), sin volver a cobrar al cliente.
  - Pedir motivo (atasco, manchada, sin tinta, error del operador, otro) y guardar cada reimpresión en una tabla `reprints` (job_id, páginas, motivo, usuario, fecha).
  - Reporte de merma: hojas reimpresas por motivo, por impresora y por periodo.
  - Alerta de mantenimiento: si una impresora acumula más de N reimpresiones por "manchada" en X días, avisar en el panel (sugerir limpieza de cabezales / revisión).
  - Mantener el PDF del trabajo disponible para reimpresión hasta que expire (no borrarlo al marcar `printed`).
- El agente debe reportar `failed` con el error del spooler cuando el sistema lo detecte (impresora desconectada, sin papel); un atasco no siempre se detecta, por eso la reimpresión manual es necesaria.
- Alertas si un agente lleva más de N minutos sin heartbeat o un trabajo falla.

## Fase 7 — Privacidad y limpieza
- Borrado automático de archivos (originales, PDFs y previews) después de N días (configurable), con job programado.
- Mensaje de aviso de privacidad al primer contacto del cliente (LFPDPPP).
- No registrar contenido de archivos en logs.

## Fase 8 (futuro) — Pagos y multi-sucursal
- Pago por link (Mercado Pago / Stripe) o "pago en mostrador"; estado `paid` antes de `queued` si aplica.
- Selección de sucursal por el cliente (QR por negocio o ubicación).
- Panel por negocio con sus trabajos, ventas y comisión.
- Instalador del agente para Raspberry Pi (servicio systemd, CUPS).

## Fase 9 (futuro) — Portal web y app para clientes
Objetivo: un segundo canal además de WhatsApp, reutilizando todo el pipeline (procesamiento, cotización, vista previa, folio, `print_mode`). Nada de lógica duplicada: WhatsApp, web y app deben llamar a los mismos servicios.

- **9a. Portal web (PWA), primero:** página responsiva a la que el cliente llega por link o QR en el mostrador.
  - Login simple con número de celular + código por WhatsApp/SMS.
  - Subir archivo(s), elegir sucursal, opciones (copias, B/N/color, rango de páginas), ver vista previa y precio, confirmar y recibir folio.
  - Historial de pedidos y estado del trabajo en tiempo real.
  - Instalable como PWA ("Agregar a pantalla de inicio").
- **9b. App nativa (Flutter o React Native), solo si el portal tiene uso real:**
  - Compartir archivos directo desde otras apps ("Compartir → Imprimir en Papelería Gabbana").
  - Notificaciones push de "tu impresión está lista".
  - Pago dentro de la app.
- Exponer una API pública versionada (`/api/v1/customer/...`) con auth por token (Sanctum) que usen el portal y la app.

## Fase 10 (futuro) — Isla / kiosco de autoservicio
Objetivo: PC + impresora + monedero en un punto de autoservicio. El cliente imprime solo, desde USB o recogiendo un trabajo de WhatsApp, y paga en el momento. Reutiliza el mismo pipeline y el mismo agente de impresión.

- **App del kiosco:** aplicación web a pantalla completa (modo kiosco de Chrome/Edge o Electron), pantalla táctil, botones grandes, sin acceso al sistema operativo.
- **Entrada de archivos:**
  - **USB:** detectar la memoria, mostrar solo archivos permitidos (pdf, doc/docx, xls/xlsx, ppt/pptx, pub, jpg, png) en un explorador propio; nunca ejecutar nada de la USB (autorun desactivado); copiar el archivo y procesarlo con el mismo pipeline.
  - **WhatsApp:** el cliente escribe su folio o un código que se muestra en pantalla y recoge su trabajo `ready`.
  - **QR en pantalla (recomendado para celulares):** la pantalla muestra un QR con un código de sesión; el cliente lo escanea, se abre una página web de subida (sin app, funciona en iPhone y Android) y el archivo aparece en la pantalla del kiosco al instante (WebSocket/polling). Reutiliza el portal web de la Fase 9.
  - **Bluetooth (solo Android):** el kiosco actúa como receptor de archivos por Bluetooth (OBEX Object Push; en Linux con BlueZ/obexd, en Windows con un receptor dedicado). Los archivos recibidos llegan a una carpeta vigilada y aparecen en pantalla para confirmar. Limitaciones: iPhone no envía archivos por Bluetooth a equipos que no sean Apple; requiere emparejar/aceptar y es lento con archivos grandes. Aceptar solo tipos permitidos, borrar al terminar la sesión.
  - **Trámites oficiales (CURP):** opción "Descargar e imprimir CURP" que abre un navegador embebido restringido por lista blanca al sitio oficial de gob.mx/CURP (sin barra de direcciones ni acceso a otros sitios). El cliente captura sus datos y resuelve el captcha él mismo; el PDF descargado entra directo al flujo de impresión. Al terminar: borrar sesión, cookies y descargas, y no guardar el PDF. Mostrar en pantalla que el trámite en gob.mx es gratuito y que solo se cobra la impresión/servicio. Diseñarlo para que se puedan agregar otros trámites después (lista configurable de sitios permitidos).
  - **Gestoría de acta de nacimiento (integración con el sistema de gestoría existente, otro proyecto):**
    - En la isla: "Solicitar acta de nacimiento" → el cliente captura su CURP (o la obtiene con la opción de CURP) y su celular → paga antes de enviar la solicitud (monedas o Mercado Pago) → recibe un folio en pantalla y por WhatsApp.
    - La isla crea la solicitud en el sistema de gestoría vía API; ese sistema avisa al proveedor y recibe el PDF como ya está diseñado.
    - Cuando el acta llega, el sistema de gestoría llama un webhook de este backend: se crea un `print_job` en `ready` con `channel = kiosk` y tipo `acta`, y se avisa al cliente por WhatsApp: "Tu acta está lista, folio #123. Pasa a la isla e ingresa tu folio para imprimirla." (Opcional: si llega en pocos minutos y el cliente sigue en la isla, mostrarla en pantalla para imprimir de inmediato.)
    - Imprimir el acta ya está pagado (incluida en el precio del servicio); reimpresiones con las reglas de la Fase 6.
    - "No encontrado": avisar al cliente y aplicar la política de devolución (saldo a favor o reembolso; definir).
    - Privacidad: el PDF del acta se guarda **cifrado y solo temporalmente** hasta imprimirse o expirar (ej. 72 h), y luego se borra. Nota: el sistema de gestoría hoy no persiste PDFs; esta excepción es solo para la espera de impresión y debe aprobarse.
    - Todos los textos deben decir explícitamente "gestoría de acta de nacimiento".
- **Flujo:** elegir archivo → opciones (copias, B/N/color, páginas) → vista previa → precio → pagar → imprimir → "Retira tu impresión".
- **Cobro:**
  - **Monedero de monedas** multimoneda programable (aceptar $1, $2, $5 y $10), conectado por pulsos a un microcontrolador (Arduino/ESP32) que reporta a la PC por USB serial. Servicio local que traduce pulsos a montos.
  - **Mercado Pago:** QR dinámico por monto en pantalla y confirmación por webhook.
  - **Cambio:** el monedero no da cambio. Opción A: saldo a favor ligado al número de celular. Opción B: hopper de monedas para dar cambio (costo extra). Decidir antes de esta fase.
  - Registro de cada moneda/pago (`kiosk_payments`) y corte de caja diario con lo contado físicamente.
- **Precios del kiosco:** B/N $1 por página; color entre $3 y $10 por página según cobertura de color (usar la detección de color de la Fase 4); reglas configurables desde el panel.
- **Operación:** el kiosco aparece en el dashboard como una sucursal más (estado, papel, tinta, ingresos, alertas de atasco), con borrado de archivos de USB al terminar la sesión y botón "Cancelar / empezar de nuevo" con timeout por inactividad.

---

**Empieza por la Fase 1.** Antes de escribir código, muéstrame la estructura de carpetas propuesta y confirma conmigo.
