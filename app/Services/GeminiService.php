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
            $role = $msg['role'] ?? 'user';
            $text = $msg['content'] ?? '';

            if ($role === 'system') {
                $contents[] = [
                    'role' => 'user',
                    'parts' => [['text' => "[INSTRUKSI SISTEM]\n".$text]],
                ];

                continue;
            }

            $contents[] = [
                'role' => $role === 'model' ? 'model' : 'user',
                'parts' => [['text' => $text]],
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

    public function answerFromSnapshot(string $prompt, array $snapshot): string
    {
        $tables = $snapshot['tables'] ?? $snapshot;
        $metadata = $snapshot['generated_at'] ?? now()->toIso8601String();
        $maxRows = $snapshot['max_rows_per_table'] ?? 'tidak diketahui';

        $context = "Kamu adalah analis data Bank Sampah Cipta Muri. Data berikut adalah snapshot JSON database yang harus kamu gunakan untuk menjawab.\n".
            "Tanggal snapshot: {$metadata}\n".
            "Maksimal baris per tabel: {$maxRows}\n".
            "Jika data yang diminta tidak ditemukan pada snapshot, jawab dengan jujur berdasarkan apa yang tersedia tanpa mengarang.\n".
            "JSON Data:\n".json_encode($tables, JSON_UNESCAPED_UNICODE);

        $history = [
            ['role' => 'system', 'content' => $context],
        ];

        return $this->generateContent($prompt, $history);
    }
}
