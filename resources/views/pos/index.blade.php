<x-app-layout title="Point of Sale (POS)">
  <div class="flex w-full h-[calc(100vh-6rem)]"
       x-data="posApp"
       x-cloak
       @walk-in-proceed.window="receiveWalkIn($event.detail)"
       @pos-toast.window="showToastMessage($event.detail.message, $event.detail.type)">

    <!-- Products Section -->
    @include('pos._components.product-section')

    <!-- Cart Panel -->
    @include('pos._components.cart-panel')

    <!-- MODAL: Pilih Pelanggan -->
    @include('pos._components.choose-customer-modal')

    <!-- MODAL: Pembayaran -->
    @include('pos._components.payment-modal')

    <!-- MODAL: Konfirmasi Transaksi -->
    @include('pos._components.transaction-confirmation-modal')

    <!-- Toast -->
    @include('pos._components.pos-toast')

    <!-- MODAL: Cetak Struk -->
    @include('pos._components.struk-print-modal')

    {{-- Auth Modal for Reprint --}}
    @include('pos._components.auth-modal-reprint')

    <script>
      window.posUserId = {{ auth()->id() }};
      const posRoutes = {
        selectCounter: "{{ route('admin.pos.select-counter') }}",
        addToCart: "{{ route('admin.pos.add-to-cart', '__PRODUCT_ID__') }}",
        updateCart: "{{ route('admin.pos.update-cart', '__PRODUCT_ID__') }}",
        removeFromCart: "{{ route('admin.pos.remove-from-cart', '__PRODUCT_ID__') }}",
        clearCart: "{{ route('admin.pos.clear-cart') }}",
        previewCheckoutAvailability: "{{ route('admin.pos.preview-checkout-availability') }}",
        checkout: "{{ route('admin.pos.checkout') }}",
        approveDiscount: "{{ route('admin.pos.discount-approvals.store') }}",
        printReceiptBase: "{{ url('admin/pos/print-receipt') }}",
        printWalkInDraftReceipt: "{{ route('admin.pos.print-walk-in-draft-receipt') }}",
        verifyAuthCode: "{{ route('admin.settings.daily-auth-code.verify') }}",
        sendAuthCodeEmail: "{{ route('admin.settings.daily-auth-code.send-email') }}",
        walkInSearchCustomers: "{{ route('admin.pos.walk-in.search-customers') }}",
        walkInCreateCustomer: "{{ route('admin.pos.walk-in.create-customer') }}",
        receiptBase: "{{ url('admin/pos/orders') }}",
      };
      const posAvailableTables = @json($availableTables);
      const posInitialData = {
        cart: {!! json_encode($cartItems->values()) !!},
        cartTotal: {{ $cartTotal }},
        cashier: {!! json_encode(auth()->user()?->name ?? 'Admin') !!},
        currentCounter: {!! json_encode($currentCounter ?? '') !!},
        kitchenUrl: "{{ route('admin.kitchen.index') }}",
        barUrl: "{{ route('admin.bar.index') }}",
        checkerUrl: "{{ route('admin.transaction-checker.index') }}",
        canChooseChecker: {{ (int) ((bool) ($generalSettings->can_choose_checker ?? false)) }},
      };
      const posWaiters = @json($waiters);
      const posCharges = {
        taxPercentage: {{ (float) ($generalSettings->tax_percentage ?? 0) }},
        serviceChargePercentage: {{ (float) ($generalSettings->service_charge_percentage ?? 0) }},
      };
    </script>

    {{-- Walk-in checkout: registered as a separate Alpine component. --}}
    {{-- Source of truth: resources/js/pos-walk-in.js --}}
    <script>
      document.addEventListener('alpine:init', () => {
        Alpine.data('walkInCheckout', () => ({
          walkInSearch: '',
          walkInFoundCustomers: [],
          walkInSearching: false,
          walkInSelected: null,
          walkInNewName: '',
          walkInNewPhone: '',
          walkInCreating: false,
          walkInCreateMode: false,
          walkInSelectedTable: null,

          reset() {
            this.walkInSearch = '';
            this.walkInFoundCustomers = [];
            this.walkInSearching = false;
            this.walkInSelected = null;
            this.walkInNewName = '';
            this.walkInNewPhone = '';
            this.walkInCreating = false;
            this.walkInCreateMode = false;
            this.walkInSelectedTable = null;
          },

          async searchWalkInCustomers() {
            if (this.walkInSearch.length < 2) {
              this.walkInFoundCustomers = [];
              return;
            }
            this.walkInSearching = true;
            try {
              const res = await fetch(
                posRoutes.walkInSearchCustomers + '?q=' + encodeURIComponent(this.walkInSearch), {
                  headers: {
                    Accept: 'application/json'
                  }
                },
              );
              const data = await res.json();
              this.walkInFoundCustomers = data.customers ?? [];
            } catch (e) {
              this.walkInFoundCustomers = [];
            } finally {
              this.walkInSearching = false;
            }
          },

          selectWalkInCustomer(c) {
            this.walkInSelected = c;
            this.walkInFoundCustomers = [];
            this.walkInSearch = '';
          },

          async createWalkInCustomer() {
            if (!this.walkInNewName.trim() || this.walkInCreating) {
              return;
            }
            this.walkInCreating = true;
            try {
              const res = await fetch(posRoutes.walkInCreateCustomer, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                  Accept: 'application/json',
                },
                body: JSON.stringify({
                  name: this.walkInNewName,
                  phone: this.walkInNewPhone
                }),
              });
              const data = await res.json();
              if (data.success) {
                this.walkInSelected = data.customer;
                this.walkInCreateMode = false;
                this.walkInNewName = '';
                this.walkInNewPhone = '';
              } else {
                this.toast(data.message || 'Gagal membuat customer', 'error');
              }
            } catch (e) {
              this.toast('Terjadi kesalahan. Coba lagi.', 'error');
            } finally {
              this.walkInCreating = false;
            }
          },

          selectWalkInTable(t) {
            this.walkInSelectedTable = t;
          },

          proceedToCheckout() {
            if (!this.walkInSelected) {
              return;
            }
            this.$dispatch('walk-in-proceed', {
              id: this.walkInSelected.id,
              name: this.walkInSelected.name,
              phone: this.walkInSelected.phone || '',
            });
          },

          toast(message, type = 'success') {
            this.$dispatch('pos-toast', {
              message,
              type
            });
          },
        }));
      });
    </script>

    <script>
      document.addEventListener('alpine:init', () => {
        Alpine.data('posApp', () => ({
          cart: posInitialData.cart,
          cartTotal: posInitialData.cartTotal,
          isProcessing: false,
          checkoutToken: null,
          showHistoryModal: false,
          recentOrders: [],
          historyLoading: false,
          showCustomerTypeModal: false,
          showCheckoutModal: false,
          isRequestingAuthCodeEmail: false,
          showConfirmModal: false,
          showReceiptModal: false,
          receiptData: null,
          checkerPrinted: {
            kitchen: false,
            bar: false,
            cashier: false,
            checker: false,
          },
          showAuthModal: false,
          authCode: '',
          authError: '',
          authPending: null,
          isVerifyingAuth: false,
          cashier: posInitialData.cashier,
          showToast: false,
          toastMessage: '',
          toastType: 'success',
          counterLocation: posInitialData.currentCounter,
          gridCols: parseInt(localStorage.getItem('posGridCols') ?? '4'),
          kitchenUrl: posInitialData.kitchenUrl,
          barUrl: posInitialData.barUrl,
          checkerUrl: posInitialData.checkerUrl,
          canChooseChecker: Boolean(posInitialData.canChooseChecker),
          selectedCheckerPrinterIds: [],
          posCharges,
          bookingStep: 'type',
          checkoutForm: {
            customer_type: '',
            customer_user_id: '',
            walk_in_customer_id: '',
            auto_print_receipt: true,
            payment_method: 'cash',
            foc_comp_payment_method: '',
            payment_mode: 'normal',
            payment_reference_number: '',
            split_cash_amount: 0,
            split_non_cash_amount: 0,
            split_non_cash_method: 'debit',
            split_non_cash_reference_number: '',
            split_second_non_cash_amount: 0,
            split_second_non_cash_method: 'debit',
            split_second_non_cash_reference_number: '',
            discount_type: 'none',
            discount_percentage: 0,
            discount_nominal: 0,
            discount_auth_code: '',
            customerName: '',
            customerInitial: '',
            customerPhone: '',
            table_id: '',
            table_display: '',
            waiterName: '',
            reservationId: null,
            assigningWaiter: false,
            assignWaiterError: '',
            minimumCharge: 0,
            ordersTotal: 0,
            minimumChargeCredit: 0,
            tierName: '',
            discountPercentage: 0,
          },
          posWaiters: posWaiters,
          availableTables: posAvailableTables,
          cartNotes: {},
          selectedDiscountItemIds: [],
          selectedDiscount: {
            type: 'none',
            value: 0,
            reason: '',
            managerCode: '',
            authCodeRequested: false,
            requestingAuthCode: false,
            token: null,
            amount: 0,
            approving: false,
          },
          menuAvailability: null,

          init() {
            this.cart = posInitialData.cart;
            this.cartTotal = posInitialData.cartTotal;

            if (this.cart.length > 0) {
              this.refreshMenuAvailability();
            }
          },

          formatCurrency(amount) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
          },

          getItemBgColor(id) {
            const colors = ['bg-blue-500', 'bg-violet-500', 'bg-cyan-600', 'bg-orange-500', 'bg-teal-500', 'bg-pink-500'];
            const hash = String(id).split('').reduce((acc, c) => acc + c.charCodeAt(0), 0);
            return colors[hash % colors.length];
          },

          inventoryId(productId) {
            return Number(String(productId || '').replace('item_', ''));
          },

          invalidateDiscountApproval() {
            this.selectedDiscount.token = null;
            this.selectedDiscount.amount = 0;
            this.selectedDiscount.managerCode = '';
            this.selectedDiscount.authCodeRequested = false;
            this.checkoutToken = null;
          },

          toggleDiscountItem(itemId) {
            const inventoryId = this.inventoryId(itemId);
            this.selectedDiscountItemIds = this.selectedDiscountItemIds.includes(inventoryId) ?
              this.selectedDiscountItemIds.filter(id => id !== inventoryId) :
              [...this.selectedDiscountItemIds, inventoryId];
            this.invalidateDiscountApproval();
          },

          async approveSelectedDiscount() {
            if (!this.selectedDiscount.authCodeRequested) {
              this.showToastMessage('Request auth code terlebih dahulu.', 'error');
              return;
            }

            if (this.selectedDiscountItemIds.length === 0 || this.selectedDiscount.type === 'none' || Number(this.selectedDiscount.value || 0) <= 0 || !String(this.selectedDiscount.reason || '').trim() || !/^\d{4}$/.test(String(this.selectedDiscount.managerCode || ''))) {
              this.showToastMessage('Pilih item, isi nilai, alasan, dan auth code manager.', 'error');
              return;
            }

            this.selectedDiscount.approving = true;
            try {
              const response = await fetch(posRoutes.approveDiscount, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'Accept': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
                body: JSON.stringify({
                  customer_type: this.checkoutForm.customer_type,
                  customer_user_id: this.checkoutForm.customer_user_id || null,
                  walk_in_customer_id: this.checkoutForm.walk_in_customer_id || null,
                  table_id: this.checkoutForm.table_id || null,
                  selected_item_ids: this.selectedDiscountItemIds,
                  discount_type: this.selectedDiscount.type,
                  discount_value: Number(this.selectedDiscount.value || 0),
                  reason: String(this.selectedDiscount.reason || '').trim(),
                  manager_auth_code: String(this.selectedDiscount.managerCode || ''),
                }),
              });
              const data = await response.json();
              if (!response.ok || !data.success) {
                this.showToastMessage(data.message || Object.values(data.errors || {})[0]?.[0] || 'Approval diskon gagal.', 'error');
                return;
              }

              this.selectedDiscount.token = data.approval_token;
              this.selectedDiscount.amount = Number(data.discount_amount || 0);
              this.selectedDiscount.managerCode = '';
              this.checkoutForm.discount_type = 'none';
              this.checkoutForm.discount_percentage = 0;
              this.checkoutForm.discount_nominal = 0;
              this.checkoutForm.discountPercentage = 0;
              this.showToastMessage('Diskon item disetujui manager.', 'success');
            } catch (error) {
              this.showToastMessage('Approval diskon gagal.', 'error');
            } finally {
              this.selectedDiscount.approving = false;
            }
          },

          async requestSelectedDiscountAuthCode() {
            if (this.selectedDiscount.type === 'none' || this.selectedDiscountItemIds.length === 0 || Number(this.selectedDiscount.value || 0) <= 0 || !String(this.selectedDiscount.reason || '').trim()) {
              this.showToastMessage('Pilih tipe, item, isi nilai diskon, dan alasan terlebih dahulu.', 'error');
              return;
            }

            if (this.selectedDiscount.requestingAuthCode) {
              return;
            }

            this.selectedDiscount.requestingAuthCode = true;
            try {
              const response = await fetch(posRoutes.sendAuthCodeEmail, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'Accept': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
                body: JSON.stringify({ source: 'pos-selected-item-discount' }),
              });
              const data = await response.json();

              if (!response.ok || !data.success) {
                this.showToastMessage(data.message || 'Gagal mengirim auth code.', 'error');
                return;
              }

              this.selectedDiscount.authCodeRequested = true;
              this.showToastMessage(data.message || 'Auth code berhasil dikirim.', 'success');
            } catch (error) {
              this.showToastMessage('Gagal mengirim auth code.', 'error');
            } finally {
              this.selectedDiscount.requestingAuthCode = false;
            }
          },

          getCounterLabel() {
            const select = document.getElementById('counterLocationSelect');
            if (select && this.counterLocation) {
              const option = select.querySelector(`option[value="${this.counterLocation}"]`);
              return option ? option.textContent : this.counterLocation;
            }
            return '';
          },

          async selectCounter(event) {
            const location = event.target.value;
            try {
              const response = await fetch(posRoutes.selectCounter, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
                body: JSON.stringify({
                  counter_location: location
                }),
              });
              const data = await response.json();
              if (data.success) {
                this.showToastMessage('Counter location updated', 'success');
              }
            } catch (error) {
              this.showToastMessage('Failed to update counter location', 'error');
            }
          },

          async addToCart(productId) {
            if (this.isProcessing) {
              return;
            }
            this.isProcessing = true;
            try {
              const response = await fetch(
                posRoutes.addToCart.replace('__PRODUCT_ID__', productId), {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    'Accept': 'application/json',
                  },
                },
              );
              const data = await response.json();
              if (data.success) {
                this.invalidateDiscountApproval();
                this.cart = data.cart;
                this.cartTotal = data.cartTotal;
                await this.refreshMenuAvailability();
                this.showToastMessage(data.message, 'success');
              } else {
                this.showToastMessage(data.message || 'Gagal menambah produk', 'error');
              }
            } catch (error) {
              this.showToastMessage('Gagal menambah produk ke keranjang', 'error');
            } finally {
              this.isProcessing = false;
            }
          },

          async updateCartQuantity(productId, action) {
            if (this.isProcessing) {
              return;
            }
            this.isProcessing = true;
            try {
              const response = await fetch(
                posRoutes.updateCart.replace('__PRODUCT_ID__', productId), {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    'Accept': 'application/json',
                  },
                  body: JSON.stringify({
                    action
                  }),
                },
              );
              const data = await response.json();
              if (data.success) {
                this.invalidateDiscountApproval();
                this.cart = data.cart;
                this.cartTotal = data.cartTotal;
                await this.refreshMenuAvailability();
              } else {
                this.showToastMessage(data.message || 'Gagal mengupdate keranjang', 'error');
              }
            } catch (error) {
              this.showToastMessage('Gagal mengupdate keranjang', 'error');
            } finally {
              this.isProcessing = false;
            }
          },

          async removeFromCart(productId) {
            if (this.isProcessing) {
              return;
            }
            this.isProcessing = true;
            try {
              const response = await fetch(
                posRoutes.removeFromCart.replace('__PRODUCT_ID__', productId), {
                  method: 'DELETE',
                  headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    'Accept': 'application/json',
                  },
                },
              );
              const data = await response.json();
              if (data.success) {
                this.invalidateDiscountApproval();
                this.cart = data.cart;
                this.cartTotal = data.cartTotal;
                await this.refreshMenuAvailability();
                this.showToastMessage(data.message, 'success');
              } else {
                this.showToastMessage(data.message || 'Gagal menghapus item', 'error');
              }
            } catch (error) {
              this.showToastMessage('Gagal menghapus item', 'error');
            } finally {
              this.isProcessing = false;
            }
          },

          async clearCart() {
            if (this.isProcessing) {
              return;
            }
            this.isProcessing = true;
            try {
              const response = await fetch(posRoutes.clearCart, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                  'Accept': 'application/json',
                },
              });
              const data = await response.json();
              if (data.success) {
                this.invalidateDiscountApproval();
                this.selectedDiscountItemIds = [];
                this.cart = [];
                this.cartTotal = 0;
                this.cartNotes = {};
                this.menuAvailability = null;
                this.showToastMessage(data.message, 'success');
              } else {
                this.showToastMessage(data.message || 'Gagal mengosongkan keranjang', 'error');
              }
            } catch (error) {
              this.showToastMessage('Gagal mengosongkan keranjang', 'error');
            } finally {
              this.isProcessing = false;
            }
          },

          async openCustomerTypeModal() {
            if (this.cart.length === 0) {
              this.showToastMessage('Keranjang masih kosong!', 'error');
              return;
            }

            const preview = await this.refreshMenuAvailability(true);
            if (!preview) {
              return;
            }

            this.bookingStep = 'type';
            this.showCustomerTypeModal = true;
          },

          async previewMenuAvailability(showError = true) {
            try {
              const response = await fetch(posRoutes.previewCheckoutAvailability, {
                headers: {
                  Accept: 'application/json',
                },
              });

              const data = await response.json();

              if (!response.ok || !data.success) {
                if (showError) {
                  this.showToastMessage(data.message || 'Gagal mengecek stok bahan menu.', 'error');
                }
                return null;
              }

              return data;
            } catch (error) {
              if (showError) {
                this.showToastMessage('Gagal mengecek stok bahan menu.', 'error');
              }
              return null;
            }
          },

          async refreshMenuAvailability(showError = false) {
            if (this.cart.length === 0) {
              this.menuAvailability = null;
              return null;
            }

            const preview = await this.previewMenuAvailability(showError);
            if (!preview) {
              return null;
            }

            this.menuAvailability = preview;

            return preview;
          },

          hasMenuAvailabilityPreview() {
            const menuItems = this.menuAvailability?.menu_items;

            return Array.isArray(menuItems) && menuItems.length > 0;
          },

          getMenuAvailabilityItem(productId) {
            const menuItems = this.menuAvailability?.menu_items;

            if (!Array.isArray(menuItems) || menuItems.length === 0) {
              return null;
            }

            const normalizedProductId = String(productId);

            return menuItems.find((menuItem) => String(menuItem.product_id) === normalizedProductId) || null;
          },

          hasMenuAvailabilityIssue(productId) {
            const menuItem = this.getMenuAvailabilityItem(productId);

            return menuItem ? menuItem.is_available === false : false;
          },

          getMenuAvailabilityLabel(productId) {
            const menuItem = this.getMenuAvailabilityItem(productId);

            if (!menuItem) {
              return '';
            }

            return `Possible ${menuItem.possible_portions} porsi • Diminta ${menuItem.requested_quantity} porsi`;
          },

          getMenuAvailabilityMessage(productId) {
            const menuItem = this.getMenuAvailabilityItem(productId);

            if (!menuItem) {
              return '';
            }

            return `Stok bahan ${menuItem.name} hanya cukup ${menuItem.possible_portions} porsi.`;
          },

          canProceedToCheckout() {
            return this.menuAvailability?.can_checkout !== false;
          },

          requiresWaiterSelection() {
            return this.checkoutForm.customer_type === 'booking' && !this.checkoutForm.waiterName;
          },

          openConfirmModal() {
            if (this.requiresWaiterSelection()) {
              this.showToastMessage('Pilih waiter terlebih dahulu sebelum menyelesaikan transaksi.', 'error');
              return;
            }

            this.prepareCheckerSelectionFromCart();

            if (!this.validateWalkInPaymentFields()) {
              return;
            }

            if (this.shouldChooseCheckerOnCheckout() && this.getSelectedCheckerPrinterIds().length === 0) {
              this.showToastMessage('Pilih minimal satu printer checker.', 'error');
              return;
            }

            this.showConfirmModal = true;
          },

          selectCustomerType(type) {
            this.checkoutForm.customer_type = type;
            this.showCustomerTypeModal = false;
            this.showCheckoutModal = true;
          },

          selectBookingSession(data) {
            this.checkoutForm.customer_type = 'booking';
            this.checkoutForm.customer_user_id = data.customerId;
            this.checkoutForm.table_id = data.tableId;
            this.checkoutForm.table_display = data.areaName + ' - Meja ' + data.tableName;
            this.checkoutForm.customerName = data.customerName;
            this.checkoutForm.customerInitial = data.customerInitial;
            this.checkoutForm.customerPhone = data.customerPhone;
            this.checkoutForm.minimumCharge = data.minimumCharge;
            this.checkoutForm.ordersTotal = data.ordersTotal;
            this.checkoutForm.minimumChargeCredit = Number(data.downPaymentAmount || 0);
            this.checkoutForm.tierName = data.tierName || '';
            this.checkoutForm.discountPercentage = data.discountPercentage || 0;
            this.checkoutForm.waiterName = data.waiterName || '';
            this.checkoutForm.reservationId = data.reservationId || null;
            this.checkoutForm.assigningWaiter = false;
            this.checkoutForm.assignWaiterError = '';
            this.showCustomerTypeModal = false;
            this.bookingStep = 'type';
            this.showCheckoutModal = true;
          },

          async assignWaiterFromPos(waiterId) {
            if (!waiterId || !this.checkoutForm.reservationId) return;
            this.checkoutForm.assigningWaiter = true;
            this.checkoutForm.assignWaiterError = '';
            try {
              const res = await fetch(`/admin/pos/assign-waiter/${this.checkoutForm.reservationId}`, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                  'Accept': 'application/json',
                },
                body: JSON.stringify({
                  waiter_id: waiterId
                }),
              });
              const data = await res.json();
              if (data.success) {
                this.checkoutForm.waiterName = data.waiterName;
              } else {
                this.checkoutForm.assignWaiterError = data.message || 'Gagal assign waiter.';
              }
            } catch (e) {
              this.checkoutForm.assignWaiterError = 'Terjadi kesalahan. Coba lagi.';
            } finally {
              this.checkoutForm.assigningWaiter = false;
            }
          },

          /**
           * Called when walkInCheckout component dispatches 'walk-in-proceed'.
           * Populates checkoutForm and opens the shared checkout modal.
           */
          receiveWalkIn(d) {
            this.invalidateDiscountApproval();
            this.checkoutForm.customer_type = 'walk-in';
            this.checkoutForm.walk_in_customer_id = d.id;
            this.checkoutForm.table_id = null;
            this.checkoutForm.table_display = 'Walk-in';
            this.checkoutForm.customerName = d.name;
            this.checkoutForm.customerInitial = d.name[0].toUpperCase();
            this.checkoutForm.customerPhone = d.phone;
            this.checkoutForm.minimumCharge = 0;
            this.checkoutForm.ordersTotal = 0;
            this.checkoutForm.minimumChargeCredit = 0;
            this.checkoutForm.tierName = '';
            this.checkoutForm.discountPercentage = 0;
            this.checkoutForm.waiterName = '';
            this.checkoutForm.reservationId = null;
            this.checkoutForm.payment_mode = 'normal';
            this.checkoutForm.payment_method = 'cash';
            this.checkoutForm.payment_reference_number = '';
            this.checkoutForm.auto_print_receipt = true;
            this.checkoutForm.split_cash_amount = 0;
            this.checkoutForm.split_non_cash_amount = this.payableTotal();
            this.checkoutForm.split_non_cash_method = 'debit';
            this.checkoutForm.split_non_cash_reference_number = '';
            this.checkoutForm.split_second_non_cash_amount = 0;
            this.checkoutForm.split_second_non_cash_method = 'debit';
            this.checkoutForm.split_second_non_cash_reference_number = '';
            this.checkoutForm.discount_type = 'none';
            this.checkoutForm.discount_percentage = 0;
            this.checkoutForm.discount_nominal = 0;
            this.checkoutForm.discount_auth_code = '';
            this.showCustomerTypeModal = false;
            this.bookingStep = 'type';
            this.showCheckoutModal = true;
          },

          getWalkInDiscountPercentage() {
            return Number(this.checkoutForm.discount_percentage || 0);
          },

          getWalkInDiscountNominal() {
            return Number(this.checkoutForm.discount_nominal || 0);
          },

          onWalkInDiscountNominalInput(event) {
            const nominal = this.extractNumber(event.target.value);
            const maxNominal = Math.max(this.cartTotal, 0);
            this.checkoutForm.discount_nominal = Math.min(Math.max(nominal, 0), maxNominal);
          },

          onSelectedDiscountNominalInput(event) {
            const nominal = this.extractNumber(event.target.value);
            const selectedGross = this.cart
              .filter(line => this.selectedDiscountItemIds.includes(this.inventoryId(line.id)))
              .reduce((sum, line) => sum + Number(line.price || 0) * Number(line.quantity || 0), 0);
            this.selectedDiscount.value = Math.max(Math.min(nominal, Math.max(selectedGross, 0)), 0);
            this.invalidateDiscountApproval();
          },

          isWalkInNonCashNormalMode() {
            return this.checkoutForm.customer_type === 'walk-in' &&
              this.checkoutForm.payment_mode === 'normal' &&
              this.checkoutForm.payment_method !== 'cash';
          },

          walkInSplitTotal() {
            return Number(this.checkoutForm.split_cash_amount || 0) + Number(this.checkoutForm.split_non_cash_amount || 0) + Number(this.checkoutForm.split_second_non_cash_amount || 0);
          },

          walkInSplitDiff() {
            return Math.round((this.payableTotal() - this.walkInSplitTotal()) * 100) / 100;
          },

          onWalkInSplitInput(which, event) {
            const enteredAmount = this.extractNumber(event.target.value);
            const maxAmount = Math.max(this.payableTotal(), 0);
            const normalizedAmount = Math.min(Math.max(enteredAmount, 0), maxAmount);

            let splitCashAmount = Number(this.checkoutForm.split_cash_amount || 0);
            let splitNonCashAmount = Number(this.checkoutForm.split_non_cash_amount || 0);
            let splitSecondNonCashAmount = Number(this.checkoutForm.split_second_non_cash_amount || 0);

            if (which === 'cash') {
              splitCashAmount = normalizedAmount;
              splitNonCashAmount = Math.max(maxAmount - splitCashAmount, 0);
              splitSecondNonCashAmount = 0;

              this.checkoutForm.split_cash_amount = splitCashAmount;
              this.checkoutForm.split_non_cash_amount = splitNonCashAmount;
              this.checkoutForm.split_second_non_cash_amount = splitSecondNonCashAmount;
              return;
            }

            if (which === 'non-cash') {
              splitNonCashAmount = normalizedAmount;

              if (splitCashAmount > maxAmount - splitNonCashAmount) {
                splitCashAmount = Math.max(maxAmount - splitNonCashAmount, 0);
              }

              splitSecondNonCashAmount = Math.max(maxAmount - splitCashAmount - splitNonCashAmount, 0);

              this.checkoutForm.split_cash_amount = splitCashAmount;
              this.checkoutForm.split_non_cash_amount = splitNonCashAmount;
              this.checkoutForm.split_second_non_cash_amount = splitSecondNonCashAmount;

              return;
            }

            splitSecondNonCashAmount = normalizedAmount;

            if (splitCashAmount > maxAmount - splitSecondNonCashAmount) {
              splitCashAmount = Math.max(maxAmount - splitSecondNonCashAmount, 0);
            }

            splitNonCashAmount = Math.max(maxAmount - splitCashAmount - splitSecondNonCashAmount, 0);

            this.checkoutForm.split_cash_amount = splitCashAmount;
            this.checkoutForm.split_non_cash_amount = splitNonCashAmount;
            this.checkoutForm.split_second_non_cash_amount = splitSecondNonCashAmount;
          },

          extractNumber(value) {
            const digits = String(value || '').replace(/[^0-9]/g, '');

            return digits ? Number(digits) : 0;
          },

          async requestAuthCodeEmail() {
            if (this.checkoutForm.discount_type === 'none') {
              this.showToastMessage('Pilih diskon terlebih dahulu sebelum request auth code.', 'error');

              return;
            }

            if (this.isRequestingAuthCodeEmail) {
              return;
            }

            this.isRequestingAuthCodeEmail = true;

            try {
              const response = await fetch(posRoutes.sendAuthCodeEmail, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'Accept': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
                body: JSON.stringify({
                  source: 'pos-walk-in-discount',
                }),
              });

              const data = await response.json();

              if (!response.ok || !data.success) {
                this.showToastMessage(data.message || 'Gagal mengirim auth code ke email.', 'error');

                return;
              }

              this.showToastMessage(data.message || 'Auth code berhasil dikirim ke email.', 'success');
            } catch (error) {
              this.showToastMessage('Gagal mengirim auth code ke email.', 'error');
            } finally {
              this.isRequestingAuthCodeEmail = false;
            }
          },

          validateWalkInPaymentFields() {
            if (this.checkoutForm.customer_type !== 'walk-in') {
              return true;
            }

            if (this.checkoutForm.discount_type === 'percentage') {
              const discountPercentage = this.getWalkInDiscountPercentage();
              if (discountPercentage <= 0 || discountPercentage > 100) {
                this.showToastMessage('Diskon persentase harus lebih dari 0 dan maksimal 100.', 'error');

                return false;
              }
            }

            if (this.checkoutForm.discount_type === 'nominal') {
              const discountNominal = this.getWalkInDiscountNominal();
              if (discountNominal <= 0) {
                this.showToastMessage('Diskon nominal harus lebih dari 0.', 'error');

                return false;
              }
            }

            if (this.checkoutForm.discount_type !== 'none' && !/^\d{4}$/.test(String(this.checkoutForm.discount_auth_code || '').trim())) {
              this.showToastMessage('Auth code diskon harus 4 digit.', 'error');

              return false;
            }

            if (this.checkoutForm.payment_mode === 'normal') {
              if (!this.checkoutForm.payment_method) {
                this.showToastMessage('Metode pembayaran wajib dipilih.', 'error');

                return false;
              }

              if (this.isWalkInNonCashNormalMode() && !String(this.checkoutForm.payment_reference_number || '').trim()) {
                this.showToastMessage('Nomor referensi pembayaran non-cash wajib diisi.', 'error');

                return false;
              }

              return true;
            }

            const splitCashAmount = Number(this.checkoutForm.split_cash_amount || 0);
            const splitNonCashAmount = Number(this.checkoutForm.split_non_cash_amount || 0);
            const splitSecondNonCashAmount = Number(this.checkoutForm.split_second_non_cash_amount || 0);
            const splitTotal = splitCashAmount + splitNonCashAmount + splitSecondNonCashAmount;
            const nonCashCount = [splitNonCashAmount, splitSecondNonCashAmount].filter((amount) => amount > 0).length;

            if (splitCashAmount < 0 || splitNonCashAmount < 0 || splitSecondNonCashAmount < 0) {
              this.showToastMessage('Nominal split bill tidak boleh minus.', 'error');

              return false;
            }

            if (splitCashAmount <= 0 && nonCashCount < 2) {
              this.showToastMessage('Untuk split non-cash + non-cash, isi dua nominal non-cash lebih dari 0.', 'error');

              return false;
            }

            if (splitCashAmount > 0 && nonCashCount < 1) {
              this.showToastMessage('Untuk split cash + non-cash, minimal satu nominal non-cash harus lebih dari 0.', 'error');

              return false;
            }

            if (splitNonCashAmount > 0 && !this.checkoutForm.split_non_cash_method) {
              this.showToastMessage('Metode non-cash pertama untuk split bill wajib dipilih.', 'error');

              return false;
            }

            if (splitNonCashAmount > 0 && !String(this.checkoutForm.split_non_cash_reference_number || '').trim()) {
              this.showToastMessage('Nomor referensi non-cash pertama untuk split bill wajib diisi.', 'error');

              return false;
            }

            if (splitSecondNonCashAmount > 0 && !this.checkoutForm.split_second_non_cash_method) {
              this.showToastMessage('Metode non-cash kedua untuk split bill wajib dipilih.', 'error');

              return false;
            }

            if (splitSecondNonCashAmount > 0 && !String(this.checkoutForm.split_second_non_cash_reference_number || '').trim()) {
              this.showToastMessage('Nomor referensi non-cash kedua untuk split bill wajib diisi.', 'error');

              return false;
            }

            if (Math.abs(splitTotal - this.payableTotal()) > 0.01) {
              this.showToastMessage('Total split harus sama dengan grand total.', 'error');

              return false;
            }

            return true;
          },

          discountAmount() {
            if (this.selectedDiscount.token) {
              return Number(this.selectedDiscount.amount || 0);
            }

            if (this.checkoutForm.customer_type === 'walk-in') {
              const discountBase = this.walkInPreDiscountTotal();

              if (this.checkoutForm.discount_type === 'percentage') {
                const amount = discountBase * (this.getWalkInDiscountPercentage() / 100);

                return Math.round(amount);
              }

              if (this.checkoutForm.discount_type === 'nominal') {
                return Math.min(Math.round(this.getWalkInDiscountNominal()), Math.round(discountBase));
              }

              return 0;
            }

            return Math.round(this.cartTotal * (this.checkoutForm.discountPercentage / 100));
          },

          finalTotal() {
            if (this.checkoutForm.customer_type === 'walk-in' && !this.selectedDiscount.token) {
              return this.cartTotal;
            }

            return this.cartTotal - this.discountAmount();
          },

          chargeableBases() {
            return this.cart.reduce((acc, item) => {
              const subtotal = Number(item.price || 0) * Number(item.quantity || 0);
              const netSubtotal = Math.max(subtotal - this.selectedItemDiscount(item), 0);
              const includeTax = item.include_tax !== false;
              const includeServiceCharge = item.include_service_charge !== false;

              if (includeServiceCharge) {
                acc.serviceChargeBase += netSubtotal;
              }

              if (includeTax) {
                acc.taxBase += netSubtotal;
              }

              if (includeTax && includeServiceCharge) {
                acc.taxAndServiceBase += netSubtotal;
              }

              return acc;
            }, {
              serviceChargeBase: 0,
              taxBase: 0,
              taxAndServiceBase: 0,
            });
          },

          selectedItemDiscount(item) {
            if (!this.selectedDiscount.token || !this.selectedDiscountItemIds.includes(this.inventoryId(item.id))) {
              return 0;
            }

            const gross = Number(item.price || 0) * Number(item.quantity || 0);
            if (this.selectedDiscount.type === 'percentage') {
              return Math.round(gross * Number(this.selectedDiscount.value || 0) / 100);
            }

            const selectedGross = this.cart
              .filter(line => this.selectedDiscountItemIds.includes(this.inventoryId(line.id)))
              .reduce((sum, line) => sum + Number(line.price || 0) * Number(line.quantity || 0), 0);

            return selectedGross > 0 ? Math.round(Number(this.selectedDiscount.amount || 0) * gross / selectedGross) : 0;
          },

          discountRatio() {
            if (this.selectedDiscount.token) {
              return 0;
            }

            if (this.cartTotal <= 0) {
              return 0;
            }

            return Math.min(Math.max(this.discountAmount() / this.cartTotal, 0), 1);
          },

          calculatedServiceCharge() {
            const bases = this.chargeableBases();
            const serviceChargeRate = this.posCharges.serviceChargePercentage / 100;

            if (this.checkoutForm.customer_type === 'walk-in' || this.selectedDiscount.token) {
              const taxRate = this.posCharges.taxPercentage / 100;
              let serviceChargeBaseWithTax = bases.serviceChargeBase;

              if (taxRate > 0) {
                serviceChargeBaseWithTax += bases.taxAndServiceBase * taxRate;
              }

              return Math.round(serviceChargeBaseWithTax * serviceChargeRate);
            }

            const discountRatio = this.discountRatio();
            const serviceChargeBaseAfterDiscount = bases.serviceChargeBase * (1 - discountRatio);
            const taxAndServiceBaseAfterDiscount = bases.taxAndServiceBase * (1 - discountRatio);
            const taxRate = this.posCharges.taxPercentage / 100;

            return Math.round((serviceChargeBaseAfterDiscount + (taxAndServiceBaseAfterDiscount * taxRate)) * serviceChargeRate);
          },

          calculatedTax() {
            const bases = this.chargeableBases();
            const taxRate = this.posCharges.taxPercentage / 100;

            if (this.checkoutForm.customer_type === 'walk-in' || this.selectedDiscount.token) {
              return Math.round(bases.taxBase * taxRate);
            }

            const discountRatio = this.discountRatio();
            const taxBaseAfterDiscount = bases.taxBase * (1 - discountRatio);

            return Math.round(taxBaseAfterDiscount * taxRate);
          },

          walkInPreDiscountTotal() {
            return this.cartTotal - (this.selectedDiscount.token ? this.selectedDiscount.amount : 0) + this.calculatedServiceCharge() + this.calculatedTax();
          },

          subTotalBeforeDiscount() {
            if (this.checkoutForm.customer_type === 'walk-in') {
              return this.walkInPreDiscountTotal();
            }

            return this.finalTotal() + this.calculatedServiceCharge() + this.calculatedTax() + this.discountAmount();
          },

          payableTotal() {
            if (this.selectedDiscount.token) {
              return this.walkInPreDiscountTotal();
            }

            if (this.checkoutForm.customer_type === 'walk-in') {
              return this.walkInPreDiscountTotal() - this.discountAmount();
            }

            return this.finalTotal() + this.calculatedServiceCharge() + this.calculatedTax();
          },

          minimumChargeCoveredAmount() {
            const baseCoveredAmount = Number(this.checkoutForm.ordersTotal || 0) + Number(this.cartTotal || 0);

            if (this.checkoutForm.customer_type !== 'booking') {
              return baseCoveredAmount;
            }

            const minimumChargeCredit = Math.max(Number(this.checkoutForm.minimumChargeCredit || 0), 0);

            return baseCoveredAmount + minimumChargeCredit;
          },

          minimumChargeShortfall() {
            return Math.max(Number(this.checkoutForm.minimumCharge || 0) - this.minimumChargeCoveredAmount(), 0);
          },

          pointsEarned() {
            return Math.floor(this.payableTotal() / 10000);
          },

          async submitCheckout(forcePrintReceipt = false) {
            if (this.isProcessing) {
              return;
            }

            if (this.requiresWaiterSelection()) {
              this.showToastMessage('Pilih waiter terlebih dahulu sebelum menyelesaikan transaksi.', 'error');
              return;
            }

            const preview = await this.previewMenuAvailability();
            if (!preview) {
              return;
            }

            this.menuAvailability = preview;

            if (!preview.can_checkout) {
              this.showToastMessage(preview.message || 'Stok bahan menu tidak mencukupi untuk checkout.', 'error');
              return;
            }

            if (!this.validateWalkInPaymentFields()) {
              return;
            }

            if (this.checkoutForm.customer_type === 'walk-in' && this.checkoutForm.discount_type !== 'none') {
              try {
                const verifyResponse = await fetch(posRoutes.verifyAuthCode, {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                  },
                  body: JSON.stringify({
                    code: String(this.checkoutForm.discount_auth_code || '').trim(),
                  }),
                });
                const verifyData = await verifyResponse.json();

                if (!verifyData.valid) {
                  this.showToastMessage('Auth code diskon tidak valid.', 'error');
                  return;
                }
              } catch (error) {
                this.showToastMessage('Gagal verifikasi auth code diskon.', 'error');
                return;
              }
            }

            this.isProcessing = true;
            try {
              const payload = {
                ...this.checkoutForm,
                cart_notes: this.cartNotes,
                idempotency_key: this.checkoutToken ??= crypto.randomUUID(),
                discount_approval_token: this.selectedDiscount.token,
              };

              if (this.shouldChooseCheckerOnCheckout()) {
                payload.checker_printer_ids = this.getSelectedCheckerPrinterIds();
              }

              if (this.checkoutForm.customer_type === 'walk-in') {
                payload.discount_type = this.checkoutForm.discount_type;
                payload.auto_print_receipt = forcePrintReceipt ? true : Boolean(this.checkoutForm.auto_print_receipt);

                if (this.checkoutForm.discount_type === 'percentage') {
                  payload.discount_percentage = this.getWalkInDiscountPercentage();
                } else if (this.checkoutForm.discount_type === 'nominal') {
                  payload.discount_nominal = this.getWalkInDiscountNominal();
                } else {
                  payload.discount_percentage = 0;
                }
              } else {
                payload.discount_percentage = this.checkoutForm.discountPercentage;
              }

              const response = await fetch(posRoutes.checkout, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                  'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
              });
              const data = await response.json();
              if (data.success) {
                this.checkoutToken = null;
                this.selectedDiscountItemIds = [];
                this.selectedDiscount = {
                  type: 'percentage', value: 0, reason: '', managerCode: '', authCodeRequested: false, requestingAuthCode: false, token: null, amount: 0, approving: false
                };
                const checkoutSnapshot = {
                  ...this.checkoutForm,
                };
                const receiptItems = this.cart.map((item) => ({
                  id: item.id,
                  name: item.name,
                  quantity: Number(item.quantity || 0),
                  price: Number(item.price || 0),
                  notes: this.cartNotes[item.id] || '',
                  assigned_printer_types: Array.isArray(item.assigned_printer_types) ? item.assigned_printer_types : [],
                  assigned_checker_printers: Array.isArray(item.assigned_checker_printers) ? item.assigned_checker_printers : [],
                  assigned_checker_printer_ids: Array.isArray(item.assigned_checker_printer_ids) ? item.assigned_checker_printer_ids : [],
                }));

                const checkerPrintersById = new Map();
                receiptItems.forEach((item) => {
                  (Array.isArray(item.assigned_checker_printers) ? item.assigned_checker_printers : []).forEach((printer) => {
                    const printerId = Number(printer?.id || 0);

                    if (printerId > 0 && !checkerPrintersById.has(printerId)) {
                      checkerPrintersById.set(printerId, {
                        id: printerId,
                        name: String(printer?.name || `Checker #${printerId}`),
                      });
                    }
                  });
                });

                const checkerPrinters = Array.from(checkerPrintersById.values());

                this.cart = [];
                this.cartTotal = 0;
                this.cartNotes = {};
                this.menuAvailability = null;
                this.showConfirmModal = false;
                this.showCheckoutModal = false;
                this.receiptData = {
                  orderId: Number(data.order_id || 0),
                  orderNumber: data.order_number || '-',
                  customerType: checkoutSnapshot.customer_type || '-',
                  customerName: checkoutSnapshot.customerName || '-',
                  tableDisplay: checkoutSnapshot.table_display || '-',
                  minimumCharge: Number(checkoutSnapshot.minimumCharge || 0),
                  ordersTotal: Number(checkoutSnapshot.ordersTotal || 0),
                  itemsTotal: Number(data.items_total || 0),
                  discountAmount: Number(data.discount_amount || 0),
                  serviceChargePercentage: Number(data.service_charge_percentage || 0),
                  serviceCharge: Number(data.service_charge || 0),
                  taxPercentage: Number(data.tax_percentage || 0),
                  tax: Number(data.tax || 0),
                  total: Number(data.total || 0),
                  formattedTotal: data.formatted_total || this.formatCurrency(Number(data.total || 0)),
                  receiptPrinted: Boolean(data.receipt_printed),
                  printedAt: new Date().toLocaleString('id-ID', {
                    timeZone: 'Asia/Jakarta',
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                  }),
                  items: receiptItems,
                  checkerPrinters,
                };
                const selectedCheckerIds = this.getSelectedCheckerPrinterIds().filter((id) => checkerPrinters.some((printer) => Number(printer.id) === Number(id)));
                this.selectedCheckerPrinterIds = selectedCheckerIds.length > 0 ? selectedCheckerIds : checkerPrinters.map((printer) => printer.id);
                this.checkerPrinted = {
                  kitchen: false,
                  bar: false,
                  cashier: false,
                  checker: false,
                };
                this.showReceiptModal = true;
                this.showToastMessage(data.message || 'Checkout berhasil.', 'success');

                this.checkoutForm = {
                  customer_type: '',
                  customer_user_id: '',
                  walk_in_customer_id: '',
                  auto_print_receipt: true,
                  payment_method: 'cash',
                  foc_comp_payment_method: '',
                  payment_mode: 'normal',
                  payment_reference_number: '',
                  split_cash_amount: 0,
                  split_non_cash_amount: 0,
                  split_non_cash_method: 'debit',
                  split_non_cash_reference_number: '',
                  split_second_non_cash_amount: 0,
                  split_second_non_cash_method: 'debit',
                  split_second_non_cash_reference_number: '',
                  discount_type: 'none',
                  discount_percentage: 0,
                  discount_nominal: 0,
                  discount_auth_code: '',
                  customerName: '',
                  customerInitial: '',
                  customerPhone: '',
                  table_id: '',
                  table_display: '',
                  waiterName: '',
                  reservationId: null,
                  minimumCharge: 0,
                  ordersTotal: 0,
                  minimumChargeCredit: 0,
                  tierName: '',
                  discountPercentage: 0,
                };
              } else {
                this.showToastMessage(data.message || 'Checkout gagal', 'error');
              }
            } catch (error) {
              this.showToastMessage('Terjadi kesalahan saat checkout', 'error');
            } finally {
              this.isProcessing = false;
            }
          },

          closeReceiptModal() {
            this.showReceiptModal = false;
            this.receiptData = null;
            this.selectedCheckerPrinterIds = [];
          },

          getCheckerPrintersFromCart() {
            const checkerPrintersById = new Map();

            (Array.isArray(this.cart) ? this.cart : []).forEach((item) => {
              (Array.isArray(item.assigned_checker_printers) ? item.assigned_checker_printers : []).forEach((printer) => {
                const printerId = Number(printer?.id || 0);

                if (printerId > 0 && !checkerPrintersById.has(printerId)) {
                  checkerPrintersById.set(printerId, {
                    id: printerId,
                    name: String(printer?.name || `Checker #${printerId}`),
                  });
                }
              });
            });

            return Array.from(checkerPrintersById.values());
          },

          shouldChooseCheckerOnCheckout() {
            return this.canChooseChecker && this.getCheckerPrintersFromCart().length > 1;
          },

          prepareCheckerSelectionFromCart() {
            const availableCheckerPrinters = this.getCheckerPrintersFromCart();
            const availableIds = availableCheckerPrinters.map((printer) => Number(printer.id));
            const retainedSelection = this.getSelectedCheckerPrinterIds().filter((id) => availableIds.includes(id));

            this.selectedCheckerPrinterIds = retainedSelection.length > 0 ? retainedSelection : availableIds;
          },

          shouldChooseCheckerPrinters() {
            return this.canChooseChecker && (this.receiptData?.checkerPrinters?.length || 0) > 1;
          },

          getSelectedCheckerPrinterIds() {
            return Array.from(new Set((this.selectedCheckerPrinterIds || [])
              .map((value) => Number(value))
              .filter((value) => Number.isInteger(value) && value > 0)));
          },

          async printCheckerAndNavigate(type, url) {
            if (type === 'checker' && this.shouldChooseCheckerPrinters() && this.getSelectedCheckerPrinterIds().length === 0) {
              this.showToastMessage('Pilih minimal satu printer checker.', 'error');
              return;
            }

            if (this.checkerPrinted[type]) {
              this.authPending = {
                type,
                url,
              };
              this.authCode = '';
              this.authError = '';
              this.showAuthModal = true;
              return;
            }
            const printed = await this._doPrintChecker(type, false);
          },

          async verifyAndPrint() {
            if (this.authCode.length !== 4 || this.isVerifyingAuth) {
              return;
            }
            this.isVerifyingAuth = true;
            this.authError = '';
            try {
              const response = await fetch(posRoutes.verifyAuthCode, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
                body: JSON.stringify({
                  code: this.authCode
                }),
              });
              const data = await response.json();
              if (data.valid) {
                const {
                  type,
                  url,
                } = this.authPending;
                this.showAuthModal = false;
                this.authCode = '';
                this.authPending = null;
                const printed = await this._doPrintChecker(type, true);
                if (printed && url) {
                  setTimeout(() => {
                    window.location.href = url;
                  }, 250);
                }
              } else {
                this.authError = 'PIN tidak valid. Periksa kembali kode harian Anda.';
              }
            } catch (e) {
              this.authError = 'Terjadi kesalahan. Coba lagi.';
            } finally {
              this.isVerifyingAuth = false;
            }
          },

          async _doPrintChecker(type, isReprint) {
            const d = this.receiptData;
            const selectedCheckerPrinterIds = this.getSelectedCheckerPrinterIds();

            if (type === 'cashier') {
              this.checkerPrinted[type] = true;
              return await this.printReceipt('cashier');
            }

            const items = d ?
              (type === 'checker' ?
                d.items.filter((item) => {
                  if (!this.shouldChooseCheckerPrinters()) {
                    return true;
                  }

                  const assignedIds = Array.isArray(item.assigned_checker_printer_ids) ?
                    item.assigned_checker_printer_ids.map((id) => Number(id)) : [];

                  if (assignedIds.length === 0) {
                    return false;
                  }

                  return assignedIds.some((id) => selectedCheckerPrinterIds.includes(id));
                }) :
                d.items.filter(i =>
                  Array.isArray(i.assigned_printer_types) && i.assigned_printer_types.includes(type),
                )) : [];

            if (!d || items.length === 0) {
              this.showToastMessage('Tidak ada item untuk tipe cetak ini.', 'error');
              return false;
            }

            const serverPrinted = await this.printReceipt(type);
            if (serverPrinted) {
              this.checkerPrinted[type] = true;
              return true;
            }

            this.checkerPrinted[type] = true;
            const titleMap = {
              kitchen: 'KITCHEN ORDER',
              bar: 'BAR ORDER',
              checker: 'ORDER CHECKER'
            };
            const title = titleMap[type] ?? type.toUpperCase();
            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
              '&': '&amp;',
              '<': '&lt;',
              '>': '&gt;',
              '"': '&quot;',
              "'": '&#39;',
            } [char]));
            const rows = items.map(i => {
              const noteText = String(i.notes || '').trim();
              const noteRow = noteText ?
                '<tr><td colspan="2" style="padding:0 0 4px 10px;font-weight:700;font-size:12px">NOTE: ' + escapeHtml(noteText) + '</td></tr>' :
                '';

              return '<tr><td style="padding:4px 0;font-weight:700;font-size:13px">' + escapeHtml(i.name) + '</td><td style="text-align:right;padding:4px 0;font-weight:700;font-size:13px"><b>' + i.quantity + '</b></td></tr>' + noteRow;
            }).join('');
            const css = 'body{font-family:Arial,monospace;font-size:13px;font-weight:600;margin:0;padding:16px;}' +
              'table{width:100%;border-collapse:collapse;}' +
              '.sep{border:none;border-top:1px dashed #000;margin:8px 0;}' +
              'th{text-align:left;font-size:12px;font-weight:700;border-bottom:1px solid #000;padding:2px 0;}' +
              'th:last-child{text-align:right;}';
            const watermarkHtml = isReprint ?
              '<p style="text-align:center;font-size:14px;font-weight:bold;color:#dc2626;border:2px solid #dc2626;padding:4px;margin-bottom:8px;letter-spacing:2px;">CETAK ULANG</p>' :
              '';
            const body = watermarkHtml +
              '<h3 style="text-align:center;margin:0 0 6px">' + title + '</h3>' +
              '<hr class="sep">' +
              '<p style="margin:2px 0">No: <b>' + d.orderNumber + '</b></p>' +
              '<p style="margin:2px 0">Tanggal: ' + d.printedAt + '</p>' +
              '<p style="margin:2px 0">Kasir: ' + this.cashier + '</p>' +
              '<hr class="sep">' +
              '<p style="margin:2px 0">Pelanggan: <b>' + d.customerName + '</b></p>' +
              '<p style="margin:2px 0">Meja: <b>' + d.tableDisplay + '</b></p>' +
              '<hr class="sep">' +
              '<table><thead><tr><th>Item</th><th style="text-align:right">Qty</th></tr></thead><tbody>' + rows + '</tbody></table>' +
              '<hr class="sep">';
            const html = '<html><head><title>' + title + '</' + 'title><style>' + css + '</' + 'style></' + 'head><body>' + body + '</' + 'body></' + 'html>';
            this._printHtml(html);
            this.showToastMessage('Printer server tidak merespons. Menggunakan print browser.', 'error');
            return true;
          },

          async printReceipt(type = 'cashier') {
            if (!this.receiptData) {
              return false;
            }

            const orderId = Number(this.receiptData.orderId || 0);

            if (orderId > 0) {
              try {
                const response = await fetch(`${posRoutes.printReceiptBase}/${orderId}`, {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    Accept: 'application/json',
                  },
                  body: JSON.stringify({
                    type,
                    checker_printer_ids: type === 'checker' && this.shouldChooseCheckerPrinters() ? this.getSelectedCheckerPrinterIds() : undefined,
                  }),
                });

                const data = await response.json();

                if (response.ok && data.success) {
                  this.showToastMessage(data.message || 'Dokumen berhasil dikirim ke printer.', 'success');

                  return true;
                }

                this.showToastMessage(data.message || 'Gagal kirim ke printer server. Menampilkan print browser sebagai cadangan.', 'error');
              } catch (error) {
                this.showToastMessage('Gagal kirim ke printer server. Menampilkan print browser sebagai cadangan.', 'error');
              }
            }

            if (type !== 'cashier') {
              return false;
            }

            const d = this.receiptData;
            const rows = d.items.map(i =>
              '<tr><td style="padding:3px 0">' + i.name + '</td><td style="text-align:right;padding:3px 0">' + i.quantity + 'x</td><td style="text-align:right;padding:3px 0">Rp ' + new Intl.NumberFormat('id-ID').format(i.price * i.quantity) + '</td></tr>'
            ).join('');
            const css = 'body{font-family:monospace;font-size:12px;margin:0;padding:16px;}' +
              'table{width:100%;border-collapse:collapse;}' +
              '.sep{border:none;border-top:1px dashed #000;margin:8px 0;}' +
              'th{text-align:left;font-size:11px;border-bottom:1px solid #000;padding:2px 0;}';
            const body = '<h3 style="text-align:center;margin:0 0 6px">STRUK PEMBAYARAN</h3>' +
              '<hr class="sep">' +
              '<p style="margin:2px 0">No: <b>' + d.orderNumber + '</b></p>' +
              '<p style="margin:2px 0">Tanggal: ' + d.printedAt + '</p>' +
              '<p style="margin:2px 0">Kasir: ' + this.cashier + '</p>' +
              '<p style="margin:2px 0">Pelanggan: ' + d.customerName + '</p>' +
              '<p style="margin:2px 0">Meja: ' + d.tableDisplay + '</p>' +
              '<hr class="sep">' +
              '<table><thead><tr><th>Item</th><th style="text-align:right">Qty</th><th style="text-align:right">Harga</th></tr></thead><tbody>' + rows + '</tbody></table>' +
              '<hr class="sep">' +
              ((d.itemsTotal || 0) > 0 ? '<p style="text-align:right;margin:2px 0">Subtotal: ' + this.formatCurrency(d.itemsTotal || 0) + '</p>' : '') +
              ((d.discountAmount || 0) > 0 ? '<p style="text-align:right;margin:2px 0">Diskon: -' + this.formatCurrency(d.discountAmount || 0) + '</p>' : '') +
              ((d.tax || 0) > 0 ? '<p style="text-align:right;margin:2px 0">PB1 (' + (d.taxPercentage || 0) + '%): ' + this.formatCurrency(d.tax || 0) + '</p>' : '') +
              ((d.serviceCharge || 0) > 0 ? '<p style="text-align:right;margin:2px 0">Service Charge (' + (d.serviceChargePercentage || 0) + '%): ' + this.formatCurrency(d.serviceCharge || 0) + '</p>' : '') +
              '<p style="text-align:right;font-weight:bold">TOTAL: ' + d.formattedTotal + '</p>' +
              '<hr class="sep">' +
              '<p style="text-align:center;margin-top:8px">Terima kasih!</p>';
            const html = '<html><head><title>Struk</' + 'title><style>' + css + '</' + 'style></' + 'head><body>' + body + '</' + 'body></' + 'html>';
            this._printHtml(html);
            return true;
          },

          printWalkInDraftReceipt() {
            if (this.checkoutForm.customer_type !== 'walk-in') {
              return;
            }

            if (!this.validateWalkInPaymentFields()) {
              return;
            }

            const payload = {
              customer_name: this.checkoutForm.customerName || 'Walk-in',
              items: this.cart.map((item) => ({
                name: item.name,
                qty: Number(item.quantity || 0),
                price: Number(item.price || 0),
                subtotal: Number(item.price || 0) * Number(item.quantity || 0),
              })),
              subtotal: Number(this.cartTotal || 0),
              discount_amount: Number(this.discountAmount() || 0),
              tax: Number(this.calculatedTax() || 0),
              tax_percentage: Number(this.posCharges.taxPercentage || 0),
              service_charge: Number(this.calculatedServiceCharge() || 0),
              service_charge_percentage: Number(this.posCharges.serviceChargePercentage || 0),
              grand_total: Number(this.payableTotal() || 0),
              payment_mode: this.checkoutForm.payment_mode || 'normal',
              foc_comp_payment_method: this.checkoutForm.foc_comp_payment_method || '',
              payment_method: this.checkoutForm.payment_method || 'cash',
              payment_reference_number: this.checkoutForm.payment_reference_number || '',
              split_cash_amount: Number(this.checkoutForm.split_cash_amount || 0),
              split_non_cash_amount: Number(this.checkoutForm.split_non_cash_amount || 0),
              split_non_cash_method: this.checkoutForm.split_non_cash_method || '',
              split_non_cash_reference_number: this.checkoutForm.split_non_cash_reference_number || '',
              split_second_non_cash_amount: Number(this.checkoutForm.split_second_non_cash_amount || 0),
              split_second_non_cash_method: this.checkoutForm.split_second_non_cash_method || '',
              split_second_non_cash_reference_number: this.checkoutForm.split_second_non_cash_reference_number || '',
            };

            fetch(posRoutes.printWalkInDraftReceipt, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                  'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
              })
              .then(async (response) => {
                const data = await response.json();

                if (!response.ok || !data.success) {
                  throw new Error(data.message || 'Gagal mencetak draft struk.');
                }

                this.showToastMessage(data.message || 'Draft struk berhasil dikirim ke printer.', 'success');
              })
              .catch((error) => {
                this.showToastMessage(error.message || 'Gagal mencetak draft struk.', 'error');
              });
          },

          _printHtml(html) {
            const iframe = document.createElement('iframe');
            iframe.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:340px;height:500px;border:none;visibility:hidden;';
            document.body.appendChild(iframe);
            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
            iframeDoc.open();
            iframeDoc.write(html);
            iframeDoc.close();
            iframe.contentWindow.focus();
            setTimeout(() => {
              iframe.contentWindow.print();
              let removed = false;
              const cleanup = () => {
                if (!removed && document.body.contains(iframe)) {
                  removed = true;
                  document.body.removeChild(iframe);
                }
              };
              iframe.contentWindow.onafterprint = cleanup;
              setTimeout(cleanup, 120000);
            }, 250);
          },

          showToastMessage(message, type = 'success') {
            this.toastMessage = message;
            this.toastType = type;
            this.showToast = true;
            setTimeout(() => {
              this.showToast = false;
            }, 3000);
          },

          async openHistoryModal() {
            this.showHistoryModal = true;
            this.historyLoading = true;
            try {
              const res = await fetch('{{ route('admin.pos.recent-orders') }}', {
                headers: {
                  'X-Requested-With': 'XMLHttpRequest'
                },
              });
              const data = await res.json();
              this.recentOrders = data.orders ?? [];
            } catch (e) {
              this.showToastMessage('Gagal memuat riwayat transaksi.', 'error');
              this.showHistoryModal = false;
            } finally {
              this.historyLoading = false;
            }
          },

          formatHistoryCurrency(amount) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
          },
        }));
      });
    </script>
    <!-- History Modal -->
    <div x-show="showHistoryModal"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;"
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"
           @click="showHistoryModal = false"></div>
      <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md flex flex-col max-h-[85vh]">

        <!-- Header -->
        <div class="flex items-start justify-between px-6 pt-6 pb-4 flex-shrink-0">
          <div>
            <h2 class="text-lg font-bold text-gray-900">&#128221; Transaksi Terakhir</h2>
            <p class="text-xs text-gray-400 mt-0.5">Klik transaksi untuk mencetak ulang</p>
          </div>
          <button @click="showHistoryModal = false"
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

        <!-- Loading -->
        <div x-show="historyLoading"
             class="flex items-center justify-center py-12">
          <svg class="w-7 h-7 text-gray-400 animate-spin"
               fill="none"
               viewBox="0 0 24 24">
            <circle class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"></circle>
            <path class="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8v8H4z"></path>
          </svg>
        </div>

        <!-- Order List -->
        <div x-show="!historyLoading"
             class="overflow-y-auto flex-1 px-4 pb-2 space-y-2">
          <template x-if="recentOrders.length === 0">
            <p class="text-center text-sm text-gray-400 py-10">Belum ada transaksi.</p>
          </template>
          <template x-for="order in recentOrders"
                    :key="order.id">
            <div class="flex items-start gap-3 p-4 rounded-xl border border-gray-100 hover:bg-gray-50 cursor-pointer transition">
              <!-- Icon -->
              <div class="w-9 h-9 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg class="w-4 h-4 text-green-600"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
              </div>
              <!-- Content -->
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-0.5">
                  <span class="text-sm font-bold text-gray-900"
                        x-text="order.order_number"></span>
                  <span class="px-1.5 py-0.5 text-xs font-semibold rounded bg-blue-100 text-blue-700"
                        x-text="order.type"></span>
                </div>
                <p class="text-xs text-gray-400 mb-2"
                   x-text="order.ordered_at"></p>
                <div class="flex items-center gap-1 mb-0.5">
                  <svg class="w-3 h-3 text-blue-400 flex-shrink-0"
                       fill="none"
                       stroke="currentColor"
                       viewBox="0 0 24 24">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                  <span class="text-xs text-blue-600 font-medium truncate"
                        x-text="order.customer_name"></span>
                </div>
                <div class="flex items-center gap-1">
                  <svg class="w-3 h-3 text-gray-400 flex-shrink-0"
                       fill="none"
                       stroke="currentColor"
                       viewBox="0 0 24 24">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                  <span class="text-xs text-gray-500 truncate"
                        x-text="order.area + ' - Meja ' + order.table"></span>
                </div>
                <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100">
                  <span class="text-xs text-gray-400"
                        x-text="order.items_count + ' item'"></span>
                  <span class="text-sm font-bold text-gray-900"
                        x-text="formatHistoryCurrency(order.total)"></span>
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- Footer -->
        <div class="px-4 py-4 flex-shrink-0 border-t border-gray-100">
          <button @click="showHistoryModal = false"
                  class="w-full flex items-center justify-center gap-2 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
            <svg class="w-4 h-4"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M6 18L18 6M6 6l12 12" />
            </svg>
            Tutup
          </button>
        </div>
      </div>
    </div>

  </div>{{-- /x-data="posApp" --}}

</x-app-layout>
