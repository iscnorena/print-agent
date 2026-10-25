<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['wa_id', 'phone', 'profile_name'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * Reduce a Mexican phone to its 10 national digits, accepting spaces,
     * dashes and the 52 / 521 country prefixes. Returns null when it is not one.
     */
    public static function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone);

        $national = match (true) {
            strlen($digits) === 10 => $digits,
            strlen($digits) === 12 && str_starts_with($digits, '52') => substr($digits, 2),
            strlen($digits) === 13 && str_starts_with($digits, '521') => substr($digits, 3),
            default => null,
        };

        return $national !== null && $national[0] !== '0' ? $national : null;
    }

    /**
     * Phone grouped for reading aloud at the counter, e.g. "744 123 4567".
     */
    public function formattedPhone(): ?string
    {
        if ($this->phone === null) {
            return null;
        }

        return substr($this->phone, 0, 3).' '.substr($this->phone, 3, 3).' '.substr($this->phone, 6);
    }

    /**
     * @return HasMany<PrintJob, $this>
     */
    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }
}
