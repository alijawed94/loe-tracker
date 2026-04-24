<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RoleAwareResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $token,
        protected string $role,
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Email delivery is temporarily disabled until a mail service is configured.
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = url(($this->role === 'admin' ? '/admin/reset-password/' : '/reset-password/').$this->token.'?email='.urlencode($notifiable->getEmailForPasswordReset()));

        return (new MailMessage)
            ->subject('Reset your LOE Tracker password')
            ->greeting("Hi {$notifiable->name},")
            ->line('We received a password reset request for your LOE Tracker account.')
            ->action('Reset password', $resetUrl)
            ->line('This password reset link will expire in '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Password reset requested',
            'message' => 'A password reset request was created for your account.',
            'role' => $this->role,
        ];
    }
}
