<x-app-layout>
  <div class="p-6">

    <!-- Back -->
    <a href="{{ route('admin.settings.index') }}"
       class="inline-flex items-center gap-1.5 text-sm text-slate-600 hover:text-slate-800 mb-6">
      <svg class="w-4 h-4"
           fill="none"
           stroke="currentColor"
           viewBox="0 0 24 24">
        <path stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M10 19l-7-7m0 0l7-7m-7 7h18" />
      </svg>
      Kembali ke Menu Pengaturan
    </a>

    @if (session('success'))
      <div class="mb-4 px-4 py-3 bg-green-100 border border-green-400 text-green-700 rounded-lg text-sm">
        {{ session('success') }}
      </div>
    @endif

    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-slate-800">Pengaturan Umum</h1>
      <p class="text-sm text-slate-500 mt-1">Konfigurasi pajak, service charge, opsi pilih checker, dan printer default</p>
    </div>

    <form method="POST"
          action="{{ route('admin.settings.general.update') }}"
          class="max-w-lg">
      @csrf
      @method('PUT')

      <div class="bg-white rounded-xl border border-slate-200 shadow-sm divide-y divide-slate-100">

        <!-- Tax Percentage -->
        <div class="p-6">
          <label class="block text-sm font-semibold text-slate-700 mb-1"
                 for="tax_percentage">
            PB1 / Pajak (%)
          </label>
          <p class="text-xs text-slate-400 mb-3">Persentase pajak yang ditambahkan ke subtotal billing customer.</p>
          <div class="flex items-center gap-3">
            <input type="number"
                   id="tax_percentage"
                   name="tax_percentage"
                   value="{{ old('tax_percentage', $settings->tax_percentage) }}"
                   min="0"
                   max="100"
                   step="1"
                   class="w-28 border @error('tax_percentage') border-red-400 @else border-slate-300 @enderror rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" />
            <span class="text-sm text-slate-500">%</span>
          </div>
          @error('tax_percentage')
            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
          @enderror
        </div>

        <!-- Service Charge Percentage -->
        <div class="p-6">
          <label class="block text-sm font-semibold text-slate-700 mb-1"
                 for="service_charge_percentage">
            Service Charge (%)
          </label>
          <p class="text-xs text-slate-400 mb-3">Persentase service charge yang ditambahkan ke subtotal billing customer.</p>
          <div class="flex items-center gap-3">
            <input type="number"
                   id="service_charge_percentage"
                   name="service_charge_percentage"
                   value="{{ old('service_charge_percentage', $settings->service_charge_percentage) }}"
                   min="0"
                   max="100"
                   step="1"
                   class="w-28 border @error('service_charge_percentage') border-red-400 @else border-slate-300 @enderror rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" />
            <span class="text-sm text-slate-500">%</span>
          </div>
          @error('service_charge_percentage')
            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
          @enderror
        </div>

        <div class="p-6"
             x-data="{ canChooseChecker: @js((bool) old('can_choose_checker', $settings->can_choose_checker)) }">
          <p class="text-sm font-semibold text-slate-700 mb-1">Can Choose Checker</p>
          <p class="text-xs text-slate-400 mb-3">Aktifkan agar kasir bisa memilih printer checker saat tersedia lebih dari satu.</p>
          <label for="can_choose_checker"
                 class="inline-flex items-center gap-3 text-sm text-slate-700 cursor-pointer select-none">
            <input type="hidden"
                   name="can_choose_checker"
                   value="0">
            <input type="checkbox"
                   id="can_choose_checker"
                   name="can_choose_checker"
                   value="1"
                   {{ old('can_choose_checker', $settings->can_choose_checker) ? 'checked' : '' }}
                   x-model="canChooseChecker"
                   class="peer sr-only">
            <span class="relative inline-flex h-6 w-11 rounded-full bg-slate-300 transition peer-checked:bg-slate-700 peer-focus-visible:ring-2 peer-focus-visible:ring-slate-400 peer-focus-visible:ring-offset-2 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-transform after:duration-200 peer-checked:after:translate-x-5"></span>
            <span class="font-medium">Izinkan pilih checker</span>
          </label>
          <p class="text-xs text-slate-500 mt-2">
            Status: <span class="font-semibold"
                  x-text="canChooseChecker ? 'Aktif' : 'Nonaktif'">{{ old('can_choose_checker', $settings->can_choose_checker) ? 'Aktif' : 'Nonaktif' }}</span>
          </p>
          @error('can_choose_checker')
            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
          @enderror
        </div>

        <div class="p-6">
          <label class="block text-sm font-semibold text-slate-700 mb-1"
                 for="closed_billing_receipt_printer_id">
            Printer Struk Closed Billing
          </label>
          <p class="text-xs text-slate-400 mb-3">Pilih printer default untuk cetak struk otomatis saat billing booking ditutup.</p>
          <select id="closed_billing_receipt_printer_id"
                  name="closed_billing_receipt_printer_id"
                  class="w-full border @error('closed_billing_receipt_printer_id') border-red-400 @else border-slate-300 @enderror rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
            <option value="">Kasir Default (otomatis)</option>
            @foreach ($printers as $printer)
              <option value="{{ $printer->id }}"
                      {{ (string) old('closed_billing_receipt_printer_id', $settings->closed_billing_receipt_printer_id) === (string) $printer->id ? 'selected' : '' }}>
                {{ $printer->name }} ({{ strtoupper($printer->printer_type ?? $printer->location) }})
              </option>
            @endforeach
          </select>
          @error('closed_billing_receipt_printer_id')
            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
          @enderror
        </div>

        <div class="p-6">
          <label class="block text-sm font-semibold text-slate-700 mb-1"
                 for="walk_in_receipt_printer_id">
            Printer Struk Walk-in
          </label>
          <p class="text-xs text-slate-400 mb-3">Pilih printer default untuk cetak struk otomatis transaksi walk-in.</p>
          <select id="walk_in_receipt_printer_id"
                  name="walk_in_receipt_printer_id"
                  class="w-full border @error('walk_in_receipt_printer_id') border-red-400 @else border-slate-300 @enderror rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
            <option value="">Kasir Default (otomatis)</option>
            @foreach ($printers as $printer)
              <option value="{{ $printer->id }}"
                      {{ (string) old('walk_in_receipt_printer_id', $settings->walk_in_receipt_printer_id) === (string) $printer->id ? 'selected' : '' }}>
                {{ $printer->name }} ({{ strtoupper($printer->printer_type ?? $printer->location) }})
              </option>
            @endforeach
          </select>
          @error('walk_in_receipt_printer_id')
            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
          @enderror
        </div>

        <div class="p-6">
          <label class="block text-sm font-semibold text-slate-700 mb-1"
                 for="end_day_receipt_printer_id">
            Printer Struk End Day
          </label>
          <p class="text-xs text-slate-400 mb-3">Pilih printer default untuk cetak hasil recap End Day.</p>
          <select id="end_day_receipt_printer_id"
                  name="end_day_receipt_printer_id"
                  class="w-full border @error('end_day_receipt_printer_id') border-red-400 @else border-slate-300 @enderror rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
            <option value="">Kasir Default (otomatis)</option>
            @foreach ($printers as $printer)
              <option value="{{ $printer->id }}"
                      {{ (string) old('end_day_receipt_printer_id', $settings->end_day_receipt_printer_id) === (string) $printer->id ? 'selected' : '' }}>
                {{ $printer->name }} ({{ strtoupper($printer->printer_type ?? $printer->location) }})
              </option>
            @endforeach
          </select>
          @error('end_day_receipt_printer_id')
            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
          @enderror
        </div>

        <div class="p-6">
          <label class="block text-sm font-semibold text-slate-700 mb-1"
                 for="end_day_kitchen_printer_id">
            Printer End Day Kitchen
          </label>
          <p class="text-xs text-slate-400 mb-3">Pilih printer target khusus untuk output End Day Kitchen.</p>
          <select id="end_day_kitchen_printer_id"
                  name="end_day_kitchen_printer_id"
                  class="w-full border @error('end_day_kitchen_printer_id') border-red-400 @else border-slate-300 @enderror rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
            <option value="">Kasir Default (otomatis)</option>
            @foreach ($printers as $printer)
              <option value="{{ $printer->id }}"
                      {{ (string) old('end_day_kitchen_printer_id', $settings->end_day_kitchen_printer_id) === (string) $printer->id ? 'selected' : '' }}>
                {{ $printer->name }} ({{ strtoupper($printer->printer_type ?? $printer->location) }})
              </option>
            @endforeach
          </select>
          @error('end_day_kitchen_printer_id')
            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
          @enderror
        </div>

        <div class="p-6">
          <label class="block text-sm font-semibold text-slate-700 mb-1"
                 for="end_day_bar_printer_id">
            Printer End Day Bar
          </label>
          <p class="text-xs text-slate-400 mb-3">Pilih printer target khusus untuk output End Day Bar.</p>
          <select id="end_day_bar_printer_id"
                  name="end_day_bar_printer_id"
                  class="w-full border @error('end_day_bar_printer_id') border-red-400 @else border-slate-300 @enderror rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
            <option value="">Kasir Default (otomatis)</option>
            @foreach ($printers as $printer)
              <option value="{{ $printer->id }}"
                      {{ (string) old('end_day_bar_printer_id', $settings->end_day_bar_printer_id) === (string) $printer->id ? 'selected' : '' }}>
                {{ $printer->name }} ({{ strtoupper($printer->printer_type ?? $printer->location) }})
              </option>
            @endforeach
          </select>
          @error('end_day_bar_printer_id')
            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
          @enderror
        </div>

        <div class="p-6">
          <label class="block text-sm font-semibold text-slate-700 mb-1"
                 for="auth_code_target_email">
            Email Tujuan Auth Code
          </label>
          <p class="text-xs text-slate-400 mb-3">Email approval tujuan untuk pengiriman auth code (kebutuhan general).</p>
          <input type="email"
                 id="auth_code_target_email"
                 name="auth_code_target_email"
                 value="{{ old('auth_code_target_email', $settings->auth_code_target_email) }}"
                 placeholder="contoh: manager@company.com"
                 class="w-full border @error('auth_code_target_email') border-red-400 @else border-slate-300 @enderror rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" />
          @error('auth_code_target_email')
            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
          @enderror
        </div>

        <div class="p-6">
          <label class="block text-sm font-semibold text-slate-700 mb-1"
                 for="daily_auth_code_access_emails">
            Email Akses Daily Auth Code
          </label>
          <p class="text-xs text-slate-400 mb-3">Daftar akun/email yang boleh membuka dan memakai menu Daily Auth Code. Tulis satu email per baris atau pisahkan dengan koma.</p>
          <textarea id="daily_auth_code_access_emails"
                    name="daily_auth_code_access_emails"
                    rows="4"
                    placeholder="manager@company.com&#10;ops@company.com"
                    class="w-full border @error('daily_auth_code_access_emails') border-red-400 @else border-slate-300 @enderror rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">{{ old('daily_auth_code_access_emails', implode("\n", $settings->dailyAuthCodeAccessEmails())) }}</textarea>
          @error('daily_auth_code_access_emails')
            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
          @enderror
        </div>

      </div>

      <!-- Info box -->
      <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mt-4 text-sm text-blue-700">
        <p class="font-semibold mb-1 flex items-center gap-1.5">
          <svg class="w-4 h-4 flex-shrink-0"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Catatan
        </p>
        <p>Pengaturan ini mengatur persentase charge, opsi pilih checker, printer default, email tujuan auth code, dan akun yang boleh membuka Daily Auth Code.</p>
      </div>

      <div class="mt-6 flex items-center gap-3">
        <button type="submit"
                class="px-6 py-2.5 bg-slate-800 text-white text-sm font-semibold rounded-xl hover:bg-slate-700 transition">
          Simpan Pengaturan
        </button>
        <a href="{{ route('admin.settings.index') }}"
           class="px-6 py-2.5 bg-slate-100 text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-200 transition">
          Batal
        </a>
      </div>
    </form>

  </div>
</x-app-layout>
