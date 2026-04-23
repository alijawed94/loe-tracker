<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\AdminMissingLoeDigestNotification;
use App\Notifications\LoeReminderNotification;
use App\Services\ReportService;
use Illuminate\Console\Command;

class SendLoeReminderNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loe:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send monthly LOE reminders and admin digests.';

    /**
     * Execute the console command.
     */
    public function handle(ReportService $reportService): int
    {
        $employees = User::query()
            ->where('status', true)
            ->whereHas('roles', fn ($query) => $query->where('name', 'employee'))
            ->get();

        foreach ($employees as $employee) {
            $now = now($employee->timezone);
            $month = $now->month;
            $year = $now->year;

            $hasSubmitted = $employee->loeReports()
                ->where('month', $month)
                ->where('year', $year)
                ->exists();

            if ($hasSubmitted) {
                continue;
            }

            if ($now->day === 1) {
                $employee->notify(new LoeReminderNotification(
                    'New LOE month opened',
                    "Your LOE window for {$month}/{$year} is open. Please submit your effort distribution for the month."
                ));
            }

            if ($now->day === $now->copy()->endOfMonth()->subDays(2)->day) {
                $employee->notify(new LoeReminderNotification(
                    'LOE deadline in 3 days',
                    "You have 3 days left to submit your LOE for {$month}/{$year}."
                ));
            }

            if ($now->isSameDay($now->copy()->endOfMonth())) {
                $employee->notify(new LoeReminderNotification(
                    'LOE deadline is today',
                    "Today is the final day to submit your LOE for {$month}/{$year}."
                ));
            }
        }

        $month = now()->month;
        $year = now()->year;
        $missingEmployees = $reportService->missingSubmissions($month, $year)->values()->all();

        if ($missingEmployees !== [] && in_array(now()->day, [1, now()->endOfMonth()->subDays(2)->day, now()->endOfMonth()->day], true)) {
            User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', 'admin'))
                ->get()
                ->each(fn (User $admin) => $admin->notify(new AdminMissingLoeDigestNotification($month, $year, $missingEmployees)));
        }

        $this->info('LOE reminders processed successfully.');

        return self::SUCCESS;
    }
}
