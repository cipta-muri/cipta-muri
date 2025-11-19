<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Sampah extends Model
{
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('sampah')
            ->logAll()
            ->setDescriptionForEvent(fn (string $eventName) => "Sampah has been {$eventName}");
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'sampah';

    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function details()
    {
        return $this->hasMany(SampahTransactions::class);
    }

    /**
     * Menghitung ulang total berat terkumpul dari transaksi dan menyimpannya.
     * Ini akan dipanggil oleh SampahTransactionsObserver.
     */
    public function updateBerat(): void
    {
        // Jika jenis sampah tidak menyimpan berat, stok selalu 0
        if (! ($this->simpan_berat ?? true)) {
            $this->total_berat_terkumpul = 0;
            $this->saveQuietly();

            return;
        }

        $masuk = $this->details()
            ->whereNull('deleted_at')
            ->where('type', 'masuk')
            ->sum('berat');

        $keluar = $this->details()
            ->whereNull('deleted_at')
            ->where('type', 'keluar')
            ->sum('berat');

        $this->total_berat_terkumpul = $masuk - $keluar;
        $this->saveQuietly();
    }

    public function recalculateTotalBerat(): void
    {
        // Jika jenis sampah tidak menyimpan berat, stok selalu 0
        if (! ($this->simpan_berat ?? true)) {
            $this->total_berat_terkumpul = 0;
            $this->saveQuietly();

            return;
        }

        $masuk = $this->details()
            ->whereNull('deleted_at') // hanya yang aktif
            ->where('type', 'masuk')
            ->sum('berat');

        $keluar = $this->details()
            ->whereNull('deleted_at')
            ->where('type', 'keluar')
            ->sum('berat');

        $this->total_berat_terkumpul = $masuk - $keluar;
        $this->saveQuietly();
    }

    protected static function booted(): void
    {
        static::creating(function ($Sampah) {
            if (! $Sampah->user_id && Auth::check()) {
                $Sampah->user_id = Auth::id();
            }
        });
    }
}
