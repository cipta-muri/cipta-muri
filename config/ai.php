<?php

return [
    'data_export' => [
        'path' => 'ai/database.json',
        'cleanup' => true,
        'cleanup_extensions' => ['.json'],
        'max_rows' => env('AI_DATA_EXPORT_MAX_ROWS', 1000),
        'tables' => [
            'rekening' => [],
            'setor_sampah' => [],
            'sampah_transactions' => [],
            'saldo_transactions' => [],
            'poin_transactions' => [],
            'permintaan_tarik_saldo' => [],
            'permintaan_setor_sampah' => [],
            'users' => ['columns' => ['id', 'name', 'email', 'created_at']],
            'news' => [],
        ],
    ],
];
