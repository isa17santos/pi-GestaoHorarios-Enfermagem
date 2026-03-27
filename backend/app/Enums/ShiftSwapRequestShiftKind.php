<?php

namespace App\Enums;

enum ShiftSwapRequestShiftKind: string
{
    case Offered = 'offered';
    case Requested = 'requested';
}
