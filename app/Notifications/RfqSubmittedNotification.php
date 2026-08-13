<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Rfq;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RfqSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Rfq $rfq) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'RFQ Baru Diajukan',
            'message' => 'RFQ '.$this->rfq->rfq_number.' telah diajukan oleh '.($this->rfq->user->full_name ?? 'buyer').' dan menunggu penawaran harga.',
            'action_url' => '/api/v1/rfqs/'.$this->rfq->id,
            'rfq_id' => $this->rfq->id,
            'rfq_number' => $this->rfq->rfq_number,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('RFQ Baru Diajukan: '.$this->rfq->rfq_number)
            ->greeting('Halo '.($notifiable->full_name ?? 'Admin').',')
            ->line('Sebuah RFQ baru telah diajukan dan menunggu penawaran harga Anda.')
            ->line('Nomor RFQ: '.$this->rfq->rfq_number)
            ->action('Lihat RFQ', url('/api/v1/rfqs/'.$this->rfq->id))
            ->line('Terima kasih telah menggunakan platform kami.');
    }
}
