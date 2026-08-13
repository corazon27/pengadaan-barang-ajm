<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Rfq;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RfqRespondedNotification extends Notification implements ShouldQueue
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
            'title' => 'Penawaran Harga Tersedia',
            'message' => 'Penawaran harga untuk RFQ '.$this->rfq->rfq_number.' sudah tersedia. Silakan tinjau dan berikan keputusan Anda.',
            'action_url' => '/api/v1/rfqs/'.$this->rfq->id,
            'rfq_id' => $this->rfq->id,
            'rfq_number' => $this->rfq->rfq_number,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Penawaran Harga Tersedia: '.$this->rfq->rfq_number)
            ->greeting('Halo '.($notifiable->full_name ?? 'Buyer').',')
            ->line('Penawaran harga untuk RFQ '.$this->rfq->rfq_number.' sudah tersedia.')
            ->action('Tinjau Penawaran', url('/api/v1/rfqs/'.$this->rfq->id))
            ->line('Silakan tinjau penawaran sebelum batas waktu berakhir.');
    }
}
