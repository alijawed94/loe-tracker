<?php

namespace App\Notifications;

use App\Models\LoeFeedback;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoeFeedbackNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected LoeFeedback $feedback,
    ) {
    }

    public function via(object $notifiable): array
    {
        // Email delivery is temporarily disabled until a mail service is configured.
        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $report = $this->feedback->loeReport;
        $author = $this->feedback->user;
        $isAdmin = $author?->roles->contains('name', 'admin');
        $portalUrl = $isAdmin ? url('/app/dashboard') : url("/admin/users/{$report->user_id}/loe-reports");

        return (new MailMessage)
            ->subject('New LOE feedback')
            ->greeting("Hi {$notifiable->name},")
            ->line(($isAdmin ? $author->name.' added feedback to your LOE.' : $author->name.' replied to an LOE feedback thread.'))
            ->line("LOE period: {$report->month}/{$report->year}")
            ->line('Message: '.$this->feedback->message)
            ->action('Open feedback thread', $portalUrl);
    }

    public function toArray(object $notifiable): array
    {
        $report = $this->feedback->loeReport;
        $author = $this->feedback->user;
        $isAdmin = $author?->roles->contains('name', 'admin');

        return [
            'title' => 'New LOE feedback',
            'message' => $isAdmin
                ? "{$author->name} left feedback on your LOE for {$report->month}/{$report->year}."
                : "{$author->name} replied to the LOE feedback thread for {$report->month}/{$report->year}.",
            'report_id' => $report->id,
            'feedback_id' => $this->feedback->id,
        ];
    }
}
