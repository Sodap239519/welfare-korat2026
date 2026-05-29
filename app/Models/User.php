<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'phone', 'email', 'position_type', 'position_other', 'active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn ($e) => match ($e) {
                'created' => 'สร้างผู้ใช้',
                'updated' => 'แก้ไขข้อมูลผู้ใช้',
                'deleted' => 'ลบผู้ใช้',
                default   => $e,
            });
    }

    protected $fillable = [
        'name', 'email', 'password',
        'phone', 'position_type', 'position_other',
        'bank_channel_id', 'bank_sub_channel',
        'active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'active'            => 'boolean',
            'last_login_at'     => 'datetime',
        ];
    }

    public function scopes(): HasMany   { return $this->hasMany(UserScope::class); }
    public function tracker()           { return $this->hasOne(Tracker::class); }

    public function bankChannel()
    {
        return $this->belongsTo(\App\Models\Channel::class, 'bank_channel_id');
    }
}
