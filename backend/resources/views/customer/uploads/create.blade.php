@extends('layouts.app')

@section('title', 'Envía tu archivo')

@section('content')
    <div style="max-width: 560px; margin: 0 auto;">
        @if ($submitted = session('submitted'))
            <div class="card" style="text-align: center;">
                <p class="muted" style="margin: 0;">Tu folio es</p>
                <p style="font-size: 2.5rem; font-weight: 700; margin: .25rem 0 1rem; letter-spacing: .02em;">#{{ $submitted['folio'] }}</p>
                @if ($submitted['queued'])
                    <p>Ya estamos imprimiendo tu archivo.</p>
                @else
                    <p>Tu archivo está listo; se imprime cuando pases a la papelería y pagues. Dinos tu folio en el mostrador.</p>
                @endif
                <a href="{{ route('customer.uploads.create') }}" class="btn">Enviar otro archivo</a>
            </div>
        @elseif ($location === null)
            <div class="card">
                <h1>Servicio no disponible</h1>
                <p>Por el momento no estamos recibiendo archivos por aquí. Intenta más tarde.</p>
            </div>
        @else
            <div class="card">
                <h1>Envía tu archivo para imprimir</h1>

                <form id="upload-form" method="POST" action="{{ route('customer.uploads.store') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="field">
                        <label for="file">Archivo PDF <span class="hint">· máximo {{ intdiv(config('printing.max_upload_kb'), 1024) }} MB</span></label>
                        <input id="file" type="file" name="file" accept="application/pdf,.pdf" required>
                        @error('file') <div class="error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label for="phone">Tu celular <span class="hint">· 10 dígitos</span></label>
                        <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" inputmode="numeric" autocomplete="tel-national" placeholder="744 123 4567" required>
                        @error('phone') <div class="error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label for="name">Tu nombre <span class="hint">· opcional</span></label>
                        <input id="name" type="text" name="name" value="{{ old('name') }}" maxlength="60" autocomplete="given-name">
                        @error('name') <div class="error">{{ $message }}</div> @enderror
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
                                <option value="long" @selected(old('duplex') === 'long')>Doble cara</option>
                            </select>
                            @error('duplex') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="page_range">Páginas <span class="hint">· vacío = todas</span></label>
                            <input id="page_range" type="text" name="page_range" value="{{ old('page_range') }}" placeholder="1-3, 5" inputmode="numeric">
                            @error('page_range') <div class="error">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    @if ($supportsColor)
                        <div class="field">
                            <label class="check"><input type="checkbox" name="color" value="1" @checked(old('color'))> A color</label>
                            @error('color') <div class="error">{{ $message }}</div> @enderror
                        </div>
                    @endif

                    <button type="submit" class="btn-block">Enviar</button>
                    <p class="hint" style="margin-top: .75rem;">Usamos tu celular solo para identificar tu pedido. Tu archivo se borra después de unos días.</p>
                </form>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        // Avoid sending the file twice on a double tap.
        document.getElementById('upload-form')?.addEventListener('submit', (event) => {
            const button = event.target.querySelector('button[type=submit]');
            button.disabled = true;
            button.textContent = 'Enviando…';
        });
    </script>
@endpush
