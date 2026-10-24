# Agente de impresión

Imprime en silencio en la impresora configurada los trabajos que le manda el backend. Probado para Windows (SumatraPDF) y Linux/Raspberry Pi (CUPS).

## Instalación en Windows

1. Instala **Python 3.10+** (marca "Add Python to PATH").
2. Instala **SumatraPDF**: https://www.sumatrapdfreader.org (instalador o versión portable).
3. Instala la Brother DCP-T720DW con el driver oficial de Brother e imprime una página de prueba normal desde Windows.
4. En esta carpeta:

```powershell
pip install -r requirements.txt
copy config.example.yaml config.yaml
copy .env.example .env
python agent.py --list-printers
```

5. Copia el nombre exacto de la Brother en `printer_name` de `config.yaml` y revisa `sumatra_path`.

## Pruebas de impresión

```powershell
# B/N, 1 copia
python agent.py --test-print prueba.pdf

# Color, 2 copias, solo páginas 1 y 3
python agent.py --test-print prueba.pdf --color --copies 2 --pages "1,3"

# Doble cara (borde largo)
python agent.py --test-print prueba.pdf --duplex long
```

**Criterio de aceptación:** el PDF sale impreso sin que se abra ninguna ventana.

Revisa en cada prueba:
- Que B/N salga realmente en gris (algunos drivers ignoran la opción; si pasa, se fuerza desde las preferencias del driver).
- Márgenes y tamaño carta.
- Que el rango de páginas y las copias se respeten.

Los logs quedan en `logs/agent.log`.

## Modo agente (Fase 2)

1. En el backend (WSL), registra la impresora y copia el token que imprime:

```bash
php artisan printer:create "Brother casa" --color
```

2. Pega el token en `agent/.env` como `AGENT_TOKEN=...`.
3. Arranca el backend en WSL: `php artisan serve` (Windows lo ve en `http://localhost:8000`).
4. Arranca el agente en Windows:

```powershell
python agent.py --run
```

5. Crea un trabajo de prueba en el backend y el agente lo imprime y lo marca `printed`:

```bash
php artisan job:create-test /mnt/c/Users/<tu-usuario>/Documents/prueba.pdf --copies 2 --color
```

El agente guarda en `state.json` los trabajos que ya procesó, para no imprimir dos veces el mismo, y reintenta los avisos al backend si este no responde.

## Pruebas automáticas

```powershell
python -m pytest
```

## Linux / Raspberry Pi (más adelante)

```bash
sudo apt install cups
python3 agent.py --list-printers
python3 agent.py --test-print prueba.pdf
```
