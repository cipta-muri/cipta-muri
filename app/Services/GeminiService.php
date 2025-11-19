<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected $apiKey;

    protected $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
    }

    public function generateContent(string $prompt, array $history = [])
    {
        $contents = [];

        foreach ($history as $msg) {
            $role = $msg['role'] === 'user' ? 'user' : 'model';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $msg['content']]],
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $prompt]],
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}?key={$this->apiKey}", [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 2048,
            ],
        ]);

        if ($response->failed()) {
            Log::error('Gemini API Error', ['response' => $response->body()]);

            return 'Maaf, saya mengalami kesalahan saat memproses permintaan Anda.';
        }

        $data = $response->json();

        return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Tidak ada respons yang dihasilkan.';
    }

    public function generateSql(string $prompt, string $schemaContext)
    {
        $systemPrompt = "Kamu adalah analis data dan pakar SQL untuk sistem Bank Sampah Cipta Muri. Gunakan informasi skema berikut untuk menjawab pertanyaan pengguna.\n\n".
            "Skema Database & Deskripsi:\n".$schemaContext."\n\n".
            "Instruksi penting:\n".
            "- Gunakan nama tabel persis seperti yang tercantum (misalnya 'setor_sampah', 'saldo_transactions').\n".
            "- Pertanyaan bisa dalam Bahasa Indonesia atau Inggris. Pahami maksudnya, lalu buat query SQL yang sesuai.\n".
            "- Jika diminta total/riwayat setoran, kolom berat dan total_harga berada pada tabel 'setor_sampah'.\n".
            "- Jika diminta saldo nasabah, gunakan tabel 'rekening' dan/atau 'saldo_transactions'.\n".
            "- Jika diminta catatan permintaan tarik saldo, gunakan tabel 'permintaan_tarik_saldo'.\n".
            "- Hanya gunakan perintah SELECT; jangan gunakan INSERT/UPDATE/DELETE.\n".
            "- Kembalikan hanya query SQL tanpa markdown. Jika data tidak lengkap, buat query SELECT terbaik yang paling mendekati kebutuhan (jangan balas 'ERROR').\n\n".
            'Pertanyaan pengguna: '.$prompt;

        // For SQL generation, we don't need history, just the direct prompt
        return $this->generateContent($systemPrompt);
    }

    public function interpretResults(string $originalQuery, array $results)
    {
        $resultsJson = json_encode($results);
        $prompt = "Pengguna bertanya: \"$originalQuery\".\n".
            "Database mengembalikan data JSON berikut:\n".$resultsJson."\n\n".
            "Berikan ringkasan dalam bahasa alami untuk menjawab pertanyaan pengguna.";

        return $this->generateContent($prompt);
    }
}
