<?php

namespace App\Enums;

enum Channel: string
{
    case WhatsApp = 'whatsapp';
    case Web = 'web';
    case App = 'app';
    case Kiosk = 'kiosk';
}
