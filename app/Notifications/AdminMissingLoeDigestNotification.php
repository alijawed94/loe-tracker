<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminMissingLoeDigestNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected int $month,
        protected int $year,
        protected array $missingEmployees,
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
        $mail = (new MailMessage)
            ->subject('Missing LOE submissions summary')
            ->greeting("Hi {$notifiable->name},")
            ->line("The following employees have not submitted their LOE for {$this->month}/{$this->year}:");

        foreach ($this->missingEmployees as $employee) {
            $mail->line("- {$employee['employee']} ({$employee['employee_code']})");
        }

        return $mail
            ->action('Open admin dashboard', url('/admin/dashboard'))
            ->line('Please follow up before the monthly deadline.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Missing LOE submissions',
            'message' => count($this->missingEmployees)." employees are still missing LOE for {$this->month}/{$this->year}.",
            'employees' => $this->missingEmployees,
        ];
    }
}
