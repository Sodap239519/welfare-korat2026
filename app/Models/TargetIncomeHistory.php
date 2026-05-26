<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TargetIncomeHistory extends Model
{
    protected $table = 'target_income_history';

    protected $fillable = [
        'target_id', 'old_income', 'new_income', 'is_baseline',
        'note', 'changed_by', 'changed_by_name', 'changed_at',
    ];

    protected $casts = [
        'old_income'  => 'integer',
        'new_income'  => 'integer',
        'is_baseline' => 'boolean',
        'changed_at'  => 'datetime',
    ];

    public function target(): BelongsTo  { return $this->belongsTo(Target::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class, 'changed_by'); }
}
