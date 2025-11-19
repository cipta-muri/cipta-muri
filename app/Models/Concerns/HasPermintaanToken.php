<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\URL;

trait HasPermintaanToken
{
    /**
     * Boot the trait and make sure a fresh token is generated whenever a record is created.
     */
    protected static function bootHasPermintaanToken(): void
    {
        static::creating(function ($model) {
            $model->initializePermintaanToken();
        });
    }

    /**
     * Ensure that the model has a valid token pair.
     */
    public function initializePermintaanToken(bool $force = false, ?int $validDays = 7): void
    {
        if (
            ! $force
            && ! empty($this->qr_token)
            && $this->qr_token_expires_at
            && $this->qr_token_expires_at->isFuture()
        ) {
            return;
        }

        $this->qr_token = $this->generateNewToken();
        $this->qr_token_expires_at = now()->addDays($validDays ?? 7);
    }

    /**
     * Regenerate the QR token and persist the change.
     */
    public function regeneratePermintaanToken(?int $validDays = 7): void
    {
        $this->qr_token = $this->generateNewToken();
        $this->qr_token_expires_at = now()->addDays($validDays ?? 7);
        $this->save();
    }

    /**
     * Verify token from request against the stored token (with expiry guard).
     */
    public function verifyPermintaanToken(?string $token): bool
    {
        if (! is_string($token) || trim($token) === '' || empty($this->qr_token)) {
            return false;
        }

        if ($this->qr_token_expires_at && $this->qr_token_expires_at->isPast()) {
            return false;
        }

        return hash_equals($this->qr_token, $token);
    }

    protected function generateNewToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function qrSignedUrl(string $type, ?int $minutes = 10): string
    {
        return URL::temporarySignedRoute(
            'permintaan.qr.redirect',
            now()->addMinutes($minutes ?? 10),
            [
                'type' => $type,
                'record' => $this->id,
                'token' => $this->qr_token,
            ]
        );
    }
}
