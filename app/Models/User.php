<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Hexters\HexaLite\HexaLiteRolePermission;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;


class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasUlids, HexaLiteRolePermission, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('user')
            ->logAll()
            ->setDescriptionForEvent(fn(string $eventName) => "Daftar Admin has been {$eventName}");
    }


    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];
    protected $fillable = [
        'name',
        'nik',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'custom_fields' => 'array',
        ];
    }

    /**
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public function getAuthIdentifierName()
    {
        return 'nik';
    }

    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getAuthIdentifier()
    {
        return $this->getAttribute('nik');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->roles()->where('name', 'Super Admin')->exists();
    }
}
