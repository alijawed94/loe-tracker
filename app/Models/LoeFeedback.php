<?php

namespace App\Models;

use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoeFeedback extends Model
{
    use HasFactory, HasUlids, LogsModelActivity;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'loe_feedback';

    protected $fillable = [
        'loe_report_id',
        'user_id',
        'message',
    ];

    public function loeReport(): BelongsTo
    {
        return $this->belongsTo(LoeReport::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
