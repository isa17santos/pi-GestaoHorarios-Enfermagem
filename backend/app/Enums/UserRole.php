<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Nurse = 'nurse';
    case HeadNurse = 'head_nurse';
}
