@extends('layouts.app')

@section('title', 'Imprimir')

@section('content')
    @if (session('status'))
        <div class="alert alert-ok">{{ session('status') }}</div>
    @endif

    <div class="card">
        <h1>Imprimir un PDF</h1>

        @if ($printers->isEmpty())
            <div class="alert alert-error">No hay impresoras activas. Registra una con <code>php artisan printer:create</code>.</div>
        @else
            <form id="print-form" method="POST" action="{{ route('operator.print-jobs.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="field">
                    <label for="file">Archivo PDF <span class="hint">· máximo {{ intdiv(config('printing.max_upload_kb'), 1024) }} MB</span></label>
                    <input id="file" type="file" name="file" accept="application/pdf,.pdf" required>
                    @error('file') <div class="error">{{ $message }}</div> @enderror
                </div>

                <div class="field">
                    <label for="printer_id">Impresora</label>
                    <select id="printer_id" name="printer_id" required>
                        @foreach ($printers as $printer)
                            <option value="{{ $printer->id }}" data-color="{{ $printer->supports_color ? 1 : 0 }}" data-online="{{ $printer->isOnline() ? 1 : 0 }}" @selected(old('printer_id') == $printer->id)>
                                {{ $printer->name }} — {{ $printer->isOnline() ? 'conectada' : 'sin conexión' }}
                            </option>
                        @endforeach
                    </select>
                    @error('printer_id') <div class="error">{{ $message }}</div> @enderror
                    <div id="offline-hint" class="hint" hidden>
                        <span class="dot"></span>El agente de esta impresora no está corriendo; el trabajo se imprimirá cuando se conecte.
                    </div>
                </div>

                <div class="grid">
                    <div class="field">
                        <label for="copies">Copias</label>
                        <input id="copies" type="number" name="copies" min="1" max="100" value="{{ old('copies', 1) }}" required>
                        @error('copies') <div class="error">{{ $message }}</div> @enderror
                    </div>
                    <div class="field">
                        <label for="duplex">Caras</label>
                        <select id="duplex" name="duplex">
                            <option value="simplex" @selected(old('duplex') === 'simplex')>Una cara</option>
                            <option value="long" @selected(old('duplex') === 'long')>Doble cara (borde largo)</option>
                            <option value="short" @selected(old('duplex') === 'short')>Doble cara (borde corto)</option>
                        </select>
                        @error('duplex') <div class="error">{{ $message }}</div> @enderror
                    </div>
                    <div class="field">
                        <label for="page_range">Páginas <span class="hint">· vacío = todas</span></label>
                        <input id="page_range" type="text" name="page_range" value="{{ old('page_range') }}" placeholder="1-3, 5" inputmode="numeric">
                        @error('page_range') <div class="error">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="field">
                    <label class="check"><input id="color" type="checkbox" name="color" value="1" @checked(old('color'))> A color</label>
                    @error('color') <div class="error">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn-block">Imprimir</button>
            </form>
        @endif
    </div>

    <div class="card">
        <h2>Últimos trabajos</h2>
        <div id="jobs" data-url="{{ route('operator.print-jobs.list') }}">
            @include('operator.print-jobs._list')
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('print-form');
            const printer = document.getElementById('printer_id');
            const color = document.getElementById('color');
            const offlineHint = document.getElementById('offline-hint');

            if (form) {
                const syncPrinter = () => {
                    const option = printer.selectedOptions[0];
                    const supportsColor = option.dataset.color === '1';
                    color.disabled = !supportsColor;
                    if (!supportsColor) color.checked = false;
                    offlineHint.hidden = option.dataset.online === '1';
                };
                printer.addEventListener('change', syncPrinter);
                syncPrinter();

                // Avoid printing twice on a double click.
                form.addEventListener('submit', () => {
                    const button = form.querySelector('button[type=submit]');
                    button.disabled = true;
                    button.textContent = 'Enviando…';
                });
            }

            // Refresh the jobs table so status changes (en cola → impreso) show up live.
            const jobs = document.getElementById('jobs');
            setInterval(async () => {
                if (document.hidden) return;
                try {
                    const response = await fetch(jobs.dataset.url, { headers: { Accept: 'text/html' } });
                    // Session expired: reload so the login page shows instead of being injected here.
                    if (response.redirected) return location.reload();
                    if (response.ok) jobs.innerHTML = await response.text();
                } catch (_) {
                    // Network hiccup: try again on the next tick.
                }
            }, 4000);
        })();
    </script>
@endpush
