<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SumberPemasukan extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('sumber_pemasukan')
            ->logAll()
            ->setDescriptionForEvent(fn (string $eventName) => "Sumber Pemasukan has been {$eventName}");
    }

    protected $table = 'sumber_pemasukan';

    protected $guarded = ['id'];

    public function pemasukan()
    {
        return $this->belongsTo(Pemasukan::class, 'nama_pemasukan');
    }
}
