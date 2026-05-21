<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport"
        content="width=device-width, initial-scale=1.0">
  <title>Preview Print End Day Kitchen</title>
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
        <p class="text-sm font-semibold text-gray-900">Preview Print Struk - End Day Kitchen</p>
        <p class="text-xs text-gray-500">Cek tampilan sebelum kirim reprint ke printer Kitchen.</p>
      </div>

      <div class="flex items-center gap-2">
        <a href="{{ route('admin.kitchen.index') }}"
           class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
          Kembali
        </a>

        <button type="button"
                onclick="window.print()"
                class="inline-flex items-center justify-center rounded-lg border border-orange-300 px-3 py-2 text-sm font-medium text-orange-700 hover:bg-orange-50">
          Save PDF
        </button>

        <form method="POST"
              action="{{ route('admin.kitchen.end-day.reprint', $history) }}">
          @csrf
          <button type="submit"
                  class="inline-flex items-center justify-center rounded-lg bg-orange-500 px-3 py-2 text-sm font-semibold text-white hover:bg-orange-600">
            Reprint Sekarang
          </button>
        </form>
      </div>
    </div>

    <div class="mx-auto max-w-5xl px-4 pb-3 space-y-2">
      @if (session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('success') }}</div>
      @endif

      @if (session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ session('error') }}</div>
      @endif
    </div>
  </div>

  <main class="preview-shell py-6 print:py-0">
    <div class="print-sheet">
      <header class="border-b border-dashed border-gray-300 pb-2 text-center">
        <h1 class="text-sm font-bold tracking-tight text-gray-900">END DAY KITCHEN</h1>
        <p class="label mt-1">{{ $history->end_day?->format('d/m/Y') ?? '-' }}</p>
        <p class="label">{{ now()->format('d/m/Y H:i:s') }}</p>
      </header>

      <section class="mt-3 space-y-1.5 border-b border-dashed border-gray-300 pb-2">
        <div class="flex items-center justify-between gap-2">
          <span class="label">Total Item</span>
          <span class="value">{{ number_format((int) ($totalItems ?? ($history->total_items ?? 0)), 0, ',', '.') }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
          <span class="label">Last Synced</span>
          <span class="value">{{ $history->last_synced_at?->format('d/m/Y H:i') ?? '-' }}</span>
        </div>
      </section>

      <section class="mt-3">
        <h2 class="text-[11px] font-semibold text-gray-900">DETAIL ITEM</h2>
        <div class="mt-1.5 space-y-1.5">
          @forelse ($items as $item)
            <div class="flex items-center justify-between rounded border border-gray-300 p-2">
              <span class="text-[10px] text-gray-800">{{ $item->inventoryItem?->pos_name ?? ($item->inventoryItem?->name ?? 'Unknown Item') }}</span>
              <span class="value">{{ (int) $item->quantity }}</span>
            </div>
          @empty
            <p class="text-center text-[10px] text-gray-500">Tidak ada detail item pada history ini.</p>
          @endforelse
        </div>
      </section>

      <p class="mt-3 border-t border-dashed border-gray-300 pt-2 text-center text-[10px] text-gray-500">--- END OF KITCHEN REPORT ---</p>
    </div>
  </main>
</body>

</html>
