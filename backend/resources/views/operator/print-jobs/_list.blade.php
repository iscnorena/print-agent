<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Folio</th>
                <th>Cliente</th>
                <th>Archivo</th>
                <th>Opciones</th>
                <th>Estado</th>
                <th>Hora</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($jobs as $job)
                <tr>
                    <td><strong>#{{ $job->folio ?? $job->id }}</strong></td>
                    <td>
                        @if ($job->customer)
                            {{ $job->customer->profile_name }}
                            <div class="muted">{{ $job->customer->formattedPhone() }}</div>
                        @else
                            <span class="muted">Mostrador</span>
                        @endif
                    </td>
                    <td class="name">{{ $job->original_name }}</td>
                    <td>
                        {{ $job->copies }} {{ $job->copies === 1 ? 'copia' : 'copias' }},
                        {{ $job->color ? 'color' : 'B/N' }}@if ($job->duplex !== \App\Enums\Duplex::Simplex), doble cara @endif
                        @if ($job->page_range) <span class="muted">· págs. {{ $job->page_range }}</span> @endif
                        @if ($job->printer) <div class="muted">{{ $job->printer->name }}</div> @endif
                    </td>
                    <td>
                        @if ($job->status === \App\Enums\PrintJobStatus::Ready)
                            <button type="button" class="js-queue"
                                data-url="{{ route('operator.print-jobs.queue', $job) }}"
                                data-folio="{{ $job->folio }}"
                                data-name="{{ $job->original_name }}"
                                data-copies="{{ $job->copies }}"
                                data-color="{{ $job->color ? 1 : 0 }}"
                                data-duplex="{{ $job->duplex->value }}"
                                data-pages="{{ $job->page_range }}">Imprimir</button>
                        @else
                            <span class="badge badge-{{ $job->status->value }}">{{ $job->status->label() }}</span>
                        @endif
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
