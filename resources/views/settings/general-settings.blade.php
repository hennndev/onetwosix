<x-app-layout title="Pengaturan Umum">
  <div class="p-4 sm:p-6 w-full max-w-full">

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
    @if (session('error'))
      <div class="mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded-lg text-sm">
        {{ session('error') }}
      </div>
    @endif

    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-slate-800">Pengaturan Umum</h1>
      <p class="text-sm text-slate-500 mt-1">Konfigurasi pajak, service charge, integrasi Accurate, opsi checker, dan printer target default per area</p>
    </div>

    <form method="POST"
          action="{{ route('admin.settings.general.update') }}"
          class="w-full max-w-full space-y-6">
      @csrf
      @method('PUT')

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- Card 1: Finansial & Accurate -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
          <h2 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-3 flex items-center gap-2">
            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Finansial & Integrasi Accurate
          </h2>

          <!-- Tax Percentage -->
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="tax_percentage">
              PB1 / Pajak (%)
            </label>
            <p class="text-xs text-slate-400 mb-2">Persentase pajak yang ditambahkan ke subtotal billing customer.</p>
            <div class="flex items-center gap-3">
              <input type="number"
                     id="tax_percentage"
                     name="tax_percentage"
                     value="{{ old('tax_percentage', $settings->tax_percentage) }}"
                     min="0"
                     max="100"
                     step="1"
                     class="w-full border @error('tax_percentage') border-red-400 @else border-slate-300 @enderror rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" />
              <span class="text-sm text-slate-500 font-semibold">%</span>
            </div>
            @error('tax_percentage')
              <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
          </div>

          <!-- Service Charge Percentage -->
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="service_charge_percentage">
              Service Charge (%)
            </label>
            <p class="text-xs text-slate-400 mb-2">Persentase service charge yang ditambahkan ke subtotal billing customer.</p>
            <div class="flex items-center gap-3">
              <input type="number"
                     id="service_charge_percentage"
                     name="service_charge_percentage"
                     value="{{ old('service_charge_percentage', $settings->service_charge_percentage) }}"
                     min="0"
                     max="100"
                     step="1"
                     class="w-full border @error('service_charge_percentage') border-red-400 @else border-slate-300 @enderror rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" />
              <span class="text-sm text-slate-500 font-semibold">%</span>
            </div>
            @error('service_charge_percentage')
              <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
          </div>

          <!-- Operational Anchor Time -->
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="operational_anchor_time">
              Jam Awal Siklus End Day
            </label>
            <p class="text-xs text-slate-400 mb-2">Batas waktu pergeseran hari operasional untuk recap, dashboard, dan sync (Format: HH:MM).</p>
            <div class="flex items-center gap-3">
              <input type="time"
                     id="operational_anchor_time"
                     name="operational_anchor_time"
                     value="{{ old('operational_anchor_time', $settings->operationalAnchorTime()) }}"
                     step="60"
                     class="w-full border @error('operational_anchor_time') border-red-400 @else border-slate-300 @enderror rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" />
            </div>
            @error('operational_anchor_time')
              <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
          </div>

          <!-- Accurate Stock Warehouse Name -->
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="accurate_stock_warehouse_name">
              Nama Gudang Stok / Warehouse Name (Accurate)
            </label>
            <p class="text-xs text-slate-400 mb-2">Nama gudang di Accurate Online untuk pengurangan stok item transaksi (Default: GD. OUTLET).</p>
            <input type="text"
                   id="accurate_stock_warehouse_name"
                   name="accurate_stock_warehouse_name"
                   value="{{ old('accurate_stock_warehouse_name', $settings->accurate_stock_warehouse_name ?? 'GD. OUTLET') }}"
                   placeholder="GD. OUTLET"
                   class="w-full border @error('accurate_stock_warehouse_name') border-red-400 @else border-slate-300 @enderror rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" />
            @error('accurate_stock_warehouse_name')
              <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
          </div>

          <!-- Accurate Tax Account No -->
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="accurate_tax_account_no">
              Nomor Akun Pajak PB1 (Accurate)
            </label>
            <p class="text-xs text-slate-400 mb-2">Nomor akun kewajiban/beban pajak PB1 di Accurate Online (Default: 210201).</p>
            <input type="text"
                   id="accurate_tax_account_no"
                   name="accurate_tax_account_no"
                   value="{{ old('accurate_tax_account_no', $settings->accurate_tax_account_no ?? '210201') }}"
                   placeholder="210201"
                   class="w-full border @error('accurate_tax_account_no') border-red-400 @else border-slate-300 @enderror rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" />
            @error('accurate_tax_account_no')
              <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
          </div>

          <!-- Accurate Service Charge Account No -->
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="accurate_service_charge_account_no">
              Nomor Akun Service Charge (Accurate)
            </label>
            <p class="text-xs text-slate-400 mb-2">Nomor akun kewajiban/pendapatan service charge di Accurate Online (Default: 210202).</p>
            <input type="text"
                   id="accurate_service_charge_account_no"
                   name="accurate_service_charge_account_no"
                   value="{{ old('accurate_service_charge_account_no', $settings->accurate_service_charge_account_no ?? '210202') }}"
                   placeholder="210202"
                   class="w-full border @error('accurate_service_charge_account_no') border-red-400 @else border-slate-300 @enderror rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" />
            @error('accurate_service_charge_account_no')
              <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
          </div>

          <!-- Accurate Bank Account No -->
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="accurate_bank_account_no">
              Nomor Akun Bank (Accurate)
            </label>
            <p class="text-xs text-slate-400 mb-2">Nomor akun bank penerima pembayaran non-tunai (Transfer/QRIS/Card) di Accurate Online (Default: 110102).</p>
            <input type="text"
                   id="accurate_bank_account_no"
                   name="accurate_bank_account_no"
                   value="{{ old('accurate_bank_account_no', $settings->accurate_bank_account_no ?? '110102') }}"
                   placeholder="110102"
                   class="w-full border @error('accurate_bank_account_no') border-red-400 @else border-slate-300 @enderror rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" />
            @error('accurate_bank_account_no')
              <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
          </div>

          <!-- Accurate Cash Account No -->
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="accurate_cash_account_no">
              Nomor Akun Kas / Tunai (Accurate)
            </label>
            <p class="text-xs text-slate-400 mb-2">Nomor akun kas/tunai penerima pembayaran tunai di Accurate Online (Default: 110101).</p>
            <input type="text"
                   id="accurate_cash_account_no"
                   name="accurate_cash_account_no"
                   value="{{ old('accurate_cash_account_no', $settings->accurate_cash_account_no ?? '110101') }}"
                   placeholder="110101"
                   class="w-full border @error('accurate_cash_account_no') border-red-400 @else border-slate-300 @enderror rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" />
            @error('accurate_cash_account_no')
              <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
          </div>

          <!-- Can Choose Checker -->
          <div class="pt-2" x-data="{ canChooseChecker: @js((bool) old('can_choose_checker', $settings->can_choose_checker)) }">
            <p class="text-sm font-semibold text-slate-700 mb-1">Can Choose Checker</p>
            <p class="text-xs text-slate-400 mb-3">Aktifkan agar kasir bisa memilih printer checker saat tersedia lebih dari satu.</p>
            <label for="can_choose_checker" class="inline-flex items-center gap-3 text-sm text-slate-700 cursor-pointer select-none">
              <input type="hidden" name="can_choose_checker" value="0">
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
              Status: <span class="font-semibold" x-text="canChooseChecker ? 'Aktif' : 'Nonaktif'">{{ old('can_choose_checker', $settings->can_choose_checker) ? 'Aktif' : 'Nonaktif' }}</span>
            </p>
            @error('can_choose_checker')
              <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
          </div>
        </div>

        <!-- Card 2: Email, WhatsApp & Auth Code -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
          <h2 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-3 flex items-center gap-2">
            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
            Email, WhatsApp & Auth Code
          </h2>

          <!-- Mail Provider -->
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="mail_provider">
              Metode Pengiriman Email
            </label>
            <p class="text-xs text-slate-400 mb-2">Provider email sistem (untuk pengiriman Auth Code/Notifikasi).</p>
            <select id="mail_provider"
                    name="mail_provider"
                    class="w-full border @error('mail_provider') border-red-400 @else border-slate-300 @enderror rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
              <option value="smtp" {{ old('mail_provider', $settings->mail_provider) === 'smtp' ? 'selected' : '' }}>SMTP (Default / Gmail)</option>
              <option value="resend" {{ old('mail_provider', $settings->mail_provider) === 'resend' ? 'selected' : '' }}>Resend API</option>
            </select>
            @error('mail_provider')
              <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
          </div>

          <!-- Auth Code Target Email -->
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="auth_code_target_email">
              Email Tujuan Auth Code
            </label>
            <p class="text-xs text-slate-400 mb-2">Email approval tujuan untuk pengiriman auth code.</p>
            <input type="email"
                   id="auth_code_target_email"
                   name="auth_code_target_email"
                   value="{{ old('auth_code_target_email', $settings->auth_code_target_email) }}"
                   placeholder="manager@company.com"
                   class="w-full border @error('auth_code_target_email') border-red-400 @else border-slate-300 @enderror rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" />
            @error('auth_code_target_email')
              <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
          </div>

          <!-- Auth Code Target WhatsApp -->
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="auth_code_target_whatsapp">
              Nomor WhatsApp Tujuan Auth Code
            </label>
            <p class="text-xs text-slate-400 mb-2">Nomor WhatsApp tujuan untuk pengiriman auth code via Fonnte.</p>
            <input type="text"
                   id="auth_code_target_whatsapp"
                   name="auth_code_target_whatsapp"
                   value="{{ old('auth_code_target_whatsapp', $settings->auth_code_target_whatsapp) }}"
                   placeholder="08123456789"
                   class="w-full border @error('auth_code_target_whatsapp') border-red-400 @else border-slate-300 @enderror rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" />
            @error('auth_code_target_whatsapp')
              <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
          </div>

          <!-- Fonnte Token -->
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="fonnte_token">
              Fonnte API Token
            </label>
            <p class="text-xs text-slate-400 mb-2">Token API Fonnte. Kosongkan jika sudah di-set di file .env.</p>
            <input type="text"
                   id="fonnte_token"
                   name="fonnte_token"
                   value="{{ old('fonnte_token', $settings->fonnte_token) }}"
                   placeholder="Masukkan token Fonnte"
                   class="w-full border @error('fonnte_token') border-red-400 @else border-slate-300 @enderror rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none" />
            @error('fonnte_token')
              <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
          </div>

          <!-- Auth Code Delivery Channel -->
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="auth_code_delivery_channel">
              Channel Pengiriman Auth Code
            </label>
            <p class="text-xs text-slate-400 mb-2">Pilih saluran pengiriman kode OTP otorisasi harian.</p>
            <select id="auth_code_delivery_channel"
                    name="auth_code_delivery_channel"
                    class="w-full border @error('auth_code_delivery_channel') border-red-400 @else border-slate-300 @enderror rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none bg-white">
              <option value="both" {{ old('auth_code_delivery_channel', $settings->auth_code_delivery_channel) === 'both' ? 'selected' : '' }}>Email & WhatsApp (Keduanya)</option>
              <option value="email" {{ old('auth_code_delivery_channel', $settings->auth_code_delivery_channel) === 'email' ? 'selected' : '' }}>Email Saja</option>
              <option value="whatsapp" {{ old('auth_code_delivery_channel', $settings->auth_code_delivery_channel) === 'whatsapp' ? 'selected' : '' }}>WhatsApp Saja</option>
            </select>
            @error('auth_code_delivery_channel')
              <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
          </div>

          <!-- Daily Auth Code Access Emails -->
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="daily_auth_code_access_emails">
              Email Akses Daily Auth Code
            </label>
            <p class="text-xs text-slate-400 mb-2">Akun yang boleh membuka/mengakses menu Daily Auth Code (1 email per baris).</p>
            <textarea id="daily_auth_code_access_emails"
                      name="daily_auth_code_access_emails"
                      rows="3"
                      placeholder="manager@company.com&#10;ops@company.com"
                      class="w-full border @error('daily_auth_code_access_emails') border-red-400 @else border-slate-300 @enderror rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none">{{ old('daily_auth_code_access_emails', implode("\n", $settings->dailyAuthCodeAccessEmails())) }}</textarea>
            @error('daily_auth_code_access_emails')
              <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
          </div>
        </div>

      </div>

      <!-- Section: Printer Target Default Mapped Per Area -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-6">
        <div class="border-b border-slate-100 pb-3 flex flex-col md:flex-row md:items-center justify-between gap-2">
          <div>
            <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
              <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
              Printer Target Default (Mapping Berdasarkan Area)
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">Tentukan printer target default untuk setiap area transaksi (ROOM, LOUNGE, dll).</p>
          </div>
          <span class="text-xs px-3 py-1 rounded-full bg-indigo-50 text-indigo-700 font-semibold border border-indigo-200 self-start md:self-auto">
            {{ $areas->count() }} Area Aktif Terdaftar
          </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          @foreach ($areas as $area)
            @php
              $areaSettings = old('area_printer_settings.' . $area->id, $settings->area_printer_settings[$area->id] ?? []);
            @endphp
            <div class="bg-slate-50/80 rounded-xl border border-slate-200 p-5 space-y-4">
              <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <h3 class="font-bold text-slate-800 flex items-center gap-2 text-sm">
                  <span class="px-2.5 py-0.5 rounded-md bg-indigo-600 text-white text-xs font-bold">{{ $area->code }}</span>
                  <span>Area: {{ $area->name }}</span>
                </h3>
              </div>

              <div class="space-y-3">
                <!-- Closed Billing -->
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-1">Printer Struk Closed Billing</label>
                  <select name="area_printer_settings[{{ $area->id }}][closed_billing]" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-slate-400 outline-none bg-white">
                    <option value="">Kasir Area / Default Otomatis</option>
                    @foreach ($printers as $printer)
                      <option value="{{ $printer->id }}" {{ (string) ($areaSettings['closed_billing'] ?? '') === (string) $printer->id ? 'selected' : '' }}>
                        {{ $printer->name }} ({{ strtoupper($printer->printer_type ?? $printer->location) }})
                      </option>
                    @endforeach
                  </select>
                </div>

                <!-- Walk-in -->
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-1">Printer Struk Walk-in</label>
                  <select name="area_printer_settings[{{ $area->id }}][walk_in]" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-slate-400 outline-none bg-white">
                    <option value="">Kasir Area / Default Otomatis</option>
                    @foreach ($printers as $printer)
                      <option value="{{ $printer->id }}" {{ (string) ($areaSettings['walk_in'] ?? '') === (string) $printer->id ? 'selected' : '' }}>
                        {{ $printer->name }} ({{ strtoupper($printer->printer_type ?? $printer->location) }})
                      </option>
                    @endforeach
                  </select>
                </div>

                <!-- End Day Receipt -->
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-1">Printer Struk End Day</label>
                  <select name="area_printer_settings[{{ $area->id }}][end_day_receipt]" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-slate-400 outline-none bg-white">
                    <option value="">Kasir Area / Default Otomatis</option>
                    @foreach ($printers as $printer)
                      <option value="{{ $printer->id }}" {{ (string) ($areaSettings['end_day_receipt'] ?? '') === (string) $printer->id ? 'selected' : '' }}>
                        {{ $printer->name }} ({{ strtoupper($printer->printer_type ?? $printer->location) }})
                      </option>
                    @endforeach
                  </select>
                </div>

                <!-- End Day Kitchen -->
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-1">Printer End Day Kitchen</label>
                  <select name="area_printer_settings[{{ $area->id }}][end_day_kitchen]" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-slate-400 outline-none bg-white">
                    <option value="">Printer Kitchen Area / Default Otomatis</option>
                    @foreach ($printers as $printer)
                      <option value="{{ $printer->id }}" {{ (string) ($areaSettings['end_day_kitchen'] ?? '') === (string) $printer->id ? 'selected' : '' }}>
                        {{ $printer->name }} ({{ strtoupper($printer->printer_type ?? $printer->location) }})
                      </option>
                    @endforeach
                  </select>
                </div>

                <!-- End Day Bar -->
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-1">Printer End Day Bar</label>
                  <select name="area_printer_settings[{{ $area->id }}][end_day_bar]" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-slate-400 outline-none bg-white">
                    <option value="">Printer Bar Area / Default Otomatis</option>
                    @foreach ($printers as $printer)
                      <option value="{{ $printer->id }}" {{ (string) ($areaSettings['end_day_bar'] ?? '') === (string) $printer->id ? 'selected' : '' }}>
                        {{ $printer->name }} ({{ strtoupper($printer->printer_type ?? $printer->location) }})
                      </option>
                    @endforeach
                  </select>
                </div>

              </div>
            </div>
          @endforeach
        </div>
      </div>

      <!-- Footer Action & Info -->
      <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-700 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-2">
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          <span>Pengaturan ini langsung berlaku untuk perhitungan pajak/service charge, sinkronisasi gudang Accurate, printer target per area, dan saluran OTP.</span>
        </div>
        <div class="flex items-center gap-3 flex-shrink-0 self-end md:self-auto">
          <a href="{{ route('admin.settings.index') }}"
             class="px-5 py-2.5 bg-white border border-slate-300 text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition">
            Batal
          </a>
          <button type="submit"
                  class="px-6 py-2.5 bg-slate-800 text-white text-sm font-semibold rounded-xl hover:bg-slate-700 transition shadow-sm">
            Simpan Pengaturan
          </button>
        </div>
      </div>
    </form>

    <div class="mt-8 pt-8 border-t border-slate-200">
      <h3 class="text-lg font-bold text-slate-800 mb-2">Uji Coba Pengiriman Email</h3>
      <p class="text-sm text-slate-500 mb-4">Kirim email percobaan untuk memastikan pengaturan email (SMTP/Resend) berfungsi dengan baik. Sistem akan mengirim pesan ke <strong>Email Tujuan Auth Code</strong>.</p>
      <form action="{{ route('admin.settings.general.test-email') }}" method="POST">
        @csrf
        <button type="submit"
                class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
          Kirim Email Percobaan
        </button>
      </form>
    </div>

  </div>
</x-app-layout>
