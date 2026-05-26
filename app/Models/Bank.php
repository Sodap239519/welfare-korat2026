<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Bank extends Model
{
    protected $fillable = ['code', 'name', 'sort_order'];

    /** Map [code => name] เหมือนเดิมที่ config('banks.banks') เคยคืน · cache 1 ชม. */
    public static function optionsMap(): array
    {
        return Cache::remember('banks.map', 3600, function () {
            return static::orderBy('sort_order')->orderBy('id')->pluck('name', 'code')->toArray();
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('banks.map');
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::clearCache());
        static::deleted(fn () => static::clearCache());
    }
}
