<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportAiDatabase extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ai:export-db {--force : Write the snapshot even if it already exists}';

    /**
     * The console command description.
     */
    protected $description = 'Export selected database tables to a JSON snapshot for the AI assistant.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $config = config('ai.data_export', []);
        $path = $config['path'] ?? 'ai/database.json';
        $cleanup = $config['cleanup'] ?? true;
        $cleanupExt = $config['cleanup_extensions'] ?? ['.json'];
        $tables = $config['tables'] ?? [];
        $maxRows = (int) ($config['max_rows'] ?? 1000);

        if (empty($tables)) {
            $this->error('No tables configured for export (config/ai.php -> data_export.tables).');

            return static::FAILURE;
        }

        $disk = Storage::disk('local');
        $directory = trim(dirname($path), '/');
        if ($directory === '' || $directory === '.') {
            $directory = '';
        }

        if (! $this->option('force') && $disk->exists($path)) {
            $this->warn("Snapshot already exists at storage/app/{$path}. Use --force to overwrite.");

            return static::SUCCESS;
        }

        if ($directory !== '' && ! $disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        $export = [
            'generated_at' => now()->toIso8601String(),
            'max_rows_per_table' => $maxRows,
            'tables' => [],
        ];

        foreach ($tables as $table => $options) {
            $columns = $options['columns'] ?? ['*'];
            $limit = $options['max_rows'] ?? $maxRows;

            $this->info("Exporting {$table} (limit {$limit})...");

            $query = DB::table($table)->limit($limit);
            if ($columns !== ['*']) {
                $query->select($columns);
            }

            $export['tables'][$table] = $query->get()->map(function ($row) {
                return (array) $row;
            })->all();
        }

        if ($cleanup) {
            $this->cleanupSnapshots($disk, $directory, $path, (array) $cleanupExt);
        }

        $disk->put($path, json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Database snapshot saved to storage/app/{$path}.");

        return static::SUCCESS;
    }

    private function cleanupSnapshots($disk, string $directory, string $currentPath, array $extensions): void
    {
        $targetDir = $directory === '' ? '.' : $directory;
        $files = $disk->files($targetDir);

        foreach ($files as $file) {
            if ($file === $currentPath) {
                continue;
            }

            if (! empty($extensions) && ! Str::endsWith($file, $extensions)) {
                continue;
            }

            $disk->delete($file);
            $this->line(" - Removed old snapshot {$file}");
        }
    }
}
