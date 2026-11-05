<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Accountant extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'office_id',
        'name',
        'phone',
        'password',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function manifests(): HasMany
    {
        return $this->hasMany(Manifest::class, 'accountant_id');
    }

    public function parcelEditLogs(): HasMany
    {
        return $this->hasMany(ParcelEditLog::class, 'accountant_id');
    }

    /**
     * التحقق من صلاحية وصول المحاسب للتطبيق:
     * يشترط أن يكون حساب المحاسب نشطاً، والمكتب التابع له نشطاً، ومفعلاً لديه ميزة المحاسبين.
     */
    public function canAccessApp(): bool
    {
        return $this->is_active === true
            && $this->office
            && $this->office->is_active === true
            && $this->office->hasFeature('office_accountants');
    }
}

