# Plan: NIK Nullable & Login Alternatif

## Context

Sistem Bank Sampah sudah di-deploy. Kolom `nik` di database sudah `nullable` sejak migration awal (`0001_01_02_create_kartu_keluarga_nasabah_rekening_tables.php:25`). Namun di beberapa tempat di application code, NIK masih diperlakukan sebagai required. Tujuan: membuat NIK benar-benar optional di form tanpa menghapus data lama, dan menambahkan alternatif login untuk nasabah tanpa NIK.

**Rumus no_rekening**: `[1 digit status_desa][2 digit month][2 digit year][3 digit sequence]` = 8 digit. **NIK TIDAK digunakan** dalam pembuatan no_rekening.

## Daftar Perubahan

### 1. Form Admin - `RekeningResource.php:79-87`
- Tambah `->nullable()` pada TextInput NIK
- Ubah `->rule('regex:/^\d+$/')` menjadi `->rule('nullable', 'regex:/^\d+$/')` agar regex hanya berlaku jika diisi
- Data lama tidak terpengaruh karena perubahan hanya di form validation

### 2. Form Registrasi Publik - `RekeningRegistrationResource.php:75`
- Hapus `'nik'` dari array `$requiredFields`
- NIK tetap tampil di form tapi tidak wajib diisi

### 3. API Login - `AuthController.php:14-68`
- Ubah validasi: `nik` no longer required, tambah optional `no_rekening` dan `telepon`
- Tambah logic lookup: cari berdasarkan `nik` ATAU `no_rekening` ATAU `telepon`
- Pastikan minimal satu identifier diisi
- Kembalikan error yang jelas jika tidak ada identifier atau data tidak ditemukan

### 4. status_lengkap - TIDAK DIUBAH
- NIK tetap di `$requiredFields` pada `Rekening::calculateAndSetStatusLengkap()` (`Rekening.php:133`)
- Rekening tanpa NIK akan `status_lengkap = false` (konsisten dengan behavior saat ini)

### 5. Export - TIDAK DIUBAH
- `CustomRekeningExport.php` dan `RekeningResource.php` export: formatStateUsing sudah handle null dengan space prefix, aman.

### 6. Seeder/Factory/Observer - TIDAK DIUBAH
- Special accounts (donasi, `no_rekening = '00000000'`) tetap pakai dummy NIK `'0000000000000000'`
- Tidak ada perubahan pada data yang sudah ada

## Risiko & Mitigasi

| Risiko | Mitigasi |
|--------|----------|
| Nasabah lama login pakai NIK tetap bisa | Login logic backward-compatible, NIK tetap jadi opsi pertama |
| Nasabah baru tanpa NIK tidak bisa login | Tersedia alternatif via no_rekening dan telepon |
| Data lama terhapus | TIDAK ADA perubahan schema/migration, hanya ubah validation di form dan login logic |
| Multiple rows dengan NIK null di unique constraint | MySQL memperbolehkan multiple NULL di kolom unique, aman |

## Validasi

1. Test login via NIK (existing user) - harus tetap berhasil
2. Test login via no_rekening - harus berhasil
3. Test login via telepon - harus berhasil
4. Test buat rekening baru tanpa NIK dari admin panel
5. Test buat rekening baru tanpa NIK dari registrasi publik
6. Cek status_lengkap = false untuk rekening tanpa NIK
7. Jalankan `php artisan test` untuk memastikan tidak ada regression
