<?php

namespace App\Notifications;

use App\Models\LoeReport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoeReviewStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected LoeReport $report,
    ) {
    }

    public function via(object $notifiable): array
    {
        // Email delivery is temporarily disabled until a mail service is configured.
        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('LOE review status updated')
            ->greeting("Hi {$notifiable->name},")
            ->line("Your LOE for {$this->report->month}/{$this->report->year} is now {$this->report->status}.")
            ->line($this->report->review_notes ? 'Review note: '.$this->report->review_notes : 'No additional review note was added.')
            ->action('Open employee dashboard', url('/app/dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'LOE review updated',
            'message' => "Your LOE for {$this->report->month}/{$this->report->year} is now {$this->report->status}.",
            'report_id' => $this->report->id,
            'status' => $this->report->status,
            'review_notes' => $this->report->review_notes,
        ];
    }
}
