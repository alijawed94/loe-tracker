<?php

namespace App\Models;

use App\Enums\ProjectEngagementType;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectFactory> */
    use HasFactory, HasUlids, LogsModelActivity, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'engagement',
        'description',
        'engagement_type',
        'status',
    ];

    protected $appends = [
        'engagement_type_label',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    public function loeEntries(): HasMany
    {
        return $this->hasMany(LoeEntry::class);
    }

    public function getEngagementTypeLabelAttribute(): string
    {
        return ProjectEngagementType::from($this->engagement_type)->label();
    }
}
