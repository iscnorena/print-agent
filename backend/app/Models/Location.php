<?php

namespace App\Models;

use App\Enums\PrintMode;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'address', 'is_active', 'print_mode', 'ready_expiration_days'])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    /**
     * @return HasMany<Printer, $this>
     */
    public function printers(): HasMany
    {
        return $this->hasMany(Printer::class);
    }

    /**
     * @return HasMany<PrintJob, $this>
     */
    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    public function readyExpirationDays(): int
    {
        return $this->ready_expiration_days ?? config('printing.ready_expiration_days');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'print_mode' => PrintMode::class,
        ];
    }
}
