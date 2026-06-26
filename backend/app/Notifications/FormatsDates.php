<?php

namespace App\Notifications;

use Carbon\Carbon;

trait FormatsDates
{
    protected function formatShiftDate(?string $date): string
    {
        if (! $date) return '-';

        return Carbon::parse($date)
            ->locale('pt_PT')
            ->isoFormat('D [de] MMMM');
    }
}
