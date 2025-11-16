# Panduan Permintaan Setor Sampah & Tarik Saldo

Dokumen ini menjelaskan rancangan alur, prosedur operasional, dan tambahan fitur yang dibutuhkan untuk mengelola permintaan setor sampah dan tarik saldo yang diajukan nasabah melalui aplikasi mobile banking. Panduan dipisahkan untuk sisi Mobile Banking (Android) dan sisi panel web (Filament).

## Gambaran Umum Arsitektur
- Tambah dua tabel baru: `permintaan_tarik_saldo` dan `permintaan_setor_sampah`. Struktur mengikuti tabel induk masing-masing plus meta kolom kontrol.
- Status permintaan minimal: `draft` -> `menunggu_konfirmasi` -> (`disetujui` | `ditolak`). Status disimpan pada tabel permintaan dan dicatat siapa serta kapan perubahan terjadi.
- Nasabah membuat permintaan melalui form embed (supaya tidak membangun UI baru). Setiap permintaan menghasilkan tautan unik yang dikonversi ke QR dan dapat dipindai admin.
- Admin dapat memproses permintaan dari tabel Filament atau melalui QR scanner yang langsung membuka halaman edit resource.
- Saat admin menekan **Konfirmasi**, data dipindahkan ke tabel induk (beserta nomor referensi & audit trail) dan permintaan ditandai `disetujui`. Saat **Tolak** ditekan, permintaan dicatat sebagai `ditolak` dan dapat dihapus fisiknya sesuai kebijakan retensi.

### Kolom Minimum Tabel Permintaan
- `id` (UUID atau snowflake untuk mudah dibagikan via QR).
- Seluruh kolom domain dari tabel induk (mis. `nasabah_id`, `jenis_sampah`, `berat`, `nilai`, dsb.).
- `status` (enum).
- `requested_at`, `requested_by` (ID nasabah).
- `confirmed_at`, `confirmed_by`, `keterangan_admin`.
- `qr_token` atau `signed_token` untuk membuka halaman embed melalui QR.
- `source` (mis. `mobile_banking`) guna audit dari kanal berbeda.

---

## Mobile Banking (Android)

### Tujuan
Memungkinkan nasabah membuat permintaan setor sampah dan tarik saldo melalui aplikasi tanpa membuat UI kompleks baru, dengan memanfaatkan embed form dari Filament yang responsif serta menghasilkan QR untuk dipindai admin.

### Alur Proses
1. **Autentikasi** - Nasabah login di aplikasi.
2. **Akses Form Embed** - Aplikasi membuka WebView menuju route khusus Filament (mis. `/filament/mobile/setor-sampah/form`). Route menggunakan token akses jangka pendek (signed URL) agar hanya mobile banking yang bisa memakainya.
3. **Isi dan Kirim Form** - Nasabah mengisi data. Server menyimpan catatan ke tabel permintaan dengan status `menunggu_konfirmasi`.
4. **Tautan / QR** - Backend mengembalikan tautan unik (berisi token aman) dan payload untuk QR. Aplikasi menampilkan QR sebagai bukti permintaan.
5. **Notifikasi** - Trigger push notification / email ke admin atau kanal terkait agar tahu ada permintaan baru.
6. **Pelacakan Status** - Halaman riwayat menampilkan status permintaan (menunggu, disetujui, ditolak) dengan polling ringan atau push via WebSocket/FCM.

### Detail Implementasi
- **WebView Embed**: aktifkan mode responsif (Tailwind + breakpoint) pada resource Filament. Hilangkan menu admin dengan guard `if ($request->user()->hasRole('nasabah_mobile'))`.
- **Autentikasi Token**: gunakan Personal Access Token / Sanctum token khusus mobile. Signed URL wajib menyertakan `nasabah_id` dan expired (mis. 5 menit) untuk mencegah reuse.
- **Validasi**: Form harus melakukan validasi sisi server (jumlah minimal setor, batas tarik saldo, dsb.) serta menampilkan error yang ramah mobile.
- **QR Payload**: gunakan format JSON terenkode base64 atau URL langsung, contoh: `https://panel.cipta-muri.id/filament/permintaan-setor/{id}?token={signed}`. QR ditampilkan sekaligus dapat disimpan di riwayat.
- **Offline Awareness**: WebView tampilkan indikator jika koneksi terputus agar nasabah tidak kehilangan data.
- **Keamanan**: 
  - Pastikan setiap permintaan dicatat dengan `device_id` atau fingerprint untuk anti-fraud.
  - Batasi jumlah permintaan aktif agar nasabah tidak menumpuk order.

