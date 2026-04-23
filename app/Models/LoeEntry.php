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
        'project_id',
        'percentage',
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
}
