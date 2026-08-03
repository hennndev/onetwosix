<x-app-layout title="Table Scanner">
  <div class="p-6">
    @if (session('success'))
      <div class="mb-4 px-4 py-3 bg-green-100 border border-green-400 text-green-700 rounded-lg">
        {{ session('success') }}
      </div>
    @endif

    @if (session('error'))
      <div class="mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded-lg">
        {{ session('error') }}
      </div>
    @endif

    <!-- Header -->
    <div class="flex items-center gap-3 mb-6">
      <div class="w-12 h-12 bg-slate-800 rounded-xl flex items-center justify-center">
        <svg class="w-6 h-6 text-white"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
        </svg>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Table Scanner</h1>
        <p class="text-sm text-gray-500">Scan QR code meja untuk melihat info dan assign customer</p>
      </div>
    </div>

    <!-- Main Content -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Scanner Section -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center gap-2 mb-4">
          <svg class="w-5 h-5 text-slate-800"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
          </svg>
          <h2 class="text-lg font-semibold text-gray-900">QR Code Scanner</h2>
        </div>

        <!-- Camera/Scanner Area -->
        <div id="scannerContainer"
             class="relative bg-slate-900 rounded-lg overflow-hidden mb-4"
             style="height: 400px;">
          <video id="qrVideo"
                 class="w-full h-full object-cover hidden"></video>

          <!-- Placeholder when camera is not active -->
          <div id="cameraPlaceholder"
               class="absolute inset-0 flex flex-col items-center justify-center text-slate-400">
            <svg class="w-24 h-24 mb-4"
                 fill="currentColor"
                 viewBox="0 0 20 20">
              <path fill-rule="evenodd"
                    d="M3 4a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm2 2V5h1v1H5zM3 13a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1v-3zm2 2v-1h1v1H5zM13 3a1 1 0 00-1 1v3a1 1 0 001 1h3a1 1 0 001-1V4a1 1 0 00-1-1h-3zm1 2v1h1V5h-1z"
                    clip-rule="evenodd" />
              <path d="M11 4a1 1 0 10-2 0v1a1 1 0 002 0V4zM10 7a1 1 0 011 1v1h2a1 1 0 110 2h-3a1 1 0 01-1-1V8a1 1 0 011-1zM16 9a1 1 0 100 2 1 1 0 000-2zM9 13a1 1 0 011-1h1a1 1 0 110 2v2a1 1 0 11-2 0v-3zM7 11a1 1 0 100-2H4a1 1 0 100 2h3zM17 13a1 1 0 01-1 1h-2a1 1 0 110-2h2a1 1 0 011 1zM16 17a1 1 0 100-2h-3a1 1 0 100 2h3z" />
            </svg>
            <p class="text-sm font-medium">Kamera Tidak Aktif</p>
            <p class="text-xs mt-1">Tekan tombol untuk mulai scan</p>
          </div>

          <!-- Scanning indicator -->
          <div id="scanningIndicator"
               class="hidden absolute inset-0">
            <div class="absolute inset-0 border-2 border-green-500 rounded-lg animate-pulse"></div>
            <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-48 h-48">
              <div class="w-full h-full border-4 border-green-500 rounded-lg"
                   style="box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.2);"></div>
            </div>
          </div>
        </div>

        <!-- Scanner Controls -->
        <button id="startScanBtn"
                onclick="startScanner()"
                class="w-full px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center justify-center gap-2">
          <svg class="w-5 h-5"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          Mulai Scan
        </button>
        <button id="stopScanBtn"
                onclick="stopScanner()"
                class="hidden w-full px-4 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition flex items-center justify-center gap-2">
          <svg class="w-5 h-5"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12" />
          </svg>
          Stop Scan
        </button>

        <!-- Manual Input -->
        <div class="mt-4 pt-4 border-t border-gray-200">
          <label class="block text-sm font-medium text-gray-700 mb-2">Atau masukkan QR Code manual:</label>
          <div class="flex gap-2">
            <input type="text"
                   id="manualQRInput"
                   placeholder="Masukkan QR code..."
                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <button onclick="manualScan()"
                    class="px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-900 transition">
              Cek
            </button>
          </div>
        </div>
      </div>

      <!-- Info Section -->
      <div class="bg-gradient-to-br from-slate-800 to-slate-900 rounded-xl shadow-sm p-6 text-white">
        <h2 class="text-lg font-semibold mb-4">Informasi Meja</h2>

        <!-- Empty State -->
        <div id="emptyState"
             class="flex flex-col items-center justify-center py-12">
          <svg class="w-24 h-24 text-slate-600 mb-4"
               fill="currentColor"
               viewBox="0 0 20 20">
            <path fill-rule="evenodd"
                  d="M3 4a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm2 2V5h1v1H5zM3 13a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1v-3zm2 2v-1h1v1H5zM13 3a1 1 0 00-1 1v3a1 1 0 001 1h3a1 1 0 001-1V4a1 1 0 00-1-1h-3zm1 2v1h1V5h-1z"
                  clip-rule="evenodd" />
            <path d="M11 4a1 1 0 10-2 0v1a1 1 0 002 0V4zM10 7a1 1 0 011 1v1h2a1 1 0 110 2h-3a1 1 0 01-1-1V8a1 1 0 011-1zM16 9a1 1 0 100 2 1 1 0 000-2zM9 13a1 1 0 011-1h1a1 1 0 110 2v2a1 1 0 11-2 0v-3zM7 11a1 1 0 100-2H4a1 1 0 100 2h3zM17 13a1 1 0 01-1 1h-2a1 1 0 110-2h2a1 1 0 011 1zM16 17a1 1 0 100-2h-3a1 1 0 100 2h3z" />
          </svg>
          <p class="text-slate-400 text-center">Scan QR code untuk melihat info meja</p>
        </div>

        <!-- Table Info (hidden by default) -->
        <div id="tableInfo"
             class="hidden space-y-4">
          <!-- Table Details -->
          <div class="bg-slate-700/50 rounded-lg p-4">
            <div class="flex items-center justify-between mb-3">
              <h3 class="text-lg font-semibold"
                  id="infoTableNumber">-</h3>
              <span class="px-3 py-1 text-xs font-medium rounded-full"
                    id="infoTableStatus">-</span>
            </div>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-slate-400">Area:</span>
                <span class="font-medium"
                      id="infoArea">-</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-400">Kapasitas:</span>
                <span class="font-medium"
                      id="infoCapacity">-</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-400">Minimum Charge:</span>
                <span class="font-medium text-orange-400"
                      id="infoMinCharge">-</span>
              </div>
            </div>
          </div>

          <!-- Reservation Info -->
          <div id="reservationInfo"
               class="hidden bg-blue-500/20 border border-blue-400/30 rounded-lg p-4">
            <h4 class="font-semibold mb-3 flex items-center gap-2">
              <svg class="w-5 h-5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Reservasi Aktif
            </h4>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-slate-300">Customer:</span>
                <span class="font-medium"
                      id="infoCustomerName">-</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-300">Telepon:</span>
                <span class="font-medium"
                      id="infoCustomerPhone">-</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-300">Tanggal:</span>
                <span class="font-medium"
                      id="infoResDate">-</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-300">Waktu:</span>
                <span class="font-medium"
                      id="infoResTime">-</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-300">Status:</span>
                <span class="font-medium"
                      id="infoResStatus">-</span>
              </div>
            </div>
          </div>

          <!-- No Reservation -->
          <div id="noReservation"
               class="hidden bg-slate-700/30 border border-slate-600 rounded-lg p-4 text-center">
            <svg class="w-12 h-12 text-slate-500 mx-auto mb-2"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-slate-400 text-sm">Tidak ada reservasi aktif</p>
          </div>

          <!-- Actions -->
          <div class="pt-4 border-t border-slate-700 space-y-2">
            <button type="button"
                    id="checkInBtn"
                    onclick="handleCheckIn()"
                    class="hidden w-full px-4 py-2 bg-blue-600 text-white text-center rounded-lg hover:bg-blue-700 transition">
              <span class="flex items-center justify-center gap-2">
                <svg class="w-5 h-5"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Check In Customer
              </span>
            </button>
            <a href="#"
               id="assignCustomerBtn"
               class="hidden w-full px-4 py-2 bg-green-600 text-white text-center rounded-lg hover:bg-green-700 transition block">
              Assign Customer
            </a>
            <a href="#"
               id="viewDetailsBtn"
               class="block w-full px-4 py-2 bg-slate-700 text-white text-center rounded-lg hover:bg-slate-600 transition">
              Lihat Detail Lengkap
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Include Check-in QR Modal -->
  @include('table-scanner._components.checkin-qr-modal')

  @push('scripts')
    <!-- QR Code Generator Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <!-- QR Scanner Library -->
    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
      let html5QrCode;
      let isScanning = false;
      let currentReservationId = null;

      function startScanner() {
        const videoElement = document.getElementById('qrVideo');
        const placeholder = document.getElementById('cameraPlaceholder');
        const scanningIndicator = document.getElementById('scanningIndicator');
        const startBtn = document.getElementById('startScanBtn');
        const stopBtn = document.getElementById('stopScanBtn');

        html5QrCode = new Html5Qrcode("scannerContainer");

        const config = {
          fps: 10,
          qrbox: {
            width: 250,
            height: 250
          }
        };

        html5QrCode.start({
            facingMode: "environment"
          },
          config,
          onScanSuccess,
          onScanError
        ).then(() => {
          isScanning = true;
          placeholder.classList.add('hidden');
          videoElement.classList.remove('hidden');
          scanningIndicator.classList.remove('hidden');
          startBtn.classList.add('hidden');
          stopBtn.classList.remove('hidden');
        }).catch(err => {
          console.error("Unable to start scanning", err);
          alert("Tidak dapat mengakses kamera. Pastikan browser memiliki izin kamera.");
        });
      }

      function stopScanner() {
        if (html5QrCode && isScanning) {
          html5QrCode.stop().then(() => {
            isScanning = false;
            const videoElement = document.getElementById('qrVideo');
            const placeholder = document.getElementById('cameraPlaceholder');
            const scanningIndicator = document.getElementById('scanningIndicator');
            const startBtn = document.getElementById('startScanBtn');
            const stopBtn = document.getElementById('stopScanBtn');

            placeholder.classList.remove('hidden');
            videoElement.classList.add('hidden');
            scanningIndicator.classList.add('hidden');
            startBtn.classList.remove('hidden');
            stopBtn.classList.add('hidden');
          }).catch(err => {
            console.error("Error stopping scanner", err);
          });
        }
      }

      function onScanSuccess(decodedText, decodedResult) {
        // Stop scanner after successful scan
        stopScanner();

        // Process the scanned QR code
        processQRCode(decodedText);
      }

      function onScanError(errorMessage) {
        // Handle scan error silently
      }

      function manualScan() {
        const qrCode = document.getElementById('manualQRInput').value.trim();
        if (qrCode) {
          processQRCode(qrCode);
        } else {
          alert('Masukkan QR code terlebih dahulu');
        }
      }

      function processQRCode(qrCode) {
        // Show loading state
        const emptyState = document.getElementById('emptyState');
        const tableInfo = document.getElementById('tableInfo');

        emptyState.innerHTML = '<div class="text-center"><div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-white"></div><p class="mt-2 text-slate-400">Memproses...</p></div>';

        // Send request to server
        fetch('{{ route('admin.table-scanner.scan') }}', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
              qr_code: qrCode
            })
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              displayTableInfo(data.data);
            } else {
              alert(data.message || 'QR Code tidak valid');
              resetEmptyState();
            }
          })
          .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat memproses QR code');
            resetEmptyState();
          });
      }

      function displayTableInfo(data) {
        const emptyState = document.getElementById('emptyState');
        const tableInfo = document.getElementById('tableInfo');
        const table = data.table;
        const reservation = data.reservation;

        // Hide empty state, show table info
        emptyState.classList.add('hidden');
        tableInfo.classList.remove('hidden');

        // Fill table details
        document.getElementById('infoTableNumber').textContent = table.table_number;
        document.getElementById('infoArea').textContent = table.area.name;
        document.getElementById('infoCapacity').textContent = table.capacity + ' orang';
        document.getElementById('infoMinCharge').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(table.minimum_charge) + 'jt';

        // Status badge
        const statusBadge = document.getElementById('infoTableStatus');
        if (table.status === 'available') {
          statusBadge.className = 'px-3 py-1 text-xs font-medium rounded-full bg-green-500 text-white';
          statusBadge.textContent = 'Tersedia';
        } else if (table.status === 'reserved') {
          statusBadge.className = 'px-3 py-1 text-xs font-medium rounded-full bg-yellow-500 text-white';
          statusBadge.textContent = 'Reserved';
        } else if (table.status === 'occupied') {
          statusBadge.className = 'px-3 py-1 text-xs font-medium rounded-full bg-red-500 text-white';
          statusBadge.textContent = 'Occupied';
        } else {
          statusBadge.className = 'px-3 py-1 text-xs font-medium rounded-full bg-gray-500 text-white';
          statusBadge.textContent = 'Maintenance';
        }

        // Reservation info
        const reservationInfo = document.getElementById('reservationInfo');
        const noReservation = document.getElementById('noReservation');
        const assignBtn = document.getElementById('assignCustomerBtn');
        const checkInBtn = document.getElementById('checkInBtn');

        if (reservation) {
          reservationInfo.classList.remove('hidden');
          noReservation.classList.add('hidden');
          assignBtn.classList.add('hidden');

          // Store reservation ID for check-in
          currentReservationId = reservation.id;

          // Show check-in button if status is confirmed
          if (reservation.status === 'confirmed') {
            checkInBtn.classList.remove('hidden');
          } else {
            checkInBtn.classList.add('hidden');
          }

          document.getElementById('infoCustomerName').textContent = reservation.customer.name;
          document.getElementById('infoCustomerPhone').textContent = reservation.customer.profile?.phone || '-';

          const reservationDateRaw = String(reservation.reservation_date || '').trim();
          const resDate = /^\d{4}-\d{2}-\d{2}$/.test(reservationDateRaw) ?
            new Date(`${reservationDateRaw}T00:00:00`) :
            new Date(reservationDateRaw);
          document.getElementById('infoResDate').textContent = Number.isNaN(resDate.getTime()) ?
            reservationDateRaw :
            resDate.toLocaleDateString('id-ID', {
              timeZone: 'Asia/Jakarta',
              day: '2-digit',
              month: 'long',
              year: 'numeric'
            });
          document.getElementById('infoResTime').textContent = reservation.reservation_time.substring(0, 5);

          const statusText = reservation.status === 'confirmed' ? 'Confirmed' : 'Checked-in';
          document.getElementById('infoResStatus').textContent = statusText;
        } else {
          reservationInfo.classList.add('hidden');
          noReservation.classList.remove('hidden');
          checkInBtn.classList.add('hidden');
          currentReservationId = null;

          // Show assign button only if table is available
          if (table.status === 'available') {
            assignBtn.classList.remove('hidden');
            assignBtn.href = '/admin/bookings?table_id=' + table.id;
          } else {
            assignBtn.classList.add('hidden');
          }
        }

        // Update action buttons
        document.getElementById('viewDetailsBtn').href = '/admin/tables';
      }

      function resetEmptyState() {
        const emptyState = document.getElementById('emptyState');
        const tableInfo = document.getElementById('tableInfo');

        tableInfo.classList.add('hidden');
        emptyState.classList.remove('hidden');
        emptyState.innerHTML = `
          <svg class="w-24 h-24 text-slate-600 mb-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm2 2V5h1v1H5zM3 13a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1v-3zm2 2v-1h1v1H5zM13 3a1 1 0 00-1 1v3a1 1 0 001 1h3a1 1 0 001-1V4a1 1 0 00-1-1h-3zm1 2v1h1V5h-1z" clip-rule="evenodd" />
            <path d="M11 4a1 1 0 10-2 0v1a1 1 0 002 0V4zM10 7a1 1 0 011 1v1h2a1 1 0 110 2h-3a1 1 0 01-1-1V8a1 1 0 011-1zM16 9a1 1 0 100 2 1 1 0 000-2zM9 13a1 1 0 011-1h1a1 1 0 110 2v2a1 1 0 11-2 0v-3zM7 11a1 1 0 100-2H4a1 1 0 100 2h3zM17 13a1 1 0 01-1 1h-2a1 1 0 110-2h2a1 1 0 011 1zM16 17a1 1 0 100-2h-3a1 1 0 100 2h3z" />
          </svg>
          <p class="text-slate-400 text-center">Scan QR code untuk melihat info meja</p>
        `;
      }

      // Allow Enter key to submit manual input
      document.getElementById('manualQRInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
          manualScan();
        }
      });

      // Handle check-in button click
      function handleCheckIn() {
        if (!currentReservationId) {
          alert('Reservation ID tidak ditemukan');
          return;
        }

        // Show loading
        const checkInBtn = document.getElementById('checkInBtn');
        const originalHTML = checkInBtn.innerHTML;
        checkInBtn.disabled = true;
        checkInBtn.innerHTML = '<span class="flex items-center justify-center gap-2"><div class="inline-block animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div> Memproses...</span>';

        // Generate check-in QR
        fetch('{{ route('admin.table-scanner.generate-checkin-qr') }}', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
              reservation_id: currentReservationId
            })
          })
          .then(response => response.json())
          .then(data => {
            checkInBtn.disabled = false;
            checkInBtn.innerHTML = originalHTML;

            if (data.success) {
              // Open modal with QR code
              window.dispatchEvent(new CustomEvent('open-checkin-modal', {
                detail: data.data
              }));
            } else {
              alert(data.message || 'Gagal generate QR Code');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            checkInBtn.disabled = false;
            checkInBtn.innerHTML = originalHTML;
            alert('Terjadi kesalahan saat generate QR Code');
          });
      }
    </script>
  @endpush
</x-app-layout>
