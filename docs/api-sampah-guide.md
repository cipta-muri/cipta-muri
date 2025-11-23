# API Sampah untuk Mobile Kotlin

Dokumen ini menjelaskan seluruh endpoint sampah yang perlu diintegrasikan oleh aplikasi Android (Kotlin + OkHttp/Moshi), baik yang publik maupun yang memerlukan token rekening.

## Daftar Endpoint

| Akses  | Method | Path                             | Deskripsi |
| ------ | ------ | -------------------------------- | --------- |
| Publik | GET    | /api/sampah                      | Daftar semua jenis sampah (opsional query `q`). |
| Publik | GET    | /api/sampah/{id}                 | Detail satu jenis sampah berdasar ID. |
| Token  | GET    | /api/setor-sampah/statistik-jenis| Total berat sampah per jenis milik nasabah login. |

## Endpoint Publik `/api/sampah`

### Contoh Response `GET /api/sampah`
```json
{
  "success": true,
  "data": [
    {
      "id": "sampah-001",
      "jenis_sampah": "Plastik PET",
      "kode_sampah": "PET",
      "kategori": "Plastik",
      "harga_per_kg": "3500.00",
      "simpan_berat": true,
      "total_berat_terkumpul": "120.50"
    }
  ]
}
```

## Implementasi Kotlin

Tambahkan ke `build.gradle` (module):
```kotlin
implementation("com.squareup.okhttp3:okhttp:4.12.0")
implementation("com.squareup.moshi:moshi-kotlin:1.15.0")
```

### Model
```kotlin
data class SampahResponse(val success: Boolean, val data: List<SampahDto>)

data class SampahDto(
    val id: String,
    val jenis_sampah: String,
    val kode_sampah: String?,
    val kategori: String?,
    val harga_per_kg: String?,
    val simpan_berat: Boolean?,
    val total_berat_terkumpul: String?
)
```

### Repository
```kotlin
class SampahRepository(
    private val client: OkHttpClient,
    private val moshi: Moshi = Moshi.Builder().build(),
    private val baseUrl: String = BuildConfig.API_BASE_URL,
) {
    private val listAdapter = moshi.adapter(SampahResponse::class.java)

    suspend fun fetchSampah(query: String? = null): Result<List<SampahDto>> =
        withContext(Dispatchers.IO) {
            val urlBuilder = HttpUrl.Builder()
                .scheme("https")
                .host(baseUrl.removePrefix("https://"))
                .addPathSegments("api/sampah")
            query?.takeIf { it.isNotBlank() }?.let { urlBuilder.addQueryParameter("q", it) }

            val request = Request.Builder()
                .url(urlBuilder.build())
                .get()
                .header("Accept", "application/json")
                .build()

            client.newCall(request).execute().use { response ->
                if (!response.isSuccessful)
                    return@withContext Result.failure(IOException("HTTP ${'$'}{response.code}"))
                val body = response.body?.string() ?: return@withContext Result.failure(IOException("Empty body"))
                val parsed = listAdapter.fromJson(body)
                    ?: return@withContext Result.failure(IOException("Parse error"))
                Result.success(parsed.data)
            }
        }
}
```

### Penggunaan di ViewModel
```kotlin
class SampahViewModel(private val repository: SampahRepository) : ViewModel() {
    private val _items = MutableStateFlow<List<SampahDto>>(emptyList())
    val items: StateFlow<List<SampahDto>> = _items

    fun load(query: String? = null) {
        viewModelScope.launch {
            when (val result = repository.fetchSampah(query)) {
                is Result.Success -> _items.value = result.getOrThrow()
                is Result.Failure -> {
                    // tampilkan snackbar/toast
                }
            }
        }
    }
}
```

## Tips
- Endpoint publik, jadi tidak perlu token.
- Tambahkan caching lokal (Room) jika daftar jarang berubah.
- Gunakan query `q` untuk pencarian (misal "plastik").

Dengan API ini, aplikasi Android dapat menampilkan daftar jenis sampah dengan detail harga, kategori, dan total berat yang tersedia di sistem.

## Endpoint Statistik Berat per Jenis `/api/setor-sampah/statistik-jenis`

Endpoint ini membutuhkan token Sanctum rekening (`Authorization: Bearer <token>`). Response berisi daftar jenis sampah beserta total berat (netto masuk - keluar) yang pernah dicatat untuk nasabah yang sedang login.

### Contoh Response
```json
{
  "success": true,
  "data": [
    {
      "sampah_id": "sampah-001",
      "jenis_sampah": "Plastik PET",
      "kode_sampah": "PET",
      "total_berat": "12.50"
    }
  ]
}
```

### Catatan
- Data diambil dari tabel `sampah_transactions` dan otomatis mengurangi transaksi bertipe `keluar`.
- Hanya transaksi dengan `rekening_id` milik token aktif yang dihitung.
- Jika sebuah jenis sampah belum pernah disetorkan oleh nasabah tersebut, tidak akan muncul.

### Konsumsi di Android
1. Login untuk mendapatkan token nasabah.
2. Sertakan header `Authorization` dan `Accept: application/json`.
3. Tampilkan daftar jenis + berat per jenis (mis. untuk grafik atau kartu ringkasan).

Dengan menggabungkan endpoint publik dan terautentikasi ini, aplikasi Android dapat menampilkan katalog sampah umum dan statistik pribadi nasabah pada satu modul integrasi.
