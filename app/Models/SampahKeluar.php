<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SampahKeluar extends Model
{
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('sampah_keluar')
            ->logAll()
            ->setDescriptionForEvent(fn (string $eventName) => "Sampah Keluar has been {$eventName}");
    }

    protected $table = 'sampah_keluar';

    protected $guarded = ['id'];

    public function rekening()
    {
        return $this->belongsTo(Rekening::class);
    }

    public function details()
    {
        return $this->morphMany(SampahTransactions::class, 'transactable');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        // All logic is moved to SampahKeluarObserver
    }
}
