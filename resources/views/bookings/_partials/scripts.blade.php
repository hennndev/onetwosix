<script>
  const allBookings = @json($bookings);

  function paxEditor(sessionId, initialPax, updateUrl) {
    return {
      editing: false,
      pax: initialPax,
      draft: initialPax ?? '',
      async save() {
        const val = parseInt(this.draft);
        if (!val || val < 1) return;
        const res = await fetch(updateUrl, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          body: JSON.stringify({
            pax: val
          }),
        });
        const data = await res.json();
        if (data.success) {
          this.pax = data.pax;
          this.editing = false;
        }
      },
    };
  }

  function bookingPage(tables, bookedIds, checkedInIds) {
    return {
      selectedCategory: @js(($areas ?? collect())->count() === 1 ? ($areas->first()->id ?? null) : null),
      selectedTableId: null,
      modalOpen: false,
      tables: tables,
      bookedIds: bookedIds,
      checkedInIds: checkedInIds,

      openModal(tableId) {
        this.selectedTableId = tableId;
        const modal = document.getElementById('bookingModal');
        if (modal) {
          const form = document.getElementById('bookingForm');
          if (form) {
            form.action = form.dataset.storeAction || form.action;
            form.reset();
          }

          const methodInput = document.getElementById('formMethod');
          if (methodInput) {
            methodInput.value = 'POST';
          }

          const statusInput = document.getElementById('status');
          if (statusInput) {
            statusInput.value = 'pending';
          }

          const title = document.getElementById('modalTitle');
          if (title) {
            title.textContent = 'Booking Baru';
          }

          if (typeof window.setBookingDownPayment === 'function') {
            window.setBookingDownPayment(false, 0);
          }

          if (typeof window.applyBookingRealtimeDateTimeDefaults === 'function') {
            window.applyBookingRealtimeDateTimeDefaults();
          }

          modal.classList.remove('hidden');
          // Pre-fill table selection if given
          if (tableId) {
            const tableInput = document.getElementById('table_id');
            if (tableInput) {
              tableInput.value = String(tableId);
            }

            const table = this.tables.find(t => String(t.id) === String(tableId));
            if (table) {
              // fire custom event to let the modal update its preview
              document.dispatchEvent(new CustomEvent('table-selected', {
                detail: table
              }));
            }
          }
        }
      },
    };
  }

  function closeModal() {
    document.getElementById('bookingModal')?.classList.add('hidden');
  }

  function editBooking(bookingId) {
    const booking = allBookings.find(b => b.id === bookingId);
    if (!booking) return;
    const modal = document.getElementById('bookingModal');
    const form = document.getElementById('bookingForm');
    document.getElementById('modalTitle').textContent = 'Edit Booking';
    form.action = `/admin/bookings/${bookingId}`;
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('table_id').value = booking.table_id;
    if (typeof window.setBookingSelectedCustomer === 'function') {
      window.setBookingSelectedCustomer(booking.customer_id);
    } else {
      document.getElementById('customer_id').value = booking.customer_id;
    }
    document.getElementById('booking_name').value = booking.booking_name || '';
    document.getElementById('phone').value = booking.customer?.profile?.phone || booking.customer?.phone || '';
    document.getElementById('email').value = booking.customer?.email || '';
    document.getElementById('reservation_date').value = booking.reservation_date;
    document.getElementById('reservation_time').value = booking.reservation_time;
    document.getElementById('status').value = booking.status;
    document.getElementById('note').value = booking.note || '';

    if (typeof window.setBookingDownPayment === 'function') {
      const downPaymentAmount = Number(booking.down_payment_amount || 0);
      window.setBookingDownPayment(downPaymentAmount > 0, downPaymentAmount);
    }

    modal?.classList.remove('hidden');
  }

  function deleteBooking(bookingId) {
    document.getElementById('deleteForm').action = `/admin/bookings/${bookingId}`;
    document.getElementById('deleteModal')?.classList.remove('hidden');
  }

  function closeDeleteModal() {
    document.getElementById('deleteModal')?.classList.add('hidden');
  }

  function openBookingInfoModal(bookingId) {
    const booking = (window.activeBookingsById || {})[bookingId];
    if (!booking) return;

    const formatRupiah = (value) => {
      return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(value || 0));
    };

    const formatReservationDate = (value) => {
      if (!value) {
        return '—';
      }

      const raw = String(value).trim();
      let parsedDate = null;

      if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
        parsedDate = new Date(`${raw}T00:00:00`);
      } else {
        parsedDate = new Date(raw);
      }

      if (Number.isNaN(parsedDate.getTime())) {
        return raw;
      }

      return parsedDate.toLocaleDateString('id-ID', {
        timeZone: 'Asia/Jakarta',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
      });
    };

    document.getElementById('biModalBookingName').textContent = booking.booking_name || '—';
    document.getElementById('biModalCustomerName').textContent = booking.customer_name || '—';
    document.getElementById('biModalCreatedBy').textContent = booking.created_by_name ?
      `${booking.created_by_name} (${booking.created_by_type || '—'})` :
      '—';
    document.getElementById('biModalPhone').textContent = booking.customer_phone || '—';
    document.getElementById('biModalTable').textContent = [booking.table_number, booking.area_name].filter(Boolean).join(' · ') || '—';

    document.getElementById('biModalDate').textContent = formatReservationDate(booking.reservation_date);
    document.getElementById('biModalTime').textContent =
      booking.reservation_time ? booking.reservation_time.substring(0, 5) : '—';
    document.getElementById('biModalDownPayment').textContent = formatRupiah(booking.down_payment_amount || 0);
    document.getElementById('biModalEvent').textContent = booking.event_name ?
      `${booking.event_name}${booking.event_adjustment_label ? ` (${booking.event_adjustment_label})` : ''}` : '—';
    document.getElementById('biModalEventMinimumCharge').textContent = Number(booking.event_adjusted_minimum_charge || 0) > 0 ?
      formatRupiah(booking.event_adjusted_minimum_charge) : '—';

    if (booking.note) {
      document.getElementById('biModalNote').textContent = booking.note;
      document.getElementById('biModalNoteWrap').classList.remove('hidden');
    } else {
      document.getElementById('biModalNoteWrap').classList.add('hidden');
    }

    const statusLabels = {
      confirmed: '<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-blue-100 text-blue-700">Confirmed</span>',
      checked_in: '<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-700">Checked-in</span>',
    };
    document.getElementById('biModalStatusBadge').innerHTML = statusLabels[booking.status] || '';

    const readOnlySection = document.getElementById('biModalReadOnlyFooter');
    readOnlySection.classList.remove('hidden');

    document.getElementById('bookingInfoModal').classList.remove('hidden');
  }

  function closeBookingInfoModal() {
    document.getElementById('bookingInfoModal')?.classList.add('hidden');
  }

  function openStatusModal(bookingId, currentStatus) {
    const modal = document.getElementById('statusModal');
    const form = document.getElementById('statusForm');
    form.action = `/admin/bookings/${bookingId}/status`;
    const radios = form.querySelectorAll('input[name="status"]');
    radios.forEach(r => {
      r.checked = r.value === currentStatus;
    });
    modal?.classList.remove('hidden');
  }

  function closeStatusModal() {
    document.getElementById('statusModal')?.classList.add('hidden');
  }

  function openAssignWaiterModal(bookingId, currentWaiterId) {
    const modal = document.getElementById('assignWaiterModal');
    const form = document.getElementById('assignWaiterForm');
    form.action = `/admin/bookings/${bookingId}/assign-waiter`;
    const select = document.getElementById('assignWaiterSelect');
    if (select) {
      select.value = currentWaiterId ?? '';
    }
    modal?.classList.remove('hidden');
  }

  function closeAssignWaiterModal() {
    document.getElementById('assignWaiterModal')?.classList.add('hidden');
  }

  function openMoveTableModal(bookingId, currentTableNumber) {
    const modal = document.getElementById('moveTableModal');
    const form = document.getElementById('moveTableForm');
    const currentTableEl = document.getElementById('moveTableCurrentTable');

    if (form) {
      form.action = `/admin/bookings/${bookingId}/move-table`;
    }

    if (currentTableEl) {
      currentTableEl.textContent = currentTableNumber || '-';
    }

    const targetSelect = document.getElementById('moveTableTargetSelect');
    if (targetSelect) {
      targetSelect.value = '';
    }

    modal?.classList.remove('hidden');
  }

  function closeMoveTableModal() {
    document.getElementById('moveTableModal')?.classList.add('hidden');
  }

  let pendingStatusForm = null;

  function openStatusConfirmModal(form, title, message, confirmLabel, confirmButtonClasses) {
    if (!form) {
      return;
    }

    pendingStatusForm = form;

    const titleEl = document.getElementById('statusConfirmTitle');
    const messageEl = document.getElementById('statusConfirmMessage');
    const submitButton = document.getElementById('statusConfirmSubmitBtn');

    if (titleEl) {
      titleEl.textContent = title || 'Konfirmasi Status';
    }

    if (messageEl) {
      messageEl.textContent = message || 'Yakin mengubah status booking ini?';
    }

    if (submitButton) {
      submitButton.textContent = confirmLabel || 'Ya';
      submitButton.className = `px-4 py-2 text-white rounded-lg transition ${confirmButtonClasses || 'bg-green-600 hover:bg-green-700'}`;
    }

    document.getElementById('statusConfirmModal')?.classList.remove('hidden');
  }

  function closeStatusConfirmModal() {
    pendingStatusForm = null;
    document.getElementById('statusConfirmModal')?.classList.add('hidden');
  }

  function submitStatusConfirmation() {
    if (!pendingStatusForm) {
      closeStatusConfirmModal();

      return;
    }

    const targetForm = pendingStatusForm;
    closeStatusConfirmModal();
    targetForm.submit();
  }

  function openActiveDeleteModal(bookingId, tableNumber) {
    const modal = document.getElementById('activeDeleteModal');
    const form = document.getElementById('activeDeleteForm');
    const tableLabel = document.getElementById('activeDeleteTableLabel');
    const confirmOneInput = document.getElementById('activeDeleteConfirmOne');
    const confirmTwoInput = document.getElementById('activeDeleteConfirmTwo');

    if (!modal || !form || !tableLabel || !confirmOneInput || !confirmTwoInput) {
      return;
    }

    form.action = `/admin/bookings/${bookingId}`;
    modal.dataset.requiredTableNumber = String(tableNumber || '-').trim();
    tableLabel.textContent = modal.dataset.requiredTableNumber;

    confirmOneInput.value = '';
    confirmTwoInput.value = '';

    updateActiveDeleteSubmitState();
    modal.classList.remove('hidden');
  }

  function closeActiveDeleteModal() {
    document.getElementById('activeDeleteModal')?.classList.add('hidden');
  }

  function updateActiveDeleteSubmitState() {
    const modal = document.getElementById('activeDeleteModal');
    const confirmOneInput = document.getElementById('activeDeleteConfirmOne');
    const confirmTwoInput = document.getElementById('activeDeleteConfirmTwo');
    const submitButton = document.getElementById('activeDeleteSubmitBtn');

    if (!modal || !confirmOneInput || !confirmTwoInput || !submitButton) {
      return;
    }

    const confirmOneValid = confirmOneInput.value.trim().toUpperCase() === 'HAPUS';
    const requiredTableNumber = String(modal.dataset.requiredTableNumber || '').trim().toUpperCase();
    const confirmTwoValid = confirmTwoInput.value.trim().toUpperCase() === requiredTableNumber;

    submitButton.disabled = !(confirmOneValid && confirmTwoValid);
  }

  @php
    $sessionOrdersJson = $activeSessions->keyBy('session_code')->map(function ($s) {
        return [
            'session_id' => $s->id,
            'booking_id' => $s->table_reservation_id,
            'customer' => $s->reservation?->customer?->profile?->name ?? ($s->reservation?->customer?->customerUser?->name ?? ($s->reservation?->customer?->name ?? 'Tamu')),
            'table' => $s->table?->table_number ?? '—',
            'orders' => $s->orders
                ->map(function ($o) {
                    return [
                        'id' => $o->id,
                        'order_number' => $o->order_number,
                        'ordered_at' => $o->ordered_at?->setTimezone('Asia/Jakarta')->format('H:i'),
                        'status' => $o->status,
                        'total' => (float) $o->total,
                        'items' => $o->items
                            ->map(function ($i) {
                                return [
                                    'id' => $i->id,
                                    'item_name' => $i->item_name,
                                    'quantity' => $i->quantity,
                                    'price' => (float) $i->price,
                                    'subtotal' => (float) $i->subtotal,
                                    'status' => $i->status,
                                ];
                            })
                            ->values(),
                    ];
                })
                ->values(),
        ];
    });

    $moveOrderTargetsJson = $activeSessions
        ->map(function ($s) {
            return [
                'session_id' => $s->id,
                'table' => $s->table?->table_number ?? '—',
                'customer' => $s->reservation?->customer?->profile?->name ?? ($s->reservation?->customer?->customerUser?->name ?? ($s->reservation?->customer?->name ?? 'Tamu')),
            ];
        })
        ->values();
  @endphp
  const sessionOrdersData = @json($sessionOrdersJson);
  const moveOrderTargets = @json($moveOrderTargetsJson);
  let currentOrderHistorySession = null;

  function openOrderHistoryModal(sessionCode) {
    const data = sessionOrdersData[sessionCode];
    if (!data) return;
    currentOrderHistorySession = data;

    document.getElementById('orderHistoryTitle').textContent =
      data.customer + ' — Meja ' + data.table;

    const container = document.getElementById('orderHistoryBody');
    const openMoveItemListButton = document.getElementById('openMoveItemListButton');
    const hasMovableItems = (data.orders || []).some(order =>
      (order.items || []).some(item => item.status !== 'cancelled')
    );

    if (openMoveItemListButton) {
      openMoveItemListButton.classList.toggle('hidden', !hasMovableItems);
    }

    if (data.orders.length === 0) {
      container.innerHTML = '<p class="text-sm text-gray-400 text-center py-6">Belum ada order.</p>';
    } else {
      container.innerHTML = data.orders.map(order => {
        const statusClass = {
          pending: 'bg-yellow-100 text-yellow-700',
          processing: 'bg-blue-100 text-blue-700',
          completed: 'bg-green-100 text-green-700',
          cancelled: 'bg-red-100 text-red-700',
        } [order.status] || 'bg-gray-100 text-gray-600';

        const itemRows = order.items.map(item =>
          `<tr class="border-b border-gray-50 last:border-0">
            <td class="py-1.5 pr-3 text-sm text-gray-700">${item.item_name}</td>
            <td class="py-1.5 px-3 text-sm text-gray-500 text-center">${item.quantity}</td>
            <td class="py-1.5 pl-3 text-sm text-gray-500 text-right">Rp ${item.price.toLocaleString('id-ID')}</td>
            <td class="py-1.5 pl-3 text-sm font-medium text-gray-700 text-right">Rp ${item.subtotal.toLocaleString('id-ID')}</td>
            <td class="py-1.5 pl-3 text-right">
              ${order.status === 'pending' && item.status !== 'cancelled' ? `<button type="button"
                      onclick="openDeleteOrderItemModal(${item.id}, '${String(order.order_number || '').replace(/'/g, "&#39;")}', '${String(item.item_name || '').replace(/'/g, "&#39;")}')"
                      class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-semibold rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition">
                Delete
              </button>` : ''}
            </td>
          </tr>`
        ).join('');

        return `<div class="mb-4 border border-gray-200 rounded-lg overflow-hidden">
          <div class="flex items-center justify-between bg-gray-50 px-4 py-2.5">
            <div class="flex items-center gap-2">
              <span class="text-xs font-mono font-semibold text-gray-600">${order.order_number}</span>
              <span class="text-xs text-gray-400">${order.ordered_at ?? ''}</span>
            </div>
            <div class="flex items-center gap-3">
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${statusClass}">${order.status}</span>
              <span class="text-sm font-bold text-gray-900">Rp ${order.total.toLocaleString('id-ID')}</span>
              ${order.status === 'pending' ? `<button type="button"
                      onclick="openCancelOrderModal(${order.id}, '${String(order.order_number || '').replace(/'/g, "&#39;")}')"
                      class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition">
                Cancel
              </button>` : ''}
            </div>
          </div>
          <table class="w-full px-4">
            <thead><tr class="bg-white">
              <th class="px-4 py-1.5 text-left text-xs text-gray-400 font-medium">Item</th>
              <th class="px-3 py-1.5 text-center text-xs text-gray-400 font-medium">Qty</th>
              <th class="px-3 py-1.5 text-right text-xs text-gray-400 font-medium">Harga</th>
              <th class="px-3 py-1.5 text-right text-xs text-gray-400 font-medium">Subtotal</th>
              <th class="px-3 py-1.5 text-right text-xs text-gray-400 font-medium">Aksi</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-50 px-4">${itemRows}</tbody>
          </table>
        </div>`;
      }).join('');
    }

    document.getElementById('orderHistoryModal')?.classList.remove('hidden');
  }

  function closeOrderHistoryModal() {
    document.getElementById('orderHistoryModal')?.classList.add('hidden');
    currentOrderHistorySession = null;
    document.getElementById('openMoveItemListButton')?.classList.add('hidden');
  }

  function openMoveOrderModal() {
    if (!currentOrderHistorySession || !currentOrderHistorySession.booking_id || !currentOrderHistorySession.session_id) {
      return;
    }

    const modal = document.getElementById('moveOrderModal');
    const form = document.getElementById('moveOrderForm');
    const orderInput = document.getElementById('moveOrderId');
    const targetSelect = document.getElementById('moveOrderTargetSessionId');
    const sourceInfo = document.getElementById('moveOrderSourceInfo');
    const orderNumberInfo = document.getElementById('moveOrderNumberInfo');
    const itemsContainer = document.getElementById('moveOrderItemsContainer');

    if (form) {
      form.action = `/admin/bookings/${currentOrderHistorySession.booking_id}/move-order`;
    }

    if (orderInput) {
      orderInput.value = '';
    }

    if (sourceInfo) {
      sourceInfo.textContent = `${currentOrderHistorySession.customer} — Meja ${currentOrderHistorySession.table}`;
    }

    if (orderNumberInfo) {
      orderNumberInfo.textContent = 'Multi-order (item terpilih)';
    }

    if (itemsContainer) {
      const movableItems = (currentOrderHistorySession.orders || []).flatMap(order =>
        (order.items || [])
        .filter(item => item.status !== 'cancelled')
        .map(item => ({
          ...item,
          order_number: order.order_number,
        }))
      );

      if (movableItems.length === 0) {
        itemsContainer.innerHTML = '<p class="px-3 py-2 text-sm text-gray-400">Tidak ada item aktif yang bisa dipindah.</p>';
      } else {
        itemsContainer.innerHTML = movableItems.map(item => {
          const itemStatusClass = {
            pending: 'bg-yellow-100 text-yellow-700',
            preparing: 'bg-blue-100 text-blue-700',
            ready: 'bg-emerald-100 text-emerald-700',
            served: 'bg-green-100 text-green-700',
          } [item.status] || 'bg-gray-100 text-gray-600';

          return `<label class="flex items-start gap-3 px-3 py-2 cursor-pointer hover:bg-gray-50">
            <input type="checkbox"
                   name="order_item_ids[]"
                   value="${item.id}"
                   class="mt-1 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
            <div class="min-w-0 flex-1">
              <div class="text-xs font-mono text-gray-400 mb-0.5">${item.order_number}</div>
              <div class="text-sm font-medium text-gray-800">${item.item_name}</div>
              <div class="text-xs text-gray-500 mt-0.5">${item.quantity}x · Rp ${Number(item.subtotal || 0).toLocaleString('id-ID')}</div>
            </div>
            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-medium ${itemStatusClass}">${item.status}</span>
          </label>`;
        }).join('');
      }
    }

    if (targetSelect) {
      const options = moveOrderTargets
        .filter(target => Number(target.session_id) !== Number(currentOrderHistorySession.session_id))
        .map(target => `<option value="${target.session_id}">Meja ${target.table} — ${target.customer}</option>`)
        .join('');

      targetSelect.innerHTML = `<option value="">Pilih sesi tujuan</option>${options}`;
      targetSelect.value = '';
    }

    modal?.classList.remove('hidden');
  }

  function closeMoveOrderModal() {
    document.getElementById('moveOrderModal')?.classList.add('hidden');

    const itemsContainer = document.getElementById('moveOrderItemsContainer');
    if (itemsContainer) {
      itemsContainer.innerHTML = '<p class="px-3 py-2 text-sm text-gray-400">Belum ada item.</p>';
    }
  }

  function openCancelOrderModal(orderId, orderNumber) {
    if (!currentOrderHistorySession || !currentOrderHistorySession.booking_id) {
      return;
    }

    const form = document.getElementById('cancelOrderForm');
    const orderInput = document.getElementById('cancelOrderId');
    const orderNumberEl = document.getElementById('cancelOrderNumber');
    const authInput = document.getElementById('cancelOrderAuthCode');

    if (form) {
      form.action = `/admin/bookings/${currentOrderHistorySession.booking_id}/cancel-order`;
    }

    if (orderInput) {
      orderInput.value = String(orderId);
    }

    if (orderNumberEl) {
      orderNumberEl.textContent = orderNumber || '-';
    }

    if (authInput) {
      authInput.value = '';
    }

    document.getElementById('cancelOrderModal')?.classList.remove('hidden');
  }

  function closeCancelOrderModal() {
    document.getElementById('cancelOrderModal')?.classList.add('hidden');
  }

  function openDeleteOrderItemModal(orderItemId, orderNumber, itemName) {
    if (!currentOrderHistorySession || !currentOrderHistorySession.booking_id) {
      return;
    }

    const form = document.getElementById('deleteOrderItemForm');
    const itemInput = document.getElementById('deleteOrderItemId');
    const orderEl = document.getElementById('deleteOrderItemOrderNumber');
    const itemEl = document.getElementById('deleteOrderItemName');

    if (form) {
      form.action = `/admin/bookings/${currentOrderHistorySession.booking_id}/delete-order-item`;
    }

    if (itemInput) {
      itemInput.value = String(orderItemId);
    }

    if (orderEl) {
      orderEl.textContent = orderNumber || '-';
    }

    if (itemEl) {
      itemEl.textContent = itemName || '-';
    }

    document.getElementById('deleteOrderItemModal')?.classList.remove('hidden');
  }

  function closeDeleteOrderItemModal() {
    document.getElementById('deleteOrderItemModal')?.classList.add('hidden');
  }

  // History tab client-side filter
  ['searchInput', 'categoryFilter', 'statusFilter'].forEach(id => {
    document.getElementById(id)?.addEventListener(id === 'searchInput' ? 'input' : 'change', filterHistory);
  });

  function filterHistory() {
    const search = (document.getElementById('searchInput')?.value || '').toLowerCase();
    const category = document.getElementById('categoryFilter')?.value || '';
    const status = document.getElementById('statusFilter')?.value || '';
    document.querySelectorAll('.booking-row').forEach(row => {
      const ms = !search || row.textContent.toLowerCase().includes(search);
      const mc = !category || row.dataset.category == category;
      const mst = !status || row.dataset.status == status;
      row.style.display = ms && mc && mst ? '' : 'none';
    });
  }

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      closeModal();
      closeDeleteModal();
      closeStatusModal();
      closeBookingInfoModal();
      if (typeof closeHistoryBookingDetailModal === 'function') closeHistoryBookingDetailModal();
      if (typeof closeHistoryPaymentEditModal === 'function') closeHistoryPaymentEditModal();
      closeMoveTableModal();
      closeStatusConfirmModal();
      closeMoveOrderModal();
      closeCancelOrderModal();
      closeDeleteOrderItemModal();
    }
  });

  document.getElementById('bookingModal')?.addEventListener('click', e => {
    if (e.target === e.currentTarget) closeModal();
  });
  document.getElementById('deleteModal')?.addEventListener('click', e => {
    if (e.target === e.currentTarget) closeDeleteModal();
  });
  document.getElementById('moveTableModal')?.addEventListener('click', e => {
    if (e.target === e.currentTarget) closeMoveTableModal();
  });
  document.getElementById('statusConfirmModal')?.addEventListener('click', e => {
    if (e.target === e.currentTarget) closeStatusConfirmModal();
  });
  document.getElementById('activeDeleteModal')?.addEventListener('click', e => {
    if (e.target === e.currentTarget) closeActiveDeleteModal();
  });
  document.getElementById('statusModal')?.addEventListener('click', e => {
    if (e.target === e.currentTarget) closeStatusModal();
  });
  document.getElementById('bookingInfoModal')?.addEventListener('click', e => {
    if (e.target === e.currentTarget) closeBookingInfoModal();
  });
  document.getElementById('orderHistoryModal')?.addEventListener('click', e => {
    if (e.target === e.currentTarget) closeOrderHistoryModal();
  });
  document.getElementById('moveOrderModal')?.addEventListener('click', e => {
    if (e.target === e.currentTarget) closeMoveOrderModal();
  });
  document.getElementById('deleteOrderItemModal')?.addEventListener('click', e => {
    if (e.target === e.currentTarget) closeDeleteOrderItemModal();
  });
  document.getElementById('cancelOrderModal')?.addEventListener('click', e => {
    if (e.target === e.currentTarget) closeCancelOrderModal();
  });

  document.getElementById('activeDeleteConfirmOne')?.addEventListener('input', updateActiveDeleteSubmitState);
  document.getElementById('activeDeleteConfirmTwo')?.addEventListener('input', updateActiveDeleteSubmitState);
</script>
