<?php

namespace App\Enums;

/**
 * Values match the agent's --duplex option.
 */
enum Duplex: string
{
    case Simplex = 'simplex';
    case Long = 'long';
    case Short = 'short';
}
