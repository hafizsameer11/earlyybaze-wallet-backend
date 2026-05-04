<?php

namespace App\Models\Concerns;

use DateTimeInterface;
use Illuminate\Support\Carbon;

trait SerializesDatesInAppTimezone
{
    /**
     * Serialize dates for JSON/API/export in the app timezone (Nigeria: Africa/Lagos).
     * Avoids UTC-only ISO strings that shift the calendar day vs what users see in-app.
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return Carbon::instance($date)
            ->timezone(config('app.timezone', 'Africa/Lagos'))
            ->toIso8601String();
    }
}
