<x-app-layout title="Atur Denah Meja">
  <div class="p-4 sm:p-6">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
      <div class="flex items-center gap-3">
        <a href="{{ route('admin.tables.index') }}"
           class="w-10 h-10 rounded-lg border border-slate-200 bg-white flex items-center justify-center hover:bg-slate-50 transition"
           aria-label="Kembali ke Manajemen Meja">
          <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </a>
        <div class="w-12 h-12 bg-slate-800 rounded-xl flex items-center justify-center">
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
          </svg>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Atur Denah Meja</h1>
          <p class="text-sm text-gray-500">Drag meja ke posisi yang sesuai pada gambar denah.</p>
        </div>
      </div>

      <div class="flex flex-col sm:flex-row gap-2">
        <a href="{{ route('denah.preview') }}" target="_blank" rel="noopener"
           class="px-4 py-2.5 bg-white border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition flex items-center justify-center gap-2 font-medium">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5h5m0 0v5m0-5L10 14M5 7v12h12" />
          </svg>
          Buka Preview
        </a>
        <button id="savePositions" type="button"
                class="px-4 py-2.5 bg-slate-800 text-white rounded-lg hover:bg-slate-900 transition flex items-center justify-center gap-2 font-semibold">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          Simpan Posisi
        </button>
      </div>
    </div>

    @if ($areas->isEmpty())
      <div class="bg-white rounded-xl border border-slate-200 p-12 text-center">
        <p class="font-semibold text-slate-700">Belum ada area aktif.</p>
        <p class="text-sm text-slate-500 mt-1">Aktifkan area terlebih dahulu di Area Management.</p>
      </div>
    @else
      <div class="flex gap-2 overflow-x-auto pb-2 mb-4" role="tablist" aria-label="Pilih area">
        @foreach ($areas as $area)
          <button type="button" data-area-tab="{{ $area->id }}"
                  class="area-tab flex-shrink-0 px-4 py-2.5 rounded-lg border text-sm font-semibold transition">
            {{ $area->name }}
            <span class="ml-1 text-xs opacity-60">{{ $area->tables->count() }} meja</span>
          </button>
        @endforeach
      </div>

      @foreach ($areas as $area)
        <section data-area-panel="{{ $area->id }}" class="area-panel hidden">
          <div class="mb-3 px-4 py-3 rounded-lg border border-blue-200 bg-blue-50 text-sm text-blue-800 flex items-start gap-2">
            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 21a9 9 0 100-18 9 9 0 000 18z" />
            </svg>
            <span>Geser meja dengan mouse atau sentuhan. Untuk penyesuaian kecil, fokuskan meja lalu gunakan tombol panah keyboard.</span>
          </div>

          <div id="floor-{{ $area->id }}" data-floor="{{ $area->id }}"
               class="relative w-full min-h-[420px] rounded-xl overflow-hidden select-none border border-slate-300 bg-slate-100 touch-none">
            @if ($area->image)
              <img src="{{ Storage::disk('public')->url($area->image) }}"
                   alt="Denah {{ $area->name }}"
                   class="w-full h-auto min-h-[420px] object-contain pointer-events-none select-none"
                   draggable="false">
            @else
              <div class="absolute inset-0 opacity-30 pointer-events-none"
                   style="background-image: radial-gradient(circle, #64748b 1px, transparent 1px); background-size: 28px 28px;"></div>
              <a href="{{ route('admin.areas.index') }}"
                 class="absolute right-3 top-3 z-20 px-3 py-2 bg-white/90 border border-amber-200 rounded-lg text-xs font-semibold text-amber-700 hover:bg-white">
                Upload gambar di Area Management
              </a>
            @endif

            @foreach ($area->tables->filter(fn ($table) => ! is_null($table->position_x) && ! is_null($table->position_y)) as $table)
              <button type="button"
                      class="table-marker absolute z-10 w-16 h-16 -translate-x-1/2 -translate-y-1/2 rounded-xl border-2 border-slate-700 bg-white/90 shadow-lg backdrop-blur flex flex-col items-center justify-center cursor-grab active:cursor-grabbing focus:outline-none focus:ring-4 focus:ring-teal-300"
                      style="left: {{ $table->position_x }}%; top: {{ $table->position_y }}%;"
                      data-table-id="{{ $table->id }}" data-area-id="{{ $area->id }}"
                      data-position-x="{{ $table->position_x }}" data-position-y="{{ $table->position_y }}"
                      aria-label="Meja {{ $table->table_number }}, {{ $table->capacity }} pax">
                <span class="text-xs font-bold text-slate-900">{{ $table->table_number }}</span>
                <span class="text-[10px] text-slate-500">{{ $table->capacity }} pax</span>
              </button>
            @endforeach
          </div>

          @php($unpositionedTables = $area->tables->filter(fn ($table) => is_null($table->position_x) || is_null($table->position_y)))
          <div id="pool-panel-{{ $area->id }}" class="mt-4 {{ $unpositionedTables->isEmpty() ? 'hidden' : '' }}">
            <p class="text-sm font-semibold text-slate-700 mb-2">Meja belum diposisikan</p>
            <div id="pool-{{ $area->id }}" class="min-h-[76px] p-3 flex flex-wrap gap-2 rounded-xl border-2 border-dashed border-slate-300 bg-white">
              @foreach ($unpositionedTables as $table)
                <button type="button"
                        class="table-marker relative w-16 h-16 rounded-xl border-2 border-slate-700 bg-white shadow flex flex-col items-center justify-center cursor-grab focus:outline-none focus:ring-4 focus:ring-teal-300"
                        data-table-id="{{ $table->id }}" data-area-id="{{ $area->id }}" data-position-x="" data-position-y=""
                        aria-label="Meja {{ $table->table_number }}, belum diposisikan">
                  <span class="text-xs font-bold text-slate-900">{{ $table->table_number }}</span>
                  <span class="text-[10px] text-slate-500">{{ $table->capacity }} pax</span>
                </button>
              @endforeach
            </div>
          </div>
        </section>
      @endforeach
    @endif

    <div id="layoutToast" role="status" aria-live="polite"
         class="fixed bottom-6 right-6 z-50 hidden max-w-sm px-4 py-3 rounded-xl shadow-xl text-sm font-semibold"></div>
  </div>

  @push('scripts')
    <script>
      (() => {
        const firstAreaId = @json($areas->first()?->id);
        let activeAreaId = firstAreaId;
        let dragging = null;

        function switchArea(areaId) {
          activeAreaId = Number(areaId);
          document.querySelectorAll('.area-panel').forEach(panel => panel.classList.add('hidden'));
          document.querySelector(`[data-area-panel="${areaId}"]`)?.classList.remove('hidden');
          document.querySelectorAll('.area-tab').forEach(tab => {
            const selected = Number(tab.dataset.areaTab) === activeAreaId;
            tab.classList.toggle('bg-slate-800', selected);
            tab.classList.toggle('text-white', selected);
            tab.classList.toggle('border-slate-800', selected);
            tab.classList.toggle('bg-white', !selected);
            tab.classList.toggle('text-slate-700', !selected);
            tab.classList.toggle('border-slate-300', !selected);
            tab.setAttribute('aria-selected', String(selected));
          });
        }

        function positionMarker(marker, clientX, clientY) {
          const floor = document.getElementById(`floor-${marker.dataset.areaId}`);
          const bounds = floor.getBoundingClientRect();
          const x = Math.min(97, Math.max(3, ((clientX - bounds.left) / bounds.width) * 100));
          const y = Math.min(96, Math.max(4, ((clientY - bounds.top) / bounds.height) * 100));

          if (!floor.contains(marker)) {
            floor.appendChild(marker);
            marker.classList.remove('relative');
            marker.classList.add('absolute', 'z-10', '-translate-x-1/2', '-translate-y-1/2');
          }

          marker.style.left = `${x}%`;
          marker.style.top = `${y}%`;
          marker.dataset.positionX = x.toFixed(4);
          marker.dataset.positionY = y.toFixed(4);
        }

        document.querySelectorAll('.area-tab').forEach(tab => {
          tab.addEventListener('click', () => switchArea(tab.dataset.areaTab));
        });

        document.addEventListener('pointerdown', event => {
          const marker = event.target.closest('.table-marker');
          if (!marker) return;
          event.preventDefault();
          marker.setPointerCapture?.(event.pointerId);
          dragging = { marker, pointerId: event.pointerId };
          positionMarker(marker, event.clientX, event.clientY);
        });

        document.addEventListener('pointermove', event => {
          if (!dragging || dragging.pointerId !== event.pointerId) return;
          positionMarker(dragging.marker, event.clientX, event.clientY);
        });

        document.addEventListener('pointerup', event => {
          if (!dragging || dragging.pointerId !== event.pointerId) return;
          const panel = document.getElementById(`pool-panel-${dragging.marker.dataset.areaId}`);
          const pool = document.getElementById(`pool-${dragging.marker.dataset.areaId}`);
          panel?.classList.toggle('hidden', !pool?.querySelector('.table-marker'));
          dragging = null;
        });

        document.addEventListener('keydown', event => {
          const marker = event.target.closest('.table-marker');
          if (!marker || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return;
          event.preventDefault();
          const floor = document.getElementById(`floor-${marker.dataset.areaId}`);
          if (!floor.contains(marker)) return;
          const step = event.shiftKey ? 2 : 0.5;
          const deltaX = event.key === 'ArrowLeft' ? -step : event.key === 'ArrowRight' ? step : 0;
          const deltaY = event.key === 'ArrowUp' ? -step : event.key === 'ArrowDown' ? step : 0;
          const x = Math.min(97, Math.max(3, Number(marker.dataset.positionX) + deltaX));
          const y = Math.min(96, Math.max(4, Number(marker.dataset.positionY) + deltaY));
          marker.style.left = `${x}%`;
          marker.style.top = `${y}%`;
          marker.dataset.positionX = x.toFixed(4);
          marker.dataset.positionY = y.toFixed(4);
        });

        document.getElementById('savePositions')?.addEventListener('click', async event => {
          const button = event.currentTarget;
          const tables = [...document.querySelectorAll('.table-marker')]
            .filter(marker => marker.dataset.positionX !== '' && marker.dataset.positionY !== '')
            .map(marker => ({
              id: Number(marker.dataset.tableId),
              position_x: Number(marker.dataset.positionX),
              position_y: Number(marker.dataset.positionY),
            }));

          if (tables.length === 0) {
            showToast('Posisikan minimal satu meja sebelum menyimpan.', false);
            return;
          }

          button.disabled = true;
          button.classList.add('opacity-60');

          try {
            const response = await fetch(@json(route('admin.tables.layout.update')), {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
              },
              body: JSON.stringify({ tables }),
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Posisi meja gagal disimpan.');
            showToast(payload.message, true);
          } catch (error) {
            showToast(error.message || 'Posisi meja gagal disimpan.', false);
          } finally {
            button.disabled = false;
            button.classList.remove('opacity-60');
          }
        });

        function showToast(message, success) {
          const toast = document.getElementById('layoutToast');
          toast.textContent = message;
          toast.className = `fixed bottom-6 right-6 z-50 max-w-sm px-4 py-3 rounded-xl shadow-xl text-sm font-semibold ${success ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white'}`;
          window.setTimeout(() => toast.classList.add('hidden'), 3500);
        }

        if (firstAreaId) switchArea(firstAreaId);
      })();
    </script>
  @endpush
</x-app-layout>
