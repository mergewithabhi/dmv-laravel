<?php

namespace App\Enums;

enum GameStatus: string
{
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Final = 'final';
    case Postponed = 'postponed';
    case Cancelled = 'cancelled';
}
