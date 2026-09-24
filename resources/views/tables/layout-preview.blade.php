<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ config('app.name') }} — Denah Gedung</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            -webkit-tap-highlight-color: transparent;
            overflow-x: auto;
        }

        .area-chip {
            transition: background-color 0.15s, border-color 0.15s, transform 0.1s;
        }

        .area-chip:active {
            transform: translate(-50%, -50%) scale(0.93);
        }

        .floor-fixed {
            width: 960px;
            flex-shrink: 0;
        }
    </style>
</head>

<body class="bg-slate-100 min-h-screen">

    {{--
    <!-- Header -->
    <div class="bg-white border-b border-slate-200 sticky top-0 z-40 px-4 py-3 flex items-center gap-3">
        <div class="flex-1 min-w-0">
            <p class="text-xs text-slate-400 font-medium uppercase tracking-wide">126 Club</p>
            <h1 class="text-base font-bold text-slate-800 leading-tight truncate">Denah Gedung</h1>
        </div>
        <!-- Legend -->
        <div class="flex items-center gap-2 text-xs font-medium text-slate-600">
            <span class="flex items-center gap-1">
                <span class="inline-block w-3 h-3 rounded bg-emerald-500 border border-emerald-600"></span> Tersedia
            </span>
            <span class="flex items-center gap-1">
                <span class="inline-block w-3 h-3 rounded bg-amber-400 border border-amber-500"></span> Dipesan
            </span>
            <span class="flex items-center gap-1">
                <span class="inline-block w-3 h-3 rounded bg-blue-500 border border-blue-600"></span> Terisi
            </span>
        </div>
    </div> --}}

    @if ($areas->isEmpty())
    <!-- Empty state -->
    <div class="flex flex-col items-center justify-center min-h-[60vh] gap-4 px-6 text-center">
        <div class="w-16 h-16 bg-slate-200 rounded-full flex items-center justify-center">
            <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
            </svg>
        </div>
        <p class="text-slate-500 text-sm">Belum ada area yang aktif.</p>
    </div>
    @else

    {{--
    <!-- Area Tabs -->
    <div class="overflow-x-auto hide-scrollbar bg-white border-b border-slate-200">
        <div class="flex gap-1 px-3 py-2 min-w-max">
            @foreach ($areas as $area)
            <button id="tab-{{ $area->id }}" onclick="switchArea({{ $area->id }})"
                class="flex-shrink-0 px-4 py-2 text-sm font-medium rounded-lg border transition whitespace-nowrap area-tab">
                {{ $area->name }}
                <span class="ml-1 text-xs opacity-60">({{ $area->tables->count() }})</span>
            </button>
            @endforeach
        </div>
    </div> --}}

    <!-- Floor canvases -->
    <div class="p-3" style="min-width: 960px;">
        @foreach ($areas as $area)
        <div id="canvas-{{ $area->id }}" class="area-canvas hidden">

            @if (!$area->image)
            <!-- No image: grid background -->
            <div id="floor-{{ $area->id }}"
                class="floor-fixed relative rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 overflow-hidden select-none"
                style="min-height: 500px;" data-area-id="{{ $area->id }}">
                <div class="absolute inset-0 opacity-20"
                    style="background-image: radial-gradient(circle, #94a3b8 1px, transparent 1px); background-size: 28px 28px;">
                </div>
                @foreach ($area->tables as $table)
                @php
                $px = $table->position_x ?? null;
                $py = $table->position_y ?? null;
                $hasPosition = ! is_null($px) && ! is_null($py);
                @endphp
                @if ($hasPosition)
                <div class="area-chip absolute flex flex-col items-center justify-center rounded-full border-2 text-center select-none z-10 shadow-md"
                    style="width: 56px; height: 56px; left: {{ $px }}%; top: {{ $py }}%; transform: translate(-50%, -50%);"
                    data-table-id="{{ $table->id }}" data-status="{{ $table->status }}"
                    onclick="handleChipClick('{{ $table->status }}', {{ $table->id }})">
                    <span class="text-xs font-bold leading-tight chip-label">{{ $table->table_number }}</span>
                    <span class="text-[10px] chip-sublabel">{{ $table->capacity }} pax</span>
                </div>
                @endif
                @endforeach
            </div>
            @else
            <!-- Has image -->
            <div id="floor-{{ $area->id }}"
                class="floor-fixed relative rounded-xl overflow-hidden select-none border border-slate-200 shadow-sm"
                data-area-id="{{ $area->id }}">
                <img src="{{ Storage::url($area->image) }}" alt="{{ $area->name }}"
                    class="block pointer-events-none select-none" style="width: 100%; height: auto;"
                    draggable="false" />
                @foreach ($area->tables as $table)
                @php
                $px = $table->position_x ?? null;
                $py = $table->position_y ?? null;
                $hasPosition = ! is_null($px) && ! is_null($py);
                @endphp
                @if ($hasPosition)
                <div class="area-chip absolute flex flex-col items-center justify-center rounded-full border-2 text-center select-none z-10 shadow-md backdrop-blur"
                    style="width: 56px; height: 56px; left: {{ $px }}%; top: {{ $py }}%; transform: translate(-50%, -50%);"
                    data-table-id="{{ $table->id }}" data-status="{{ $table->status }}"
                    onclick="handleChipClick('{{ $table->status }}', {{ $table->id }})">
                    <span class="text-xs font-bold leading-tight chip-label">{{ $table->table_number }}</span>
                    <span class="text-[10px] chip-sublabel">{{ $table->capacity }} pax</span>
                </div>
                @endif
                @endforeach
            </div>
            @endif

        </div>
        @endforeach
    </div>
    @endif

    <script>
        const areasData = @json($areas->map(fn($a) => ['id' => $a->id, 'name' => $a->name]));
        let currentAreaId = {{ $activeAreaId ?? 'null' }};

        const BASE_URL = '{{ rtrim(route("denah.preview"), "/") }}';

        const STATUS_STYLE = {
            available:   { bg: '#cbd5e1', border: '#64748b', text: '#1e293b', sub: '#475569', cursor: 'pointer' },
            reserved:    { bg: '#fde68a', border: '#b45309', text: '#451a03', sub: '#92400e', cursor: 'pointer' },
            occupied:    { bg: '#fca5a5', border: '#b91c1c', text: '#450a0a', sub: '#991b1b', cursor: 'pointer' },
            maintenance: { bg: '#ddd6fe', border: '#6d28d9', text: '#2e1065', sub: '#5b21b6', cursor: 'pointer' },
        };

        function applyChipColors() {
            document.querySelectorAll('.area-chip').forEach(chip => {
                const status = chip.dataset.status || 'available';
                const style = STATUS_STYLE[status] || STATUS_STYLE.available;
                chip.style.backgroundColor = style.bg;
                chip.style.borderColor = style.border;
                chip.style.cursor = style.cursor;
                const label = chip.querySelector('.chip-label');
                const sublabel = chip.querySelector('.chip-sublabel');
                if (label) label.style.color = style.text;
                if (sublabel) sublabel.style.color = style.sub;
                chip.style.boxShadow = '0 2px 8px rgba(0,0,0,0.10)';
            });
        }

        function switchArea(areaId) {
            currentAreaId = areaId;

            history.pushState({ areaId }, '', BASE_URL + '/' + areaId);

            document.querySelectorAll('.area-tab').forEach(tab => {
                tab.classList.remove('bg-indigo-600', 'text-white', 'border-indigo-600');
                tab.classList.add('bg-white', 'text-slate-600', 'border-slate-200');
            });
            const active = document.getElementById('tab-' + areaId);
            if (active) {
                active.classList.add('bg-indigo-600', 'text-white', 'border-indigo-600');
                active.classList.remove('bg-white', 'text-slate-600', 'border-slate-200');
            }

            document.querySelectorAll('.area-canvas').forEach(c => c.classList.add('hidden'));
            const canvas = document.getElementById('canvas-' + areaId);
            if (canvas) canvas.classList.remove('hidden');

            applyChipColors();
        }

        function handleChipClick(status, tableId) {
            const url = BASE_URL + '/' + currentAreaId + '/' + status + '/' + tableId;
            history.pushState({ areaId: currentAreaId, status, tableId }, '', url);
        }

        document.addEventListener('DOMContentLoaded', function () {
            if (currentAreaId) {
                switchArea(currentAreaId);
            }
        });
    </script>
</body>

</html>
