<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\UserStatus;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifies an applicant of an administrative lifecycle decision.
 *
 * Synchronous and best-effort: it is dispatched only AFTER the decision commits,
 * and a delivery failure must never roll back that decision.
 */
class AccountStatusChangedNotification extends Notification
{
    public function __construct(
        public readonly UserStatus $status,
        public readonly ?string $role = null,
        public readonly ?string $reason = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Status Akun Anda: '.$this->status->value)
            ->greeting('Halo '.($notifiable->full_name ?? 'Pengguna').',');

        $message = match ($this->status) {
            UserStatus::ACTIVE => $message
                ->line('Akun Anda telah disetujui dan aktif.')
                ->line($this->role !== null ? 'Peran akun Anda: '.$this->role : '')
                ->action('Masuk', route('login')),
            UserStatus::REJECTED => $message
                ->line('Pengajuan akun Anda ditolak.')
                ->line($this->reason !== null ? 'Alasan: '.$this->reason : '')
                ->line('Anda dapat memperbaiki dan mengirim ulang pengajuan melalui tautan yang kami kirimkan.'),
            UserStatus::SUSPENDED => $message
                ->line('Akun Anda ditangguhkan.')
                ->line($this->reason !== null ? 'Alasan: '.$this->reason : '')
                ->line('Hubungi administrator untuk informasi lebih lanjut.'),
            UserStatus::PENDING => $message->line('Pengajuan akun Anda sedang ditinjau.'),
        };

        return $message
            ->line('Email ini bersifat pemberitahuan; keputusan resmi tercatat pada sistem AJM.');
    }
}
