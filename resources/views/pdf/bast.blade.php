<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Berita Acara Serah Terima {{ $bast->bast_number }}</title>
    @include('pdf.partials.styles')
</head>
<body>
    @include('pdf.partials.kop-surat')

    @php
        app()->setLocale('id');
        \Illuminate\Support\Carbon::setLocale('id');
        $order = $bast->order;
        $money = fn (float|string $value): string => number_format((float) $value, 2, ',', '.');
        $buyerName = $order->user->company_name ?: $order->user->full_name;
    @endphp

    <div class="doc-title">BERITA ACARA SERAH TERIMA</div>
    <div class="doc-subtitle">Nomor: {{ $bast->bast_number }}</div>

    <p class="intro">
        Pada hari ini, {{ $bast->created_at?->translatedFormat('l, j F Y') }},
        yang bertanda tangan di bawah ini:
    </p>

    <table class="info">
        <tr>
            <td class="info-label">Pihak Pertama</td>
            <td>{{ config('company.legal_entity') }} (Penjual)</td>
        </tr>
        <tr>
            <td class="info-label">Alamat</td>
            <td>{{ config('company.address') }}</td>
        </tr>
        <tr>
            <td class="info-label">Pihak Kedua</td>
            <td>{{ $buyerName }} (Pembeli)</td>
        </tr>
        <tr>
            <td class="info-label">Alamat</td>
            <td>{{ $order->user->address ?? '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">Nomor Pesanan</td>
            <td>{{ $order->order_number }}</td>
        </tr>
    </table>

    <p class="intro">
        Dengan ini menyatakan bahwa Pihak Pertama telah menyerahkan barang-barang
        sebagaimana rincian berikut kepada Pihak Kedua:
    </p>

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
                    <td>{{ $item->product?->title }}</td>
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
        <tfoot>
            <tr class="grand-total">
                <td colspan="4" class="right">Total</td>
                <td class="col-total">{{ $money($order->total_amount) }}</td>
            </tr>
        </tfoot>
    </table>

    <p class="closing">
        Barang tersebut telah diperiksa dan diterima dengan keadaan baik oleh Pihak Kedua.
        Berita Acara ini dibuat dalam rangkap dua, masing-masing pihak menerima satu
        rangkap sebagai bukti serah terima yang sah.
    </p>

    @if ($bast->notes)
        <p class="note"><strong>Catatan:</strong> {{ $bast->notes }}</p>
    @endif

    <table class="signature">
        <tr>
            <td>
                <div>Pihak Pertama (Penjual)</div>
                <div class="sign-space"></div>
                <div class="sign-space"></div>
                <div>( {{ config('company.legal_entity') }} )</div>
            </td>
            <td>
                <div>Pihak Kedua (Pembeli)</div>
                <div class="sign-space"></div>
                <div class="sign-space"></div>
                <div>( ................. )</div>
            </td>
        </tr>
    </table>
</body>
</html>
