<?php

namespace App\Enums;

enum ProjectEngagementType: string
{
    case Project = 'project';
    case Product = 'product';
    case Marketing = 'marketing';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Project => 'Project',
            self::Product => 'Product',
            self::Marketing => 'Marketing and Sales',
            self::Admin => 'HR and Admin',
        };
    }
}
