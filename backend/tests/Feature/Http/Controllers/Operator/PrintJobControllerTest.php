<?php

use App\Enums\Channel;
use App\Enums\Duplex;
use App\Enums\PrintJobStatus;
use App\Models\Customer;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function pdfUpload(string $name = 'tarea.pdf'): UploadedFile
{
    return new UploadedFile(base_path('tests/Fixtures/one-page.pdf'), $name, 'application/pdf', null, true);
}

beforeEach(function () {
    Storage::fake('local');
    $this->printer = Printer::factory()->create(['name' => 'Brother casa']);
});

it('sends guests to the login page', function (string $method, string $route) {
    $this->call($method, route($route))->assertRedirect(route('login'));
})->with([
    'index' => ['GET', 'operator.print-jobs.index'],
    'list' => ['GET', 'operator.print-jobs.list'],
    'store' => ['POST', 'operator.print-jobs.store'],
]);

it('sends guests trying to print a waiting job to the login page', function () {
    $job = PrintJob::factory()->status(PrintJobStatus::Ready)->create();

    $this->post(route('operator.print-jobs.queue', $job))->assertRedirect(route('login'));

    expect($job->fresh()->status)->toBe(PrintJobStatus::Ready);
});

describe('index', function () {
    it('shows active printers with their connection state and the latest jobs', function () {
        $this->printer->forceFill(['last_seen_at' => now()])->save();
        Printer::factory()->disabled()->create(['name' => 'Epson vieja']);
        PrintJob::factory()->queuedOn($this->printer)->create(['original_name' => 'examen.pdf']);

        $this->actingAs(User::factory()->create())
            ->get(route('operator.print-jobs.index'))
            ->assertOk()
            ->assertSee('Brother casa — conectada')
            ->assertDontSee('Epson vieja')
            ->assertSee('examen.pdf')
            ->assertSee('En cola');
    });
});

describe('list', function () {
    it('returns only the jobs table', function () {
        PrintJob::factory()->queuedOn($this->printer)->create(['original_name' => 'examen.pdf']);

        $this->actingAs(User::factory()->create())
            ->get(route('operator.print-jobs.list'))
            ->assertOk()
            ->assertSee('examen.pdf')
            ->assertDontSee('<form', false);
    });
});

describe('store', function () {
    it('queues the uploaded PDF on the chosen printer', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('operator.print-jobs.store'), [
                'file' => pdfUpload(),
                'printer_id' => $this->printer->id,
                'copies' => 2,
                'color' => '1',
                'duplex' => 'long',
                'page_range' => ' 1 - 3, 5 ',
            ])
            ->assertRedirect(route('operator.print-jobs.index'))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'Brother casa'));

        $job = PrintJob::sole();
        expect($job)
            ->status->toBe(PrintJobStatus::Queued)
            ->channel->toBe(Channel::Web)
            ->printer_id->toBe($this->printer->id)
            ->original_name->toBe('tarea.pdf')
            ->copies->toBe(2)
            ->color->toBeTrue()
            ->duplex->toBe(Duplex::Long)
            ->page_range->toBe('1-3,5');
        Storage::disk('local')->assertExists($job->pdf_path);
    });

    it('defaults to black and white, single sided, all pages', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('operator.print-jobs.store'), [
                'file' => pdfUpload(),
                'printer_id' => $this->printer->id,
                'copies' => 1,
                'duplex' => 'simplex',
                'page_range' => '',
            ])
            ->assertRedirect(route('operator.print-jobs.index'));

        expect(PrintJob::sole())
            ->color->toBeFalse()
            ->duplex->toBe(Duplex::Simplex)
            ->page_range->toBeNull();
    });

    it('requires a file, a printer, copies and sides', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('operator.print-jobs.store'), [])
            ->assertSessionHasErrors([
                'file' => 'Elige un archivo PDF.',
                'printer_id',
                'copies',
                'duplex',
            ]);

        expect(PrintJob::count())->toBe(0);
    });

    it('rejects a file that is not a PDF even if it is named .pdf', function () {
        // A real file, not UploadedFile::fake(): fakes report their MIME type from the name.
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, 'just some text');
        $renamedText = new UploadedFile($path, 'tarea.pdf', 'application/pdf', null, true);

        $this->actingAs(User::factory()->create())
            ->post(route('operator.print-jobs.store'), [
                'file' => $renamedText,
                'printer_id' => $this->printer->id,
                'copies' => 1,
                'duplex' => 'simplex',
            ])
            ->assertSessionHasErrors(['file' => 'Por ahora solo se aceptan archivos PDF.']);
    });

    it('rejects a malformed page range', function (string $pageRange) {
        $this->actingAs(User::factory()->create())
            ->post(route('operator.print-jobs.store'), [
                'file' => pdfUpload(),
                'printer_id' => $this->printer->id,
                'copies' => 1,
                'duplex' => 'simplex',
                'page_range' => $pageRange,
            ])
            ->assertSessionHasErrors(['page_range' => 'Escribe las páginas así: 1-3, 5']);
    })->with(['letters' => 'uno', 'open range' => '3-', 'semicolon' => '1;2']);

    it('rejects color on a black and white printer', function () {
        $printer = Printer::factory()->create(['supports_color' => false]);

        $this->actingAs(User::factory()->create())
            ->post(route('operator.print-jobs.store'), [
                'file' => pdfUpload(),
                'printer_id' => $printer->id,
                'copies' => 1,
                'color' => '1',
                'duplex' => 'simplex',
            ])
            ->assertSessionHasErrors(['color' => 'Esta impresora solo imprime en blanco y negro.']);

        expect(PrintJob::count())->toBe(0);
    });

    it('rejects a disabled printer', function () {
        $printer = Printer::factory()->disabled()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('operator.print-jobs.store'), [
                'file' => pdfUpload(),
                'printer_id' => $printer->id,
                'copies' => 1,
                'duplex' => 'simplex',
            ])
            ->assertSessionHasErrors(['printer_id' => 'Elige una impresora activa.']);
    });
});

