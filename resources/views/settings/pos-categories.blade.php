<x-app-layout title="Kategori POS">
  <style>
    .settings-toggle {
      position: relative;
      display: inline-flex;
      cursor: pointer;
      align-items: center;
    }

    .settings-toggle__input {
      position: absolute;
      width: 1px;
      height: 1px;
      padding: 0;
      margin: -1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      white-space: nowrap;
      border-width: 0;
    }

    .settings-toggle__track {
      position: relative;
      display: inline-block;
      width: 2.25rem;
      height: 1.25rem;
      border-radius: 9999px;
      background-color: rgb(226 232 240);
      transition: background-color 150ms ease-in-out;
    }

    .settings-toggle__track::after {
      position: absolute;
      top: 2px;
      left: 2px;
      width: 1rem;
      height: 1rem;
      content: '';
      border-radius: 9999px;
      background-color: rgb(255 255 255);
      box-shadow: 0 1px 2px 0 rgb(15 23 42 / 0.15);
      transition: transform 150ms ease-in-out;
    }

    .settings-toggle__input:checked+.settings-toggle__track {
      background-color: rgb(99 102 241);
    }

    .settings-toggle--menu .settings-toggle__input:checked+.settings-toggle__track {
      background-color: rgb(16 185 129);
    }

    .settings-toggle--group .settings-toggle__input:checked+.settings-toggle__track {
      background-color: rgb(14 165 233);
    }

    .settings-toggle__input:checked+.settings-toggle__track::after {
      transform: translateX(1rem);
    }

    .settings-toggle__input:focus-visible+.settings-toggle__track {
      outline: 2px solid rgb(165 180 252);
      outline-offset: 2px;
    }

    .settings-toggle__input:indeterminate+.settings-toggle__track {
      background-color: rgb(148 163 184);
    }

    .settings-toggle__input:indeterminate+.settings-toggle__track::after {
      transform: translateX(0.5rem);
    }
  </style>

  <div class="p-4 sm:p-6">

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
      <h1 class="text-2xl font-bold text-slate-800">Kategori POS</h1>
      <p class="text-sm text-slate-500 mt-1">Atur kategori mana yang tampil di POS</p>
    </div>

    @if ($errors->any())
      <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-inside list-disc space-y-1">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <!-- Info Box -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 mb-6 text-sm text-blue-700">
      <p class="font-semibold mb-2">Petunjuk:</p>
      <ul class="space-y-1 list-disc list-inside">
        <li><strong>Tampil di POS</strong> — aktifkan agar kategori ini muncul di halaman POS.</li>
        <li><strong>Menu</strong> — penanda kategori menu untuk kebutuhan listing/organisasi menu.</li>
      </ul>
    </div>

    @if ($knownTypes->isEmpty())
      <div class="bg-white border border-slate-200 rounded-xl p-10 text-center text-slate-500">
        Belum ada data inventory. Sync dari Accurate terlebih dahulu.
      </div>
    @else
      <form method="POST"
            action="{{ route('admin.settings.pos-categories.save') }}"
            id="pos-categories-form">
        @csrf
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
              <tr>
                <th class="px-5 py-3 text-left font-medium text-slate-600">Kategori</th>
                <th class="px-5 py-3 text-center font-medium text-slate-600">
                  <div class="flex flex-col items-center gap-1.5">
                    <span>Tampil di POS</span>
                    <label class="settings-toggle"
                           title="Aktifkan/matikan semua">
                      <input type="checkbox"
                             data-bulk="show_in_pos"
                             aria-label="Aktifkan atau matikan Tampil di POS untuk semua kategori"
                             class="settings-toggle__input">
                      <span class="settings-toggle__track"></span>
                    </label>
                  </div>
                </th>
                <th class="px-5 py-3 text-center font-medium text-slate-600">
                  <div class="flex flex-col items-center gap-1.5">
                    <span>Menu</span>
                    <label class="settings-toggle settings-toggle--menu"
                           title="Aktifkan/matikan semua">
                      <input type="checkbox"
                             data-bulk="is_menu"
                             aria-label="Aktifkan atau matikan Menu untuk semua kategori"
                             class="settings-toggle__input">
                      <span class="settings-toggle__track"></span>
                    </label>
                  </div>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              @foreach ($knownTypes as $type)
                @php $s = $settings->get($type) @endphp
                <tr class="hover:bg-slate-50 transition">
                  <td class="px-5 py-3 font-medium text-slate-800">{{ $type }}</td>

                  {{-- Tampil di POS toggle --}}
                  <td class="px-5 py-3 text-center">
                    <input type="hidden"
                           name="categories[{{ $type }}][_present]"
                           value="1">
                    <input type="hidden"
                           name="categories[{{ $type }}][show_in_pos]"
                           value="0">
                    <label class="settings-toggle">
                      <input type="checkbox"
                             name="categories[{{ $type }}][show_in_pos]"
                             value="1"
                             class="settings-toggle__input"
                             {{ $s && $s->show_in_pos ? 'checked' : '' }}>
                      <span class="settings-toggle__track"></span>
                    </label>
                  </td>

                  <td class="px-5 py-3 text-center">
                    <input type="hidden"
                           name="categories[{{ $type }}][is_menu]"
                           value="0">
                    <label class="settings-toggle settings-toggle--menu">
                      <input type="checkbox"
                             name="categories[{{ $type }}][is_menu]"
                             value="1"
                             class="settings-toggle__input"
                             {{ $s && $s->is_menu ? 'checked' : '' }}>
                      <span class="settings-toggle__track"></span>
                    </label>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
          </div>
        </div>

        <div class="mt-5 flex justify-end">
          <button type="submit"
                  class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            Simpan Pengaturan
          </button>
        </div>
      </form>

      <script>
        (() => {
          const form = document.getElementById('pos-categories-form');
          const masters = form.querySelectorAll('[data-bulk]');
          const rowsOf = (field) =>
            form.querySelectorAll(`tbody input[type=checkbox][name$="[${field}]"]`);

          const syncMaster = (master) => {
            const rows = [...rowsOf(master.dataset.bulk)];
            const checked = rows.filter((r) => r.checked).length;
            master.checked = checked === rows.length && rows.length > 0;
            master.indeterminate = checked > 0 && checked < rows.length;
          };

          masters.forEach((master) => {
            syncMaster(master);
            master.addEventListener('change', () => {
              master.indeterminate = false;
              rowsOf(master.dataset.bulk).forEach((r) => (r.checked = master.checked));
            });
          });

          form.addEventListener('change', (e) => {
            if (e.target.matches('tbody input[type=checkbox]')) masters.forEach(syncMaster);
          });
        })();
      </script>
    @endif
  </div>
</x-app-layout>
