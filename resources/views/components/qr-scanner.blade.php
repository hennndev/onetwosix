@props([
  'name',
  'title' => 'Scan QR Code',
  'event',
  'expected' => 'customer',
])

<div id="qr-scanner-{{ $name }}"
     data-qr-scanner
     data-name="{{ $name }}"
     data-event="{{ $event }}"
     data-expected="{{ $expected }}"
     class="hidden fixed inset-0 z-[100] items-center justify-center bg-black/60 p-4"
     role="dialog"
     aria-modal="true"
     aria-labelledby="qr-scanner-title-{{ $name }}">
  <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
      <div>
        <h3 id="qr-scanner-title-{{ $name }}" class="font-bold text-gray-900">{{ $title }}</h3>
        <p class="mt-0.5 text-xs text-gray-500">Arahkan kamera ke QR hingga terbaca otomatis.</p>
      </div>
      <button type="button"
              data-qr-close
              class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700"
              aria-label="Tutup scanner">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <div class="space-y-4 p-5">
      <div id="qr-reader-{{ $name }}" class="min-h-64 overflow-hidden rounded-xl bg-slate-900"></div>

      <p data-qr-error class="hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>

      <div class="border-t border-gray-100 pt-4">
        <label for="qr-manual-{{ $name }}" class="mb-1.5 block text-xs font-semibold text-gray-600">
          Input manual jika kamera tidak tersedia
        </label>
        <div class="flex gap-2">
          <input id="qr-manual-{{ $name }}"
                 data-qr-manual
                 type="text"
                 autocomplete="off"
                 placeholder="Tempel isi QR di sini"
                 class="min-w-0 flex-1 rounded-lg border-gray-300 text-sm focus:border-slate-500 focus:ring-slate-500">
          <button type="button"
                  data-qr-submit
                  class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
            Pilih
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

@once
  @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
      (() => {
        const activeScanners = new Map();

        function scannerElement(name) {
          return document.getElementById(`qr-scanner-${name}`);
        }

        function showError(element, message = '') {
          const error = element?.querySelector('[data-qr-error]');

          if (!error) return;

          error.textContent = message;
          error.classList.toggle('hidden', !message);
        }

        function parseBottlePayload(rawValue) {
          const parts = rawValue.split('|');
          const idMatch = parts[0]?.match(/^BOTTLE:(\d+)$/i);
          const quantityMatch = parts[3]?.trim().match(/^(\d+(?:[.,]\d+)?)(.+)$/);

          if (parts.length !== 4 || !idMatch || !parts[1]?.trim() || !['weekday', 'weekend_event'].includes(parts[2]?.trim()) || !quantityMatch) {
            throw new Error('QR botol tidak valid. Format: BOTTLE:ID|Nama|weekday/weekend_event|1Botol');
          }

          return {
            id: Number(idMatch[1]),
            item_name: parts[1].trim(),
            type: parts[2].trim(),
            quantity: Number(quantityMatch[1].replace(',', '.')),
            unit: quantityMatch[2].trim(),
          };
        }

        function parseCustomerPayload(rawValue) {
          let payload;

          try {
            payload = JSON.parse(rawValue.replace(/\\_/g, '_'));
          } catch (error) {
            throw new Error('QR customer tidak valid atau bukan data JSON.');
          }

          const userId = Number(payload.id || 0);
          const customerId = Number(payload.customer_id || 0);

          if ((!userId && !customerId) || !payload.name) {
            throw new Error('QR customer tidak memiliki ID dan nama yang valid.');
          }

          return {
            id: userId,
            customer_id: customerId,
            name: String(payload.name),
            email: String(payload.email || '').replace(/^\[([^\]]+)]\(mailto:[^)]+\)$/, '$1'),
            phone: String(payload.phone || ''),
            tier: payload.tier ?? null,
            points: Number(payload.points || 0),
          };
        }

        function parsePayload(rawValue, expected) {
          const value = String(rawValue || '').trim();

          if (!value) {
            throw new Error('Isi QR tidak boleh kosong.');
          }

          return expected === 'bottle' ? parseBottlePayload(value) : parseCustomerPayload(value);
        }

        async function stopScanner(name) {
          const scanner = activeScanners.get(name);

          if (!scanner) return;

          try {
            if (scanner.isScanning) {
              await scanner.stop();
            }
            await scanner.clear();
          } catch (error) {
            // Scanner may already be stopped by the browser when the modal closes.
          }

          activeScanners.delete(name);
        }

        async function closeScanner(name) {
          const element = scannerElement(name);
          await stopScanner(name);
          element?.classList.add('hidden');
          element?.classList.remove('flex');
        }

        async function acceptValue(element, rawValue) {
          try {
            const parsed = parsePayload(rawValue, element.dataset.expected);
            showError(element);
            await closeScanner(element.dataset.name);
            window.dispatchEvent(new CustomEvent(element.dataset.event, {
              detail: { raw: String(rawValue).trim(), parsed },
            }));
          } catch (error) {
            showError(element, error.message || 'QR tidak dapat dibaca.');
          }
        }

        window.openQrScanner = async function(name) {
          const element = scannerElement(name);

          if (!element) return;

          element.classList.remove('hidden');
          element.classList.add('flex');
          element.querySelector('[data-qr-manual]').value = '';
          showError(element);

          if (typeof Html5Qrcode === 'undefined') {
            showError(element, 'Library scanner gagal dimuat. Gunakan input manual.');
            return;
          }

          const scanner = new Html5Qrcode(`qr-reader-${name}`);
          activeScanners.set(name, scanner);

          try {
            await scanner.start(
              { facingMode: 'environment' },
              { fps: 10, qrbox: { width: 220, height: 220 } },
              decodedText => acceptValue(element, decodedText),
              () => {},
            );
          } catch (error) {
            activeScanners.delete(name);
            showError(element, 'Kamera tidak dapat dibuka. Izinkan akses kamera atau gunakan input manual.');
          }
        };

        document.addEventListener('click', event => {
          const closeButton = event.target.closest('[data-qr-close]');
          const submitButton = event.target.closest('[data-qr-submit]');

          if (closeButton) {
            closeScanner(closeButton.closest('[data-qr-scanner]').dataset.name);
          }

          if (submitButton) {
            const element = submitButton.closest('[data-qr-scanner]');
            acceptValue(element, element.querySelector('[data-qr-manual]').value);
          }
        });

        document.addEventListener('keydown', event => {
          if (event.key !== 'Escape') return;

          document.querySelectorAll('[data-qr-scanner].flex').forEach(element => {
            closeScanner(element.dataset.name);
          });
        });
      })();
    </script>
  @endpush
@endonce
