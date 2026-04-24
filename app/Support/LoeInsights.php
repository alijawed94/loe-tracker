<?php

namespace App\Support;

use App\Models\LoeReport;
use App\Models\User;

class LoeInsights
{
    public static function reportWarnings(LoeReport $report, ?User $user = null): array
    {
        $warnings = [];
        $total = (float) $report->total_percentage;

        if ($total < 50 || $total > 110) {
            $warnings[] = [
                'level' => 'critical',
                'message' => "Total LOE is {$total}%, which is outside the safe 50%-110% range.",
            ];
        } elseif ($total < 90) {
            $warnings[] = [
                'level' => 'medium',
                'message' => "Total LOE is {$total}%, which is below the preferred 90%-110% range.",
            ];
        }

        if ($user) {
            $allocationTotal = round((float) $user->allocations->sum('percentage'), 2);
            $variance = round(abs($allocationTotal - $total), 2);

            if ($allocationTotal > 0 && $variance >= 20) {
                $warnings[] = [
                    'level' => 'medium',
                    'message' => "LOE total differs from current allocations by {$variance}%.",
                ];
            }
        }

        return $warnings;
    }
}
