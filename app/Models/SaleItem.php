<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'product_name',
        'price',
        'quantity',
        'subtotal',
        'cost_price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'cost_price' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getProfitAttribute(): float
    {
        return ($this->price - $this->cost_price) * $this->quantity;
    }

    public function getProfitPercentAttribute(): float
    {
        if ((float) $this->cost_price == 0) {
            return 0;
        }
        return ((($this->price - $this->cost_price) / $this->cost_price) * 100);
    }

    // Calculate subtotal when price or quantity changes
    public function calculateSubtotal(): void
    {
        $this->subtotal = $this->price * $this->quantity;
        $this->save();
    }
}
