<?php

use App\Enums\Channel;
use App\Enums\Duplex;
use App\Enums\PrintJobStatus;
use App\Enums\PrintMode;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Printer;
use App\Models\PrintJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function customerPdf(): UploadedFile
{
    return new UploadedFile(base_path('tests/Fixtures/one-page.pdf'), 'tarea.pdf', 'application/pdf', null, true);
}

beforeEach(function () {
    Storage::fake('local');
    $this->location = Location::factory()->printMode(PrintMode::Manual)->create();
    $this->printer = Printer::factory()->create(['location_id' => $this->location->id, 'supports_color' => true]);
});

describe('create', function () {
    it('shows the upload form without logging in', function () {
        $this->get(route('customer.uploads.create'))
            ->assertOk()
            ->assertSee('Envía tu archivo para imprimir')
            ->assertSee('A color');
    });

    it('hides the color option when no printer supports it', function () {
        $this->printer->update(['supports_color' => false]);

        $this->get(route('customer.uploads.create'))
            ->assertOk()
            ->assertDontSee('A color');
    });

    it('says the service is unavailable when no location is active', function () {
        $this->location->update(['is_active' => false]);

        $this->get(route('customer.uploads.create'))
            ->assertOk()
            ->assertSee('Servicio no disponible');
    });
});

describe('store', function () {
    it('leaves the job ready with a folio ending in the phone digits', function () {
        $this->post(route('customer.uploads.store'), [
            'file' => customerPdf(),
            'phone' => '744 123 4521',
            'name' => ' Ana ',
            'copies' => 2,
            'color' => '1',
            'duplex' => 'long',
            'page_range' => '1-2',
        ])
            ->assertRedirect(route('customer.uploads.create'))
            ->assertSessionHas('submitted', fn (array $submitted) => $submitted['folio'] === PrintJob::sole()->folio && ! $submitted['queued']);

        $job = PrintJob::sole();
        expect($job)
            ->status->toBe(PrintJobStatus::Ready)
            ->channel->toBe(Channel::Web)
            ->location_id->toBe($this->location->id)
            ->folio->toBe("{$job->id}-4521")
            ->copies->toBe(2)
            ->color->toBeTrue()
            ->duplex->toBe(Duplex::Long)
            ->page_range->toBe('1-2')
            ->customer->phone->toBe('7441234521')
            ->customer->profile_name->toBe('Ana');
        Storage::disk('local')->assertExists($job->pdf_path);
    });

    it('shows the folio to the customer after sending', function () {
        $this->followingRedirects()
            ->post(route('customer.uploads.store'), [
                'file' => customerPdf(), 'phone' => '7441234521', 'copies' => 1, 'duplex' => 'simplex',
            ])
            ->assertSee('Tu folio es')
            ->assertSee('#'.PrintJob::sole()->folio)
            ->assertSee('se imprime cuando pases a la papelería');
    });

    it('reuses the customer with the same phone', function () {
        $customer = Customer::factory()->create(['phone' => '7441234521', 'profile_name' => 'Ana']);

        $this->post(route('customer.uploads.store'), [
            'file' => customerPdf(), 'phone' => '+52 744 123 4521', 'copies' => 1, 'duplex' => 'simplex',
        ]);

        expect(Customer::count())->toBe(1)
            ->and(PrintJob::sole()->customer_id)->toBe($customer->id)
            ->and($customer->fresh()->profile_name)->toBe('Ana');
    });

    it('requires a valid 10-digit phone', function (?string $phone, string $message) {
        $this->post(route('customer.uploads.store'), [
            'file' => customerPdf(), 'phone' => $phone, 'copies' => 1, 'duplex' => 'simplex',
        ])->assertSessionHasErrors(['phone' => $message]);

        expect(PrintJob::count())->toBe(0);
    })->with([
        'missing' => [null, 'Escribe tu número de celular.'],
        'too short' => ['74412345', 'Escribe tu número de celular a 10 dígitos.'],
    ]);

    it('rejects color when no printer supports it', function () {
        $this->printer->update(['supports_color' => false]);

        $this->post(route('customer.uploads.store'), [
            'file' => customerPdf(), 'phone' => '7441234521', 'copies' => 1, 'color' => '1', 'duplex' => 'simplex',
        ])->assertSessionHasErrors(['color' => 'Por ahora solo imprimimos en blanco y negro.']);
    });

    it('rejects uploads when no location is active', function () {
        $this->location->update(['is_active' => false]);

        $this->post(route('customer.uploads.store'), [
            'file' => customerPdf(), 'phone' => '7441234521', 'copies' => 1, 'duplex' => 'simplex',
        ])->assertServiceUnavailable();

        expect(PrintJob::count())->toBe(0);
    });

    it('limits uploads to ten per minute', function () {
        foreach (range(1, 10) as $attempt) {
            $this->post(route('customer.uploads.store'), []);
        }

        $this->post(route('customer.uploads.store'), [])->assertTooManyRequests();
    });
});
