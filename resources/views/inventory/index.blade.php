<x-app-layout title="Stok & Inventory">
  @include('inventory._components.main-content')

  <!-- Threshold Bulk Edit Modal -->
  @include('inventory._components.threshold-modal')

  <!-- Sync Result Modal -->
  @include('inventory._components.sync-result-modal')

  <!-- Add/Edit Modal -->
  @include('inventory._components.add-edit-modal')

  <!-- Delete Modal -->
  @include('inventory._components.delete-modal')

  {{-- Detail Group Modal --}}
  @include('inventory._components.detail-group-modal')

  @push('scripts')
    <script>
      const SYNC_ICON_HTML = document.querySelector('[data-sync-icon]').innerHTML;

      function syncFromAccurate() {
        const btn = document.querySelector('[data-sync-btn]');
        const icon = document.querySelector('[data-sync-icon]');
        const text = document.querySelector('[data-sync-text]');

        btn.disabled = true;
        icon.innerHTML = `<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>`;
        text.textContent = 'Syncing...';

        fetch('/admin/accurate/sync/items', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
          })
          .then(res => res.json())
          .then(data => {
            btn.disabled = false;
            icon.innerHTML = SYNC_ICON_HTML;
            text.textContent = 'Sync dari Accurate';
            showSyncResult(data.success, data.message, data.output ?? null);
          })
          .catch(err => {
            btn.disabled = false;
            icon.innerHTML = SYNC_ICON_HTML;
            text.textContent = 'Sync dari Accurate';
            showSyncResult(false, 'Koneksi gagal: ' + err.message, null);
          });
      }

      function showSyncResult(success, message, output) {
        const modal = document.getElementById('syncResultModal');
        const icon = document.getElementById('syncResultIcon');
        const title = document.getElementById('syncResultTitle');
        const msg = document.getElementById('syncResultMessage');
        const pre = document.getElementById('syncResultOutput');

        if (success) {
          icon.className = 'w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 bg-green-100';
          icon.innerHTML = `<svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>`;
          title.textContent = 'Sync Berhasil!';
        } else {
          icon.className = 'w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 bg-red-100';
          icon.innerHTML = `<svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>`;
          title.textContent = 'Sync Gagal';
        }

        msg.textContent = message;

        if (output && output.trim()) {
          pre.textContent = output.trim();
          pre.classList.remove('hidden');
        } else {
          pre.classList.add('hidden');
        }

        modal.classList.remove('hidden');
      }

      function openModal(mode, itemId = null) {
        const modal = document.getElementById('itemModal');
        const form = document.getElementById('itemForm');
        const modalTitle = document.getElementById('modalTitle');
        const formMethod = document.getElementById('formMethod');

        if (mode === 'add') {
          modalTitle.textContent = 'Tambah Produk';
          form.action = '{{ route('admin.inventory.store') }}';
          formMethod.value = 'POST';
          form.reset();
          document.getElementById('is_active').checked = true;
        } else if (mode === 'edit' && itemId) {
          const row = document.querySelector(`.item-row[data-item-id="${itemId}"]`);
          const item = row ? JSON.parse(row.dataset.item) : null;
          if (item) {
            modalTitle.textContent = 'Edit Produk';
            form.action = `/admin/inventory/${itemId}`;
            formMethod.value = 'PUT';

            document.getElementById('name').value = item.name;
            document.getElementById('code').value = item.code;
            document.getElementById('category_type').value = item.category_type;
            document.getElementById('price').value = item.price;
            document.getElementById('stock_quantity').value = item.stock_quantity;
            document.getElementById('threshold').value = item.threshold;
            document.getElementById('unit').value = item.unit;
            document.getElementById('is_active').checked = !!item.is_active;
          }
        }

        modal.classList.remove('hidden');
      }

      function closeModal() {
        document.getElementById('itemModal').classList.add('hidden');
      }

      function editItem(itemId) {
        openModal('edit', itemId);
      }

      function deleteItem(itemId) {
        const form = document.getElementById('deleteForm');
        form.action = `/admin/inventory/${itemId}`;
        document.getElementById('deleteModal').classList.remove('hidden');
      }

      function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
      }

      function openThresholdModal() {
        document.getElementById('thresholdSearch').value = '';
        filterThresholdList();
        document.getElementById('thresholdModal').classList.remove('hidden');
      }

      function closeThresholdModal() {
        document.getElementById('thresholdModal').classList.add('hidden');
      }

      function filterThresholdList() {
        const q = document.getElementById('thresholdSearch').value.toLowerCase();
        document.querySelectorAll('.threshold-item').forEach(function(el) {
          el.style.display = el.dataset.name.includes(q) ? '' : 'none';
        });
      }

      function toggleStockFilter() {
        const input = document.getElementById('stockFilterInput');
        input.value = input.value === 'low' ? '' : 'low';
        document.getElementById('filterForm').submit();
      }

      // Search: submit form dengan debounce agar server-side filter jalan
      let searchDebounce;
      document.getElementById('searchInput').addEventListener('input', function() {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => document.getElementById('filterForm').submit(), 400);
      });

      // Close modals on Escape key
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          closeModal();
          closeDeleteModal();
          closeThresholdModal();
        }
      });

      // Close modals on outside click
      document.getElementById('itemModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
      });

      document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeleteModal();
      });

      document.getElementById('thresholdModal').addEventListener('click', function(e) {
        if (e.target === this) closeThresholdModal();
      });
      document.getElementById('detailGroupModal').addEventListener('click', function(e) {
        if (e.target === this) closeDetailModal();
      });

      function fetchDetail(itemId, itemName) {
        document.getElementById('detailGroupModal').classList.remove('hidden');
        document.getElementById('detailGroupItemName').textContent = itemName;
        document.getElementById('detailGroupLoading').classList.remove('hidden');
        document.getElementById('detailGroupEmpty').classList.add('hidden');
        document.getElementById('detailGroupTable').classList.add('hidden');

        fetch(`/admin/inventory/${itemId}/detail`)
          .then(r => r.json())
          .then(data => {
            document.getElementById('detailGroupLoading').classList.add('hidden');
            if (!data.success || !data.detail_group.length) {
              document.getElementById('detailGroupEmpty').classList.remove('hidden');
              return;
            }
            const tbody = document.getElementById('detailGroupBody');
            tbody.innerHTML = data.detail_group.map((g, i) => `
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 text-gray-500">${g.seq ?? i + 1}</td>
                <td class="px-4 py-2 font-medium text-gray-800">${g.detail_name ?? '-'}</td>
                <td class="px-4 py-2 text-right text-gray-700">${g.quantity}</td>
                <td class="px-4 py-2 text-gray-600">${g.unit ?? '-'}</td>
              </tr>
            `).join('');
            document.getElementById('detailGroupTable').classList.remove('hidden');
          })
          .catch(() => {
            document.getElementById('detailGroupLoading').classList.add('hidden');
            document.getElementById('detailGroupEmpty').textContent = 'Gagal mengambil data.';
            document.getElementById('detailGroupEmpty').classList.remove('hidden');
          });
      }

      function closeDetailModal() {
        document.getElementById('detailGroupModal').classList.add('hidden');
      }
    </script>
  @endpush
</x-app-layout>
