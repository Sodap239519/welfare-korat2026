<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TargetStatusLog extends Model
{
    protected $fillable = ['target_id', 'status_code', 'channel_id', 'sub_channel', 'note', 'user_id', 'user_name_snapshot', 'changed_at'];

    protected $casts = ['changed_at' => 'datetime'];

    public function target(): BelongsTo  { return $this->belongsTo(Target::class); }
    public function channel(): BelongsTo { return $this->belongsTo(Channel::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
}
