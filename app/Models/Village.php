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
     * คืน [lat, lng] โดยลำดับ fallback:
     *   1) centroid ของหมู่บ้านอื่นใน tambon เดียวกัน
     *   2) centroid ของหมู่บ้านใน amphur เดียวกัน
     *   3) centroid ของทุกหมู่บ้านในระบบ (= จังหวัด)
     *   4) hardcoded จุดศูนย์กลางจังหวัดนครราชสีมา
     */
    public static function resolveCenterCoords(int $tambonId): array
    {
        // 1) tambon centroid
        $c = DB::table('villages')
            ->where('tambon_id', $tambonId)
            ->whereNotNull('lat')->whereNotNull('lng')
            ->selectRaw('AVG(lat) as lat, AVG(lng) as lng')
            ->first();
        if ($c && $c->lat) return ['lat' => (float) $c->lat, 'lng' => (float) $c->lng];

        // 2) amphur centroid
        $amphurId = Tambon::where('id', $tambonId)->value('amphur_id');
        if ($amphurId) {
            $c = DB::table('villages')
                ->join('tambons', 'tambons.id', '=', 'villages.tambon_id')
                ->where('tambons.amphur_id', $amphurId)
                ->whereNotNull('villages.lat')->whereNotNull('villages.lng')
                ->selectRaw('AVG(villages.lat) as lat, AVG(villages.lng) as lng')
                ->first();
            if ($c && $c->lat) return ['lat' => (float) $c->lat, 'lng' => (float) $c->lng];
        }

        // 3) province centroid
        $c = DB::table('villages')
            ->whereNotNull('lat')->whereNotNull('lng')
            ->selectRaw('AVG(lat) as lat, AVG(lng) as lng')
            ->first();
        if ($c && $c->lat) return ['lat' => (float) $c->lat, 'lng' => (float) $c->lng];

        // 4) Korat province center
        return ['lat' => 14.97, 'lng' => 102.10];
    }

    /** ใส่พิกัดให้ village ที่ยังว่าง + jitter เล็กๆ กันแท่งซ้อนกันเป๊ะ */
    public function ensureCoords(): void
    {
        if ($this->lat && $this->lng) return;
        $c = self::resolveCenterCoords($this->tambon_id);
        // jitter ~ ±0.0005° ≈ 55 m เพื่อไม่ให้หมู่บ้านใหม่หลายตัวจาก fallback เดียวกัน stack
        $this->lat = $c['lat'] + (mt_rand(-50, 50) / 100000);
        $this->lng = $c['lng'] + (mt_rand(-50, 50) / 100000);
        $this->save();
    }
}
