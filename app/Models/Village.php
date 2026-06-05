<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Village extends Model
{
    protected $fillable = ['tambon_id', 'moo', 'name', 'lat', 'lng'];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];

    public function tambon(): BelongsTo { return $this->belongsTo(Tambon::class); }
    public function households(): HasMany { return $this->hasMany(Household::class); }
    public function trackers(): HasMany { return $this->hasMany(Tracker::class); }

    /**
     * คืน [lat, lng, source] โดยลำดับ fallback:
     *   1) tambon.lat/lng จริง (จากข้อมูลพิกัด ปกครอง)         · source = 'tambon'
     *   2) centroid ของหมู่บ้านอื่นใน tambon เดียวกัน        · source = 'tambon_centroid'
     *   3) amphur.lat/lng จริง                                · source = 'amphur'
     *   4) centroid ของหมู่บ้านใน amphur เดียวกัน             · source = 'amphur_centroid'
     *   5) centroid ของทุกหมู่บ้านในระบบ (= จังหวัด)           · source = 'province_centroid'
     *   6) hardcoded จุดศูนย์กลางจังหวัดนครราชสีมา             · source = 'province_hardcoded'
     */
    public static function resolveCenterCoords(int $tambonId): array
    {
        $tambon = Tambon::find($tambonId);

        // 1) tambon's own real coords
        if ($tambon && $tambon->lat && $tambon->lng) {
            return ['lat' => $tambon->lat, 'lng' => $tambon->lng, 'source' => 'tambon'];
        }

        // 2) tambon centroid from sibling villages
        $c = DB::table('villages')
            ->where('tambon_id', $tambonId)
            ->whereNotNull('lat')->whereNotNull('lng')
            ->selectRaw('AVG(lat) as lat, AVG(lng) as lng')
            ->first();
        if ($c && $c->lat) return ['lat' => (float) $c->lat, 'lng' => (float) $c->lng, 'source' => 'tambon_centroid'];

        // 3) amphur's own real coords
        if ($tambon && $tambon->amphur && $tambon->amphur->lat && $tambon->amphur->lng) {
            return ['lat' => $tambon->amphur->lat, 'lng' => $tambon->amphur->lng, 'source' => 'amphur'];
        }

        // 4) amphur centroid from villages
        if ($tambon) {
            $c = DB::table('villages')
                ->join('tambons', 'tambons.id', '=', 'villages.tambon_id')
                ->where('tambons.amphur_id', $tambon->amphur_id)
                ->whereNotNull('villages.lat')->whereNotNull('villages.lng')
                ->selectRaw('AVG(villages.lat) as lat, AVG(villages.lng) as lng')
                ->first();
            if ($c && $c->lat) return ['lat' => (float) $c->lat, 'lng' => (float) $c->lng, 'source' => 'amphur_centroid'];
        }

        // 5) province centroid
        $c = DB::table('villages')
            ->whereNotNull('lat')->whereNotNull('lng')
            ->selectRaw('AVG(lat) as lat, AVG(lng) as lng')
            ->first();
        if ($c && $c->lat) return ['lat' => (float) $c->lat, 'lng' => (float) $c->lng, 'source' => 'province_centroid'];

        // 6) Korat hardcoded
        return ['lat' => 14.97, 'lng' => 102.10, 'source' => 'province_hardcoded'];
    }

    /**
     * ใส่พิกัดให้ village + jitter ตาม source:
     *   - real (tambon/amphur):     ±0.005° (~550m) — กระจายในเขต tambon
     *   - centroid:                  ±0.005° (~550m)
     *   - province fallback:         ±0.05° (~5.5km) — ให้รู้ว่าผิด ต้องแก้
     * $force = true จะคำนวณใหม่แม้มีพิกัดอยู่
     */
    public function ensureCoords(bool $force = false): void
    {
        if (!$force && $this->lat && $this->lng) return;
        $c = self::resolveCenterCoords($this->tambon_id);

        // jitter range ตาม source — ที่แม่นน้อยกว่า ใช้ jitter ใหญ่กว่า
        $jitter = match ($c['source']) {
            'tambon', 'tambon_centroid' => 0.005,    // ±550m
            'amphur', 'amphur_centroid' => 0.035,    // ±3.8km — กระจายในเขตอำเภอ
            default                      => 0.05,    // ±5.5km — เห็นชัดว่าต้องแก้
        };
        $r = fn() => (mt_rand(-1000, 1000) / 1000) * $jitter;

        $this->lat = $c['lat'] + $r();
        $this->lng = $c['lng'] + $r();
        $this->save();
    }
}
