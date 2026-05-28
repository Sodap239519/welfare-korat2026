<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Bank extends Model
{
    protected $fillable = ['code', 'name', 'logo_path', 'sort_order'];

    /** auto-append logo_url ใน JSON */
    protected $appends = ['logo_url'];

    /** Map [code => name] เหมือนเดิมที่ config('banks.banks') เคยคืน · cache 1 ชม. */
    public static function optionsMap(): array
    {
        return Cache::remember('banks.map', 3600, function () {
            return static::orderBy('sort_order')->orderBy('id')->pluck('name', 'code')->toArray();
        });
    }

    /** Map [code => logo_url] · ใช้ใน Dashboard by_bank breakdown */
    public static function logoMap(): array
    {
        return Cache::remember('banks.logo_map', 3600, function () {
            return static::orderBy('sort_order')->orderBy('id')
                ->get(['code', 'logo_path'])
                ->mapWithKeys(fn ($b) => [$b->code => $b->logo_path])
                ->toArray();
        });
    }

    /** logo_url = logo_path ถ้าเริ่มด้วย / หรือ http ก็คืนทันที · ถ้าเป็นชื่อไฟล์เปล่า ก็เติม /img/banks/ */
    protected function logoUrl(): Attribute
    {
        return Attribute::make(get: function () {
            $p = $this->logo_path;
            if (!$p) return null;
            if (str_starts_with($p, 'http') || str_starts_with($p, '/')) return $p;
            return '/img/banks/' . $p;
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('banks.map');
        Cache::forget('banks.logo_map');
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::clearCache());
        static::deleted(fn () => static::clearCache());
    }
}
