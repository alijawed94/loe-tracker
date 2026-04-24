<?php

namespace App\Enums;

enum UserStream: string
{
    case Engineering = 'engineering';
    case Experience = 'experience';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Engineering => 'Engineering',
            self::Experience => 'Experience',
            self::Admin => 'HR and Admin',
        };
    }
}
