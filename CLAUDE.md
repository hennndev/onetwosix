# CLAUDE.md — Panduan Kerja untuk 126club-v2

Proyek ini adalah sistem POS / manajemen club berbasis Laravel. Untuk menghemat token,
**knowledge graph adalah referensi utama** dalam memahami codebase. Ikuti aturan di bawah.

## Aturan Utama: Graph Dulu, Baca File Kemudian

Sebelum membaca banyak file untuk memahami struktur, alur, atau keterkaitan antar modul,
**query knowledge graph terlebih dahulu**:

```
/graphify query "<pertanyaan>"
```

Perintah ini membaca `graphify-out/graph.json` yang sudah ada (tidak membangun ulang),
sehingga jauh lebih hemat token dibanding membuka banyak file sumber.

Contoh pertanyaan yang cocok dijawab lewat graph:
- "Modul apa saja yang bergantung pada AccurateService?"
- "Alur dari PosController sampai ke Billing lewat apa saja?"
- "Controller mana yang menyentuh model Reward?"

### Artifact graph yang tersedia (`graphify-out/`)
- **`graph.json`** — knowledge graph mentah (± 1760 node, 3422 edge, 349 community). Referensi utama.
- **`GRAPH_REPORT.md`** — ringkasan audit: god nodes, koneksi lintas-modul, pertanyaan yang disarankan.
- **`graph.html`** — visualisasi interaktif (buka di browser bila perlu melihat peta besar).
- **`.graphify_labels.json`** — label community (nama modul).

Untuk pertanyaan arsitektural tingkat tinggi, **baca `GRAPH_REPORT.md` dulu** sebelum menyelam ke file.

## Kapan Boleh Langsung Baca File

Graph untuk memahami *keterkaitan* dan *lokasi*. Untuk mengedit atau melihat implementasi
detail sebuah fungsi/method tertentu, tetap baca file sumbernya secara langsung — graph
tidak menggantikan pembacaan kode saat menulis perubahan.

## Menjaga Graph Tetap Terkini

Setiap kali ada **modul atau fitur baru** (controller, model, service, migration, route, atau
view baru), **perbarui graph** agar tetap akurat:

```
/graphify . --update
```

`--update` hanya mengekstrak ulang file baru/berubah, jadi tetap hemat.

### Scope yang dipakai (JANGAN diubah tanpa alasan)
Graph di-scope ke direktori berikut saja:
`app/`, `routes/`, `resources/`, `database/`, `config/`

Saat menjalankan `--update`, pertahankan scope yang sama agar konsisten dengan build awal.

## Ringkas
1. Mau paham codebase / cari keterkaitan → `/graphify query "..."` atau baca `GRAPH_REPORT.md`.
2. Mau edit implementasi detail → baca file sumbernya langsung.
3. Ada modul/fitur baru → `/graphify . --update` (scope tetap: app, routes, resources, database, config).
