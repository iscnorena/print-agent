<?php

use App\Models\Customer;

it('normalizes a Mexican phone to its 10 national digits', function (string $input, ?string $expected) {
    expect(Customer::normalizePhone($input))->toBe($expected);
})->with([
    'plain' => ['7441234521', '7441234521'],
    'with spaces and dashes' => ['744 123-45 21', '7441234521'],
    'with +52' => ['+52 744 123 4521', '7441234521'],
    'WhatsApp id with 521' => ['5217441234521', '7441234521'],
    'too short' => ['744123452', null],
    'too long' => ['74412345210', null],
    'starts with 0' => ['0441234521', null],
    'letters' => ['siete', null],
]);

it('groups the phone for reading at the counter', function () {
    expect((new Customer(['phone' => '7441234521']))->formattedPhone())->toBe('744 123 4521')
        ->and((new Customer)->formattedPhone())->toBeNull();
});
