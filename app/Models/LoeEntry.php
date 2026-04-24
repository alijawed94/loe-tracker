<?php

namespace App\Models;

use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoeEntry extends Model
{
    /** @use HasFactory<\Database\Factories\LoeEntryFactory> */
    use HasFactory, HasUlids, LogsModelActivity;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'loe_report_id',
        'entry_type',
        'project_id',
        'time_off_type',
        'percentage',
    ];

    public const ENTRY_TYPE_PROJECT = 'project';

    public const ENTRY_TYPE_TIME_OFF = 'time_off';

    public const TIME_OFF_TYPES = [
        'vacation',
        'sick_leave',
        'public_holiday',
        'personal_leave',
        'other',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
        ];
    }

    public function loeReport(): BelongsTo
    {
        return $this->belongsTo(LoeReport::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function entryTypeLabel(): string
    {
        return $this->entry_type === self::ENTRY_TYPE_TIME_OFF ? 'Time Off' : 'Project';
    }

    public function timeOffTypeLabel(): ?string
    {
        if (! $this->time_off_type) {
            return null;
        }

        return str($this->time_off_type)->replace('_', ' ')->title()->value();
    }

    public function displayName(): string
    {
        return $this->entry_type === self::ENTRY_TYPE_TIME_OFF
            ? $this->timeOffTypeLabel() ?? 'Time Off'
            : ($this->project?->name ?? 'Project');
    }
}
