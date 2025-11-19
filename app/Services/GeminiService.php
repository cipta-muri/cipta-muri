<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected $apiKey;

    protected $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

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

            return 'Sorry, I encountered an error while processing your request.';
        }

        $data = $response->json();

        return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'No response generated.';
    }

    public function generateSql(string $prompt, string $schemaContext)
    {
        $systemPrompt = "You are a SQL expert. Convert the following natural language query into a valid SQL query for a MySQL database. \n\n".
            "Database Schema:\n".$schemaContext."\n\n".
            "Rules:\n".
            "1. Return ONLY the SQL query. No markdown, no explanations.\n".
            "2. Use only SELECT statements. Do not use INSERT, UPDATE, DELETE, DROP, etc.\n".
            "3. If the query cannot be answered with the schema, return 'ERROR: Cannot answer'.\n\n".
            'Query: '.$prompt;

        // For SQL generation, we don't need history, just the direct prompt
        return $this->generateContent($systemPrompt);
    }

    public function interpretResults(string $originalQuery, array $results)
    {
        $resultsJson = json_encode($results);
        $prompt = "The user asked: \"$originalQuery\".\n".
            "The database returned the following JSON data:\n".$resultsJson."\n\n".
            "Please provide a natural language summary of this data to answer the user's question.";

        return $this->generateContent($prompt);
    }
}
