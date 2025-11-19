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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PermintaanTarikSaldo extends Model
{
    /** @use HasFactory<\Database\Factories\PermintaanTarikSaldoFactory> */
    use HasFactory;

    use HasPermintaanToken;
    use HasUlids;
    use LogsActivity;
    use NotifiesPermintaanStatus;
    use SoftDeletes;

    protected $table = 'permintaan_tarik_saldo';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => PermintaanStatus::class,
        'requested_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'qr_token_expires_at' => 'datetime',
        'meta' => 'array',
    ];

    protected $attributes = [
        'status' => 'menunggu_konfirmasi',
        'meta' => '[]',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('permintaan_tarik_saldo')
            ->logAll()
            ->setDescriptionForEvent(fn (string $event) => "Permintaan Tarik Saldo {$this->id} {$event}");
    }

    protected static function booted(): void
    {
        static::creating(function (self $record) {
            if (! $record->requested_at) {
                $record->requested_at = now();
            }

            if (! $record->requested_by_rekening_id) {
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

    public function finalWithdrawRequest(): BelongsTo
    {
        return $this->belongsTo(WithdrawRequest::class, 'final_withdraw_request_id');
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

    public function confirm(User $admin, string $via = 'table', ?string $note = null, ?string $notificationUrl = null): WithdrawRequest
    {
        if (! $this->isWaitingConfirmation()) {
            throw ValidationException::withMessages([
                'status' => 'Permintaan sudah diproses.',
            ]);
        }

        return DB::transaction(function () use ($admin, $via, $note, $notificationUrl) {
            $rekening = $this->rekening()->lockForUpdate()->first();

            if (! $rekening?->hasSufficientBalance($this->amount)) {
                throw ValidationException::withMessages([
                    'amount' => 'Saldo nasabah tidak mencukupi untuk penarikan ini.',
                ]);
            }

            $withdraw = WithdrawRequest::create([
                'rekening_id' => $this->rekening_id,
                'user_id' => $this->requested_by_user_id ?? optional($this->requestedByRekening)->user_id,
                'amount' => $this->amount,
                'jenis' => $this->jenis,
                'catatan' => $this->catatan,
                'is_new_pegadaian_registration' => (bool) data_get($this->meta, 'is_new_pegadaian_registration', false),
                'no_rek_pegadaian' => data_get($this->meta, 'no_rek_pegadaian'),
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ]);

            $this->final_withdraw_request_id = $withdraw->id;
            $this->markAsApproved($admin, $note, $via);

            $this->notifyNasabah(
                PermintaanStatus::Disetujui,
                'Permintaan tarik saldo disetujui',
                sprintf(
                    'Permintaan penarikan sebesar %s telah diproses.',
                    number_format($this->amount, 0, ',', '.')
                ),
                $notificationUrl
            );

            return $withdraw;
        });
    }

    public function reject(?User $admin, string $reason, string $via = 'table', ?string $notificationUrl = null): void
    {
        if (! $this->isWaitingConfirmation()) {
            throw ValidationException::withMessages([
                'status' => 'Permintaan sudah diproses.',
            ]);
        }

        DB::transaction(function () use ($admin, $reason, $via, $notificationUrl) {
            $this->markAsRejected($admin, $reason, $via);
            $this->notifyNasabah(
                PermintaanStatus::Ditolak,
                'Permintaan tarik saldo ditolak',
                $reason,
                $notificationUrl
            );
        });
    }

    protected function permintaanNotificationType(): string
    {
        return 'tarik_saldo';
    }

    public function qrRouteType(): string
    {
        return 'tarik-saldo';
    }
}
