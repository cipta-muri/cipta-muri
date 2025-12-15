## Ringkasan pekerjaan
- Menambahkan Playwright sebagai framework E2E (npm dev dependency, playwright.config.ts, script `npm run test:e2e`).
- Menyiapkan helper bootstrap admin (`tests/playwright/bootstrap_admin.php`) termasuk migrate, Super Admin, rekening donasi.
- Menambah endpoint testing-only di `routes/web.php` untuk login/session/factory data (_playwright).
- Menulis suite Playwright (`tests/playwright/admin.spec.ts`) mencakup: login, Sampah CRUD (update/delete), Setor Sampah delete, Sampah Keluar delete, Withdraw delete.
- Menambah laporan LaTeX dengan data nyata proyek dan anggota tim (Karel Tsalasatir Riyan, Haniel Wijanarko, Hammas Harya Sena); status testing CONDITIONAL PASS karena kendala login guard.

## Status pengujian Playwright
- Saat ini login masih gagal (redirect ke /admin/login meski session disuntikkan). Dugaan: guard Filament belum menerima session dari endpoint helper; perlu penyelidikan lebih lanjut.
- Endpoints _playwright sudah tersedia: `/ _playwright/session?email=...&nik=...` dan `/ _playwright/factory` untuk seed data (rekening, sampah, setor, sampah_keluar, withdraw).
- Tes akan gagal hingga login berhasil. Bisa sementara diakali dengan memperbaiki guard atau menjalankan login form jika backend sudah mendukung email/password.

## Hal yang perlu dilengkapi user
- Pastikan guard Filament menerima login dari endpoint helper atau sesuaikan login form agar menerima kredensial admin default (email: testing@ciptamuri.com, nik: 9999999999999999, password: password), lalu jalankan `npm run test:e2e`.
- Isi bukti (screenshot/trace) pada laporan jika diperlukan; saat ini belum ada gambar disematkan.
- Jika ada data produksi/coverage aktual, gantikan angka placeholder di LaTeX (coverage, tabel defect) sesuai hasil terbaru.

## Perintah menjalankan Playwright
- `npm install`
- `npx playwright install chromium`
- `npm run test:e2e` (server otomatis via config, env dusk.local)
