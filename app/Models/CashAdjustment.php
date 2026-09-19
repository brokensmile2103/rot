<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashAdjustment extends Model
{
    protected $fillable = ['shift_id', 'type', 'amount', 'note'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}
