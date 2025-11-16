## Panduan Integrasi Permintaan Setor & Tarik Saldo (Android Kotlin)

Dokumen ini menjelaskan cara menggunakan endpoint terbaru untuk membuat permintaan setor sampah dan tarik saldo melalui aplikasi Android.

### 1. Prasyarat

1. Nasabah sudah login menggunakan endpoint `POST /api/nasabah/login` dan menyimpan token bearer dari Sanctum.
2. Setiap request wajib menyertakan header:
   - `Authorization: Bearer {token}`
   - `Accept: application/json`
   - Opsional untuk audit: `X-Device-Id`, `X-App-Version`.

### 2. Endpoint Permintaan Setor Sampah

`POST /api/permintaan/setor-sampah`

Body (JSON):

```json
{
  "tanggal_setor": "2025-11-17",
  "items": [
    { "sampah_id": "01HF...", "berat": 3.5, "description": "Botol plastik" },
    { "sampah_id": "01HG...", "berat": 1.2 }
  ]
}
```

Respons 201:

```json
{
  "success": true,
  "message": "Permintaan setor sampah berhasil dikirim.",
  "data": {
    "permintaan": { "...": "..." },
    "qr_url": "https://domain/permintaan/qr/setor-sampah/..."
  }
}
```

Catatan:
- `items` minimal 1 baris.
- `berat` dalam kilogram (min 0.01).
- Sistem otomatis menghitung total berat/saldo berdasarkan master `sampah`.
- `qr_url` bisa dipakai untuk menampilkan QR atau deep link status.

### 3. Endpoint Permintaan Tarik Saldo

`POST /api/permintaan/tarik-saldo`

Body:

```json
{
  "amount": 150000,
  "jenis": "transfer",
  "catatan": "Butuh tunai minggu depan"
}
```

Respons:

```json
{
  "success": true,
  "message": "Permintaan tarik saldo berhasil dikirim.",
  "data": {
    "permintaan": { "...": "..." },
    "qr_url": "https://domain/permintaan/qr/tarik-saldo/..."
  }
}
```

Validasi:
- `amount` minimal Rp1.000 dan tidak boleh melebihi saldo berjalan.
- `jenis` opsional (`tunai`, `transfer`, dll) maksimal 50 karakter.

### 4. Contoh Implementasi Retrofit

#### Interface

```kotlin
interface PermintaanApi {
    @POST("permintaan/setor-sampah")
    suspend fun createSetor(
        @Body body: PermintaanSetorRequest
    ): ApiResponse<PermintaanResponse>

    @POST("permintaan/tarik-saldo")
    suspend fun createTarik(
        @Body body: PermintaanTarikRequest
    ): ApiResponse<PermintaanResponse>
}
```

#### DTO

```kotlin
data class PermintaanSetorRequest(
    @SerializedName("tanggal_setor") val tanggal: String?,
    val items: List<Item>
) {
    data class Item(
        @SerializedName("sampah_id") val sampahId: String,
        val berat: Double,
        val description: String? = null
    )
}

data class PermintaanTarikRequest(
    val amount: Double,
    val jenis: String? = null,
    val catatan: String? = null
)

data class PermintaanResponse(
    val permintaan: JsonObject,
    @SerializedName("qr_url") val qrUrl: String
)
```

#### OkHttp Interceptor untuk Header

```kotlin
class AuthInterceptor(
    private val tokenProvider: () -> String,
    private val deviceIdProvider: () -> String
) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val request = chain.request().newBuilder()
            .addHeader("Authorization", "Bearer ${tokenProvider()}")
            .addHeader("Accept", "application/json")
            .addHeader("X-Device-Id", deviceIdProvider())
            .addHeader("X-App-Version", BuildConfig.VERSION_NAME)
            .build()
        return chain.proceed(request)
    }
}
```

#### Pemanggilan di ViewModel

```kotlin
viewModelScope.launch {
    val request = PermintaanSetorRequest(
        tanggal = LocalDate.now().toString(),
        items = listOf(
            PermintaanSetorRequest.Item(sampahId = "01HF...", berat = 2.0)
        )
    )
    try {
        val response = api.createSetor(request)
        // tampilkan QR atau update UI
    } catch (e: HttpException) {
        // handle error, parsing body.message
    }
}
```

### 5. UI/UX Saran

1. Setelah submit, tampilkan ringkasan permintaan + tombol “Lihat QR”.
2. Simpan response di local DB untuk daftar riwayat permintaan.
3. Gunakan `qr_url` untuk men-generate QR (pakai ZXing) atau menampilkan tautan.
4. Lakukan polling ringan (mis. setiap 30 detik) atau gunakan push notification untuk status perubahan.

### 6. Error Handling

- Jika terjadi `422`, tampilkan pesan dari JSON `errors`.
- Jika `401`, redirect ke layar login (token expired).
- Jika jaringan putus, simpan permintaan di local storage dan kirim ulang saat online.

### 7. Checklist Implementor

- [ ] Retrofit + OkHttp siap dengan header otomatis.
- [ ] Form validasi (jumlah minimal/format).
- [ ] Riwayat permintaan dengan status (menunggu/disetujui/ditolak).
- [ ] QR preview & share.
- [ ] Notifikasi sukses/error yang ramah pengguna.

Dengan mengikuti panduan ini, tim Android dapat menambahkan fitur permintaan setor dan tarik saldo tanpa membangun ulang UI backoffice.
