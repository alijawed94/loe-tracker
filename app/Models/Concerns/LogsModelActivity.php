<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait LogsModelActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        $sensitiveAttributes = array_values(array_unique(array_merge(
            ['password', 'remember_token'],
            property_exists($this, 'hidden') ? $this->hidden : [],
            method_exists($this, 'activityLogSensitiveAttributes') ? $this->activityLogSensitiveAttributes() : [],
        )));

        return LogOptions::defaults()
            ->useLogName($this->getTable())
            ->logFillable()
            ->logExcept($sensitiveAttributes)
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly($sensitiveAttributes)
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => class_basename($this)." {$eventName}");
    }
}
