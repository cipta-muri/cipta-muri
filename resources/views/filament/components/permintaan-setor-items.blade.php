@php
    $items = collect($items ?? []);
@endphp

@if ($items->isEmpty())
    <div class="text-gray-500 dark:text-gray-400 text-sm">
        Tidak ada detail setoran.
    </div>
@else
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Jenis Sampah</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Berat (Kg)</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Saldo/ Kg</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Subtotal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($items as $item)
                    <tr>
                        <td class="px-3 py-2 text-gray-800 dark:text-gray-100">
                            {{ $item['sampah_name'] ?? $item['sampah_id'] }}
                        </td>
                        <td class="px-3 py-2 text-gray-800 dark:text-gray-100">
                            {{ number_format($item['berat'] ?? 0, 2) }}
                        </td>
                        <td class="px-3 py-2 text-gray-800 dark:text-gray-100">
                            Rp {{ number_format($item['harga_saldo'] ?? 0, 0, ',', '.') }}
                        </td>
                        <td class="px-3 py-2 text-gray-800 dark:text-gray-100">
                            Rp {{ number_format(($item['berat'] ?? 0) * ($item['harga_saldo'] ?? 0), 0, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
