<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OperationsIncidentAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $incidentId,
        private readonly string $severity,
        private readonly string $status,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('OTT operations incident update')
            ->line("Incident #{$this->incidentId} is {$this->status} with {$this->severity} severity.")
            ->line('Sign in to the admin operations dashboard for the incident record.');
    }
}
