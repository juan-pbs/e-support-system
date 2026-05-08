<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\OrdenServicio;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'puesto',
        'contacto',
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
        ];
    }

    public function normalizedRole(): string
    {
        $role = $this->puesto ?? $this->role ?? $this->rol ?? $this->tipo ?? null;

        return is_string($role) ? mb_strtolower(trim($role)) : '';
    }

    public function hasRole(string $role): bool
    {
        return $this->normalizedRole() === mb_strtolower(trim($role));
    }

    public function hasAnyRole(array $roles): bool
    {
        $normalizedRoles = array_map(
            static fn ($role) => is_string($role) ? mb_strtolower(trim($role)) : '',
            $roles
        );

        return in_array($this->normalizedRole(), $normalizedRoles, true);
    }

    public function isSystem(): bool
    {
        return $this->hasRole('sistema');
    }

    public function ordenesAsignadas()
{
    return $this->belongsToMany(OrdenServicio::class, 'orden_servicio_tecnico', 'user_id', 'id_orden_servicio')
                ->withTimestamps();
}

    public function googleCalendarAccount()
    {
        return $this->hasOne(GoogleCalendarAccount::class);
    }

}
