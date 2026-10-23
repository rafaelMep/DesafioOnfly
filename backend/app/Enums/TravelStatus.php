<?php

namespace App\Enums;

enum TravelStatus: string
{
    case Requested = 'solicitado';
    case Approved = 'aprovado';
    case Cancelled = 'cancelado';

    public function canTransitionTo(self $next): bool
    {
        return $this === self::Requested && in_array($next, [self::Approved, self::Cancelled], true);
    }
}
