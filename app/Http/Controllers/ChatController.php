<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiMemory;
use App\Models\AiMessage;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    protected $gemini;
    protected string $knowledgeFile;

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
        $this->knowledgeFile = base_path('docs/ai-knowledge.md');
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $message = trim($request->input('message'));
        $conversation = $this->resolveConversation($request);
        $history = $this->injectMemories(
            $this->buildHistoryFromConversation($conversation),
            $message,
            $conversation
        );
        $history = $this->prependKnowledgeContext($history);

        $isCorrection = $this->isCorrectionMessage($message);
        $normalizedMessage = $isCorrection ? $this->normalizeCorrectionMessage($message) : $message;

        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => $normalizedMessage,
            'is_correction' => $isCorrection,
        ]);

        if ($isCorrection) {
            $this->storeMemory($conversation, $normalizedMessage);
            $ack = 'Catatan sudah saya simpan. Terima kasih atas koreksinya!';
            $conversation->messages()->create([
                'role' => 'model',
                'content' => $ack,
            ]);
            $conversation->update(['last_message_at' => now()]);

            return response()->json(['response' => $ack]);
        }

        // Simple heuristic to decide if we need DB access
        $needsDb = $this->checkIfNeedsDb($normalizedMessage);

        if ($needsDb) {
            try {
                $schema = $this->getDatabaseSchema();
                $sql = $this->gemini->generateSql($normalizedMessage, $schema);

                // Clean up SQL (remove markdown code blocks if present)
                $sql = preg_replace('/^```sql\s*|```\s*$/', '', trim($sql));

                if (str_starts_with(strtoupper($sql), 'SELECT')) {
                    $results = DB::select($sql);
                    $response = $this->gemini->interpretResults($normalizedMessage, $results);
                } else {
                    $response = "Saya tidak dapat membuat query yang aman untuk permintaan tersebut. (Query: $sql)";
                }
            } catch (\Exception $e) {
                Log::error('Chat DB Error: ' . $e->getMessage());
                $response = 'Terjadi kesalahan ketika mengakses database: ' . $e->getMessage();
            }
        } else {
            $response = $this->gemini->generateContent($normalizedMessage, $history);
        }

        $conversation->messages()->create([
            'role' => 'model',
            'content' => $response,
        ]);
        $conversation->update(['last_message_at' => now()]);

        return response()->json(['response' => $response]);
    }

    private function checkIfNeedsDb(string $message): bool
    {
        $message = mb_strtolower($message);

        $knowledgeKeywords = [
            'cara daftar',
            'bagaimana cara',
            'tatacara',
            'panduan',
            'langkah',
            'prosedur',
            'syarat',
            'cara setor',
            'cara menabung',
            'cara buka',
            'cara registrasi',
            'cara daftar nasabah',
            'cara buka rekening',
            'bagaimana setor',
            'bagaimana daftar',
            'proses pendaftaran',
        ];

        foreach ($knowledgeKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return false;
            }
        }

        $primaryKeywords = [
            'database',
            'data',
            'saldo',
            'setoran',
            'setor',
            'berat',
            'transaksi',
            'rekap',
            'riwayat',
            'tarik saldo',
            'poin',
            'permintaan',
            'nasabah',
            'rekening',
            'users',
            'logs',
            'activity',
            'laporan',
            'statistik',
            'jenis sampah',
            'kategori sampah',
            'penjemputan',
            'permintaan setor',
            'permintaan tarik',
            'mutasi',
            'deposit'
        ];

        $contextualIndicators = [
            'berapa',
            'jumlah',
            'total',
            'list',
            'daftar',
            'show',
            'how many',
            'average',
            'report',
            'history',
            'trend',
            'statistik',
            'rekap',
            'perubahan',
            'grafik',
            'rangking',
            'terbanyak',
            'paling banyak',
            'paling sedikit',
            'detail',
            'jenis',
            'tipe',
            'sebutkan',
            'tampilkan'
        ];

        $questionIndicators = ['siapa', 'apa', 'kapan', 'dimana', 'bagaimana', 'mengapa'];

        $primaryHits = 0;
        foreach ($primaryKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                $primaryHits++;
            }
        }

        $hasIndicator = Str::contains($message, array_merge($contextualIndicators, $questionIndicators)) ||
            preg_match('/\d/', $message) ||
            str_contains($message, '?');

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
            $schema .= "Tabel: {$table} ({$readable})\nDeskripsi: {$description}\nKolom: " . implode(', ', $columns) . "\n\n";
        }

        return $schema;
    }

    private function prependKnowledgeContext(array $history): array
    {
        $context = $this->getKnowledgeContext();
        if ($context) {
            array_unshift($history, [
                'role' => 'system',
                'content' => "Informasi internal yang wajib diikuti:\n" . $context,
            ]);
        }

        return $history;
    }

    private function getKnowledgeContext(): ?string
    {
        if (!file_exists($this->knowledgeFile)) {
            return null;
        }

        $content = trim(file_get_contents($this->knowledgeFile));

        return $content !== '' ? $content : null;
    }

    private function resolveConversation(Request $request): AiConversation
    {
        $sessionId = $request->session()->getId();
        $attributes = [
            'session_id' => $sessionId,
            'user_id' => Auth::id(),
        ];

        return AiConversation::firstOrCreate($attributes, ['last_message_at' => now()]);
    }

    private function buildHistoryFromConversation(AiConversation $conversation, int $limit = 10): array
    {
        return $conversation->messages()
            ->where('is_correction', false)
            ->latest()
            ->take($limit)
            ->get()
            ->sortBy('id')
            ->map(fn(AiMessage $message) => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->values()
            ->all();
    }

    private function injectMemories(array $history, string $message, AiConversation $conversation): array
    {
        $memories = AiMemory::query()
            ->where(function ($query) use ($conversation) {
                $query->whereNull('ai_conversation_id')
                    ->orWhere('ai_conversation_id', $conversation->id);
            })
            ->latest()
            ->take(8)
            ->get()
            ->filter(function (AiMemory $memory) use ($message) {
                if (!$memory->topic) {
                    return true;
                }

                return Str::contains(mb_strtolower($message), mb_strtolower($memory->topic));
            })
            ->reverse(); // so oldest memories are prepended first

        foreach ($memories as $memory) {
            array_unshift($history, [
                'role' => 'system',
                'content' => 'Catatan penting: ' . $memory->content,
            ]);
        }

        return $history;
    }

    private function isCorrectionMessage(string $message): bool
    {
        return (bool) preg_match('/^(koreksi|catatan|note|ingat|lupa|camkan|catat)\s*:/i', $message);
    }

    private function normalizeCorrectionMessage(string $message): string
    {
        return trim(preg_replace('/^(koreksi|catatan|note)\s*:/i', '', $message));
    }

    private function storeMemory(AiConversation $conversation, string $content): AiMemory
    {
        [$topic, $body] = $this->extractMemoryTopicAndBody($content);

        return AiMemory::create([
            'ai_conversation_id' => $conversation->id,
            'user_id' => Auth::id(),
            'topic' => $topic,
            'content' => $body,
        ]);
    }

    private function extractMemoryTopicAndBody(string $content): array
    {
        $delimiters = ['|', '-', '—', ':'];

        foreach ($delimiters as $delimiter) {
            if (str_contains($content, $delimiter)) {
                [$topic, $body] = array_map('trim', explode($delimiter, $content, 2));

                return [$topic ?: null, $body ?: $content];
            }
        }

        return [Str::limit($content, 60), $content];
    }
}
