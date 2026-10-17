<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Accountant = 'accountant';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Accountant => 'Accountant',
            self::Member => 'Member',
        };
    }
}
