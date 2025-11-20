# CiptaMuri AI Chat – Debug Guide (API & Backend)

Tujuan: menjelaskan kenapa chatbot di mobile masih gagal (contoh: error `Sesi chat belum siap. Coba kirim lagi.` dari HTTP 419) dan apa yang perlu dicek di sisi API/back-end agar bisa diakses tanpa login.

## Konteks Implementasi Mobile
- Mobile mengirim ke `BuildConfig.CHAT_BASE_URL` (default `https://ciptamuri.com` unless `chat.apiUrl` di `local.properties`).
- Endpoint chat: `POST {BASE_URL}/api/chat`.
- CSRF bootstrap (tanpa login): `GET {BASE_URL}/sanctum/csrf-cookie`. Cookie XSRF-TOKEN dari sini dipakai sebagai header `X-XSRF-TOKEN` untuk permintaan berikutnya.
- Tidak ada Authorization header/token yang dikirim dari mobile untuk chat (akses harus public/guest).
- Client memakai OkHttp + cookie jar; saat dapat 419, cookie lama dihapus dan bootstrap CSRF diulang once.

## Gejala yang Terlihat
- Balasan gagal dengan status 419 dan pesan “Sesi chat belum siap. Coba kirim lagi.”
- Ini berarti server tidak menerima/validasi CSRF yang diharapkan, atau endpoint menolak akses guest.

## Checklist API/Backend
1) **Pastikan CSRF-cookie accessible untuk guest**
   - `GET {BASE_URL}/sanctum/csrf-cookie` harus 200 dan mengirim cookie `XSRF-TOKEN`.
   - Jika ada middleware auth/role di route ini, cabut untuk mobile.

2) **Pastikan path chat terbuka untuk guest**
   - `POST {BASE_URL}/api/chat` harus bisa diakses tanpa session login.
   - Kalau backend Laravel, pastikan route tidak lewat middleware auth/session; cukup validasi payload saja.

3) **CORS dan cookie**
   - Izinkan origin aplikasi mobile (atau wildcard untuk sementara) agar cookie XSRF-TOKEN diterima.
   - Header yang harus diizinkan: `X-Requested-With`, `X-XSRF-TOKEN`, `Content-Type`.
   - Pastikan `supports_credentials` di CORS true jika menggunakan Sanctum + cookie.

4) **Perbedaan base URL**
   - Jika server chat beda domain (misal subdomain khusus), set `chat.apiUrl=https://chat.example.com` di `local.properties`.
   - `CHAT_BASE_URL` harus konsisten antara endpoint CSRF (`/sanctum/csrf-cookie`) dan chat (`/api/chat`).

5) **Validasi manual via curl**
   ```bash
   # 1) Ambil CSRF (harus 200, ada Set-Cookie XSRF-TOKEN)
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
   - Jika 419 di sini, masalah ada di backend (CSRF/session/guest access).

6) **Sanctum konfigurasi**
   - `SESSION_DOMAIN` harus cocok dengan domain cookie yang dikirim ke mobile.
   - Jika pakai subdomain, tambahkan titik depan (contoh `.example.com`).
   - Jika hanya butuh CSRF untuk guest, pastikan middleware `EnsureFrontendRequestsAreStateful` tidak memaksa auth untuk path `/api/chat`.

7) **Rate limit atau firewall**
   - Pastikan tidak ada WAF/limit khusus yang menolak tanpa Authorization header.

## Rekomendasi cepat untuk membuka akses
- Jadikan `/sanctum/csrf-cookie` dan `/api/chat` bebas auth (guest) tapi tetap guarded oleh CSRF.
- Pastikan CORS mengizinkan origin aplikasi mobile dan header `X-XSRF-TOKEN`.
- Uji dengan curl di atas dari jaringan yang sama dengan device. Jika sukses, mobile harus ikut sukses (cookie jar sudah diterapkan).

## Jika tetap 419 dari mobile
- Log di backend apa nilai XSRF yang diterima dan domain cookie yang terbaca.
- Periksa apakah base URL di mobile benar (lihat BuildConfig di APK atau set `chat.apiUrl` lokal).
- Pastikan server memberi Set-Cookie dengan path `/` dan domain sesuai; Secure flag disarankan (HTTPS).
