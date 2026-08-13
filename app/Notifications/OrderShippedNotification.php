<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderShippedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

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
            'title' => 'Pesanan Telah Dikirim',
            'message' => 'Pesanan '.$this->order->order_number.' telah dikirim dan sedang dalam perjalanan. Silakan siapkan diri untuk menandatangani BAST setelah barang diterima.',
            'action_url' => '/api/v1/orders/'.$this->order->id,
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pesanan Dikirim: '.$this->order->order_number)
            ->greeting('Halo '.($notifiable->full_name ?? 'Buyer').',')
            ->line('Pesanan '.$this->order->order_number.' telah dikirim dan sedang dalam perjalanan ke alamat Anda.')
            ->action('Lacak Pesanan', url('/api/v1/orders/'.$this->order->id))
            ->line('Pastikan Anda siap menerima barang dan menandatangani BAST setelah pengiriman diterima.');
    }
}
