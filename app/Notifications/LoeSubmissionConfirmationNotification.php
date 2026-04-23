<?php

namespace App\Notifications;

use App\Models\LoeReport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoeSubmissionConfirmationNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected LoeReport $report,
        protected bool $created,
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $label = $this->created ? 'submitted' : 'updated';

        return (new MailMessage)
            ->subject("LOE {$label} successfully")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your LOE for {$this->report->month}/{$this->report->year} was {$label} successfully.")
            ->line('Total assigned percentage: '.$this->report->total_percentage.'%')
            ->action('Open employee dashboard', url('/app/dashboard'))
            ->line('Thanks for keeping your effort allocation up to date.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->created ? 'LOE submitted' : 'LOE updated',
            'message' => "Your LOE for {$this->report->month}/{$this->report->year} has been saved.",
            'report_id' => $this->report->id,
        ];
    }
}
