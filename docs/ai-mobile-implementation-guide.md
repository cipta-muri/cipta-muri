# Panduan Implementasi CiptaMuri AI di Android (Kotlin + Jetpack Compose)

Dokumen ini menjelaskan cara mereplikasi pengalaman chatbot CiptaMuri AI (ikon, animasi, integrasi API) ke aplikasi Android Studio berbasis Kotlin dan Jetpack Compose. Panduan bersifat praktikal sehingga tim mobile dapat mengikuti langkah demi langkah.

---

## 1. Kebutuhan Awal

1. **Android Studio Hedgehog atau lebih baru** dengan SDK 24+.
2. **Language level**: Kotlin 1.9+.
3. **Dependensi utama**:
   ```kotlin
   implementation("androidx.compose.ui:ui:<latest>")
   implementation("androidx.compose.material3:material3:<latest>")
   implementation("androidx.navigation:navigation-compose:<latest>")
   implementation("io.coil-kt:coil-compose:<latest>") // jika butuh ikon dinamis
   implementation("com.squareup.okhttp3:okhttp:4.12.0")
   implementation("com.squareup.moshi:moshi-kotlin:1.15.0")
   implementation("com.airbnb.android:lottie-compose:6.5.0") // untuk animasi partikel
   ```
4. **Endpoint back-end**: gunakan `POST /api/chat` yang sudah ada pada aplikasi Laravel. Pastikan otentikasi (bila ada) dan CORS untuk domain mobile sudah diizinkan.

---

## 2. Arsitektur Chat di Mobile

1. **ViewModel** (`ChatViewModel`) untuk menangani state:
   - daftar pesan (`List<Message>`),
   - input pengguna,
   - status loading,
   - animasi idle.
2. **Repository** (`ChatRepository`) yang melakukan request OkHttp ke `/api/chat`.
3. **UI Compose**:
   - `FloatingChatButton()` (ikon + animasi idle),
   - `ChatSheet()` atau `ChatDialog()` meniru tampilan panel web.

> **Tip**: gunakan `ModalBottomSheet` Compose untuk memunculkan panel agar konsisten dengan web.

---

## 3. Skema Data & Model Kotlin

```kotlin
data class ChatMessage(
    val id: String = UUID.randomUUID().toString(),
    val role: Role,
    val content: String,
)

enum class Role { USER, MODEL }

data class ChatRequest(
    val message: String,
    val history: List<HistoryMessage>,
)

data class HistoryMessage(
    val role: String,
    val content: String,
)

data class ChatResponse(
    val response: String,
)
```

Konversi `history` mengikuti struktur di aplikasi web (role `user` atau `model`). History bisa diambil dari state lokal ViewModel.

---

## 4. Integrasi API

```kotlin
class ChatRepository(
    private val client: OkHttpClient,
    private val moshi: Moshi,
) {
    private val requestAdapter = moshi.adapter(ChatRequest::class.java)
    private val responseAdapter = moshi.adapter(ChatResponse::class.java)

    suspend fun sendMessage(message: String, history: List<ChatMessage>): Result<String> =
        withContext(Dispatchers.IO) {
            val payload = ChatRequest(
                message = message,
                history = history.map {
                    HistoryMessage(role = if (it.role == Role.USER) "user" else "model", content = it.content)
                },
            )

            val requestBody = requestAdapter.toJson(payload).toRequestBody("application/json".toMediaType())
            val request = Request.Builder()
                .url("https://<domain-anda>/api/chat")
                .post(requestBody)
                .build()

            client.newCall(request).execute().use { response ->
                if (!response.isSuccessful) return@withContext Result.failure(IOException("HTTP ${response.code}"))
                val body = response.body?.string() ?: return@withContext Result.failure(IOException("Empty body"))
                val parsed = responseAdapter.fromJson(body) ?: return@withContext Result.failure(IOException("Parse error"))
                Result.success(parsed.response)
            }
        }
}
```

Tambahkan header auth/token bila server memerlukannya.

### 4.1 Ringkasan Berat per Jenis (opsional)
Untuk menampilkan statistik berat per jenis sampah milik nasabah, gunakan endpoint:

```
GET /api/setor-sampah/statistik-jenis
```

- Wajib menyertakan token Sanctum nasabah.
- Response berupa array `sampah_id`, `jenis_sampah`, `kode_sampah`, dan `total_berat` (netto).
- Gunakan endpoint ini ketika nasabah membuka halaman ringkasan sehingga ponsel tidak perlu menghitung manual.


---

## 5. UI: Floating Button + Animasi (Ikon AI)

### 5.1 State & Animasi Idle

Gunakan `rememberInfiniteTransition` untuk efek glow dan ring seperti di web.

```kotlin
@Composable
fun FloatingChatButton(
    isOpen: Boolean,
    onClick: () -> Unit,
) {
    val infiniteTransition = rememberInfiniteTransition(label = "idle")
    val glowAlpha by infiniteTransition.animateFloat(
        initialValue = 0.2f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(
            animation = tween(1300, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "glowAlpha"
    )
    val ringScale by infiniteTransition.animateFloat(
        initialValue = 0.9f,
        targetValue = 1.4f,
        animationSpec = infiniteRepeatable(
            animation = tween(2600, easing = LinearEasing),
            repeatMode = RepeatMode.Restart
        ),
        label = "ringScale"
    )

    Box(
        modifier = Modifier
            .size(72.dp)
            .clip(RoundedCornerShape(24.dp))
            .background(
                brush = Brush.linearGradient(
                    listOf(Color(0xFF10B981), Color(0xFF34D399))
                )
            )
            .clickable { onClick() },
        contentAlignment = Alignment.Center
    ) {
        if (!isOpen) {
            Box(
                modifier = Modifier
                    .matchParentSize()
                    .graphicsLayer(scaleX = ringScale, scaleY = ringScale, alpha = 0.25f)
                    .background(Color(0x8010B981), shape = CircleShape)
            )
        }
        Icon(
            imageVector = if (isOpen) Icons.Default.Close else Icons.Default.AutoAwesome,
            contentDescription = null,
            tint = Color.White.copy(alpha = glowAlpha),
            modifier = Modifier.size(28.dp)
        )
        Text("AI", color = Color.White, fontWeight = FontWeight.Bold)
    }
}
```

