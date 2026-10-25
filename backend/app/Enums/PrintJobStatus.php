<?php

namespace App\Enums;

enum PrintJobStatus: string
{
    case Received = 'received';
    case Processing = 'processing';
    case Quoted = 'quoted';
    case Confirmed = 'confirmed';
    case Ready = 'ready';
    case Queued = 'queued';
    case Printing = 'printing';
    case Printed = 'printed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * Name shown to operators.
     */
    public function label(): string
    {
        return match ($this) {
            self::Received => 'Recibido',
            self::Processing => 'Procesando',
            self::Quoted => 'Cotizado',
            self::Confirmed => 'Confirmado',
            self::Ready => 'Listo',
            self::Queued => 'En cola',
            self::Printing => 'Imprimiendo',
            self::Printed => 'Impreso',
            self::Failed => 'Falló',
            self::Cancelled => 'Cancelado',
            self::Expired => 'Expirado',
        };
    }

    /**
     * Statuses this one may move to.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Received => [self::Processing, self::Failed, self::Cancelled],
            self::Processing => [self::Quoted, self::Failed, self::Cancelled],
            self::Quoted => [self::Confirmed, self::Cancelled, self::Expired],
            self::Confirmed => [self::Ready, self::Queued, self::Cancelled],
            self::Ready => [self::Queued, self::Cancelled, self::Expired],
            self::Queued => [self::Printing, self::Cancelled],
            self::Printing => [self::Printed, self::Failed],
            self::Printed, self::Failed, self::Cancelled, self::Expired => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}
