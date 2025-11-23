# CiptaMuri AI Chat - Debug Guide (API & Backend)

Tujuan: menjelaskan kenapa chatbot di mobile gagal (contoh error `Sesi chat belum siap. Coba kirim lagi.` dari HTTP 419) dan apa yang perlu dicek di sisi API/back-end agar bisa diakses tanpa login.

## Konteks Implementasi Mobile
- Mobile mengirim ke `BuildConfig.CHAT_BASE_URL` (default `https://ciptamuri.com` kecuali `chat.apiUrl` di `local.properties` ditimpa).
- Endpoint chat: `POST {BASE_URL}/api/chat`.
- Bootstrap CSRF tanpa login: `GET {BASE_URL}/sanctum/csrf-cookie`. Cookie `XSRF-TOKEN` dari sini dikirim lewat header `X-XSRF-TOKEN` ke permintaan berikutnya.
- Tidak ada Authorization header/token yang dikirim dari mobile untuk chat (akses publik/guest).
- Client memakai OkHttp + cookie jar; ketika mendapat 419, cookie lama dibuang dan bootstrap CSRF diulang sekali.

## Gejala yang Terlihat
- Balasan gagal dengan status 419 dan pesan `Sesi chat belum siap. Coba kirim lagi.`.
- Ini mengindikasikan server tidak menerima atau memvalidasi CSRF sesuai ekspektasi, atau endpoint menolak akses guest.

## Checklist API/Backend
1. **Pastikan CSRF cookie bisa diambil guest**
   - `GET {BASE_URL}/sanctum/csrf-cookie` harus 200 dan mengirim cookie `XSRF-TOKEN`.
   - Jika ada middleware auth/role di route ini, cabut agar mobile bisa bootstrap.

2. **Pastikan route chat terbuka**
   - `POST {BASE_URL}/api/chat` harus bisa diakses tanpa session login.
   - Untuk Laravel, hindari middleware auth/session di route ini; cukup validasi payload.

3. **CORS dan cookie**
   - Izinkan origin aplikasi mobile (atau wildcard sementara) agar cookie `XSRF-TOKEN` diterima.
   - Header wajib diizinkan: `X-Requested-With`, `X-XSRF-TOKEN`, `Content-Type`.
   - Aktifkan `supports_credentials` di konfigurasi CORS kalau memakai Sanctum + cookie.

4. **Konsistensi base URL**
   - Jika server chat beda domain (misal subdomain khusus), set `chat.apiUrl=https://chat.example.com` di `local.properties`.
   - `CHAT_BASE_URL` harus sama untuk endpoint CSRF (`/sanctum/csrf-cookie`) dan chat (`/api/chat`).

5. **Validasi manual via curl**
   ```bash
   # 1) Ambil CSRF (expected 200 + Set-Cookie XSRF-TOKEN)
   curl -i -c cookies.txt https://your-base-url/sanctum/csrf-cookie

   # 2) Kirim chat dengan cookie + header X-XSRF-TOKEN
   TOKEN=$(grep XSRF-TOKEN cookies.txt | awk '{print $7}')
   curl -i -b cookies.txt -H "X-XSRF-TOKEN: $TOKEN" \
     -H "Accept: application/json" \
     -H "X-Requested-With: XMLHttpRequest" \
     -H "Content-Type: application/json" \
     -d '{"message":"halo","history":[]}' \
     https://your-base-url/api/chat
   ```
   - Expected: HTTP 200 dengan body `{"response": "<string>"}`.
   - Jika tetap 419, masalah di backend (CSRF/session/akses guest).

6. **Konfigurasi Sanctum**
   - `SESSION_DOMAIN` harus cocok dengan domain cookie ke mobile.
   - Jika pakai subdomain, tambahkan titik depan (contoh `.example.com`).
   - Jika hanya perlu CSRF guest, pastikan middleware `EnsureFrontendRequestsAreStateful` tidak memaksa auth untuk `/api/chat`.

7. **Rate limit atau firewall**
   - Pastikan tidak ada WAF/limit khusus yang menolak request tanpa Authorization header.

## Rekomendasi cepat membuka akses
- Buka `/sanctum/csrf-cookie` dan `/api/chat` untuk guest tetapi tetap pakai proteksi CSRF.
- Pastikan CORS mengizinkan origin aplikasi mobile dan header `X-XSRF-TOKEN`.
- Jalankan uji curl di atas dari jaringan yang sama dengan device; jika sukses, mobile juga seharusnya berhasil karena cookie jar sudah dipakai.

## Jika mobile tetap mendapat 419
- Log di backend nilai XSRF yang diterima serta domain cookie yang terbaca.
- Pastikan base URL di mobile benar (cek `BuildConfig` di APK atau override `chat.apiUrl`).
- Pastikan server memberi Set-Cookie dengan path `/`, domain yang sesuai, dan flag Secure jika melalui HTTPS.