### 5.2 Panel Chat

Gunakan `ModalBottomSheet` atau `AnimatedVisibility` untuk menampilkan panel:

```kotlin
@Composable
fun ChatPanel(
    messages: List<ChatMessage>,
    input: String,
    onInputChange: (String) -> Unit,
    onSend: () -> Unit,
) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(max = 520.dp)
            .clip(RoundedCornerShape(topStart = 32.dp, topEnd = 32.dp))
            .background(Color.White.copy(alpha = 0.9f))
            .padding(16.dp)
    ) {
        // header, list, input mirip web
        LazyColumn(
            modifier = Modifier.weight(1f),
            contentPadding = PaddingValues(vertical = 8.dp)
        ) {
            items(messages) { msg ->
                MessageBubble(msg)
            }
        }

        Row(
            modifier = Modifier
                .clip(RoundedCornerShape(50))
                .background(Color.White)
                .padding(horizontal = 12.dp, vertical = 4.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            TextField(
                value = input,
                onValueChange = onInputChange,
                modifier = Modifier.weight(1f),
                placeholder = { Text("Tulis pesan...") },
                colors = TextFieldDefaults.textFieldColors(containerColor = Color.Transparent)
            )
            IconButton(onClick = onSend, enabled = input.isNotBlank()) {
                Icon(Icons.Default.Send, contentDescription = null, tint = Color(0xFF10B981))
            }
        }
    }
}
```

`MessageBubble()` set panggilan border, warna gradient mirip web:

```kotlin
@Composable
fun MessageBubble(message: ChatMessage) {
    val isUser = message.role == Role.USER
    val background = if (isUser) Brush.linearGradient(listOf(Color(0xFF10B981), Color(0xFF34D399))) else null
    val bubbleModifier = Modifier
        .padding(vertical = 4.dp)
        .fillMaxWidth(0.8f)
        .clip(RoundedCornerShape(24.dp))
        .background(
            brush = background ?: Brush.linearGradient(listOf(Color.White, Color.White)),
            alpha = if (isUser) 1f else 0.9f
        )
        .border(
            width = 1.dp,
            color = if (isUser) Color.Transparent else Color(0xFFE5E7EB),
            shape = RoundedCornerShape(24.dp)
        )
        .align(if (isUser) Alignment.CenterEnd else Alignment.CenterStart)

    Column(
        modifier = bubbleModifier.padding(horizontal = 16.dp, vertical = 12.dp)
    ) {
        message.content
            .split("\n\n")
            .forEachIndexed { index, paragraph ->
                Text(
                    text = paragraph.trim(),
                    color = if (isUser) Color.White else Color(0xFF065F46),
                    modifier = if (index > 0) Modifier.padding(top = 4.dp) else Modifier
                )
            }
    }
}
```

---

## 6. Manajemen History & Feedback

1. **History per sesi**: simpan di `ChatViewModel` agar setiap kirim pesan baru, history dikirim ke backend.
2. **Koreksi/memori**: jika ingin meniru fitur “koreksi: …”, sediakan input khusus. Kirim pesan dengan awalan `koreksi:` seperti di web agar backend mengenali dan menyimpan memori.

---

## 7. Konfigurasi Pranala & Keamanan

1. Simpan `BASE_URL` atau `CHAT_API_URL` di `local.properties` / BuildConfig, bukan hard-coded.
2. Jika perlu token, gunakan interceptor OkHttp untuk menambahkan header `Authorization`.
3. Aktifkan HTTPS (wajib) untuk mencegah sniffing.

---

## 8. Testing

1. **Unit test**: mocking `ChatRepository` untuk memastikan ViewModel menambah pesan sesuai respon.
2. **Instrumented test**: gunakan Compose Testing (`compose.ui.test`) untuk memastikan animasi & bubble tampil benar.
3. **Network error**: tampilkan fallback “Maaf, terjadi kesalahan…” sama seperti web.

---

## 9. Deployment Checklist

1. Pastikan `/api/chat` bisa diakses dari perangkat (izin internet, domain valid).
2. Jika server butuh cookie/session, gunakan token khusus mobile agar lebih aman.
3. Build release + proguard: tambahkan keep rules untuk Moshi/OkHttp.

---

## 10. Rangkuman

- **UI**: Floating button + panel mengikuti gaya web (gradient hijau, animasi idle).
- **Integrasi**: Reuse endpoint `/api/chat` dengan payload `message` + `history`.
- **Interaksi**: Animasi idle berjalan hingga pengguna membuka panel; bubble mendukung paragraf berganda.
- **Perawatan**: Update `BASE_URL`, tangani error network, sediakan log catatan per chat bila diperlukan.

Dengan panduan ini, tim mobile bisa mereplikasi CiptaMuri AI pada aplikasi Android dengan tampilan dan perilaku yang konsisten dengan versi web.

