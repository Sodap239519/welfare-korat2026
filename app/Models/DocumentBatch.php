<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DocumentBatch extends Model
{
    protected $fillable = [
        'batch_no', 'tracker_user_id', 'batch_date',
        'channel_id', 'sub_channel', 'target_amphur_id', 'status',
        'submitter_role', 'submitter_name',
        'submitted_at',
        // ขั้นอำเภอ (Path A)
        'district_received_at', 'district_received_by_user_id',
        'forwarded_at', 'forwarded_by_user_id',
        'forwarded_to_channel_id', 'forwarded_to_sub_channel',
        // ขั้นธนาคาร
        'received_at', 'received_by_user_id',
        'recorded_at', 'recorded_by_user_id',
        'notes', 'reject_reason', 'photo_paths',
    ];

    protected $casts = [
        'batch_date'           => 'date',
        'submitted_at'         => 'datetime',
        'district_received_at' => 'datetime',
        'forwarded_at'         => 'datetime',
        'received_at'          => 'datetime',
        'recorded_at'          => 'datetime',
        'photo_paths'          => 'array',
    ];

    // ─── Status constants — 2-path lifecycle ───
    public const ST_DRAFT                 = 'draft';
    public const ST_SUBMITTED_TO_DISTRICT = 'submitted_to_district';   // tracker → อำเภอ
    public const ST_DISTRICT_RECEIVED     = 'district_received';        // อำเภอรับ
    public const ST_FORWARDED_TO_BANK     = 'forwarded_to_bank';        // อำเภอส่งต่อ
    public const ST_BANK_RECEIVED         = 'bank_received';            // ธนาคารรับ
    public const ST_BANK_RECORDED         = 'bank_recorded';            // ธนาคารบันทึกครบ (จบ)
    public const ST_REJECTED              = 'rejected';

    // ─── Backward-compat (Phase A-F naming) ───
    public const ST_SUBMITTED = self::ST_SUBMITTED_TO_DISTRICT;
    public const ST_RECEIVED  = self::ST_BANK_RECEIVED;
    public const ST_RECORDED  = self::ST_BANK_RECORDED;

    // ─── Submitter role constants ───
    public const ROLE_SELF       = 'self';
    public const ROLE_KAMNAN     = 'kamnan';
    public const ROLE_PHUYAIBAN  = 'phuyaiban';
    public const ROLE_OSM        = 'osm';
    public const ROLE_OPT        = 'opt';
    public const ROLE_OTHER      = 'other';

    public static function submitterRoleLabels(): array
    {
        return [
            self::ROLE_SELF      => 'ลงทะเบียนด้วยตนเอง',
            self::ROLE_KAMNAN    => 'กำนัน',
            self::ROLE_PHUYAIBAN => 'ผู้ใหญ่บ้าน',
            self::ROLE_OSM       => 'อสม.',
            self::ROLE_OPT       => 'อปท.',
            self::ROLE_OTHER     => 'อื่นๆ (ระบุ)',
        ];
    }

    // ─── Relations ───
    public function tracker(): BelongsTo      { return $this->belongsTo(User::class, 'tracker_user_id'); }
    public function channel(): BelongsTo      { return $this->belongsTo(Channel::class); }
    public function receivedBy(): BelongsTo   { return $this->belongsTo(User::class, 'received_by_user_id'); }
    public function recordedBy(): BelongsTo   { return $this->belongsTo(User::class, 'recorded_by_user_id'); }
    public function districtReceivedBy(): BelongsTo { return $this->belongsTo(User::class, 'district_received_by_user_id'); }
    public function forwardedBy(): BelongsTo  { return $this->belongsTo(User::class, 'forwarded_by_user_id'); }
    public function forwardedToChannel(): BelongsTo { return $this->belongsTo(Channel::class, 'forwarded_to_channel_id'); }
    public function targetAmphur(): BelongsTo { return $this->belongsTo(\App\Models\Amphur::class, 'target_amphur_id'); }

    public function targets(): BelongsToMany
    {
        // ระบุ FK ชัด — pivot column ของเราชื่อ batch_id (ไม่ใช่ document_batch_id ที่ Laravel เดา)
        return $this->belongsToMany(Target::class, 'document_batch_targets', 'batch_id', 'target_id')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    // ─── Helpers ───
    public function getTargetCountAttribute(): int
    {
        return $this->targets()->count();
    }

    /** Generate batch_no อัตโนมัติแบบ YYYY-MM-DD-NNN (NNN = running ในวันนั้น) */
    public static function generateBatchNo(\Carbon\Carbon|string|null $date = null): string
    {
        $d = $date ? \Carbon\Carbon::parse($date) : now();
        $prefix = $d->format('Y-m-d');
        $count = static::where('batch_no', 'like', $prefix . '%')->count() + 1;
        return $prefix . '-' . str_pad((string) $count, 3, '0', STR_PAD_LEFT);
    }

    // ─── Scopes ───
    public function scopePending($q) { return $q->whereIn('status', [self::ST_SUBMITTED, self::ST_RECEIVED]); }
    public function scopeForChannel($q, int $channelId, ?string $subChannel = null) {
        $q->where('channel_id', $channelId);
        if ($subChannel) $q->where('sub_channel', $subChannel);
        return $q;
    }
}
