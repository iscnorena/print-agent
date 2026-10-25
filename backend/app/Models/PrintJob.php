<?php

namespace App\Models;

use App\Enums\Channel;
use App\Enums\Duplex;
use App\Enums\PrintJobStatus;
use Database\Factories\PrintJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'channel', 'location_id', 'customer_id', 'printer_id',
    'original_path', 'original_name', 'pdf_path', 'total_pages',
    'page_range', 'copies', 'color', 'duplex', 'price',
])]
class PrintJob extends Model
{
    /** @use HasFactory<PrintJobFactory> */
    use HasFactory;

    /**
     * Status starts as "received"; mirrored here so new models see it before saving.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'received',
    ];

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Printer, $this>
     */
    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    /**
     * Give the job its counter folio once: the job number, plus the last four
     * digits of the customer's phone when there is one (e.g. "125-4521"), so the
     * counter can confirm it is the right person.
     */
    public function assignFolio(): void
    {
        if ($this->folio !== null) {
            return;
        }

        $phone = $this->customer?->phone;
        $this->folio = $phone === null ? (string) $this->id : $this->id.'-'.substr($phone, -4);
        $this->save();
    }

    /**
     * Oldest queued job first, so customers are served in order.
     */
    #[Scope]
    protected function queuedFor(Builder $query, Printer $printer): Builder
    {
        return $query->whereBelongsTo($printer)
            ->where('status', PrintJobStatus::Queued)
            ->orderBy('queued_at')
            ->orderBy('id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'status' => PrintJobStatus::class,
            'duplex' => Duplex::class,
            'color' => 'boolean',
            'price' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'ready_at' => 'datetime',
            'paid_at' => 'datetime',
            'queued_at' => 'datetime',
            'printed_at' => 'datetime',
        ];
    }
}
