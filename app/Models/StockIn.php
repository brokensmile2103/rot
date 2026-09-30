<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockIn extends Model
{
    protected $fillable = ['ingredient_id', 'quantity', 'total_cost', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'total_cost' => 'decimal:2'];
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
