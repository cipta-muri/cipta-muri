<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiMemory;
use App\Models\AiMessage;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    protected $gemini;
    protected string $knowledgeFile;
    protected string $snapshotPath;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
        $this->knowledgeFile = base_path('docs/ai-knowledge.md');
        $this->snapshotPath = storage_path('app/'.(config('ai.data_export.path') ?? 'ai/database.json'));
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
            $snapshot = $this->getSnapshotData();
            if (! $snapshot) {
                $response = 'Maaf, data terbaru belum tersedia. Jalankan perintah `php artisan ai:export-db` untuk memperbarui snapshot.';
            } else {
                $response = $this->gemini->answerFromSnapshot($normalizedMessage, $snapshot);
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

    private function getSnapshotData(): ?array
    {
        if (! file_exists($this->snapshotPath)) {
            return null;
        }

        $content = file_get_contents($this->snapshotPath);
        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
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
