# Desarrollo local

Cómo levantar todo en la PC (backend en WSL, agente en Windows) y cómo exponerlo a internet con ngrok para probar desde el celular.

## 1. Backend (WSL)

```bash
cd ~/projects/print-agent/backend
php artisan serve            # http://localhost:8000
```

Solo la primera vez:

```bash
php artisan migrate
php artisan printer:create "Brother casa" --color    # imprime el token del agente (se muestra una sola vez)
php artisan user:create "Mostrador" tu-correo@gmail.com   # usuario del panel; también sirve para cambiar la contraseña
```

PHP debe aceptar archivos grandes (por defecto solo 2 MB). Una sola vez:

```bash
printf 'upload_max_filesize = 25M\npost_max_size = 26M\n' | sudo tee /etc/php/8.5/cli/conf.d/99-uploads.ini
```

## 2. Agente de impresión (Windows)

El agente corre desde `C:\agente\agent`, una copia de la carpeta `agent/` del repo. Después de cambiar el código del agente hay que volver a copiarlo ahí, sin tocar `config.yaml`, `.env` ni `logs/`.

```powershell
cd C:\agente\agent
python -m pip install -r requirements.txt   # después de cada actualización
python agent.py --run
```

El token de la impresora va en `C:\agente\agent\.env` como `AGENT_TOKEN=...`.

## 3. Páginas

| Página | Para quién | Dirección local |
| --- | --- | --- |
| Subir archivo (cliente) | Público, sin contraseña | http://localhost:8000/subir |
| Mostrador | Operador, con usuario y contraseña | http://localhost:8000/print |

- **Cliente:** sube su PDF con su celular. El trabajo queda en **Listo** con folio `<número>-<últimos 4 dígitos del celular>`, por ejemplo `125-4521`.
- **Mostrador:** lo que sube el operador se imprime directo. Los trabajos de clientes aparecen en "Últimos trabajos" con el botón **Imprimir**, que permite ajustar las opciones antes de mandarlos.

Si después de una actualización un botón no responde, recarga la página con **Ctrl+F5**. La lista se refresca sola, pero el código de la página se queda en la versión con la que se abrió.

## 4. Probar desde el celular (ngrok)

Liga pública (dominio fijo gratuito de ngrok):

**https://unedited-straddle-moneybags.ngrok-free.dev**

- Cliente: https://unedited-straddle-moneybags.ngrok-free.dev/subir
- Mostrador: https://unedited-straddle-moneybags.ngrok-free.dev/print

Con el backend corriendo, abre el túnel en otra terminal de WSL:

```bash
ngrok http 8000 --url=unedited-straddle-moneybags.ngrok-free.dev
```

- El dominio va tal cual, terminado en `.ngrok-free.dev`. Si le agregas algo más, ngrok responde `ERR_NGROK_313` (subdominio personalizado, solo planes de pago).
- Solo puede haber un túnel abierto a la vez con este dominio.
- La primera visita desde un navegador muestra un aviso de ngrok; presiona **Visit Site**.
- Mientras el túnel esté abierto, cualquiera con la liga puede usar `/subir` (limitado a 10 envíos por minuto). `/print` sigue pidiendo contraseña. **Cierra el túnel con Ctrl+C al terminar de probar.**
- Esta misma liga será la Callback URL del webhook de WhatsApp (Fase 3): `https://unedited-straddle-moneybags.ngrok-free.dev/webhook/whatsapp`.

Instalación (ya hecha en esta PC): binario en `~/.local/bin/ngrok`. El authtoken de la cuenta se configura una vez con `ngrok config add-authtoken <token>`. El token es secreto y no va en el repo.

Laravel confía en los encabezados `X-Forwarded-*` solo si vienen de `127.0.0.1` (ver `backend/bootstrap/app.php`). Así las ligas y formularios usan `https://` detrás de ngrok sin permitir que alguien falsifique su IP.
