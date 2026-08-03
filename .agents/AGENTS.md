<graphify-guidelines>
=== graphify rules ===

# Graphify Knowledge Base & Synchronizations

- `graphify-out` (`graphify-out/GRAPH_REPORT.md`, `graphify-out/graph.json`, `graphify-out/graph.html`) adalah **referensi utama pengetahuan arsitektur dan struktur hubungan kode** dalam repositori ini.
- **Membaca Knowledge Base**: Sebelum melakukan analisis arsitektur, refactoring, atau investigasi hubungan antar file, selalu periksa `graphify-out/GRAPH_REPORT.md` untuk memahami Community Hubs, God Nodes, dan relasi dependencies.
- **Update Otomatis Graphify**: Setiap kali terjadi perubahan kode PHP/Blade/JS (penambahan, pengeditan, atau penghapusan controller, model, migration, service, view, atau komponen baru), Anda **WAJIB memperbarui `graphify-out`** dengan menjalankan perintah terminal:
  ```bash
  graphify update .
  ```
- **Verifikasi Sinkronisasi**: Pastikan `graphify-out` selalu up-to-date setelah menyelesaikan pengeditan kode dan sebelum finalisasi tugas.

</graphify-guidelines>
