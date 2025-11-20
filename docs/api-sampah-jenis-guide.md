# API Berat Sampah per Jenis untuk Nasabah

Endpoint ini menampilkan total berat sampah per jenis yang pernah disetorkan oleh nasabah (rekening) yang sedang login.

## Endpoint

```
GET /api/setor-sampah/statistik-jenis
```

- **Autentikasi**: Wajib. Gunakan token Sanctum rekening (`Authorization: Bearer <token>`).
- **Response**: JSON dengan daftar jenis sampah dan total berat (netto: setoran dikurangi keluaran) milik nasabah login.

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
    },
    {
      "sampah_id": "sampah-002",
      "jenis_sampah": "Kertas",
      "kode_sampah": "KRT",
      "total_berat": "9.80"
    }
  ]
}
```

### Catatan
- Berat dihitung dari tabel `sampah_transactions`, otomatis mengurangi transaksi bertipe `keluar`.
- Hanya transaksi milik nasabah yang sedang login (berdasarkan `rekening_id`) yang dihitung.
- Jika sebuah jenis sampah belum pernah disetorkan, tidak akan muncul di daftar.

## Konsumsi di Android

1. Setelah nasabah login, simpan token Sanctum.
2. Panggil endpoint di atas dengan header `Authorization`.
3. Tampilkan daftar jenis sampah + berat pada UI (mis. kartu per jenis).
