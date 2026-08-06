<x-app-layout title="Manajemen Menu">
  <div class="p-4 sm:p-6"
       x-data="menuForm()">

    <!-- Header -->
    <div class="flex items-center gap-3 mb-6">
      <div class="w-10 h-10 bg-slate-800 rounded-xl flex items-center justify-center">
        <svg class="w-5 h-5 text-white"
             fill="none"
             stroke="currentColor"
             viewBox="0 0 24 24">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Menu</h1>
        <p class="text-sm text-gray-500">Kelola daftar menu dan buat menu baru untuk Accurate</p>
      </div>
    </div>

    {{-- Tabs Navigation --}}
    <div class="mb-6 flex border-b border-gray-200">
      <button type="button"
              @click="activeTab = 'list'"
              :class="activeTab === 'list' ? '-mb-px border-b-2 border-slate-800 text-slate-900 font-semibold' : 'text-gray-500 hover:text-gray-700'"
              class="px-5 py-3 text-sm transition">
        Daftar Menu
      </button>
      <button type="button"
              @click="activeTab = 'create'"
              :class="activeTab === 'create' ? '-mb-px border-b-2 border-slate-800 text-slate-900 font-semibold' : 'text-gray-500 hover:text-gray-700'"
              class="px-5 py-3 text-sm transition">
        Buat Menu
      </button>
    </div>

    {{-- Tab: Buat Menu --}}
    <div x-show="activeTab === 'create'">

      <!-- Notification -->
      <div x-show="notification.show"
           x-transition:enter="transition ease-out duration-300"
           x-transition:enter-start="opacity-0 -translate-y-1"
           x-transition:enter-end="opacity-100 translate-y-0"
           x-transition:leave="transition ease-in duration-200"
           x-transition:leave-start="opacity-100 translate-y-0"
           x-transition:leave-end="opacity-0 -translate-y-1"
           :class="notification.type === 'success' ? 'bg-green-50 border-green-400 text-green-800' : 'bg-red-50 border-red-400 text-red-800'"
           class="mb-5 px-4 py-3 border rounded-lg text-sm"
           style="display: none;">
        <span x-text="notification.message"></span>
      </div>

      <div class="bg-white border border-gray-200 rounded-xl p-6 space-y-5">

        <!-- No & Name -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Mode Kode Item <span class="text-red-500">*</span></label>
            <select x-model="form.code_mode"
                    @change="onCodeModeChange()"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-slate-500 focus:border-transparent">
              <option value="manual">Input Manual</option>
              <option value="auto">Otomatis dari Accurate</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Kode Item <span class="text-red-500">*</span></label>
            <input type="text"
                   x-model="form.no"
                   :disabled="form.code_mode === 'auto'"
                   :placeholder="form.code_mode === 'auto' ? 'Otomatis dibuat oleh Accurate' : 'Contoh: MENU-001'"
                   :class="form.code_mode === 'auto' ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : ''"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-slate-500 focus:border-transparent">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Menu <span class="text-red-500">*</span></label>
            <input type="text"
                   x-model="form.name"
                   placeholder="Contoh: Nasi Goreng Spesial"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-slate-500 focus:border-transparent">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama di POS <span class="text-gray-400 font-normal text-xs">(opsional)</span></label>
            <input type="text"
                   x-model="form.pos_name"
                   :placeholder="form.name || 'Sama dengan Nama Menu'"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-slate-500 focus:border-transparent">
          </div>
        </div>

        <!-- Item Type, Category, Unit, Price -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipe Item <span class="text-red-500">*</span></label>
            <select x-model="form.item_type"
                    @change="onItemTypeChange()"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-slate-500 focus:border-transparent">
              <option value="GROUP">GROUP</option>
              <option value="INVENTORY">INVENTORY</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Kategori <span class="text-red-500">*</span></label>
            <input type="text"
                   x-model="form.category_type"
                   list="inventoryCategoryOptions"
                   placeholder="Ketik kategori..."
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-slate-500 focus:border-transparent">
            <datalist id="inventoryCategoryOptions">
              @foreach ($inventoryCategoryTypes as $cat)
                <option value="{{ $cat }}"></option>
              @endforeach
            </datalist>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Satuan <span class="text-red-500">*</span></label>
            <input type="text"
                   x-model="form.unit"
                   placeholder="Contoh: porsi"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-slate-500 focus:border-transparent">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Harga Jual (Rp) <span class="text-red-500">*</span></label>
            <input type="text"
                   :value="formatRupiahInput(form.selling_price)"
                   @input="onSellingPriceInput($event)"
                   placeholder="Rp 0"
                   inputmode="numeric"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-slate-500 focus:border-transparent">
          </div>
        </div>

        <!-- Category Main -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Kategori Utama <span class="text-gray-400 font-normal text-xs">(opsional)</span></label>
          <select x-model="form.category_main"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-slate-500 focus:border-transparent">
            <option value="">Pilih kategori utama...</option>
            <option value="food">Food</option>
            <option value="alcohol">Alcohol</option>
            <option value="beverage">Beverage</option>
            <option value="cigarette">Cigarette</option>
            <option value="breakage">Breakage</option>
            <option value="room">Room</option>
            <option value="staff_meal">Staff Meal</option>
            <option value="compliment">Compliment</option>
            <option value="foc">FOC</option>
            <option value="LD">LD</option>
          </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-700">
            <input type="checkbox"
                   x-model="form.include_tax"
                   class="h-4 w-4 rounded border-gray-300 text-slate-700 focus:ring-slate-500">
            Pakai PB1
          </label>
          <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-700">
            <input type="checkbox"
                   x-model="form.include_service_charge"
                   class="h-4 w-4 rounded border-gray-300 text-slate-700 focus:ring-slate-500">
            Pakai Service Charge
          </label>
        </div>

        <div>
          <label class="mb-2 block text-sm font-medium text-gray-700">Target Printer</label>
          @if ($printers->isEmpty())
            <div class="rounded-lg border border-dashed border-gray-300 px-4 py-3 text-sm text-gray-400">
              Belum ada printer aktif.
            </div>
          @else
            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
              @foreach ($printers as $printer)
                <label class="flex items-start gap-2 rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-700">
                  <input type="checkbox"
                         :value="{{ $printer->id }}"
                         x-model="form.printer_ids"
                         class="mt-0.5 h-4 w-4 rounded border-gray-300 text-slate-700 focus:ring-slate-500">
                  <span>
                    <span class="block font-medium text-gray-800">{{ $printer->name }}</span>
                    <span class="text-xs text-gray-400">{{ $printer->location ?: 'Tanpa lokasi' }}</span>
                  </span>
                </label>
              @endforeach
            </div>
          @endif
        </div>

        <!-- Ingredients -->
        <div x-show="form.item_type === 'GROUP'">
          <div class="flex items-center justify-between mb-2">
            <label class="text-sm font-medium text-gray-700">Bahan-bahan (Detail Group)</label>
            <button type="button"
                    @click="addIngredient()"
                    class="flex items-center gap-1 text-xs font-medium text-slate-700 border border-slate-300 rounded-lg px-2.5 py-1.5 hover:bg-slate-50 transition">
              <svg class="w-3.5 h-3.5"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 4v16m8-8H4" />
              </svg>
              Tambah Bahan
            </button>
          </div>

          <!-- Table header -->
          <div x-show="form.detail_group.length > 0"
               class="grid grid-cols-12 gap-2 px-1 mb-1.5">
            <div class="col-span-7 text-xs font-medium text-gray-500">Bahan</div>
            <div class="col-span-4 text-xs font-medium text-gray-500 text-center">Jumlah</div>
            <div class="col-span-1"></div>
          </div>

          <div class="space-y-2">
            <template x-for="(row, index) in form.detail_group"
                      :key="index">
              <div class="grid grid-cols-12 gap-2 items-center">
                <div class="col-span-7">
                  <select x-model="row.inventory_item_id"
                          @change="onRowItemChange(index)"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500 focus:border-transparent bg-white">
                    <option value="">-- Pilih Bahan --</option>
                    <template x-for="item in inventoryItems"
                              :key="item.id">
                      <option :value="item.id"
                              x-text="item.name + (item.unit ? ' (' + item.unit + ')' : '')"></option>
                    </template>
                  </select>
                </div>
                <div class="col-span-4">
                  <input type="number"
                         x-model="row.quantity"
                         placeholder="1"
                         min="1"
                         step="1"
                         class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500 focus:border-transparent text-center">
                </div>
                <div class="col-span-1 flex justify-center">
                  <button type="button"
                          @click="removeRow(index)"
                          class="p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition">
                    <svg class="w-4 h-4"
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
              </div>
            </template>

            <div x-show="form.detail_group.length === 0"
                 class="text-center py-5 text-sm text-gray-400 border border-dashed border-gray-300 rounded-lg">
              Belum ada bahan. Klik "Tambah Bahan" untuk menambahkan.
            </div>
          </div>
        </div>

        <!-- Error -->
        <p x-show="formError"
           x-text="formError"
           class="text-sm text-red-600"
           style="display: none;"></p>
      </div>

      <!-- Footer Actions -->
      <div class="mt-5 flex items-center justify-between">
        <button type="button"
                @click="resetForm()"
                class="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
          Reset
        </button>
        <button type="button"
                @click="submitForm()"
                :disabled="saving"
                class="flex items-center gap-2 px-5 py-2 text-sm font-medium text-white bg-slate-800 hover:bg-slate-900 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
          <svg x-show="saving"
               class="w-4 h-4 animate-spin"
               fill="none"
               viewBox="0 0 24 24"
               style="display: none;">
            <circle class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"></circle>
            <path class="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span x-text="saving ? 'Menyimpan ke Accurate...' : 'Simpan ke Accurate'"></span>
        </button>
      </div>

    </div>{{-- end tab: buat menu --}}

    {{-- Tab: Daftar Menu --}}
    <div x-show="activeTab === 'list'">

      <div class="mt-2 space-y-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <h2 class="text-xl font-bold text-gray-900">Daftar Menu</h2>
            <p class="text-sm text-gray-500">Menu ditampilkan berdasarkan kategori yang ditandai sebagai menu.</p>
          </div>

          <form method="GET"
                action="{{ route('admin.menus.index') }}"
                class="flex w-full gap-2">
            <input type="text"
                   name="search"
                   value="{{ $search ?? '' }}"
                   placeholder="Cari nama menu / nama POS / kode..."
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-slate-500 focus:ring-slate-500 lg:w-[36rem]">
            <button type="submit"
                    class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-900">
              Cari
            </button>
            @if (filled($search ?? null))
              <a href="{{ route('admin.menus.index') }}"
                 class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                Reset
              </a>
            @endif
          </form>
        </div>

        @if (filled($search ?? null))
          <p class="text-sm text-gray-500">Hasil pencarian untuk: <span class="font-semibold text-gray-700">{{ $search }}</span></p>
        @endif

        @if ($menuCategoryTypes->isEmpty())
          <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-10 text-center text-sm text-gray-500">
            @if (filled($search ?? null))
              Tidak ada menu yang cocok dengan pencarian.
            @else
              Belum ada kategori menu aktif, jadi daftar menu belum dapat ditampilkan.
            @endif
          </div>
        @else
          <div class="space-y-5">
            @foreach ($menuCategoryTypes as $categoryType)
              @php $menus = $menusByCategory->get($categoryType, collect()) @endphp
              <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-5 py-4">
                  <div>
                    <h3 class="text-base font-bold text-gray-900">{{ $categoryType }}</h3>
                    <p class="text-xs text-gray-500">{{ $menus->count() }} menu</p>
                  </div>
                  <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                    Menu Aktif
                  </span>
                </div>

                @if ($menus->isEmpty())
                  <div class="px-5 py-8 text-center text-sm text-gray-500">
                    Belum ada menu pada kategori ini.
                  </div>
                @else
                  <div class="grid gap-3 p-4 grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4">
                    @foreach ($menus as $menu)
                      <article class="cursor-pointer rounded-xl border border-gray-200 bg-white p-3.5 transition hover:border-slate-400 hover:shadow-md"
                               onclick='openMenuModal(@json($menu))'>
                        <div class="flex items-start justify-between gap-2">
                          <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-900 leading-tight line-clamp-2">{{ $menu->name }}</p>
                            @if ($menu->pos_name && $menu->pos_name !== $menu->name)
                              <p class="mt-0.5 text-[11px] text-blue-600 truncate"
                                 title="Nama POS: {{ $menu->pos_name }}">POS: {{ $menu->pos_name }}</p>
                            @endif
                            <p class="mt-0.5 text-[11px] text-gray-400">{{ $menu->code }}</p>
                          </div>
                        </div>
                        <div class="mt-3 flex items-center justify-between">
                          <span class="text-[11px] text-gray-400">Rp</span>
                          <span class="text-xs font-bold text-emerald-700">{{ number_format((float) $menu->price, 0, ',', '.') }}</span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1">
                          @if ($menu->category_main)
                            <span class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold bg-indigo-100 text-indigo-700">{{ strtoupper($menu->category_main) }}</span>
                          @endif
                          <span data-card-tax="{{ $menu->id }}"
                                class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold {{ $menu->include_tax ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-400' }}">PB1</span>
                          <span data-card-sc="{{ $menu->id }}"
                                class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold {{ $menu->include_service_charge ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-400' }}">SC</span>
                          <span data-card-group="{{ $menu->id }}"
                                class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold {{ $menu->is_item_group ? 'bg-violet-100 text-violet-700' : 'bg-gray-100 text-gray-400' }}">{{ $menu->is_item_group ? 'GROUP' : 'ITEM' }}</span>
                          @if ($menu->is_item_group)
                            <span data-card-groupsoldout="{{ $menu->id }}"
                                  class="rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $menu->is_group_sold_out ? 'bg-red-100 text-red-700 border border-red-200' : 'bg-emerald-100 text-emerald-700 border border-emerald-200' }}">{{ $menu->is_group_sold_out ? 'SOLD OUT' : 'AVAILABLE' }}</span>
                          @endif
                          <span data-card-visible="{{ $menu->id }}"
                                class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold {{ $menu->is_visible_in_pos ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-400' }}">{{ $menu->is_visible_in_pos ? 'VISIBLE' : 'HIDDEN' }}</span>
                          <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500">{{ $menu->unit ?: '-' }}</span>
                        </div>
                      </article>
                    @endforeach
                  </div>
                @endif
              </section>
            @endforeach
          </div>
        @endif
      </div>

    </div>{{-- end tab: daftar menu --}}

    {{-- Combined Menu Edit Modal --}}
    <div id="menuModal"
         class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
      <div class="w-full max-w-lg max-h-[85vh] overflow-y-auto rounded-2xl bg-white shadow-xl">

        {{-- Modal Header --}}
        <div class="flex items-start justify-between border-b border-gray-200 px-5 py-4">
          <div>
            <h2 id="menuModalName"
                class="text-base font-bold text-gray-900"></h2>
            <p id="menuModalMeta"
               class="text-xs text-gray-400 mt-0.5"></p>
          </div>
          <button type="button"
                  onclick="closeMenuModal()"
                  class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
            <svg class="h-5 w-5"
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

        {{-- Price Row --}}
        <div class="flex items-center justify-between px-5 py-3 bg-gray-50 border-b border-gray-100">
          <span class="text-sm text-gray-500">Harga jual</span>
          <span id="menuModalPrice"
                class="text-sm font-bold text-emerald-700"></span>
        </div>

        {{-- POS Name Row --}}
        <div class="flex items-center justify-between gap-3 px-5 py-3 border-b border-gray-100">
          <span class="text-sm text-gray-500 shrink-0">Nama di POS</span>
          <div class="flex items-center gap-2 min-w-0">
            <input type="text"
                   id="menuModalPosNameInput"
                   class="border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm focus:ring-2 focus:ring-slate-500 focus:border-transparent w-44"
                   placeholder="Sama dengan nama menu">
            <button type="button"
                    id="menuModalPosNameSave"
                    onclick="savePosName()"
                    class="shrink-0 px-2.5 py-1.5 text-xs font-medium text-white bg-slate-700 hover:bg-slate-800 rounded-lg transition disabled:opacity-50">
              Simpan
            </button>
          </div>
        </div>

        {{-- Category Main Row --}}
        <div class="flex items-center justify-between gap-3 px-5 py-3 border-b border-gray-100">
          <span class="text-sm text-gray-500 shrink-0">Kategori Utama</span>
          <div class="flex items-center gap-2 min-w-0">
            <select id="menuModalCategoryMainSelect"
                    class="border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm focus:ring-2 focus:ring-slate-500 focus:border-transparent w-44">
              <option value="">Pilih kategori...</option>
              <option value="food">Food</option>
              <option value="alcohol">Alcohol</option>
              <option value="beverage">Beverage</option>
              <option value="cigarette">Cigarette</option>
              <option value="breakage">Breakage</option>
              <option value="room">Room</option>
              <option value="staff_meal">Staff Meal</option>
              <option value="compliment">Compliment</option>
              <option value="foc">FOC</option>
              <option value="LD">LD</option>
            </select>
            <button type="button"
                    id="menuModalCategoryMainSave"
                    onclick="saveCategoryMain()"
                    class="shrink-0 px-2.5 py-1.5 text-xs font-medium text-white bg-slate-700 hover:bg-slate-800 rounded-lg transition disabled:opacity-50">
              Simpan
            </button>
          </div>
        </div>

        {{-- POS Visibility Row --}}
        <div class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3">
          <div class="flex items-center gap-2.5">
            <svg class="h-4 w-4 text-emerald-500"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 12H9m12 0A9 9 0 1112 3a9 9 0 019 9z" />
            </svg>
            <div>
              <p class="text-sm font-medium text-gray-800">Visible di POS</p>
              <p class="text-xs text-gray-400">Sembunyikan item ini dari daftar penjualan POS</p>
            </div>
          </div>
          <button type="button"
                  id="modalVisibleToggle"
                  class="flex cursor-pointer items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 transition"
                  data-tax-toggle-button="1"
                  data-field="is_visible_in_pos">
          </button>
        </div>

        {{-- Item Group Sold Out Row --}}
        <div id="modalGroupSoldOutContainer"
             class="flex items-center justify-between rounded-xl border border-red-200 bg-red-50/50 px-4 py-3 mx-5 mt-3">
          <div class="flex items-center gap-2.5">
            <svg class="h-4 w-4 text-red-600"
                 fill="none"
                 stroke="currentColor"
                 viewBox="0 0 24 24">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
            </svg>
            <div>
              <p class="text-sm font-bold text-red-800">Status Sold Out (Item Group)</p>
              <p class="text-xs text-red-600">Tandai menu group ini sebagai Sold Out di POS</p>
            </div>
          </div>
          <button type="button"
                  id="modalGroupSoldOutToggle"
                  class="flex cursor-pointer items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-bold ring-1 transition"
                  data-tax-toggle-button="1"
                  data-field="is_group_sold_out">
          </button>
        </div>

        {{-- Charge Toggles --}}
        <div class="px-5 py-4 space-y-3">
          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Pengaturan Biaya</p>

          <div class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3">
            <div class="flex items-center gap-2.5">
              <svg class="h-4 w-4 text-amber-500"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
              </svg>
              <div>
                <p class="text-sm font-medium text-gray-800">PB1</p>
                <p class="text-xs text-gray-400">Pajak Pertambahan Nilai</p>
              </div>
            </div>
            <button type="button"
                    id="modalTaxToggle"
                    class="flex cursor-pointer items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 transition"
                    data-tax-toggle-button="1"
                    data-field="include_tax">
            </button>
          </div>

          <div class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3">
            <div class="flex items-center gap-2.5">
              <svg class="h-4 w-4 text-blue-500"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
              <div>
                <p class="text-sm font-medium text-gray-800">Service Charge</p>
                <p class="text-xs text-gray-400">Biaya layanan</p>
              </div>
            </div>
            <button type="button"
                    id="modalServiceToggle"
                    class="flex cursor-pointer items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 transition"
                    data-tax-toggle-button="1"
                    data-field="include_service_charge">
            </button>
          </div>

          <div class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3">
            <div class="flex items-center gap-2.5">
              <svg class="h-4 w-4 text-violet-500"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M4 7h16M4 12h16M4 17h16" />
              </svg>
              <div>
                <p class="text-sm font-medium text-gray-800">Item Group</p>
                <p class="text-xs text-gray-400">Aktifkan jika item ini adalah group/bundle</p>
              </div>
            </div>
            <button type="button"
                    id="modalItemGroupToggle"
                    class="flex cursor-pointer items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 transition"
                    data-tax-toggle-button="1"
                    data-field="is_item_group">
            </button>
          </div>

          <div id="modalCountPortionWrap"
               class="hidden items-center justify-between rounded-xl border border-gray-200 px-4 py-3">
            <div class="flex items-center gap-2.5">
              <svg class="h-4 w-4 text-emerald-500"
                   fill="none"
                   stroke="currentColor"
                   viewBox="0 0 24 24">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M11 5a1 1 0 011-1h6a1 1 0 011 1v14a1 1 0 01-1 1h-6a1 1 0 01-1-1V5zM7 7h.01M7 11h.01M7 15h.01" />
              </svg>
              <div>
                <p class="text-sm font-medium text-gray-800">Count Portion Possible</p>
                <p class="text-xs text-gray-400">Hitung porsi possible berdasarkan komponen item group</p>
              </div>
            </div>
            <button type="button"
                    id="modalCountPortionToggle"
                    class="flex cursor-pointer items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 transition"
                    data-tax-toggle-button="1"
                    data-field="is_count_portion_possible">
            </button>
          </div>
        </div>

        {{-- Detail Group --}}
        <div class="px-5 pb-5">
          <div class="mb-4">
            <div class="mb-3 flex items-center justify-between">
              <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Target Printer</p>
              @if (!$printers->isEmpty())
                <button id="menuModalPrinterSave"
                        type="button"
                        class="rounded bg-slate-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800 disabled:opacity-50">
                  Simpan
                </button>
              @endif
            </div>
            @if ($printers->isEmpty())
              <div class="rounded-lg border border-dashed border-gray-300 px-4 py-3 text-sm text-gray-400">
                Belum ada printer aktif.
              </div>
            @else
              <div id="menuModalPrinterTargets"
                   class="grid gap-2 sm:grid-cols-2">
                @foreach ($printers as $printer)
                  <label class="flex items-start gap-2 rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-700">
                    <input type="checkbox"
                           value="{{ $printer->id }}"
                           data-menu-modal-printer="{{ $printer->id }}"
                           class="mt-0.5 h-4 w-4 rounded border-gray-300 text-slate-700 focus:ring-slate-500">
                    <span>
                      <span class="block font-medium text-gray-800">{{ $printer->name }}</span>
                      <span class="text-xs text-gray-400">{{ $printer->location ?: 'Tanpa lokasi' }}</span>
                    </span>
                  </label>
                @endforeach
              </div>
            @endif
          </div>

          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Detail Group (Bahan-bahan)</p>
          <div id="menuModalDetailLoading"
               class="py-4 text-center text-sm text-gray-400">Memuat...</div>
          <div id="menuModalDetailEmpty"
               class="hidden py-4 text-center text-sm text-gray-400">Tidak ada bahan terdaftar.</div>
          <div id="menuModalDetailTableWrap"
               class="hidden overflow-x-auto rounded-lg border border-gray-100">
            <table class="w-full text-sm">
              <thead class="bg-gray-50">
                <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                  <th class="px-3 py-2">#</th>
                  <th class="px-3 py-2">Bahan</th>
                  <th class="px-3 py-2 text-right">Qty</th>
                  <th class="px-3 py-2">Unit</th>
                </tr>
              </thead>
              <tbody id="menuModalDetailBody"
                     class="divide-y divide-gray-100"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div>

  @push('scripts')
    <script>
      const inventoryItems = @json($inventoryItems);
      const printers = @json($printers);
      const menuDetailRouteTemplate = "{{ route('admin.menus.fetch-detail', ['inventory' => '__INVENTORY__']) }}";
      const taxFlagsRouteTemplate = "{{ route('admin.menus.update-tax-flags', ['inventory' => '__INVENTORY__']) }}";
      const printerTargetsRouteTemplate = "{{ route('admin.menus.update-printer-targets', ['inventory' => '__INVENTORY__']) }}";
      const posNameRouteTemplate = "{{ route('admin.menus.update-pos-name', ['inventory' => '__INVENTORY__']) }}";
      const categoryMainRouteTemplate = "{{ route('admin.menus.update-category-main', ['inventory' => '__INVENTORY__']) }}";

      // ── helpers ────────────────────────────────────────────────────────────
      function applyToggleStyle(el, field, isActive) {
        if (field === 'is_group_sold_out') {
          if (isActive) {
            el.className = 'flex cursor-pointer items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-bold ring-1 transition bg-red-600 text-white ring-red-500 shadow-sm';
            el.textContent = 'SOLD OUT: YES';
          } else {
            el.className = 'flex cursor-pointer items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-bold ring-1 transition bg-emerald-100 text-emerald-800 ring-emerald-300';
            el.textContent = 'SOLD OUT: NO (AVAILABLE)';
          }
          return;
        }
        const activeClasses = field === 'include_tax' ? ['bg-amber-50', 'text-amber-700', 'ring-amber-200'] :
          field === 'include_service_charge' ? ['bg-blue-50', 'text-blue-700', 'ring-blue-200'] :
          field === 'is_count_portion_possible' ? ['bg-emerald-50', 'text-emerald-700', 'ring-emerald-200'] :
          field === 'is_visible_in_pos' ? ['bg-emerald-50', 'text-emerald-700', 'ring-emerald-200'] : ['bg-violet-50', 'text-violet-700', 'ring-violet-200'];
        const inactiveClasses = ['bg-gray-100', 'text-gray-400', 'ring-gray-200'];
        if (isActive) {
          el.classList.add(...activeClasses);
          el.classList.remove(...inactiveClasses);
          el.textContent = field === 'include_tax' ? 'PB1: ON' : field === 'include_service_charge' ? 'SC: ON' : field === 'is_count_portion_possible' ? 'COUNT: ON' : field === 'is_visible_in_pos' ? 'VISIBLE: ON' : 'GROUP: ON';
        } else {
          el.classList.add(...inactiveClasses);
          el.classList.remove(...activeClasses);
          el.textContent = field === 'include_tax' ? 'PB1: OFF' : field === 'include_service_charge' ? 'SC: OFF' : field === 'is_count_portion_possible' ? 'COUNT: OFF' : field === 'is_visible_in_pos' ? 'VISIBLE: OFF' : 'GROUP: OFF';
        }
      }

      function setCountPortionToggleVisibility(isItemGroup) {
        const wrap = document.getElementById('modalCountPortionWrap');
        const groupSoldOutWrap = document.getElementById('modalGroupSoldOutContainer');

        if (wrap instanceof HTMLElement) {
          if (isItemGroup) {
            wrap.classList.remove('hidden');
            wrap.classList.add('flex');
          } else {
            wrap.classList.add('hidden');
            wrap.classList.remove('flex');
          }
        }

        if (groupSoldOutWrap instanceof HTMLElement) {
          if (isItemGroup) {
            groupSoldOutWrap.classList.remove('hidden');
            groupSoldOutWrap.classList.add('flex');
          } else {
            groupSoldOutWrap.classList.add('hidden');
            groupSoldOutWrap.classList.remove('flex');
          }
        }
      }

      async function toggleTaxFlag(itemId, field, value, toggleEl) {
        const url = taxFlagsRouteTemplate.replace('__INVENTORY__', String(itemId));
        const previousValue = !value;
        toggleEl.disabled = true;
        try {
          const response = await fetch(url, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
              'Accept': 'application/json',
            },
            body: JSON.stringify({
              field,
              value
            }),
          });
          const contentType = response.headers.get('content-type') || '';
          if (!response.ok || !contentType.includes('application/json')) throw new Error('Invalid server response');
          const data = await response.json();
          if (!data.success) return;
          const isActive = Boolean(data.value);
          toggleEl.dataset.value = isActive ? '1' : '0';
          toggleEl.setAttribute('aria-pressed', isActive ? 'true' : 'false');
          applyToggleStyle(toggleEl, field, isActive);

          if (field === 'is_item_group') {
            setCountPortionToggleVisibility(isActive);
          }

          if (field === 'is_visible_in_pos') {
            const visibleToggle = document.getElementById('modalVisibleToggle');
            if (visibleToggle) {
              visibleToggle.dataset.value = isActive ? '1' : '0';
              visibleToggle.setAttribute('aria-pressed', isActive ? 'true' : 'false');
              applyToggleStyle(visibleToggle, 'is_visible_in_pos', isActive);
            }
          }

          // Sync the small badge on the card
          const cardBadgeSelector = field === 'include_tax' ?
            `[data-card-tax="${itemId}"]` :
            field === 'include_service_charge' ?
            `[data-card-sc="${itemId}"]` :
            field === 'is_visible_in_pos' ?
            `[data-card-visible="${itemId}"]` :
            field === 'is_group_sold_out' ?
            `[data-card-groupsoldout="${itemId}"]` :
            `[data-card-group="${itemId}"]`;
          const badge = document.querySelector(cardBadgeSelector);
          if (badge) {
            if (field === 'is_group_sold_out') {
              badge.textContent = isActive ? 'SOLD OUT' : 'AVAILABLE';
              badge.className = isActive ?
                'rounded-full px-1.5 py-0.5 text-[10px] font-bold bg-red-100 text-red-700 border border-red-200' :
                'rounded-full px-1.5 py-0.5 text-[10px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200';
            } else if (field === 'is_item_group') {
              badge.textContent = isActive ? 'GROUP' : 'ITEM';
            } else if (field === 'is_visible_in_pos') {
              badge.textContent = isActive ? 'VISIBLE' : 'HIDDEN';
            }

            if (field !== 'is_group_sold_out') {
              if (isActive) {
                if (field === 'include_tax') {
                  badge.classList.add('bg-amber-100', 'text-amber-700');
                  badge.classList.remove('bg-blue-100', 'text-blue-700', 'bg-violet-100', 'text-violet-700', 'bg-gray-100', 'text-gray-400');
                } else if (field === 'include_service_charge') {
                  badge.classList.add('bg-blue-100', 'text-blue-700');
                  badge.classList.remove('bg-amber-100', 'text-amber-700', 'bg-violet-100', 'text-violet-700', 'bg-gray-100', 'text-gray-400');
                } else if (field === 'is_visible_in_pos') {
                  badge.classList.add('bg-emerald-100', 'text-emerald-700');
                  badge.classList.remove('bg-amber-100', 'text-amber-700', 'bg-blue-100', 'text-blue-700', 'bg-violet-100', 'text-violet-700', 'bg-gray-100', 'text-gray-400');
                } else {
                  badge.classList.add('bg-violet-100', 'text-violet-700');
                  badge.classList.remove('bg-amber-100', 'text-amber-700', 'bg-blue-100', 'text-blue-700', 'bg-gray-100', 'text-gray-400');
                }
              } else {
                badge.classList.add('bg-gray-100', 'text-gray-400');
                badge.classList.remove('bg-amber-100', 'text-amber-700', 'bg-blue-100', 'text-blue-700', 'bg-violet-100', 'text-violet-700', 'bg-emerald-100', 'text-emerald-700');
              }
            }
          }
        } catch {
          toggleEl.dataset.value = previousValue ? '1' : '0';
          toggleEl.setAttribute('aria-pressed', previousValue ? 'true' : 'false');
        } finally {
          toggleEl.disabled = false;
        }
      }

      async function syncPrinterTargets(itemId, printerIds) {
        const url = printerTargetsRouteTemplate.replace('__INVENTORY__', String(itemId));

        const response = await fetch(url, {
          method: 'PATCH',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
          },
          body: JSON.stringify({
            printer_ids: printerIds,
          }),
        });

        const contentType = response.headers.get('content-type') || '';
        if (!response.ok || !contentType.includes('application/json')) {
          throw new Error('Invalid server response');
        }

        return response.json();
      }

      document.addEventListener('click', function(event) {
        const toggleEl = event.target instanceof Element ?
          event.target.closest('[data-tax-toggle-button]') : null;
        if (!(toggleEl instanceof HTMLButtonElement)) return;
        event.preventDefault();
        const itemId = toggleEl.getAttribute('data-item-id');
        const field = toggleEl.getAttribute('data-field');
        const currentValue = toggleEl.getAttribute('data-value') === '1';
        if (!itemId || !field) return;
        toggleTaxFlag(itemId, field, !currentValue, toggleEl);
      });

      // ── Menu modal ─────────────────────────────────────────────────────────
      function openMenuModal(menu) {
        const modal = document.getElementById('menuModal');
        document.getElementById('menuModalName').textContent = menu.name;
        document.getElementById('menuModalMeta').textContent = menu.code + (menu.unit ? ' · ' + menu.unit : '');
        document.getElementById('menuModalPrice').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(menu.price || 0);

        const posNameInput = document.getElementById('menuModalPosNameInput');
        posNameInput.value = menu.pos_name || '';
        posNameInput.dataset.itemId = menu.id;
        const posNameSave = document.getElementById('menuModalPosNameSave');
        posNameSave.textContent = 'Simpan';
        posNameSave.disabled = false;

        const categoryMainSelect = document.getElementById('menuModalCategoryMainSelect');
        categoryMainSelect.value = menu.category_main || '';
        categoryMainSelect.dataset.itemId = menu.id;
        const categoryMainSave = document.getElementById('menuModalCategoryMainSave');
        categoryMainSave.textContent = 'Simpan';
        categoryMainSave.disabled = false;

        const taxToggle = document.getElementById('modalTaxToggle');
        taxToggle.dataset.itemId = menu.id;
        taxToggle.dataset.value = menu.include_tax ? '1' : '0';
        taxToggle.setAttribute('aria-pressed', menu.include_tax ? 'true' : 'false');
        applyToggleStyle(taxToggle, 'include_tax', Boolean(menu.include_tax));

        const scToggle = document.getElementById('modalServiceToggle');
        scToggle.dataset.itemId = menu.id;
        scToggle.dataset.value = menu.include_service_charge ? '1' : '0';
        scToggle.setAttribute('aria-pressed', menu.include_service_charge ? 'true' : 'false');
        applyToggleStyle(scToggle, 'include_service_charge', Boolean(menu.include_service_charge));

        const itemGroupToggle = document.getElementById('modalItemGroupToggle');
        itemGroupToggle.dataset.itemId = menu.id;
        itemGroupToggle.dataset.value = menu.is_item_group ? '1' : '0';
        itemGroupToggle.setAttribute('aria-pressed', menu.is_item_group ? 'true' : 'false');
        applyToggleStyle(itemGroupToggle, 'is_item_group', Boolean(menu.is_item_group));

        const groupSoldOutToggle = document.getElementById('modalGroupSoldOutToggle');
        if (groupSoldOutToggle) {
          groupSoldOutToggle.dataset.itemId = menu.id;
          groupSoldOutToggle.dataset.value = menu.is_group_sold_out ? '1' : '0';
          groupSoldOutToggle.setAttribute('aria-pressed', menu.is_group_sold_out ? 'true' : 'false');
          applyToggleStyle(groupSoldOutToggle, 'is_group_sold_out', Boolean(menu.is_group_sold_out));
        }

        const countPortionToggle = document.getElementById('modalCountPortionToggle');
        countPortionToggle.dataset.itemId = menu.id;
        countPortionToggle.dataset.value = menu.is_count_portion_possible ? '1' : '0';
        countPortionToggle.setAttribute('aria-pressed', menu.is_count_portion_possible ? 'true' : 'false');
        applyToggleStyle(countPortionToggle, 'is_count_portion_possible', Boolean(menu.is_count_portion_possible));

        const visibleToggle = document.getElementById('modalVisibleToggle');
        visibleToggle.dataset.itemId = menu.id;
        visibleToggle.dataset.value = menu.is_visible_in_pos ? '1' : '0';
        visibleToggle.setAttribute('aria-pressed', menu.is_visible_in_pos ? 'true' : 'false');
        applyToggleStyle(visibleToggle, 'is_visible_in_pos', Boolean(menu.is_visible_in_pos));

        setCountPortionToggleVisibility(Boolean(menu.is_item_group));

        const modalPrinterTargets = document.getElementById('menuModalPrinterTargets');
        if (modalPrinterTargets) {
          modalPrinterTargets.dataset.selectionTouched = '0';
        }

        const selectedPrinterIdsFromMenu = Array.isArray(menu.printers) ?
          menu.printers.map((printer) => Number(printer?.id || 0)).filter((id) => Number.isInteger(id) && id > 0) : [];

        document.querySelectorAll('[data-menu-modal-printer]').forEach((checkbox) => {
          checkbox.dataset.itemId = menu.id;
          checkbox.checked = selectedPrinterIdsFromMenu.includes(Number(checkbox.value));
        });

        const saveBtn = document.getElementById('menuModalPrinterSave');
        if (saveBtn) {
          saveBtn.dataset.itemId = menu.id;
        }

        // Load detail group
        const loading = document.getElementById('menuModalDetailLoading');
        const empty = document.getElementById('menuModalDetailEmpty');
        const tableWrap = document.getElementById('menuModalDetailTableWrap');
        const body = document.getElementById('menuModalDetailBody');
        loading.classList.remove('hidden');
        empty.classList.add('hidden');
        tableWrap.classList.add('hidden');
        body.innerHTML = '';

        const detailUrl = menuDetailRouteTemplate.replace('__INVENTORY__', String(menu.id));
        fetch(detailUrl, {
            headers: {
              Accept: 'application/json'
            }
          })
          .then(r => r.json())
          .then(data => {
            loading.classList.add('hidden');

            if (!data.success || !Array.isArray(data.detail_group) || data.detail_group.length === 0) {
              empty.textContent = data.message || 'Tidak ada bahan terdaftar.';
              empty.classList.remove('hidden');
              return;
            }
            body.innerHTML = data.detail_group.map((g, i) => `
              <tr>
                <td class="px-3 py-2 text-gray-400">${i + 1}</td>
                <td class="px-3 py-2 font-medium text-gray-800">${g.detail_name ?? '-'}</td>
                <td class="px-3 py-2 text-right text-gray-700">${g.quantity ?? 0}</td>
                <td class="px-3 py-2 text-gray-500">${g.unit ?? '-'}</td>
              </tr>`).join('');
            tableWrap.classList.remove('hidden');
          })
          .catch(() => {
            loading.classList.add('hidden');
            empty.textContent = 'Gagal mengambil data bahan.';
            empty.classList.remove('hidden');
          });

        modal.classList.remove('hidden');
        modal.classList.add('flex');
      }

      function closeMenuModal() {
        const modal = document.getElementById('menuModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      }

      document.addEventListener('click', function(e) {
        const modal = document.getElementById('menuModal');
        if (e.target === modal) closeMenuModal();
      });

      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeMenuModal();
      });

      async function savePosName() {
        const posNameInput = document.getElementById('menuModalPosNameInput');
        const saveBtn = document.getElementById('menuModalPosNameSave');
        const itemId = posNameInput.dataset.itemId;

        if (!itemId) {
          return;
        }

        saveBtn.disabled = true;
        saveBtn.textContent = '...';

        try {
          const response = await fetch(posNameRouteTemplate.replace('__INVENTORY__', String(itemId)), {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
              'Accept': 'application/json',
            },
            body: JSON.stringify({
              pos_name: posNameInput.value
            }),
          });
          const data = await response.json();
          if (data.success) {
            saveBtn.textContent = 'Tersimpan ✓';
            setTimeout(() => {
              saveBtn.textContent = 'Simpan';
              saveBtn.disabled = false;
            }, 2000);
            return;
          }
          throw new Error(data.message || 'Gagal');
        } catch {
          saveBtn.textContent = 'Gagal';
          setTimeout(() => {
            saveBtn.textContent = 'Simpan';
            saveBtn.disabled = false;
          }, 2000);
        }
      }

      async function saveCategoryMain() {
        const categoryMainSelect = document.getElementById('menuModalCategoryMainSelect');
        const saveBtn = document.getElementById('menuModalCategoryMainSave');
        const itemId = categoryMainSelect.dataset.itemId;

        if (!itemId) {
          return;
        }

        saveBtn.disabled = true;
        saveBtn.textContent = '...';

        try {
          const response = await fetch(categoryMainRouteTemplate.replace('__INVENTORY__', String(itemId)), {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
              'Accept': 'application/json',
            },
            body: JSON.stringify({
              category_main: categoryMainSelect.value || null
            }),
          });
          const data = await response.json();
          if (data.success) {
            saveBtn.textContent = 'Tersimpan ✓';
            setTimeout(() => {
              saveBtn.textContent = 'Simpan';
              saveBtn.disabled = false;
            }, 2000);
            return;
          }
          throw new Error(data.message || 'Gagal');
        } catch {
          saveBtn.textContent = 'Gagal';
          setTimeout(() => {
            saveBtn.textContent = 'Simpan';
            saveBtn.disabled = false;
          }, 2000);
        }
      }

      document.addEventListener('click', async function(event) {
        const saveBtn = event.target instanceof HTMLElement && event.target.id === 'menuModalPrinterSave' ?
          event.target :
          null;

        if (!saveBtn) {
          return;
        }

        const itemId = saveBtn.dataset.itemId;
        if (!itemId) {
          return;
        }

        const allCheckboxes = Array.from(document.querySelectorAll('[data-menu-modal-printer]'));
        const selectedPrinterIds = allCheckboxes
          .filter((input) => input instanceof HTMLInputElement && input.checked)
          .map((input) => Number(input.value));

        saveBtn.disabled = true;
        const originalText = saveBtn.textContent;
        saveBtn.textContent = '...';

        try {
          const response = await syncPrinterTargets(itemId, selectedPrinterIds);
          location.reload();
        } catch (error) {
          saveBtn.textContent = 'Error';
          setTimeout(() => {
            saveBtn.textContent = originalText;
            saveBtn.disabled = false;
          }, 2000);
        }
      });

      function menuForm() {
        return {
          inventoryItems,
          activeTab: 'list',
          saving: false,
          formError: '',
          notification: {
            show: false,
            type: 'success',
            message: ''
          },
          form: {
            code_mode: 'manual',
            no: '',
            name: '',
            pos_name: '',
            item_type: 'GROUP',
            category_type: '',
            category_main: '',
            unit: '',
            selling_price: '',
            include_tax: false,
            include_service_charge: false,
            printer_ids: [],
            detail_group: [],
          },

          showNotification(type, message) {
            this.notification = {
              show: true,
              type,
              message
            };
            setTimeout(() => {
              this.notification.show = false;
            }, 5000);
          },

          addIngredient() {
            this.form.detail_group.push({
              inventory_item_id: '',
              item_no: '',
              detail_name: '',
              quantity: 1
            });
          },

          onRowItemChange(index) {
            const row = this.form.detail_group[index];
            const item = this.inventoryItems.find(i => i.id == row.inventory_item_id);
            if (item) {
              row.item_no = item.code;
              row.detail_name = item.name;
            } else {
              row.item_no = '';
              row.detail_name = '';
            }
          },

          removeRow(index) {
            this.form.detail_group.splice(index, 1);
          },

          onItemTypeChange() {
            if (this.form.item_type !== 'GROUP') {
              this.form.detail_group = [];
            }
          },

          onCodeModeChange() {
            if (this.form.code_mode === 'auto') {
              this.form.no = '';
            }
          },

          formatRupiahInput(value) {
            const numeric = String(value ?? '').replace(/\D/g, '');

            if (!numeric) {
              return '';
            }

            return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(numeric));
          },

          onSellingPriceInput(event) {
            const numeric = event.target.value.replace(/\D/g, '');
            this.form.selling_price = numeric;
            event.target.value = this.formatRupiahInput(numeric);
          },

          resetForm() {
            this.form = {
              code_mode: 'manual',
              no: '',
              name: '',
              pos_name: '',
              item_type: 'GROUP',
              category_type: '',
              category_main: '',
              unit: '',
              selling_price: '',
              include_tax: false,
              include_service_charge: false,
              printer_ids: [],
              detail_group: [],
            };
            this.formError = '';
          },

          async submitForm() {
            if (!['manual', 'auto'].includes(this.form.code_mode)) {
              this.formError = 'Mode kode item tidak valid.';
              return;
            }
            if (this.form.code_mode === 'manual' && !this.form.no.trim()) {
              this.formError = 'Kode item wajib diisi.';
              return;
            }
            if (!this.form.name.trim()) {
              this.formError = 'Nama menu wajib diisi.';
              return;
            }
            if (!['GROUP', 'INVENTORY'].includes(this.form.item_type)) {
              this.formError = 'Tipe item harus GROUP atau INVENTORY.';
              return;
            }
            if (!this.form.category_type.trim()) {
              this.formError = 'Kategori menu wajib dipilih.';
              return;
            }
            if (!this.form.unit.trim()) {
              this.formError = 'Satuan wajib diisi.';
              return;
            }
            if (this.form.selling_price === '' || this.form.selling_price === null) {
              this.formError = 'Harga jual wajib diisi.';
              return;
            }

            const detailGroup = this.form.item_type === 'GROUP' ?
              this.form.detail_group
              .filter((row) => row.item_no.trim())
              .map((row) => ({
                ...row,
                quantity: Number(row.quantity),
              })) : [];

            if (this.form.item_type === 'GROUP') {
              const invalidQuantity = detailGroup.find((row) => !Number.isInteger(row.quantity) || row.quantity <= 0);
              if (invalidQuantity) {
                this.formError = 'Jumlah bahan wajib bilangan bulat dan lebih dari 0.';
                return;
              }
            }

            this.formError = '';
            this.saving = true;

            try {
              const response = await fetch('{{ route('admin.menus.store') }}', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                  'Accept': 'application/json',
                },
                body: JSON.stringify({
                  code_mode: this.form.code_mode,
                  no: this.form.no,
                  name: this.form.name,
                  pos_name: this.form.pos_name || null,
                  item_type: this.form.item_type,
                  category_type: this.form.category_type,
                  unit: this.form.unit,
                  selling_price: Number(this.form.selling_price || 0),
                  include_tax: Boolean(this.form.include_tax),
                  include_service_charge: Boolean(this.form.include_service_charge),
                  printer_ids: this.form.printer_ids.map((id) => Number(id)),
                  detail_group: detailGroup,
                }),
              });

              const data = await response.json();

              if (data.success) {
                this.showNotification('success', 'Menu "' + this.form.name + '" berhasil disimpan ke Accurate.');
                this.resetForm();
                setTimeout(() => window.location.reload(), 1500);
              } else {
                if (data.errors) {
                  this.formError = Object.values(data.errors).flat().join(' ');
                } else {
                  this.formError = data.message || 'Gagal menyimpan ke Accurate.';
                }
              }
            } catch (e) {
              this.formError = 'Terjadi kesalahan jaringan.';
            } finally {
              this.saving = false;
            }
          },
        };
      }
    </script>
  @endpush
</x-app-layout>
