## Tujuan
Menampilkan **info stok berbasis bahan (porsi)** di kartu produk POS kasir untuk **menu group dengan COUNT ON** — mengisi celah: saat ini kartu group hanya menampilkan badge "Item Group" tanpa angka, padahal `possible_portions` sudah dihitung dan tersedia di payload (server-render + polling live).

## Perilaku baru
| Tipe item | Badge di kartu POS |
|---|---|
| Item biasa | "Stock: X" (tidak berubah) |
| Group + COUNT ON | Badge amber **"Sisa X porsi"** — nilai awal dari server, ter-update otomatis oleh polling live 30 detik; sold-out overlay tetap saat 0 |
| Group + COUNT OFF | Tetap hanya "Item Group" (by design — tidak dihitung) |

## Perubahan

### 1. `resources/views/pos/_components/product-section.blade.php`
- Untuk item dengan `possible_portions` tidak null (hanya group+count yang punya): tampilkan badge amber **"Sisa X porsi"** di area bawah gambar kartu (posisi badge stok).
- Binding Alpine: nilai live dari `liveMap['id'].possible_portions` bila ada, fallback ke nilai server — jadi saat polling `admin.pos.live` mengembalikan angka baru, badge ikut berubah tanpa reload.
- Tidak menyentuh logika sold-out overlay yang sudah ada.

### 2. Tidak ada perubahan backend
`index()` dan `live()` sudah mengembalikan `possible_portions`; polling `admin.pos.live` tiap 30 detik tetap memperbarui `liveMap`.

### 3. Test
- Feature test: POS index dengan 1 menu group count ON (BOM lokal 2 bahan, stok cukup) → halaman memuat teks "Sisa N porsi" (server-rendered) + payload live mengandung `possible_portions`.

## Verifikasi
- Suite penuh (harus tetap hijau), `pint --dirty`, `graphify update`.
- (Opsional verifikasi visual via browser jika diminta.)

## Di luar scope
- Waiter POS sudah menampilkan "Tersisa X porsi" — tidak diubah.
- Group COUNT OFF tetap tanpa info stok (keputusan desain).