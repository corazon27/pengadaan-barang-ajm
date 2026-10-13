<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Signed, expiring email-verification mail. The link opens a confirmation page
 * (GET) that performs no state change; the applicant must explicitly confirm
 * (POST) before `email_verified_at` is set. No session or token is created.
 */
class VerifyEmailNotification extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute('verification.show', now()->addHours(24), [
            'user' => $notifiable->getKey(),
            'hash' => sha1((string) $notifiable->email),
        ]);

        return (new MailMessage)
            ->subject('Verifikasi Email Akun AJM')
            ->greeting('Halo '.($notifiable->full_name ?? 'Pengguna').',')
            ->line('Terima kasih telah mendaftar. Konfirmasikan alamat email Anda untuk melanjutkan.')
            ->action('Verifikasi Email', $url)
            ->line('Tautan ini berlaku selama 24 jam. Membuka tautan saja tidak mengaktifkan akun; Anda perlu mengonfirmasi pada halaman yang terbuka.')
            ->line('Akun Anda tetap menunggu persetujuan administrator setelah email diverifikasi.');
    }
}
