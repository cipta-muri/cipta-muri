# API Sampah Transactions untuk Mobile Kotlin

Endpoint ini mengembalikan riwayat detail sampah (masuk/keluar) milik nasabah yang sedang login. Data bisa dipakai untuk layar histori setoran/penarikan per jenis sampah di aplikasi Android.

## Endpoint

| Method | Path | Auth | Deskripsi |
| ------ | ---- | ---- | --------- |
| GET | /api/sampah-transactions | Bearer token (nasabah) | Daftar transaksi sampah milik rekening login. |

### Query params
- `type` _(opsional)_: `masuk` atau `keluar`.
- `sampah_id` _(opsional)_: filter berdasarkan jenis sampah tertentu.
- `limit` _(opsional)_: jumlah baris yang diambil (default `50`, maksimal `100`).

## Contoh cURL

```bash
curl -X GET https://ciptamuri.com/api/sampah-transactions \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN_REKENING>" \
  --data-urlencode "type=masuk" \
  --data-urlencode "limit=25"
```

## Contoh Response

```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "type": "masuk",
      "sampah_id": "sampah-001",
      "rekening_id": "01JY2...",
      "berat": "3.50",
      "description": "Setor #SET-2025-0004",
      "transactable_type": "App\\\\Models\\\\SetorSampah",
      "transactable_id": "01JY3...",
      "created_at": "2025-01-05T10:11:12.000000Z",
      "updated_at": "2025-01-05T10:11:12.000000Z",
      "sampah": {
        "id": "sampah-001",
        "jenis_sampah": "Plastik PET",
        "kode_sampah": "PET",
        "kategori": "Plastik",
        "harga_per_kg": "3500.00"
      }
    }
  ],
  "meta": {
    "limit": 25,
    "type": "masuk",
    "sampah_id": null
  }
}
```

Perhatikan bahwa kolom `berat` dikirim dalam bentuk string karena presisi desimal dari database.

## Implementasi Kotlin

Tambahkan dependensi (jika belum ada):

```kotlin
implementation("com.squareup.okhttp3:okhttp:4.12.0")
implementation("com.squareup.moshi:moshi-kotlin:1.15.0")
```

Tambahkan juga `import okhttp3.HttpUrl.Companion.toHttpUrl` di repository agar fungsi `toHttpUrl()` tersedia.

### Data class

```kotlin
data class SampahTransactionResponse(
    val success: Boolean,
    val data: List<SampahTransactionDto>,
    val meta: SampahTransactionMeta?
)

data class SampahTransactionDto(
    val id: Long,
    val type: String?,
    @Json(name = "sampah_id") val sampahId: String,
    @Json(name = "rekening_id") val rekeningId: String,
    val berat: String,
    val description: String,
    @Json(name = "transactable_type") val transactableType: String,
    @Json(name = "transactable_id") val transactableId: String,
    @Json(name = "created_at") val createdAt: String,
    @Json(name = "updated_at") val updatedAt: String,
    val sampah: SampahDto?
)

data class SampahDto(
    val id: String,
    @Json(name = "jenis_sampah") val jenisSampah: String,
    @Json(name = "kode_sampah") val kodeSampah: String?,
    val kategori: String?,
    @Json(name = "harga_per_kg") val hargaPerKg: String?
)

data class SampahTransactionMeta(
    val limit: Int,
    val type: String?,
    @Json(name = "sampah_id") val sampahId: String?
)
```

### Repository

```kotlin
class SampahTransactionRepository(
    private val client: OkHttpClient,
    private val moshi: Moshi = Moshi.Builder()
        .add(KotlinJsonAdapterFactory())
        .build(),
    private val baseUrl: String = BuildConfig.API_BASE_URL,
    private val tokenProvider: () -> String
) {
    private val adapter = moshi.adapter(SampahTransactionResponse::class.java)

    suspend fun fetchTransactions(
        type: String? = null,
        sampahId: String? = null,
        limit: Int = 50
    ): Result<List<SampahTransactionDto>> = withContext(Dispatchers.IO) {
        val httpUrl = baseUrl.toHttpUrl().newBuilder()
            .addPathSegments("api/sampah-transactions")
            .apply {
                if (type in listOf("masuk", "keluar")) addQueryParameter("type", type)
                sampahId?.takeIf { it.isNotBlank() }?.let { addQueryParameter("sampah_id", it) }
                addQueryParameter("limit", limit.coerceIn(1, 100).toString())
            }
            .build()

        val request = Request.Builder()
            .url(httpUrl)
            .get()
            .header("Accept", "application/json")
            .header("Authorization", "Bearer ${'$'}{tokenProvider()}")
            .build()

        client.newCall(request).execute().use { response ->
            if (!response.isSuccessful) {
                return@withContext Result.failure(IOException("HTTP ${'$'}{response.code}"))
            }
            val body = response.body?.string()
                ?: return@withContext Result.failure(IOException("Body kosong"))
            val parsed = adapter.fromJson(body)
                ?: return@withContext Result.failure(IOException("Gagal parsing JSON"))
            Result.success(parsed.data)
        }
    }
}
```

### Penggunaan di ViewModel

```kotlin
class SampahTransactionViewModel(
    private val repository: SampahTransactionRepository
) : ViewModel() {
    private val _items = MutableStateFlow<List<SampahTransactionDto>>(emptyList())
    val items: StateFlow<List<SampahTransactionDto>> = _items

    fun load(type: String? = null) {
        viewModelScope.launch {
            when (val result = repository.fetchTransactions(type = type)) {
                is Result.Success -> _items.value = result.getOrThrow()
                is Result.Failure -> {
                    // TODO: tampilkan error ke UI
                }
            }
        }
    }
}
```

Gunakan token Sanctum yang didapat dari `/api/nasabah/login` pada header `Authorization`. Sesuaikan `baseUrl` dengan konfigurasi aplikasi (contoh `https://ciptamuri.com`). Response `meta` bisa dipakai untuk debugging atau diabaikan jika tidak perlu.
