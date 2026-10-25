<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Folio</th>
                <th>Archivo</th>
                <th>Impresora</th>
                <th>Opciones</th>
                <th>Estado</th>
                <th>Hora</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($jobs as $job)
                <tr>
                    <td>#{{ $job->folio ?? $job->id }}</td>
                    <td class="name">{{ $job->original_name }}</td>
                    <td>{{ $job->printer?->name ?? '—' }}</td>
                    <td>
                        {{ $job->copies }} {{ $job->copies === 1 ? 'copia' : 'copias' }},
                        {{ $job->color ? 'color' : 'B/N' }}@if ($job->duplex !== \App\Enums\Duplex::Simplex), doble cara @endif
                        @if ($job->page_range) <span class="muted">· págs. {{ $job->page_range }}</span> @endif
                    </td>
                    <td>
                        <span class="badge badge-{{ $job->status->value }}">{{ $job->status->label() }}</span>
                        @if ($job->error_message) <div class="error">{{ $job->error_message }}</div> @endif
                    </td>
                    <td class="muted">{{ $job->created_at->timezone(config('printing.display_timezone'))->format('d/m H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Todavía no hay trabajos.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
