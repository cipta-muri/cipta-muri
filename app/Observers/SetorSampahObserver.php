<?php

namespace App\Observers;

use App\Models\SetorSampah;
use App\Models\Rekening;
use App\Models\SaldoTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class SetorSampahObserver
{
    /**
     * Handle the SetorSampah "creating" event.
     */
    public function creating(SetorSampah $setorSampah): void
    {
        if (!$setorSampah->user_id && Auth::check()) {
            $setorSampah->user_id = Auth::id();
        }

        if (($setorSampah->jenis_setoran ?? null) === 'donasi') {
            $rekening = Rekening::firstOrCreate(
                ['no_rekening' => '00000000'],
                [
                    'nama' => 'Kas Bank Sampah (Donasi)',
                    'user_id' => User::first()->id ?? throw new \Exception('Sistem membutuhkan minimal satu user.'),
                    'nik' => '0000000000000000',
                    'no_kk' => '0000000000000000',
                    'gender' => 'Laki-laki',
                    'tanggal_lahir' => now()->subYears(20)->toDateString(),
                    'pendidikan' => 'TIDAK/BELUM SEKOLAH',
                    'dusun' => '0',
                    'rw' => '0',
                    'rt' => '0',
                ]
            );
            $setorSampah->rekening_id = $rekening->id;
            $setorSampah->total_saldo_dihasilkan = 0;
            $setorSampah->total_poin_dihasilkan = 0;
        }
    }

    /**
     * Handle the SetorSampah "created" event.
     */
    public function created(SetorSampah $setorSampah): void
    {
        // Buat transaksi saldo hanya jika bukan donasi dan ada saldo
        if (!$setorSampah->isDonation() && $setorSampah->total_saldo_dihasilkan > 0) {
            $setorSampah->rekening->saldoTransactions()->create([
                'amount' => $setorSampah->total_saldo_dihasilkan,
                'type' => 'credit',
                'description' => 'Setoran Sampah',
                'transactable_id' => $setorSampah->id,
                'transactable_type' => SetorSampah::class,
                'user_id' => $setorSampah->user_id,
            ]);
        }
    }

    /**
     * Sinkronkan SaldoTransaction & detail setelah create/update.
     */
    public function saved(SetorSampah $setorSampah): void
    {
        // Pastikan semua detail punya rekening_id terbaru
        $setorSampah->details()
            ->where('rekening_id', '!=', $setorSampah->rekening_id)
            ->update(['rekening_id' => $setorSampah->rekening_id]);

        // Ambil transaksi saldo terkait (termasuk yang terhapus)
        $existing = SaldoTransaction::withTrashed()
            ->where('transactable_type', SetorSampah::class)
            ->where('transactable_id', $setorSampah->id)
            ->first();

        // Jika donasi atau total saldo 0, hapus transaksi saldo bila ada
        if ($setorSampah->isDonation() || $setorSampah->total_saldo_dihasilkan <= 0) {
            if ($existing && !$existing->trashed()) {
                $existing->delete();
            }
            return;
        }

        // Non-donasi dan ada saldo: pastikan transaksi saldo sesuai total & rekening
        if ($existing) {
            // Jika rekening berubah, hapus transaksi lama dan buat baru agar kedua saldo terbarui
            if ($existing->rekening_id !== $setorSampah->rekening_id) {
                if (!$existing->trashed()) {
                    $existing->delete();
                }
                $setorSampah->rekening->saldoTransactions()->create([
                    'amount' => $setorSampah->total_saldo_dihasilkan,
                    'type' => 'credit',
                    'description' => 'Setoran Sampah',
                    'transactable_id' => $setorSampah->id,
                    'transactable_type' => SetorSampah::class,
                    'user_id' => $setorSampah->user_id,
                ]);
                return;
            }

            // Rekening sama: update amount, type, description, pulihkan jika terhapus
            $existing->amount = $setorSampah->total_saldo_dihasilkan;
            $existing->type = 'credit';
            $existing->description = 'Setoran Sampah';
            $existing->user_id = $setorSampah->user_id;
            if ($existing->trashed()) {
                $existing->restore();
            } else {
                $existing->save();
            }
        } else {
            // Tidak ada transaksi: buat baru
            $setorSampah->rekening->saldoTransactions()->create([
                'amount' => $setorSampah->total_saldo_dihasilkan,
                'type' => 'credit',
                'description' => 'Setoran Sampah',
                'transactable_id' => $setorSampah->id,
                'transactable_type' => SetorSampah::class,
                'user_id' => $setorSampah->user_id,
            ]);
        }
    }

    /**
     * Handle the SetorSampah "deleted" event.
     */
    public function deleted(SetorSampah $setorSampah): void
    {
        // Hapus (soft delete) semua transaksi terkait satu per satu untuk memicu observer mereka.
        $setorSampah->details()->get()->each->delete();
        if (!$setorSampah->isDonation()) {
            $setorSampah->rekening->saldoTransactions()->where('transactable_id', $setorSampah->id)->get()->each->delete();
        }
    }

    /**
     * Handle the SetorSampah "restored" event.
     */
    public function restored(SetorSampah $setorSampah): void
    {
        // Pulihkan (restore) semua transaksi terkait satu per satu untuk memicu observer mereka.
        $setorSampah->details()->onlyTrashed()->where('transactable_id', $setorSampah->id)->get()->each->restore();
        if (!$setorSampah->isDonation()) {
            $setorSampah->rekening->saldoTransactions()->onlyTrashed()->where('transactable_id', $setorSampah->id)->get()->each->restore();
        }
    }

    public function forceDeleted(SetorSampah $setorSampah): void
    {
        // Hapus permanen semua transaksi terkait
        $setorSampah->details()->withTrashed()->where('transactable_id', $setorSampah->id)->get()->each->forceDelete();
        $setorSampah->rekening->saldoTransactions()->withTrashed()->where('transactable_id', $setorSampah->id)->get()->each->forceDelete();
    }
}
