<?php

namespace App\Enums;

enum ShiftSwapRequestShiftType: string
{
    case Offered = 'offered';
    case Requested = 'requested';
}