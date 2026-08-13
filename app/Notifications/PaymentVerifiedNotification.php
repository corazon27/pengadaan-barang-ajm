<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentVerifiedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Payment $payment,
        public readonly Invoice $invoice,
    ) {}

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
        $isPaid = $this->invoice->status === InvoiceStatus::PAID;

        return [
            'title' => 'Pembayaran Terverifikasi',
            'message' => $isPaid
                ? 'Pembayaran untuk invoice '.$this->invoice->invoice_number.' telah diverifikasi. Invoice telah lunas.'
                : 'Pembayaran untuk invoice '.$this->invoice->invoice_number.' telah diverifikasi. Tagihan masih tersisa dan berstatus dibayar sebagian.',
            'action_url' => '/api/v1/invoices/'.$this->invoice->id,
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'invoice_status' => $this->invoice->status->value,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isPaid = $this->invoice->status === InvoiceStatus::PAID;

        return (new MailMessage)
            ->subject($isPaid ? 'Invoice Lunas: '.$this->invoice->invoice_number : 'Pembayaran Sebagian Terverifikasi: '.$this->invoice->invoice_number)
            ->greeting('Halo '.($notifiable->full_name ?? 'Buyer').',')
            ->line($isPaid
                ? 'Pembayaran Anda untuk invoice '.$this->invoice->invoice_number.' telah diverifikasi dan invoice dinyatakan lunas.'
                : 'Pembayaran Anda untuk invoice '.$this->invoice->invoice_number.' telah diverifikasi. Tagihan masih tersisa (dibayar sebagian).')
            ->line('Total tagihan: Rp '.number_format((float) $this->invoice->grand_total, 0, ',', '.'))
            ->action('Lihat Invoice', url('/api/v1/invoices/'.$this->invoice->id));
    }
}
