<?php

namespace App\Models;

use App\Enums\PrinterStatus;
use Database\Factories\PrinterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['location_id', 'name', 'supports_color', 'status'])]
#[Hidden(['agent_token_hash'])]
class Printer extends Model
{
    /** @use HasFactory<PrinterFactory> */
    use HasFactory;

    /**
     * Agent tokens are random, so a plain SHA-256 is enough and lets us look
     * the printer up by hash on every request (bcrypt would not).
     */
    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public static function findActiveByToken(string $plainToken): ?self
    {
        return self::query()
            ->where('agent_token_hash', self::hashToken($plainToken))
            ->where('status', PrinterStatus::Active)
            ->first();
    }

    /**
     * Generate a new agent token, store its hash and return the plain value.
     * The plain token is never stored, so it can only be shown once.
     */
    public function issueToken(): string
    {
        $plainToken = 'pa_'.Str::random(48);

        $this->agent_token_hash = self::hashToken($plainToken);
        $this->save();

        return $plainToken;
    }

    /**
     * Whether the agent has sent a heartbeat recently.
     */
    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subSeconds(config('printing.agent_offline_after_seconds')));
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return HasMany<PrintJob, $this>
     */
    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'supports_color' => 'boolean',
            'status' => PrinterStatus::class,
            'last_seen_at' => 'datetime',
        ];
    }
}
