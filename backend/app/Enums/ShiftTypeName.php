<?php

namespace App\Enums;

enum ShiftTypeName: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case Night = 'night';
    case DayOff = 'dayOff';
    case Holidays = 'holidays';
    case SickLeave = 'sick leave';
    case ParentalLeave = 'parental leave';
}
