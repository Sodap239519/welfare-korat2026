<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationSubStatus extends Model
{
    protected $fillable = [
        'code', 'parent_code', 'label', 'icon', 'color',
        'sort_order', 'is_active', 'actor_role',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(RegistrationStatus::class, 'parent_code', 'code');
    }

    /** Scope: เฉพาะ active */
    public function scopeActive($q) { return $q->where('is_active', true); }
    /** Scope: ลูกของ parent_code */
    public function scopeChildrenOf($q, string $parentCode) { return $q->where('parent_code', $parentCode); }
}
