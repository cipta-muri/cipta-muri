## Panduan Embed Halaman Registrasi Filament di Android (Kotlin)

Registrasi nasabah tersedia sebagai halaman Filament di `https://{domain}/registrasi`. Dokumen ini membantu tim Android menanamkan form tersebut ke aplikasi Kotlin menggunakan `WebView`.

### 1. Kenapa WebView?
- Halaman registrasi sudah menjalankan seluruh validasi & tampilan, sehingga kita tidak perlu menulis ulang UI Native.
- Pembaruan di panel langsung tercermin di aplikasi.
- WebView cukup menampilkan `https://domain/registrasi` (auto-redirect ke form) dan menyembunyikan navigasi admin.

### 2. Persiapan Android Studio

1. Tambahkan permission internet pada `AndroidManifest.xml`:
   ```xml
   <uses-permission android:name="android.permission.INTERNET" />
   ```

2. Buat Activity/Fragment khusus, misal `RegistrasiActivity`.
3. Layout XML:
   ```xml
   <android.webkit.WebView
       android:id="@+id/webRegistrasi"
       android:layout_width="match_parent"
       android:layout_height="match_parent"/>
   ```

4. Aktifkan setting WebView:
   ```kotlin
   class RegistrasiActivity : AppCompatActivity() {
       override fun onCreate(savedInstanceState: Bundle?) {
           super.onCreate(savedInstanceState)
           setContentView(R.layout.activity_registrasi)

           val webView = findViewById<WebView>(R.id.webRegistrasi)
           webView.settings.javaScriptEnabled = true
           webView.settings.domStorageEnabled = true
           webView.webViewClient = object : WebViewClient() {
               override fun shouldOverrideUrlLoading(
                   view: WebView?,
                   request: WebResourceRequest?
               ): Boolean {
                   return false // biarkan semua link di WebView
               }

               override fun onReceivedSslError(
                   view: WebView?,
                   handler: SslErrorHandler?,
                   error: SslError?
               ) {
                   // TODO: handle jika sertifikat gagal (biasanya production aman)
                   handler?.proceed()
               }
           }

           webView.loadUrl("https://domain-anda/registrasi")
       }

       override fun onBackPressed() {
           val webView = findViewById<WebView>(R.id.webRegistrasi)
           if (webView.canGoBack()) webView.goBack() else super.onBackPressed()
       }
   }
   ```

### 3. Mengirim Data Tambahan
- Jika perlu prefill, bisa tambahkan query `?nama=...&nik=...` lalu modifikasi form Filament untuk membaca `request()->query()`.
- Pastikan input yang diisi otomatis tetap dapat diedit user.

### 4. Handling Sukses
- Saat registrasi berhasil Filament menampilkan notifikasi. Tambahkan JavaScript interface jika ingin menangkap event tersebut:
  ```kotlin
  webView.addJavascriptInterface(object {
      @JavascriptInterface
      fun onSuccess(message: String) {
          // tampilkan toast, redirect, dll
      }
  }, "Android")
  ```
  Lalu di view Blade registrasi, setelah form submit sukses, panggil `window.Android.onSuccess('Berhasil')`.

### 5. Loading & Error State
- Gunakan `WebChromeClient` untuk memantau progress:
  ```kotlin
  webView.webChromeClient = object : WebChromeClient() {
      override fun onProgressChanged(view: WebView?, newProgress: Int) {
          // tampilkan progress bar
      }
  }
  ```
- Jika halaman gagal dimuat (mis. offline), tampilkan tombol “Coba Lagi” yang memanggil `webView.reload()`.

### 6. Keamanan
- Pastikan hanya domain resmi yang dimuat (`shouldOverrideUrlLoading` bisa memblokir domain asing).
- Gunakan HTTPS dan sertifikat valid.
- Bila ingin menutup halaman jika user menekan link keluar, periksa `request?.url?.host`.

### 7. Checklist Implementasi
- [ ] Layout WebView dengan progress indicator.
- [ ] WebViewClient + WebChromeClient aktif.
- [ ] JavaScript & DOM storage on.
- [ ] Error handling (offline, SSL, dsb).
- [ ] Event sukses (opsional) via JavaScript interface.

Dengan langkah-langkah tersebut, tim Android bisa menampilkan form registrasi Filament secara seamless tanpa duplikasi logika.
