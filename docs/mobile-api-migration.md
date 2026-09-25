# Migrasi 126 Club Mobile API

Mobile API dari `126club-v2` tersedia di prefix `/api/v1` dengan kontrak route, middleware Sanctum, status HTTP, dan envelope JSON yang sama. Dokumentasi interaktif dibuat oleh Scramble:

- UI: `/docs/api`
- OpenAPI JSON: `/docs/api.json`
- Static export: `api.json` di root project

Dokumentasi dapat dibuka tanpa login pada environment `local`. Pada environment lain, akses dibatasi untuk user web dengan role `Administrator`.

## Menjalankan

```bash
composer install
php artisan migrate
php artisan storage:link
```

## Data demo API

Dataset pengujian API dapat dibuat atau diperbarui tanpa menghapus data lain:

```bash
php artisan db:seed --class=Database\\Seeders\\ApiDemoSeeder
```

Seeder ini idempotent sehingga aman dijalankan berulang kali. Kredensial customer utama:

```text
Email    : mobile.demo@126club.test
Telepon  : 081266660126
Password : password
```

Customer utama memiliki tier, profil, booking mendatang, booking aktif/check-in, booking selesai, billing, order dan item, bottle keep, request lagu, display message, reward redemption, serta token Firebase demo. Dataset juga memuat customer leaderboard lain, area dan meja tersedia, event today/upcoming/past, promo, reward, bank account, QRIS, dan nomor WhatsApp.

Contoh login:

```bash
curl -X POST http://localhost/api/v1/login \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"email":"mobile.demo@126club.test","password":"password","device_name":"API Test"}'
```

Konfigurasi opsional:

```dotenv
API_VERSION=1.0.0
FIREBASE_SERVICE_ACCOUNT_FILE=/absolute/path/service-account-file.json
```

Untuk memperbarui static OpenAPI export:

```bash
php artisan scramble:export
```

## Struktur data yang ditambahkan

Tidak ada row/record bisnis atau seed baru yang ditambahkan. Migration berikut hanya mengembalikan struktur data yang memang dipakai oleh API mobile lama:

| Struktur                   | Penambahan                                              | Alasan                                                                                                                                     |
| -------------------------- | ------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| `personal_access_tokens`   | Tabel token Sanctum                                     | Login mobile menghasilkan bearer token dan endpoint privat memakai `auth:sanctum`.                                                         |
| `events`                   | `image` nullable                                        | Respons event lama mengirim URL gambar.                                                                                                    |
| `customer_users`           | `tier_id` nullable + foreign key                        | Membership, leaderboard, dan profil customer membutuhkan tier.                                                                             |
| `promos`                   | Tabel promo beserta periode, diskon, gambar, dan syarat | Endpoint public `/promos` membaca data ini.                                                                                                |
| `bank_accounts`            | Tabel rekening pembayaran                               | Endpoint `/payment-info` mengirim rekening aktif.                                                                                          |
| `whatsapp_settings`        | Tabel nomor konfirmasi pembayaran                       | Endpoint `/payment-info` mengirim nomor WhatsApp aktif dengan bentuk data lama. Ini berbeda fungsi dari nomor OTP pada `general_settings`. |
| `rewards`                  | `image` nullable                                        | Respons reward lama mengirim URL gambar.                                                                                                   |
| `qris_settings`            | Tabel QRIS                                              | Endpoint `/payment-info` mengirim QRIS aktif.                                                                                              |
| `song_requests`            | `table_session_id` nullable + foreign key               | Request lagu harus terkait sesi check-in aktif dan muncul di histori booking.                                                              |
| `billings`                 | `song_tip`, `display_tip`, default `0`                  | Nilai tip menjadi bagian total dan histori transaksi mobile.                                                                               |
| `display_message_requests` | `table_session_id` nullable + foreign key               | Pesan display harus terkait sesi check-in aktif dan muncul di histori booking.                                                             |
| `areas`                    | `image` nullable                                        | Respons area/table lama menyediakan gambar area.                                                                                           |
| `tables`                   | `position_x`, `position_y` nullable                     | Menjaga data posisi meja yang sudah ada pada project lama.                                                                                 |
| `users`                    | `token_firebase` nullable                               | Mobile app menyimpan token perangkat untuk Firebase push notification.                                                                     |

Schema `reward_redemptions` yang sudah ada di `onetwosix` dipakai kembali karena sudah memuat seluruh kolom yang diperlukan API. Tidak dibuat tabel duplikat.

## Kompatibilitas

- 35 operasi route API lama tersedia kembali pada 31 path OpenAPI.
- Envelope respons tetap `{ error, message, data }`.
- Endpoint privat tetap menggunakan bearer token Sanctum.
- Field `song_tip` dan `display_tip` dipertahankan pada model `Billing` agar histori transaksi mobile tidak kehilangan nilai.
- Fitur baru `onetwosix`, termasuk multi-area dan partial payment, tidak dihapus atau ditimpa.

## Status data existing

Database `126club-v2` dan `onetwosix` menggunakan nama database berbeda. Saat audit, database lama masih memiliki row API (antara lain area, event, promo, reward, customer, request lagu/pesan, dan redemption), sedangkan database baru sudah memiliki user sendiri tetapi tabel domain API masih kosong. Migration schema di atas sudah diterapkan ke database `onetwosix`, namun row lama sengaja tidak di-import otomatis karena primary key user/customer/session dapat bertabrakan dan menimpa data project baru.

Pemindahan row produksi perlu dijalankan sebagai proses merge terpisah: tentukan mapping user berdasarkan email, mapping customer berdasarkan `accurate_id`/`customer_code`, mapping area/table, lalu remap seluruh foreign key histori. Personal access token lama juga sebaiknya tidak disalin; user mobile login ulang agar mendapat token baru dari project baru.
