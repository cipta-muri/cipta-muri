<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Scan Permintaan</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Arahkan kamera ke QR code permintaan untuk membuka halaman detail di tab ini.
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <button id="start-scan"
                    class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500">
                    Mulai Scanner
                </button>
                <button id="stop-scan"
                    class="inline-flex items-center rounded-lg bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-800 shadow hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    disabled>
                    Hentikan
                </button>
                <button id="open-last"
                    class="inline-flex items-center rounded-lg bg-emerald-100 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    disabled>
                    Buka Tautan Terakhir
                </button>
            </div>
            <div id="qr-reader" class="mt-6 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 text-center text-gray-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                Tekan tombol “Mulai Scanner” untuk mengaktifkan kamera.
            </div>
            <div id="qr-result" class="mt-4 text-sm text-gray-700 dark:text-gray-200"></div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Tips Keamanan</h3>
            <ul class="mt-3 list-disc space-y-2 pl-6 text-sm text-gray-700 dark:text-gray-300">
                <li>Pastikan URL yang muncul berasal dari domain panel resmi sebelum membuka tautan.</li>
                <li>Tautan QR hanya berlaku beberapa menit. Scan ulang jika sudah tidak berlaku.</li>
                <li>Gunakan perangkat yang sudah login agar halaman detail dapat terbuka tanpa hambatan.</li>
            </ul>
        </div>
    </div>

    @push('scripts')
        <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const startBtn = document.getElementById('start-scan');
                const stopBtn = document.getElementById('stop-scan');
                const openBtn = document.getElementById('open-last');
                const resultEl = document.getElementById('qr-result');
                let lastUrl = null;
                let scanner = null;

                const startScanner = async () => {
                    if (scanner) {
                        return;
                    }

                    scanner = new Html5Qrcode("qr-reader");
                    try {
                        await scanner.start(
                            { facingMode: 'environment' },
                            { fps: 10, qrbox: 250 },
                            decodedText => {
                                lastUrl = decodedText;
                                resultEl.innerHTML = `<span class="text-emerald-600 dark:text-emerald-400 font-semibold">QR terbaca:</span> <a href="${decodedText}" class="underline" target="_blank" rel="noopener">${decodedText}</a>`;
                                openBtn.disabled = false;
                                window.location.assign(decodedText);
                            },
                            errorMessage => {
                                resultEl.textContent = errorMessage;
                            }
                        );
                        startBtn.disabled = true;
                        stopBtn.disabled = false;
                    } catch (error) {
                        console.error(error);
                        resultEl.textContent = 'Tidak dapat mengakses kamera. Pastikan izin kamera diberikan.';
                        scanner = null;
                    }
                };

                const stopScanner = async () => {
                    if (!scanner) {
                        return;
                    }

                    await scanner.stop();
                    scanner.clear();
                    scanner = null;
                    startBtn.disabled = false;
                    stopBtn.disabled = true;
                    document.getElementById('qr-reader').innerHTML = 'Scanner dihentikan.';
                };

                startBtn.addEventListener('click', startScanner);
                stopBtn.addEventListener('click', stopScanner);
                openBtn.addEventListener('click', () => {
                    if (lastUrl) {
                        window.location.assign(lastUrl);
                    }
                });
            });
        </script>
    @endpush
</x-filament-panels::page>
