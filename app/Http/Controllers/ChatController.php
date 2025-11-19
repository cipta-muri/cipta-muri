<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ChatController extends Controller
{
    protected $gemini;

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
                    $response = "I couldn't generate a safe query for that request. (Generated: $sql)";
                }
            } catch (\Exception $e) {
                Log::error('Chat DB Error: '.$e->getMessage());
                $response = 'I encountered an error trying to access the database: '.$e->getMessage();
            }
        } else {
            $response = $this->gemini->generateContent($message, $history);
        }

        return response()->json(['response' => $response]);
    }

    private function checkIfNeedsDb(string $message): bool
    {
        $keywords = ['count', 'list', 'show', 'how many', 'who', 'what', 'where', 'find', 'search', 'data', 'database', 'users', 'logs', 'activity'];
        foreach ($keywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    private function getDatabaseSchema(): string
    {
        $tables = Schema::getTableListing();
        $schema = '';

        foreach ($tables as $table) {
            // Skip internal tables or sensitive ones if needed
            if (in_array($table, ['migrations', 'password_reset_tokens', 'sessions', 'jobs', 'failed_jobs'])) {
                continue;
            }

            $columns = Schema::getColumnListing($table);
            $schema .= "Table: $table\nColumns: ".implode(', ', $columns)."\n\n";
        }

        return $schema;
    }
}