### Checklist Tambahan (Mobile)
- [ ] Implementasi WebView yang memuat route embed + handling token.
- [ ] Fungsi generate & refresh signed URL/QR.
- [ ] Penyimpanan riwayat permintaan + status realtime.
- [ ] Notifikasi/push ke admin atau channel monitoring ketika permintaan dibuat.
- [ ] Validasi batas transaksi (min/max) dan lampiran foto bukti bila perlu.
- [ ] Error handling khusus (expired token, koneksi lambat, dll).

---

## Panel Web (Filament)

### Tujuan
Memberi admin antarmuka tunggal untuk meninjau, menyetujui/menolak, dan melacak semua permintaan. Panel juga menyediakan QR scanner agar admin lapangan bisa langsung membuka resource terkait tanpa menelusuri tabel secara manual.

### Resource & Fitur Utama
1. **Resource Permintaan Tarik Saldo** dan **Resource Permintaan Setor Sampah**:
   - Menampilkan kolom ringkas (nasabah, nominal/berat, status, token, kanal).
   - Action `Konfirmasi` -> memvalidasi, memindahkan ke tabel induk, membuat transaksi/ledger, lalu mengubah status + log.
   - Action `Tolak` -> wajib isi alasan, kirim notifikasi ke nasabah, ubah status ke `ditolak`.
   - Filter status, tanggal permintaan, kanal.
2. **Resource Induk**:
   - Perlu endpoint helper untuk menerima data hasil konfirmasi (pindahkan data permintaan ke induk, generate nomor referensi, update saldo).
3. **QR Scanner Page**:
   - Halaman Filament kustom yang mengakses kamera (dengan user permission) menggunakan library JS (mis. `html5-qrcode`).
   - Setelah QR berhasil dipindai, redirect ke halaman edit resource `permintaan/{id}` dengan token yang disertakan.

### Prosedur Konfirmasi
1. Admin membuka resource permintaan.
2. Validasi data (cek duplikasi, cek saldo cukup untuk tarik, cek parameter setor).
3. Klik **Konfirmasi**:
   - Jalankan transaksi database untuk: menambahkan record ke tabel induk, menyesuaikan saldo nasabah, menulis log audit, menandai permintaan `disetujui`.
   - Kirim notifikasi ke nasabah (push/email).
4. Jika **Tolak**:
   - Catat alasan (`keterangan_admin`), ubah status `ditolak`, hapus QR token (supaya tidak bisa dipakai).
   - Opsional: hapus fisik record setelah X hari dengan job scheduler.

### Keamanan & Audit
- Signed token diverifikasi sebelum menampilkan halaman edit permintaan via QR.
- Gunakan policy/guard Filament sehingga hanya role tertentu yang bisa men-scan atau menyetujui.
- Catat `confirmed_by`, IP, device admin, serta jalur akses (`via_qr` / `via_table`).
- Buat log activity (mis. Spatie Activity Log) untuk investigasi sengketa.

### Automasi & Integrasi
- **Notifikasi**: Integrasi ke channel internal (Telegram/Slack) setiap ada permintaan baru atau permintaan menunggu > X jam.
- **Job Pembersihan**: Scheduler untuk menghapus permintaan `ditolak`/`expired`.
- **Laporan**: Tambah widget statistik (jumlah permintaan harian, rata-rata respon admin).
- **SLA Reminder**: Job yang menandai permintaan terlambat dan mengirim reminder ke admin.

### Checklist Tambahan (Filament)
- [ ] Migration dua tabel permintaan + relasi dengan tabel induk.
- [ ] Seeder/Factory untuk pengujian alur.
- [ ] Resource Filament lengkap dengan filter, action konfirmasi/tolak, dan log audit.
- [ ] Halaman QR scanner + integrasi ke kamera browser.
- [ ] Signed URL middleware untuk route embed & route QR.
- [ ] Notifikasi (email/push) ke nasabah & admin.
- [ ] Scheduler untuk membersihkan permintaan kedaluwarsa dan reminder SLA.
- [ ] Test otomatis (Feature/Unit) untuk proses konfirmasi & tolak.

---

## Catatan Tambahan Agar Sistem Efisien
- Gunakan queue untuk proses berat (generate referensi, kirim notifikasi) agar konfirmasi admin tetap cepat.
- Simpan QR token secara terpisah atau gunakan table `signed_links` supaya mudah dicabut jika terjadi kebocoran.
- Pertimbangkan auto-approval rules untuk permintaan kecil dengan parameter aman supaya admin hanya fokus pada kasus besar.
- Dokumentasikan API dan event (webhook) sehingga tim mobile & ops bisa melakukan integrasi lanjutan dengan minim miskomunikasi.

Dengan mengikuti panduan ini, proses permintaan dari nasabah hingga konfirmasi admin dapat berjalan efisien, terukur, dan mudah diaudit.
