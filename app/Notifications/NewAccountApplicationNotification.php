<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Best-effort administrator alert for a new pending application.
 *
 * Deliberately synchronous (no ShouldQueue and no database channel): the
 * protected pending-application list is the authoritative source of work, and
 * the intended shared-hosting target cannot be assumed to run a queue worker.
 */
class NewAccountApplicationNotification extends Notification
{
    public function __construct(public readonly User $applicant) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pengajuan Akun Baru Menunggu Persetujuan')
            ->greeting('Halo '.($notifiable->full_name ?? 'Administrator').',')
            ->line('Akun berikut telah memverifikasi email dan menunggu peninjauan administrator:')
            ->line('Nama: '.$this->applicant->full_name)
            ->line('Email: '.$this->applicant->email)
            ->line('Peran yang diajukan: '.($this->applicant->requested_role?->value ?? 'BUYER_B2B'))
            ->action('Tinjau Pengajuan', route('admin.accounts.index'))
            ->line('Daftar pengajuan di dasbor administrator tetap menjadi sumber kebenaran; email ini bersifat pemberitahuan.');
    }
}
