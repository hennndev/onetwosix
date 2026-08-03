<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport"
        content="width=device-width, initial-scale=1.0">
  <title>Struk - {{ $billing?->transaction_code ?? 'N/A' }}</title>
  <style>
    @page {
      margin: 0;
      size: 80mm auto;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Courier New', Courier, monospace;
      font-size: 12px;
      font-weight: 400;
      color: #111;
      background: #fff;
      width: 80mm;
      margin: 0 auto;
      padding: 16px 12px 24px;
    }

    body.booking-receipt,
    body.booking-receipt * {
      font-weight: bold !important;
    }

    .center {
      text-align: center;
    }

    .right {
      text-align: right;
    }

    .bold {
      font-weight: bold;
    }

    .sep {
      border: none;
      border-top: 1px dashed #555;
      margin: 10px 0;
    }

    .two-col {
      display: flex;
      justify-content: space-between;
      margin: 2px 0;
    }

    .two-col .label {
      color: #333;
    }

    .two-col .value {
      font-weight: 600;
      text-align: right;
    }

    .items-list {
      margin: 4px 0;
    }

    .receipt-item {
      padding: 5px 0;
      border-bottom: 1px dashed #ddd;
    }

    .receipt-item:last-child {
      border-bottom: none;
    }

    .item-meta {
      margin-top: 2px;
      font-size: 11px;
      font-weight: 500;
      color: #222;
      line-height: 1.35;
    }

    .item-price-total {
      display: flex;
      justify-content: space-between;
      gap: 8px;
    }

    .item-name {
      font-weight: 600;
      font-size: 11px;
    }

    .item-total {
      font-weight: 700;
    }

    .item-cat {
      font-size: 10px;
      color: #555;
    }

    .total-row {
      display: flex;
      justify-content: space-between;
      margin: 2px 0;
      font-size: 11px;
    }

    .total-row span:last-child {
      font-weight: 600;
    }

    .grand-total {
      font-size: 14px;
      font-weight: 700;
      margin: 6px 0 2px;
    }

    .footer-text {
      font-size: 10px;
      color: #444;
      margin: 2px 0;
    }

    .print-btn {
      display: block;
      margin: 20px auto 0;
      padding: 8px 24px;
      background: #1e293b;
      color: #fff;
      border: none;
      border-radius: 6px;
      font-size: 13px;
      cursor: pointer;
    }

    @media print {
      .no-print {
        display: none !important;
      }

      body {
        padding: 8px 8px 16px;
      }
    }
  </style>
</head>

@php
  $receiptTypeLabel = strtoupper((string) ($receiptType ?? 'BOOKING'));
  $isBookingReceipt = $receiptTypeLabel === 'BOOKING';
  $tableLabel = $tableDisplay ?? ($booking?->table?->table_number ?? '-');
  $cashierLabel = $cashierName ?? (auth()->user()?->name ?? 'Admin');
  $printedAtLabel = $printedAt ?? ($billing?->updated_at?->format('d M Y H:i') ?? now()->format('d M Y H:i'));
@endphp

