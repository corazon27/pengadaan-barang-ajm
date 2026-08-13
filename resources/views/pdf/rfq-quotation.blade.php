<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Penawaran Harga {{ $rfq->rfq_number }}</title>
    @include('pdf.partials.styles')
</head>
<body>
    @include('pdf.partials.kop-surat')

    @php
        app()->setLocale('id');
        \Illuminate\Support\Carbon::setLocale('id');
        $money = fn (float|string $value): string => number_format((float) $value, 2, ',', '.');
        $totalOffered = $rfq->items->sum(fn ($item) => (float) $item->negotiated_price * $item->quantity);
        $buyerName = $rfq->user->company_name ?: $rfq->user->full_name;
    @endphp

    <div class="doc-title">SURAT PENAWARAN HARGA</div>
    <div class="doc-subtitle">Nomor: {{ $rfq->rfq_number }}</div>

    <table class="info">
        <tr>
            <td class="info-label">Kepada Yth.</td>
            <td>{{ $buyerName }}</td>
        </tr>
        <tr>
            <td class="info-label">NPWP</td>
            <td>{{ $rfq->user->npwp_number ?? '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">Alamat</td>
            <td>{{ $rfq->user->address ?? '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">Tanggal Penawaran</td>
            <td>{{ $rfq->created_at?->translatedFormat('j F Y') }}</td>
        </tr>
        <tr>
            <td class="info-label">Masa Berlaku</td>
            <td>{{ $rfq->valid_until?->translatedFormat('j F Y') ?? 'Sesuai kesepakatan' }}</td>
        </tr>
    </table>

    <p class="intro">
        Dengan hormat, sehubungan dengan permintaan penawaran yang Bapak/Ibu ajukan,
        bersama ini kami sampaikan penawaran harga sebagai berikut:
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
            @forelse ($rfq->items as $index => $item)
                <tr>
                    <td class="col-no">{{ $index + 1 }}</td>
                    <td>{{ $item->product?->title }}</td>
                    <td class="col-qty">{{ $item->quantity }}</td>
                    <td class="col-price">{{ $money($item->negotiated_price) }}</td>
                    <td class="col-total">{{ $money((float) $item->negotiated_price * $item->quantity) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">Tidak ada item.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="grand-total">
                <td colspan="4" class="right">Total Harga Penawaran</td>
                <td class="col-total">{{ $money($totalOffered) }}</td>
            </tr>
        </tfoot>
    </table>

    @if ($rfq->notes)
        <p class="note"><strong>Catatan:</strong> {{ $rfq->notes }}</p>
    @endif

    <p class="closing">
        Harga di atas belum termasuk PPN dan PPh sesuai ketentuan perpajakan yang berlaku.
        Penawaran ini berlaku selama masa berlakunya. Demikian penawaran ini kami sampaikan,
        atas perhatian dan kepercayaan Bapak/Ibu, kami ucapkan terima kasih.
    </p>

    <table class="signature">
        <tr>
            <td>
                <div class="sign-date">{{ now()->translatedFormat('j F Y') }}</div>
                <div class="sign-space"></div>
                <div>Hormat kami,</div>
                <div class="sign-space"></div>
                <div><strong>{{ config('company.legal_entity') }}</strong></div>
            </td>
            <td></td>
        </tr>
    </table>
</body>
</html>
