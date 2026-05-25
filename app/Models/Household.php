<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class Household extends Model
{
    protected $fillable = ['village_id', 'house_code_hash', 'house_code_enc', 'address_no'];

    protected $hidden = ['house_code_hash', 'house_code_enc'];

    public function village(): BelongsTo { return $this->belongsTo(Village::class); }
    public function targets(): HasMany { return $this->hasMany(Target::class); }

    public function setHouseCode(string $code): void
    {
        $this->house_code_hash = hash('sha256', $code);
        $this->house_code_enc  = Crypt::encryptString($code);
    }

    public function getHouseCodeAttribute(): ?string
    {
        return $this->house_code_enc ? Crypt::decryptString($this->house_code_enc) : null;
    }

    public static function hashFor(string $code): string
    {
        return hash('sha256', $code);
    }
}
