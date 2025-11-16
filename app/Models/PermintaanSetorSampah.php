<?php

namespace App\Models;

use App\Enums\PermintaanStatus;
use App\Models\Concerns\HasPermintaanToken;
use App\Models\Concerns\NotifiesPermintaanStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PermintaanSetorSampah extends Model
{
    /** @use HasFactory<\Database\Factories\PermintaanSetorSampahFactory> */
    use HasFactory;
    use HasUlids;
    use SoftDeletes;
    use LogsActivity;
    use HasPermintaanToken;
    use NotifiesPermintaanStatus;

    protected $table = 'permintaan_setor_sampah';

    protected $guarded = ['id'];

    protected $casts = [
        'status' => PermintaanStatus::class,
        'requested_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'qr_token_expires_at' => 'datetime',
        'total_berat' => 'decimal:4',
        'total_saldo_dihasilkan' => 'decimal:2',
        'total_poin_dihasilkan' => 'integer',
        'detail_items' => 'array',
        'meta' => 'array',
        'tanggal_setor' => 'date',
        'calculation_performed' => 'boolean',
    ];

    protected $attributes = [
        'status' => 'menunggu_konfirmasi',
        'detail_items' => '[]',
        'meta' => '[]',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('permintaan_setor_sampah')
            ->logAll()
            ->setDescriptionForEvent(fn (string $event) => "Permintaan Setor Sampah {$this->id} {$event}");
    }

    protected static function booted(): void
    {
        static::creating(function (self $record) {
            if (!$record->requested_at) {
                $record->requested_at = now();
            }

            if (!$record->requested_by_rekening_id) {
                $record->requested_by_rekening_id = $record->rekening_id;
            }
        });
    }

    public function rekening(): BelongsTo
    {
        return $this->belongsTo(Rekening::class);
    }

    public function requestedByRekening(): BelongsTo
    {
        return $this->belongsTo(Rekening::class, 'requested_by_rekening_id');
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function finalSetorSampah(): BelongsTo
    {
        return $this->belongsTo(SetorSampah::class, 'final_setor_sampah_id');
    }

    public function scopeStatus($query, PermintaanStatus|string $status)
    {
        $status = $status instanceof PermintaanStatus ? $status : PermintaanStatus::from($status);
        return $query->where('status', $status->value);
    }

    public function isWaitingConfirmation(): bool
    {
        return $this->status === PermintaanStatus::MenungguKonfirmasi;
    }

    public function markAsApproved(User $admin, ?string $note = null, string $via = 'table'): void
    {
        $this->status = PermintaanStatus::Disetujui;
        $this->confirmed_by = $admin->id;
        $this->confirmed_at = now();
        $this->processed_via = $via;
        $this->keterangan_admin = $note;
        $this->save();
    }

    public function markAsRejected(?User $admin, string $reason, string $via = 'table'): void
    {
        $this->status = PermintaanStatus::Ditolak;
        $this->confirmed_by = $admin?->id;
        $this->confirmed_at = now();
        $this->processed_via = $via;
        $this->rejection_reason = $reason;
        $this->keterangan_admin = $reason;
        $this->save();
    }

    public function confirm(User $admin, string $via = 'table', ?string $note = null, ?string $notificationUrl = null): SetorSampah
    {
        if (!$this->isWaitingConfirmation()) {
            throw ValidationException::withMessages([
                'status' => 'Permintaan sudah diproses.',
            ]);
        }

        if (!$this->calculation_performed) {
            throw ValidationException::withMessages([
                'calculation_performed' => 'Perhitungan belum dilakukan pada permintaan ini.',
            ]);
        }

        if ($this->detailItems()->isEmpty()) {
            throw ValidationException::withMessages([
                'detail_items' => 'Detail setoran tidak ditemukan.',
            ]);
        }

        return DB::transaction(function () use ($admin, $via, $note, $notificationUrl) {
            $setor = SetorSampah::create([
                'rekening_id' => $this->rekening_id,
                'user_id' => $admin->id,
                'tanggal' => $this->tanggal_setor ?? now()->toDateString(),
                'jenis_setoran' => $this->jenis_setoran,
                'berat' => $this->total_berat,
                'total_saldo_dihasilkan' => $this->total_saldo_dihasilkan,
                'total_poin_dihasilkan' => $this->total_poin_dihasilkan,
                'calculation_performed' => true,
            ]);

            $setor->details()->createMany(
                $this->detailItems()
                    ->map(function (array $detail) use ($admin) {
                        if (empty($detail['sampah_id'])) {
                            throw ValidationException::withMessages([
                                'detail_items' => 'Detail setoran tidak memiliki sampah_id yang valid.',
                            ]);
                        }

                        return [
                            'sampah_id' => $detail['sampah_id'],
                            'rekening_id' => $this->rekening_id,
                            'berat' => $detail['berat'] ?? 0,
                            'description' => $detail['description'] ?? 'Setoran Sampah',
                            'type' => $detail['type'] ?? 'masuk',
                            'user_id' => $admin->id,
                        ];
                    })
                    ->toArray()
            );

            $this->final_setor_sampah_id = $setor->id;
            $this->markAsApproved($admin, $note, $via);

            $this->notifyNasabah(
                PermintaanStatus::Disetujui,
                'Permintaan setor sampah disetujui',
                sprintf('Setoran dengan total %s Kg telah disetujui.', number_format($this->total_berat, 2)),
                $notificationUrl
            );

            return $setor;
        });
    }

    public function reject(?User $admin, string $reason, string $via = 'table', ?string $notificationUrl = null): void
    {
        if (!$this->isWaitingConfirmation()) {
            throw ValidationException::withMessages([
                'status' => 'Permintaan sudah diproses.',
            ]);
        }

        DB::transaction(function () use ($admin, $reason, $via, $notificationUrl) {
            $this->markAsRejected($admin, $reason, $via);

            $this->notifyNasabah(
                PermintaanStatus::Ditolak,
                'Permintaan setor sampah ditolak',
                $reason,
                $notificationUrl
            );
        });
    }

    protected function detailItems(): Collection
    {
        return collect($this->detail_items ?? []);
    }

    public function detailItemsResolved(): Collection
    {
        $items = $this->detailItems();
        $names = Sampah::query()
            ->whereIn('id', $items->pluck('sampah_id')->filter()->all())
            ->pluck('jenis_sampah', 'id');

        return $items->map(function (array $item) use ($names) {
            $item['sampah_name'] = $names[$item['sampah_id']] ?? $item['sampah_id'];
            return $item;
        });
    }

    protected function permintaanNotificationType(): string
    {
        return 'setor_sampah';
    }

    public function qrRouteType(): string
    {
        return 'setor-sampah';
    }
}