<body class="{{ $isBookingReceipt ? 'booking-receipt' : '' }}">
  <!-- Header -->
  <div class="center">
    <div style="font-size:18px;font-weight:bold;letter-spacing:1px;">126 CLUB</div>
    <div style="font-size:11px;margin-top:2px;">Premium Nightclub &amp; Lounge</div>
    <div style="font-size:10px;color:#444;margin-top:2px;">Jl. Premium No. 126, Jakarta</div>
    <div style="font-size:10px;color:#444;">Telp: (021) 1234-5678</div>
  </div>

  <hr class="sep"
      style="margin-top:12px;">

  <!-- Transaction Info -->
  <div class="two-col">
    <span class="label">No. Transaksi</span>
    <span class="value">{{ $billing?->transaction_code ?? '-' }}</span>
  </div>
  <div class="two-col">
    <span class="label">Tanggal</span>
    <span class="value">{{ $printedAtLabel }}</span>
  </div>
  <div class="two-col">
    <span class="label">Kasir</span>
    <span class="value">{{ $cashierLabel }}</span>
  </div>

  <hr class="sep">

  <!-- Customer Info -->
  <div class="two-col">
    <span class="label">Pelanggan</span>
    <span class="value">{{ $customerName }}</span>
  </div>
  <div class="two-col">
    <span class="label">Tipe</span>
    <span class="value">{{ $receiptTypeLabel }}</span>
  </div>
  <div class="two-col">
    <span class="label">Meja</span>
    <span class="value">{{ $tableLabel }}</span>
  </div>

  <hr class="sep">

  <!-- Items -->
  <div class="items-list">
    @foreach ($allItems as $item)
      <div class="receipt-item">
        <div class="item-name">{{ $item['name'] }} {{ $item['qty'] }}x</div>
        <div class="item-meta item-price-total">
          <span>Harga: Rp {{ number_format($item['price'], 0, ',', '.') }}</span>
          <span class="item-total">Total: Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</span>
        </div>
      </div>
    @endforeach
  </div>

  <hr class="sep">

  <!-- Totals -->
  @php
    $totalBillAmount = (float) ($billing?->subtotal ?? 0);
    $taxAmount = (float) ($billing?->tax ?? 0);
    $serviceChargeAmount = (float) ($billing?->service_charge ?? 0);
    $subTotalAmount = $totalBillAmount + $taxAmount + $serviceChargeAmount;
    $downPaymentAmount = (float) ($booking?->down_payment_amount ?? 0);
  @endphp

  <div class="total-row">
    <span>Total Bill</span>
    <span>Rp {{ number_format($totalBillAmount, 0, ',', '.') }}</span>
  </div>

  @if ($taxAmount > 0)
    <div class="total-row">
      <span>PB1 ({{ (int) ($billing?->tax_percentage ?? 0) }}%)</span>
      <span>Rp {{ number_format($taxAmount, 0, ',', '.') }}</span>
    </div>
  @endif

  @if ($serviceChargeAmount > 0)
    <div class="total-row">
      <span>Service Charge ({{ (int) ($billing?->service_charge_percentage ?? 0) }}%)</span>
      <span>Rp {{ number_format($serviceChargeAmount, 0, ',', '.') }}</span>
    </div>
  @endif

  <div class="total-row">
    <span>Sub Total</span>
    <span>Rp {{ number_format($subTotalAmount, 0, ',', '.') }}</span>
  </div>

  @if ($downPaymentAmount > 0)
    <div class="total-row">
      <span>DP</span>
      <span>- Rp {{ number_format($downPaymentAmount, 0, ',', '.') }}</span>
    </div>
  @endif

  @if ($billing?->is_parsial_payment || $billing?->is_debt || ($billing?->payment_mode ?? '') === 'partial' || ($billing?->remaining_balance ?? 0) > 0)
    <div class="two-col grand-total">
      <span>Total Tagihan</span>
      <span>Rp {{ number_format($billing?->grand_total ?? 0, 0, ',', '.') }}</span>
    </div>
    <div class="two-col font-bold" style="color: #15803d;">
      <span>Telah Dibayar</span>
      <span>Rp {{ number_format($billing?->paid_amount ?? 0, 0, ',', '.') }}</span>
    </div>
    @if (($billing?->remaining_balance ?? 0) > 0)
      <div class="two-col grand-total" style="color: #dc2626;">
        <span>Sisa Tagihan (Hutang)</span>
        <span>Rp {{ number_format($billing?->remaining_balance ?? 0, 0, ',', '.') }}</span>
      </div>
    @else
      <div class="two-col grand-total" style="color: #16a34a;">
        <span>Status</span>
        <span>LUNAS</span>
      </div>
    @endif
  @else
    <div class="two-col grand-total">
      <span>Sisa Bayar</span>
      <span>Rp {{ number_format($billing?->grand_total ?? 0, 0, ',', '.') }}</span>
    </div>
  @endif

  @if ($billing?->payments && $billing->payments->count() > 0)
    <hr class="sep">
    <div class="bold" style="margin-bottom: 4px; font-size: 11px; text-align: center;">RIWAYAT PEMBAYARAN</div>
    @foreach ($billing->payments as $index => $pm)
      <div class="two-col" style="font-size: 11px;">
        <span class="label">#{{ $index + 1 }} {{ strtoupper($pm->payment_type === 'initial_partial' ? 'DP / Parsial' : ($pm->payment_type === 'debt_settlement' ? 'Pelunasan' : 'Pembayaran')) }} ({{ strtoupper($pm->payment_method) }})</span>
        <span class="value">Rp {{ number_format($pm->amount_paid, 0, ',', '.') }}</span>
      </div>
      @if ($pm->paid_at || $pm->payment_reference_number)
        <div style="font-size: 9px; color: #555; text-align: right; margin-bottom: 3px;">
          {{ $pm->paid_at ? $pm->paid_at->format('d/m/Y H:i') : '' }} {{ $pm->payment_reference_number ? '| Ref: '.$pm->payment_reference_number : '' }}
        </div>
      @endif
    @endforeach
  @endif

  <hr class="sep">

  <div class="two-col">
    <span class="label">Mode Pembayaran</span>
    <span class="value">
      @if ($billing?->is_parsial_payment || $billing?->is_debt || ($billing?->payment_mode ?? '') === 'partial')
        BAYAR PARSIAL / HUTANG
      @elseif (($billing?->payment_mode ?? 'normal') === 'split')
        SPLIT BILL
      @else
        BIASA
      @endif
    </span>
  </div>

  <div class="two-col">
    <span class="label">Metode Pembayaran</span>
    <span class="value">{{ strtoupper($billing?->payment_method ?? (($billing?->payment_mode ?? 'normal') === 'split' ? 'split' : '-')) }}</span>
  </div>

  @if (($billing?->payment_mode ?? 'normal') !== 'split' && filled($billing?->payment_reference_number))
    <div class="two-col">
      <span class="label">No. Referensi</span>
      <span class="value">{{ $billing->payment_reference_number }}</span>
    </div>
  @endif

  @if (($billing?->payment_mode ?? 'normal') === 'split')
    @if (($billing?->split_cash_amount ?? 0) > 0)
      <div class="two-col">
        <span class="label">Cash</span>
        <span class="value">Rp {{ number_format($billing?->split_cash_amount ?? 0, 0, ',', '.') }}</span>
      </div>
    @endif

    @if (($billing?->split_debit_amount ?? 0) > 0)
      <div class="two-col">
        <span class="label">{{ strtoupper((string) ($billing?->split_non_cash_method ?? 'NON-CASH 1')) }}</span>
        <span class="value">Rp {{ number_format($billing?->split_debit_amount ?? 0, 0, ',', '.') }}</span>
      </div>
      @if (filled($billing?->split_non_cash_reference_number))
        <div class="two-col">
          <span class="label">Ref 1</span>
          <span class="value">{{ $billing->split_non_cash_reference_number }}</span>
        </div>
      @endif
    @endif

    @if (($billing?->split_second_non_cash_amount ?? 0) > 0)
      <div class="two-col">
        <span class="label">{{ strtoupper((string) ($billing?->split_second_non_cash_method ?? 'NON-CASH 2')) }}</span>
        <span class="value">Rp {{ number_format($billing?->split_second_non_cash_amount ?? 0, 0, ',', '.') }}</span>
      </div>
      @if (filled($billing?->split_second_non_cash_reference_number))
        <div class="two-col">
          <span class="label">Ref 2</span>
          <span class="value">{{ $billing->split_second_non_cash_reference_number }}</span>
        </div>
      @endif
    @endif
  @endif

  <hr class="sep">

  <!-- Footer -->
  <div class="center"
       style="margin-top:6px;">
    <div class="bold"
         style="font-size:12px;">Terima Kasih Atas Kunjungan<br>Anda!</div>
    <div class="footer-text"
         style="margin-top:6px;">Barang yang sudah dibeli tidak dapat<br>ditukar/dikembalikan</div>
    <div class="footer-text"
         style="margin-top:4px;">Simpan struk ini sebagai bukti<br>pembayaran yang sah</div>
    <div class="bold"
         style="margin-top:10px;font-size:11px;">FOLLOW US</div>
    <div class="footer-text">@126club | www.126club.com</div>
    <div style="font-size:10px;font-style:italic;color:#777;margin-top:8px;">Powered by 126 Club POS System</div>
  </div>

  <!-- Print button (hidden when printing) -->
  <button class="print-btn no-print"
          onclick="window.print()">&#128438; Cetak Struk</button>
  <script>
    // Auto-print when opened in a new tab
    window.addEventListener('load', function() {
      if (window.opener || document.referrer.includes('/bookings') || document.referrer.includes('/pos')) {
        setTimeout(function() {
          window.print();
        }, 300);
      }
    });
  </script>
</body>

</html>
