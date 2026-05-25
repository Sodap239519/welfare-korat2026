<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TargetCurrentStatus extends Model
{
    protected $table = 'target_current_status';
    protected $primaryKey = 'target_id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['target_id', 'status_code', 'channel_id', 'note', 'updated_by', 'updated_at'];
    protected $casts = ['updated_at' => 'datetime'];

    public function target(): BelongsTo  { return $this->belongsTo(Target::class); }
    public function channel(): BelongsTo { return $this->belongsTo(Channel::class); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
