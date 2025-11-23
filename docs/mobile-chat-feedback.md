# Feedback Integrasi Chat AI untuk Tim Mobile

Status saat ini: Permintaan ke `/api/chat` dari aplikasi Android masih gagal (HTTP 419). Berikut umpan balik lengkap agar tim mobile dapat memvalidasi dan memperbaiki sisi client.

## 1. Pastikan Flow CSRF Diikuti Persis

1. Hit `GET https://<base-url>/sanctum/csrf-cookie` terlebih dahulu.
2. Simpan cookie `XSRF-TOKEN` dan `laravel_session` di cookie jar.
3. Setiap kali memanggil `POST https://<base-url>/api/chat` kirim:
   - Header `X-XSRF-TOKEN` berisi nilai **decoded** dari cookie `XSRF-TOKEN`.
   - Header `X-Requested-With: XMLHttpRequest`.
   - Body JSON `{"message":"...","history":[]}`.

Jika langkah 1 tidak dilakukan, backend akan balas 419.

## 2. Gunakan Base URL yang Sama untuk CSRF & API

`CHAT_BASE_URL` di app harus sama untuk kedua request. Hindari mencampur `http://127.0.0.1:8000` dan `https://ciptamuri.com` dalam satu sesi karena cookie domain-nya berbeda.

## 3. Validasi Manual dengan Curl

Tim mobile mohon mencoba langsung di mesin dev:
```bash
curl -i -c cookies.txt https://<base-url>/sanctum/csrf-cookie
TOKEN=$(grep XSRF-TOKEN cookies.txt | awk '{print $7}')
curl -i -b cookies.txt \
  -H "X-XSRF-TOKEN: $TOKEN" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"message":"halo","history":[]}' \
  https://<base-url>/api/chat
```
Jika curl sukses (200), berarti backend siap pakai dan bug ada di sisi mobile.

## 4. Logging & Debug di Android

- Log semua header & URL yang dikirim.
- Pastikan OkHttp CookieJar menyimpan cookie setelah `sanctum/csrf-cookie`.
- Tangani respon 419 dengan log lengkap (status body) sebelum otomatis retry.

## 5. Saran Implementasi Kotlin

```kotlin
suspend fun bootstrapCsrf(client: OkHttpClient, baseUrl: String) {
    val request = Request.Builder()
        .url("$baseUrl/sanctum/csrf-cookie")
        .get()
        .build()
    client.newCall(request).execute().use { response ->
        if (!response.isSuccessful) throw IOException("CSRF bootstrap failed: ${response.code}")
    }
}
```
Pemanggilan `bootstrapCsrf()` wajib sebelum chat pertama kali atau sesudah cookie kedaluwarsa.

## 6. Komunikasikan Temuan

Jika setelah mengikuti langkah di atas masih gagal, laporkan:
- URL yang digunakan.
- Header & cookie yang dikirim OKHttp.
- Response lengkap (status + body).

Dokumentasi tambahan: `docs/chat-ai-troubleshooting.md` menjelaskan checklist backend yang sudah dicek.