describe('queue', function () {
    beforeEach(function () {
        $this->customer = Customer::factory()->create(['phone' => '7441234521', 'profile_name' => 'Ana']);
        $this->job = PrintJob::factory()->status(PrintJobStatus::Ready)->create([
            'location_id' => $this->printer->location_id,
            'customer_id' => $this->customer->id,
            'folio' => '7-4521',
            'copies' => 1,
            'color' => false,
        ]);
    });

    it('shows an Imprimir button with the customer for jobs waiting in ready', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('operator.print-jobs.list'))
            ->assertSee('#7-4521')
            ->assertSee('Ana')
            ->assertSee('744 123 4521')
            ->assertSee(route('operator.print-jobs.queue', $this->job));
    });

    it('applies the operator options and sends the job to the location printer', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('operator.print-jobs.queue', $this->job), [
                'copies' => 3,
                'color' => '1',
                'duplex' => 'short',
                'page_range' => '2',
            ])
            ->assertRedirect(route('operator.print-jobs.index'))
            ->assertSessionHas('status', 'Folio #7-4521 enviado a Brother casa.');

        expect($this->job->fresh())
            ->status->toBe(PrintJobStatus::Queued)
            ->printer_id->toBe($this->printer->id)
            ->copies->toBe(3)
            ->color->toBeTrue()
            ->duplex->toBe(Duplex::Short)
            ->page_range->toBe('2');
    });

    it('does not print a job that is no longer waiting', function () {
        $this->job->forceFill(['status' => PrintJobStatus::Queued])->save();

        $this->actingAs(User::factory()->create())
            ->post(route('operator.print-jobs.queue', $this->job), ['copies' => 5, 'duplex' => 'simplex'])
            ->assertRedirect(route('operator.print-jobs.index'))
            ->assertSessionHasErrorsIn('queue', ['job' => 'El folio #7-4521 ya no está esperando para imprimirse.']);

        expect($this->job->fresh()->copies)->toBe(1);
    });

    it('rejects color when the location printer is black and white', function () {
        $this->printer->update(['supports_color' => false]);

        $this->actingAs(User::factory()->create())
            ->post(route('operator.print-jobs.queue', $this->job), ['copies' => 1, 'color' => '1', 'duplex' => 'simplex'])
            ->assertSessionHasErrorsIn('queue', ['color' => 'Esta impresora solo imprime en blanco y negro.']);

        expect($this->job->fresh()->status)->toBe(PrintJobStatus::Ready);
    });
});
