<!-- Settle Debt / Partial Payment Modal -->
<div id="settlePaymentModal"
     class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-black/50 p-4">
  <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl"
       onclick="event.stopPropagation()">

    <!-- Header -->
    <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center">
          <svg class="w-5 h-5 text-orange-600"
               fill="none"
               stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
        </div>
        <div>
          <h3 class="font-bold text-gray-900 text-base">Pelunasan Sisa Hutang</h3>
          <p id="spModalSubtitle" class="text-xs text-gray-500 mt-0.5">-</p>
        </div>
      </div>
      <button onclick="closeSettlePaymentModal()"
              class="text-gray-400 hover:text-gray-600 transition">
        <svg class="w-5 h-5"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- Summary Box -->
    <div class="bg-gray-50 rounded-xl p-4 space-y-2 text-sm mb-4">
      <div class="flex justify-between text-gray-600">
        <span>Total Bill</span>
        <span id="spGrandTotal" class="font-semibold text-gray-900">Rp 0</span>
      </div>
      <div class="flex justify-between text-gray-600">
        <span>Telah Dibayar</span>
        <span id="spPaidAmount" class="font-semibold text-green-600">Rp 0</span>
      </div>
      <div class="border-t border-gray-200 pt-2 flex justify-between font-bold text-red-600 text-base">
        <span>Sisa Hutang</span>
        <span id="spRemainingBalance">Rp 0</span>
      </div>
    </div>

    <!-- Form -->
    <form id="settlePaymentForm" onsubmit="submitSettlePayment(event)" class="space-y-4">
      <div>
        <label for="sp_amount_paid_display" class="block text-xs font-semibold text-gray-700 mb-1">Nominal Pelunasan Saat Ini</label>
        <input id="sp_amount_paid_display"
               type="text"
               inputmode="numeric"
               required
               oninput="onSettleAmountInput(this.value)"
               class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm font-semibold text-gray-900 focus:ring-2 focus:ring-orange-500 focus:border-transparent">
        <input id="sp_amount_paid" type="hidden" name="amount_paid">
      </div>

      <div>
        <label class="block text-xs font-semibold text-gray-700 mb-1">Metode Pembayaran</label>
        <select id="sp_payment_method"
                onchange="toggleSettleRefInput()"
                class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent bg-white">
          <option value="cash">Tunai (Cash)</option>
          <option value="kredit">Kredit</option>
          <option value="debit">Debit</option>
          <option value="qris">QRIS</option>
          <option value="transfer">Transfer</option>
        </select>
      </div>

      <div id="spRefBlock" class="hidden">
        <label for="sp_payment_reference_number" class="block text-xs font-semibold text-gray-700 mb-1">Nomor Referensi (Wajib untuk Non-Cash)</label>
        <input id="sp_payment_reference_number"
               type="text"
               placeholder="Nomor kartu / approval / referensi"
               class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent">
      </div>

      <div>
        <label for="sp_notes" class="block text-xs font-semibold text-gray-700 mb-1">Catatan (Opsional)</label>
        <input id="sp_notes"
               type="text"
               placeholder="Contoh: Pelunasan sisa via Transfer"
               class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent">
      </div>

      <div class="flex gap-3 pt-2">
        <button type="button"
                onclick="closeSettlePaymentModal()"
                class="flex-1 px-4 py-2.5 border border-gray-200 text-gray-600 rounded-xl hover:bg-gray-50 font-medium text-sm transition">
          Batal
        </button>
        <button type="submit"
                id="spSubmitBtn"
                class="flex-1 px-4 py-2.5 bg-orange-600 text-white rounded-xl hover:bg-orange-500 font-semibold text-sm transition flex items-center justify-center gap-2">
          Simpan Pelunasan
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  let spBookingId = null;
  let spMaxRemaining = 0;

  function openSettlePaymentModal(bookingId, customerName, tableNo, grandTotal, paidAmount, remainingBalance) {
    spBookingId = bookingId;
    spMaxRemaining = Number(remainingBalance || 0);

    const fmt = v => 'Rp ' + new Intl.NumberFormat('id-ID').format(v || 0);

    document.getElementById('spModalSubtitle').textContent = `${customerName} — Meja ${tableNo}`;
    document.getElementById('spGrandTotal').textContent = fmt(grandTotal);
    document.getElementById('spPaidAmount').textContent = fmt(paidAmount);
    document.getElementById('spRemainingBalance').textContent = fmt(remainingBalance);

    document.getElementById('sp_amount_paid').value = String(spMaxRemaining);
    document.getElementById('sp_amount_paid_display').value = fmt(spMaxRemaining);
    document.getElementById('sp_payment_method').value = 'cash';
    document.getElementById('sp_payment_reference_number').value = '';
    document.getElementById('sp_notes').value = '';

    toggleSettleRefInput();
    document.getElementById('settlePaymentModal').classList.remove('hidden');
  }

  function closeSettlePaymentModal() {
    document.getElementById('settlePaymentModal').classList.add('hidden');
    spBookingId = null;
    spMaxRemaining = 0;
  }

  function onSettleAmountInput(val) {
    const digits = String(val || '').replace(/[^0-9]/g, '');
    let num = digits ? Number(digits) : 0;
    if (num > spMaxRemaining) {
      num = spMaxRemaining;
    }
    document.getElementById('sp_amount_paid').value = String(num);
    document.getElementById('sp_amount_paid_display').value = 'Rp ' + new Intl.NumberFormat('id-ID').format(num);
  }

  function toggleSettleRefInput() {
    const method = document.getElementById('sp_payment_method').value;
    const refBlock = document.getElementById('spRefBlock');
    if (method === 'cash') {
      refBlock.classList.add('hidden');
    } else {
      refBlock.classList.remove('hidden');
    }
  }

  async function submitSettlePayment(e) {
    e.preventDefault();
    if (!spBookingId) return;

    const amountPaid = Number(document.getElementById('sp_amount_paid').value || 0);
    if (amountPaid <= 0) {
      alert('Nominal pelunasan harus lebih besar dari 0.');
      return;
    }

    const method = document.getElementById('sp_payment_method').value;
    const refNo = document.getElementById('sp_payment_reference_number').value.trim();
    if (method !== 'cash' && !refNo) {
      alert('Nomor referensi wajib diisi untuk pembayaran non-cash.');
      return;
    }

    const btn = document.getElementById('spSubmitBtn');
    btn.disabled = true;
    btn.textContent = 'Memproses...';

    try {
      const response = await fetch(`/admin/bookings/${spBookingId}/settle-payment`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          amount_paid: amountPaid,
          payment_method: method,
          payment_reference_number: refNo || null,
          notes: document.getElementById('sp_notes').value.trim() || null,
        }),
      });

      const data = await response.json();
      if (data.success) {
        alert(data.message || 'Pelunasan berhasil dicatat!');
        window.open(`/admin/bookings/${spBookingId}/receipt`, '_blank', 'width=450,height=650');
        window.location.reload();
      } else {
        alert(data.message || 'Gagal melakukan pelunasan.');
      }
    } catch (err) {
      alert('Error: ' + err.message);
    } finally {
      btn.disabled = false;
      btn.textContent = 'Simpan Pelunasan';
    }
  }
</script>
