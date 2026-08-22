<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport"
        content="width=device-width, initial-scale=1.0">
  <title>Preview Print End Day</title>
  @vite(['resources/css/app.css'])
  <style>
    @page {
      size: 80mm auto;
      margin: 0;
    }

    .preview-shell {
      width: 80mm;
      max-width: 100%;
      margin: 0 auto;
    }

    .print-sheet {
      width: 80mm;
      min-height: auto;
      max-width: 100%;
      margin: 0 auto;
      background: #fff;
      padding: 4mm;
      box-sizing: border-box;
      box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
      font-size: 11px;
      line-height: 1.35;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    }

    .label {
      color: #475569;
      font-size: 10px;
    }

    .value {
      color: #0f172a;
      font-size: 11px;
      font-weight: 600;
    }

    @media print {
      .no-print {
        display: none !important;
      }

      body {
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
      }

      .preview-shell {
        width: 80mm !important;
        max-width: none !important;
      }

      .print-sheet {
        width: 80mm !important;
        min-height: auto !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 4mm !important;
        box-shadow: none !important;
      }
    }
  </style>
</head>

<body class="bg-gray-100 text-gray-900">
  <div class="no-print sticky top-0 z-20 border-b border-gray-200 bg-white/95 backdrop-blur">
    <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
      <div>
        <p class="text-sm font-semibold text-gray-900">Preview Print Struk - End Day</p>
        <p class="text-xs text-gray-500">Cek tampilan lalu klik Cetak / Save as PDF sebelum tutup end day.</p>
      </div>

      <div class="flex items-center gap-2">
        <a href="{{ route('admin.recap.index') }}"
           class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
          Kembali
        </a>

        <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
          <input type="checkbox"
                 id="includeTransactionHistory"
                 class="h-4 w-4 rounded border-gray-300 text-slate-800 focus:ring-slate-500"
                 checked>
          <span>Riwayat transaksi</span>
        </label>

        <button type="button"
                onclick="triggerEndDayPrint()"
                class="inline-flex items-center justify-center rounded-lg bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-900">
          Cetak Otomatis
        </button>

        <button type="button"
                onclick="window.print()"
                class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
          Save PDF
        </button>

        @unless ($isReprintPreview ?? false)
          <form method="POST"
                action="{{ route('admin.recap.close-export') }}">
            @csrf
            @if ($selectedAreaId ?? null)
              <input type="hidden" name="area_id" value="{{ $selectedAreaId }}">
            @endif
            <button type="submit"
                    class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
              Tutup End Day {{ $selectedAreaId ? '(' . ($areas->firstWhere('id', $selectedAreaId)?->name ?? 'Area') . ')' : '' }}
            </button>
          </form>
        @endunless
      </div>
    </div>
    <div class="mx-auto max-w-5xl px-4 pb-3">
      <div id="printStatus"
           class="hidden rounded-lg border px-3 py-2 text-sm"></div>
    </div>
  </div>

  <main class="preview-shell py-6 print:py-0">
    <div class="print-sheet">
      <header class="border-b border-dashed border-gray-300 pb-2 text-center">
        <h1 class="text-sm font-bold tracking-tight text-gray-900">REKAP END DAY</h1>
        <p class="label mt-1">Preview Cetak Struk</p>
        <p class="label mt-1">{{ $selectedStartDatetime }} - {{ $selectedEndDatetime }}</p>
        <p class="label">{{ $printedAt->format('d/m/Y H:i:s') }}</p>
      </header>

      <section class="mt-3 space-y-1.5 border-b border-dashed border-gray-300 pb-2">
        <div class="flex items-center justify-between gap-2">
          <span class="label">Transaksi Kasir</span>
          <span class="value">{{ number_format($cashierCount, 0, ',', '.') }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
          <span class="label">Gross Sales (Included DP)</span>
          <span class="value">Rp {{ number_format($grossSales ?? 0, 0, ',', '.') }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
          <span class="label">Net Sales (Included DP)</span>
          <span class="value">Rp {{ number_format($netSales ?? 0, 0, ',', '.') }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
          <span class="label">Total Pajak</span>
          <span class="value">Rp {{ number_format($totalTax, 0, ',', '.') }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
          <span class="label">Total Service</span>
          <span class="value">Rp {{ number_format($totalServiceCharge, 0, ',', '.') }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
          <span class="label">Item Keluar Kitchen</span>
          <span class="value">{{ number_format((int) ($dashboardPreview['total_kitchen_items'] ?? ($kitchenQtyTotal ?? 0)), 0, ',', '.') }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
          <span class="label">Item Keluar Bar</span>
          <span class="value">{{ number_format((int) ($dashboardPreview['total_bar_items'] ?? ($barQtyTotal ?? 0)), 0, ',', '.') }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
          <span class="label">Total Food</span>
          <span class="value">Rp {{ number_format((float) ($dashboardPreview['total_food'] ?? 0), 0, ',', '.') }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
          <span class="label">Total Alcohol</span>
          <span class="value">Rp {{ number_format((float) ($dashboardPreview['total_alcohol'] ?? 0), 0, ',', '.') }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
          <span class="label">Total Beverage</span>
          <span class="value">Rp {{ number_format((float) ($dashboardPreview['total_beverage'] ?? 0), 0, ',', '.') }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
          <span class="label">Total Cigarette</span>
          <span class="value">Rp {{ number_format((float) ($dashboardPreview['total_cigarette'] ?? 0), 0, ',', '.') }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
          <span class="label">Total Breakage</span>
          <span class="value">Rp {{ number_format((float) ($dashboardPreview['total_breakage'] ?? 0), 0, ',', '.') }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
          <span class="label">Total Room</span>
          <span class="value">Rp {{ number_format((float) ($dashboardPreview['total_room'] ?? 0), 0, ',', '.') }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
          <span class="label">Total Staff Meal</span>
          <span class="value">Rp {{ number_format((float) ($dashboardPreview['total_staff_meal'] ?? 0), 0, ',', '.') }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
          <span class="label">Total Compliment</span>
          <span class="value">Rp {{ number_format((float) ($dashboardPreview['total_compliment_amount'] ?? 0), 0, ',', '.') }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
          <span class="label">Total Compliment Qty</span>
          <span class="value">Qty {{ number_format((int) ($dashboardPreview['total_compliment_quantity'] ?? 0), 0, ',', '.') }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
          <span class="label">Total FOC</span>
          <span class="value">Rp {{ number_format((float) ($dashboardPreview['total_foc_amount'] ?? 0), 0, ',', '.') }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
          <span class="label">Total FOC Qty</span>
          <span class="value">Qty {{ number_format((int) ($dashboardPreview['total_foc_quantity'] ?? 0), 0, ',', '.') }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
          <span class="label">Total LD</span>
          <span class="value">Rp {{ number_format((float) ($dashboardPreview['total_ld'] ?? 0), 0, ',', '.') }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
          <span class="label">Total LD Qty</span>
          <span class="value">Qty {{ number_format((int) ($dashboardPreview['total_ld_quantity'] ?? 0), 0, ',', '.') }}</span>
        </div>
      </section>

      <section class="mt-3 border-b border-dashed border-gray-300 pb-2">
        <h2 class="text-[11px] font-semibold text-gray-900">RINGKASAN PEMBAYARAN</h2>
        <div class="mt-1.5 space-y-1">
          <div class="flex items-center justify-between gap-2"><span class="label">Tunai</span><span class="value">Rp {{ number_format($paymentMethodTotals['cash'] ?? 0, 0, ',', '.') }}</span></div>
          <div class="flex items-center justify-between gap-2"><span class="label">Transfer</span><span class="value">Rp {{ number_format($paymentMethodTotals['transfer'] ?? 0, 0, ',', '.') }}</span></div>
          <div class="flex items-center justify-between gap-2"><span class="label">Debit</span><span class="value">Rp {{ number_format($paymentMethodTotals['debit'] ?? 0, 0, ',', '.') }}</span></div>
          <div class="flex items-center justify-between gap-2"><span class="label">Kredit</span><span class="value">Rp {{ number_format($paymentMethodTotals['kredit'] ?? 0, 0, ',', '.') }}</span></div>
          <div class="flex items-center justify-between gap-2"><span class="label">QRIS</span><span class="value">Rp {{ number_format($paymentMethodTotals['qris'] ?? 0, 0, ',', '.') }}</span></div>
          <div class="flex items-center justify-between gap-2"><span class="label">Total Diskon</span><span class="value">Rp {{ number_format($totalDiscount ?? 0, 0, ',', '.') }}</span></div>
          <div class="flex items-center justify-between gap-2"><span class="label">Total DP (booking)</span><span class="value">Rp {{ number_format($totalDownPayment ?? 0, 0, ',', '.') }}</span></div>
        </div>
      </section>

      <section class="mt-3 border-b border-dashed border-gray-300 pb-2">
        <h2 class="text-[11px] font-semibold text-gray-900">INFO ROKOK</h2>
        <div class="mt-1.5 space-y-1">
          @forelse (($rokokItems ?? []) as $rokokItem)
            <div class="flex items-center justify-between gap-2">
              <span class="label">{{ $rokokItem['name'] ?? '-' }}</span>
              <span class="value">{{ number_format((int) ($rokokItem['quantity'] ?? 0), 0, ',', '.') }}x</span>
            </div>
          @empty
            <p class="text-[10px] text-gray-500">Tidak ada item rokok.</p>
          @endforelse
        </div>
      </section>

      {{--
      <section class="mt-3">
        <h2 class="text-[11px] font-semibold text-gray-900">DAFTAR TRANSAKSI</h2>
        <div class="mt-1.5 space-y-2">
          @forelse ($cashierTransactions as $transaction)
            <div class="rounded border border-gray-300 p-2">
              <p class="value">{{ $transaction['order_number'] }}</p>
              <p class="label">{{ $transaction['datetime'] }}</p>
              <p class="label">Customer: {{ $transaction['customer_name'] }}</p>
              <p class="label">Metode: {{ $transaction['payment_method'] }}</p>
              <p class="label">Ref: {{ $transaction['payment_reference_number'] ?: '-' }}</p>
              <div class="mt-1.5 space-y-1 border-t border-dashed border-gray-300 pt-1.5">
                @forelse ($transaction['items'] as $orderItem)
                  <div>
                    <p class="text-[10px] text-gray-800"><span class="font-semibold">{{ $orderItem['quantity'] }}x</span> {{ $orderItem['name'] }}</p>
                    <p class="text-[10px] text-gray-600">Subtotal: Rp {{ number_format($orderItem['subtotal'], 0, ',', '.') }}</p>
                    @if (($orderItem['tax_amount'] ?? 0) > 0)
                      <p class="text-[10px] text-amber-700">PB1: Rp {{ number_format($orderItem['tax_amount'], 0, ',', '.') }}</p>
                    @endif
                    @if (($orderItem['service_charge_amount'] ?? 0) > 0)
                      <p class="text-[10px] text-orange-700">Service: Rp {{ number_format($orderItem['service_charge_amount'], 0, ',', '.') }}</p>
                    @endif
                  </div>
                @empty
                  <p class="text-[10px] text-gray-500">Tidak ada item.</p>
                @endforelse
              </div>
              <div class="mt-1.5 flex items-center justify-between border-t border-dashed border-gray-300 pt-1.5">
                <span class="label">Qty: {{ $transaction['items_count'] }}</span>
                <span class="value">Rp {{ number_format($transaction['total'], 0, ',', '.') }}</span>
              </div>

              <div class="mt-1.5 space-y-1 border-t border-dashed border-gray-300 pt-1.5">
                <div class="flex items-center justify-between"><span class="label">Total Bill</span><span class="value">Rp {{ number_format($transaction['total_bill'] ?? 0, 0, ',', '.') }}</span></div>
                <div class="flex items-center justify-between"><span class="label">PB1</span><span class="value">Rp {{ number_format($transaction['tax_total'] ?? 0, 0, ',', '.') }}</span></div>
                <div class="flex items-center justify-between"><span class="label">Service Charge</span><span class="value">Rp {{ number_format($transaction['service_charge_total'] ?? 0, 0, ',', '.') }}</span></div>
                <div class="flex items-center justify-between"><span class="label">Sub Total</span><span class="value">Rp {{ number_format($transaction['sub_total'] ?? 0, 0, ',', '.') }}</span></div>
                <div class="flex items-center justify-between"><span class="label">Diskon</span><span class="value">- Rp {{ number_format($transaction['discount_amount'] ?? 0, 0, ',', '.') }}</span></div>
                <div class="flex items-center justify-between"><span class="label">DP</span><span class="value">Rp {{ number_format($transaction['down_payment_amount'] ?? 0, 0, ',', '.') }}</span></div>
                <div class="flex items-center justify-between border-t border-dashed border-gray-300 pt-1"><span class="label">Sisa Bayar</span><span class="value">Rp {{ number_format($transaction['total'] ?? 0, 0, ',', '.') }}</span></div>
              </div>
            </div>
          @empty
            <p class="text-center text-[10px] text-gray-500">Tidak ada transaksi kasir pada rentang ini.</p>
          @endforelse
        </div>
      </section>
      --}}

      <p class="mt-3 border-t border-dashed border-gray-300 pt-2 text-center text-[10px] text-gray-500">--- END OF REPORT ---</p>
    </div>
  </main>

  <script>
    const recapPrintEndpoint = @json(route('admin.recap.close-preview.print'));
    const recapPrintPayload = {
      start_datetime: @json($selectedStartDatetime),
      end_datetime: @json($selectedEndDatetime),
      recap_history_id: @json(($reprintHistoryId ?? 0) > 0 ? (int) $reprintHistoryId : null),
      include_transaction_history: true,
    };

    async function triggerEndDayPrint() {
      const statusEl = document.getElementById('printStatus');
      const includeTransactionHistory = document.getElementById('includeTransactionHistory')?.checked ?? true;

      const showStatus = (message, type = 'info') => {
        const typeClass = type === 'success' ?
          'border-emerald-200 bg-emerald-50 text-emerald-700' :
          type === 'error' ?
          'border-red-200 bg-red-50 text-red-700' :
          'border-slate-200 bg-slate-50 text-slate-700';

        statusEl.className = `rounded-lg border px-3 py-2 text-sm ${typeClass}`;
        statusEl.textContent = message;
        statusEl.classList.remove('hidden');
      };

      showStatus('Mengirim data End Day ke printer...');
      recapPrintPayload.include_transaction_history = includeTransactionHistory;

      try {
        const response = await fetch(recapPrintEndpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': @json(csrf_token()),
          },
          body: JSON.stringify(recapPrintPayload),
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
          showStatus(data.message ?? 'Gagal mengirim print End Day.', 'error');

          return;
        }

        let successMessage = data.message ?? 'Print End Day berhasil.';

        if (data.connection_type === 'log' && data.log_path) {
          successMessage += ` Log tersimpan di: ${data.log_path}`;
        }

        showStatus(successMessage, 'success');
      } catch (error) {
        showStatus('Terjadi kesalahan saat print End Day.', 'error');
      }
    }
  </script>
</body>

</html>
