<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    @include('pdf.partials.styles')
</head>
<body>
    @include('pdf.partials.kop-surat')

    @php
        app()->setLocale('id');
        \Illuminate\Support\Carbon::setLocale('id');
        $order = $invoice->order;
        $money = fn (float|string $value): string => number_format((float) $value, 2, ',', '.');
        $buyerName = $order->user->company_name ?: $order->user->full_name;
    @endphp

    <div class="doc-title">INVOICE / FAKTUR PENJUALAN</div>
    <div class="doc-subtitle">Nomor: {{ $invoice->invoice_number }}</div>

    <table class="info">
        <tr>
            <td class="info-label">Kepada Yth.</td>
            <td>{{ $buyerName }}</td>
        </tr>
        <tr>
            <td class="info-label">NPWP</td>
            <td>{{ $order->user->npwp_number ?? '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">Alamat</td>
            <td>{{ $order->user->address ?? '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">Tanggal Penerbitan</td>
            <td>{{ $invoice->issued_date?->translatedFormat('j F Y') }}</td>
        </tr>
        <tr>
            <td class="info-label">Jatuh Tempo</td>
            <td>{{ $invoice->due_date?->translatedFormat('j F Y') }} ({{ $invoice->payment_term->statusLabel() }})</td>
        </tr>
        <tr>
            <td class="info-label">No. Referensi Pesanan</td>
            <td>{{ $order->order_number }}</td>
        </tr>
        <tr>
            <td class="info-label">Kode Faktur</td>
            <td>{{ $invoice->relationLoaded('ruleSnapshots') ? ($invoice->ruleSnapshots->pluck('faktur_code')->first(fn ($code) => $code !== null) ?? '-') : '-' }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th>Nama Barang</th>
                <th class="col-qty">Qty</th>
                <th class="col-price">Harga Satuan (Rp)</th>
                <th class="col-total">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($order->items as $index => $item)
                <tr>
                    <td class="col-no">{{ $index + 1 }}</td>
                    <td>{{ $item->product_title_snapshot ?: $item->product?->title }}</td>
                    <td class="col-qty">{{ $item->quantity }}</td>
                    <td class="col-price">{{ $money($item->unit_price) }}</td>
                    <td class="col-total">{{ $money($item->subtotal) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">Tidak ada item.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td class="amount">{{ $money($invoice->subtotal) }}</td>
        </tr>
        @if ($invoice->status?->value !== \App\Enums\InvoiceStatus::REVIEW_REQUIRED->value)
            <tr>
                <td>Dasar Pengenaan Pajak (DPP)</td>
                <td class="amount">{{ $money((string) collect($invoice->relationLoaded('ruleSnapshots') ? $invoice->ruleSnapshots : [])->reduce(fn ($carry, $snap) => bcadd($carry, (string) $snap->dpp_amount, 2), '0.00')) }}</td>
            </tr>
        @endif
        <tr>
            <td>PPN</td>
            <td class="amount">{{ $money($invoice->ppn_amount) }}</td>
        </tr>
        <tr>
            <td>PPh (pemotongan pajak, ditanggung pembeli)</td>
            <td class="amount">{{ $money($invoice->pph_amount) }}</td>
        </tr>
        <tr class="grand">
            <td>Jumlah yang Harus Dibayar</td>
            <td class="amount">{{ $money($invoice->grand_total) }}</td>
        </tr>
    </table>

    @if ($invoice->status?->value === \App\Enums\InvoiceStatus::REVIEW_REQUIRED->value)
        <p class="note" style="color: #b45309;">
            <strong>Perhatian:</strong> Perhitungan pajak atas invoice ini masih memerlukan peninjauan.
            Nilai PPN dan jumlah yang harus dibayar akan diterbitkan setelah perhitungan diselesaikan.
        </p>
    @endif

    <p class="note">
        <strong>No. e-Faktur:</strong> {{ $invoice->faktur_pajak_number ?? 'Menunggu penerbitan' }}
        @if ($invoice->tax_calculation_version)
            &nbsp;|&nbsp; <strong>Versi Perhitungan Pajak:</strong> {{ $invoice->tax_calculation_version }}
        @endif
    </p>

    <p class="closing">
        Mohon pembayaran dilakukan selambat-lambatnya pada tanggal jatuh tempo melalui transfer
        ke rekening berikut:
    </p>

    <table class="info">
        <tr>
            <td class="info-label">Bank</td>
            <td>{{ config('company.bank.name') }} &mdash; {{ config('company.bank.branch') }}</td>
        </tr>
        <tr>
            <td class="info-label">Atas Nama</td>
            <td>{{ config('company.bank.account_name') }}</td>
        </tr>
        <tr>
            <td class="info-label">No. Rekening</td>
            <td>{{ config('company.bank.account_number') }}</td>
        </tr>
    </table>

    <p class="note muted">
        Pembayaran yang terlambat akan dikenakan status tagihan &ldquo;Overdue&rdquo; sesuai
        ketentuan yang berlaku. Terima kasih atas kepercayaan Bapak/Ibu.
    </p>

    <table class="signature">
        <tr>
            <td>
                <div class="sign-date">{{ $invoice->issued_date?->translatedFormat('j F Y') }}</div>
                <div class="sign-space"></div>
                <div>Hormat kami,</div>
                <div class="sign-space"></div>
                <div><strong>{{ config('company.legal_entity') }}</strong></div>
            </td>
            <td></td>
        </tr>
    </table>

    <div class="footer-note">
        {{ config('company.website') }} &nbsp;|&nbsp; Dokumen ini diterbitkan secara elektronik.
    </div>
</body>
</html>
