<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    protected $gemini;

    /**
     * Additional descriptions for important tables to guide the LLM.
     *
     * @var array<string, string>
     */
    protected array $tableDescriptions = [
        'setor_sampah' => 'Riwayat setoran sampah. Gunakan kolom berat, berat_total, tanggal_setor, rekening_id, user_id, total_harga.',
        'sampah_transactions' => 'Rincian item sampah per transaksi setoran. Kolom jumlah, berat, harga, kategori.',
        'rekening' => 'Data rekening / nasabah bank sampah. Kolom balance, points_balance, status_desa, status_lengkap.',
        'saldo_transactions' => 'Mutasi saldo rekening (credit/debit). Kolom amount, type, rekening_id, description.',
        'poin_transactions' => 'Mutasi poin pengguna. Kolom amount, rekening_id, description.',
        'permintaan_tarik_saldo' => 'Permintaan penarikan saldo yang diajukan nasabah. Kolom amount, status, rekening_id.',
        'permintaan_setor_sampah' => 'Permintaan penjemputan/setoran sampah. Kolom berat_estimasi, status, rekening_id.',
        'users' => 'Akun pengguna internal/admin.',
        'news' => 'Konten berita / publikasi.',
        'activity_log' => 'Log aktivitas sistem.',
    ];

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'history' => 'array',
        ]);

        $message = $request->input('message');
        $history = $request->input('history', []);

        // Simple heuristic to decide if we need DB access
        // In a real app, we might ask the AI to classify the intent first.
        $needsDb = $this->checkIfNeedsDb($message);

        if ($needsDb) {
            try {
                $schema = $this->getDatabaseSchema();
                $sql = $this->gemini->generateSql($message, $schema);

                // Clean up SQL (remove markdown code blocks if present)
                $sql = preg_replace('/^```sql\s*|```\s*$/', '', trim($sql));

                if (str_starts_with(strtoupper($sql), 'SELECT')) {
                    $results = DB::select($sql);
                    $response = $this->gemini->interpretResults($message, $results);
                } else {
                    $response = "Saya tidak dapat membuat query yang aman untuk permintaan tersebut. (Query: $sql)";
                }
            } catch (\Exception $e) {
                Log::error('Chat DB Error: '.$e->getMessage());
                $response = 'Terjadi kesalahan ketika mengakses database: '.$e->getMessage();
            }
        } else {
            $response = $this->gemini->generateContent($message, $history);
        }

        return response()->json(['response' => $response]);
    }

    private function checkIfNeedsDb(string $message): bool
    {
        $message = mb_strtolower($message);

        $primaryKeywords = [
            'database', 'data', 'saldo', 'setoran', 'setor', 'berat', 'transaksi', 'rekap', 'riwayat', 'tarik saldo',
            'poin', 'permintaan', 'nasabah', 'rekening', 'users', 'logs', 'activity', 'laporan', 'statistik'
        ];

        $contextualIndicators = [
            'berapa', 'jumlah', 'total', 'list', 'daftar', 'show', 'how many', 'average', 'report', 'history',
            'trend', 'statistik', 'rekap', 'perubahan', 'grafik'
        ];

        $primaryHits = 0;
        foreach ($primaryKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                $primaryHits++;
            }
        }

        $hasIndicator = Str::contains($message, $contextualIndicators) || preg_match('/\d/', $message);

        return $primaryHits >= 1 && $hasIndicator;
    }

    private function getDatabaseSchema(): string
    {
        $tables = Schema::getTableListing();
        $schema = '';

        foreach ($tables as $table) {
            $columns = Schema::getColumnListing($table);
            $readable = Str::headline($table);
            $description = $this->tableDescriptions[$table] ?? "Data terkait {$readable}.";
            $schema .= "Tabel: {$table} ({$readable})\nDeskripsi: {$description}\nKolom: ".implode(', ', $columns)."\n\n";
        }

        return $schema;
    }
}
