<?php

use App\Models\Location;
use App\Models\Printer;

it('creates the printer and its location and prints a working token', function () {
    $this->artisan('printer:create', ['name' => 'Brother casa', '--location' => 'Casa', '--color' => true])
        ->expectsOutputToContain('Agent token')
        ->assertSuccessful();

    $printer = Printer::sole();
    expect($printer)
        ->name->toBe('Brother casa')
        ->supports_color->toBeTrue()
        ->location->name->toBe('Casa');
});

it('reuses an existing location with the same name', function () {
    Location::factory()->create(['name' => 'Matriz']);

    $this->artisan('printer:create', ['name' => 'Epson'])->assertSuccessful();

    expect(Location::count())->toBe(1)
        ->and(Printer::sole()->supports_color)->toBeFalse();
});
