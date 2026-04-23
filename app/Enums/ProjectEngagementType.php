<?php

namespace App\Enums;

enum ProjectEngagementType: string
{
    case Project = 'project';
    case Product = 'product';
    case Marketing = 'marketing';
    case Admin = 'admin';
}
