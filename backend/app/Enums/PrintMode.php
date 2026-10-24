<?php

namespace App\Enums;

/**
 * What happens to a job once the customer confirms it.
 */
enum PrintMode: string
{
    /** Stays ready until the operator clicks "Imprimir" at the counter. */
    case Manual = 'manual';

    /** Queued automatically only when already paid; otherwise stays ready. */
    case AutoIfPaid = 'auto_if_paid';

    /** Queued as soon as the customer confirms. */
    case Auto = 'auto';
}
