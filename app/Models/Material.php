<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'unit',
        'stock',
        'min_stock',
        'purchase_price',
    ];

    protected $casts = [
        'stock' => 'decimal:2',
        'min_stock' => 'decimal:2',
        'purchase_price' => 'decimal:2',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_material')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function branchStocks(): HasMany
    {
        return $this->hasMany(MaterialBranchStock::class);
    }

    public function getStockForBranch(?int $branchId): ?float
    {
        if (! $branchId) {
            return $this->stock;
        }

        $branchStock = $this->branchStocks()->where('branch_id', $branchId)->first();

        return $branchStock?->stock ?? $this->stock;
    }

    public function isLowStock(?int $branchId = null): bool
    {
        $stock = $this->getStockForBranch($branchId);

        return $stock <= $this->min_stock;
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock', '<=', 'min_stock');
    }

    public function scopeSearch($query, $term)
    {
        return $query->where('name', 'like', "%{$term}%");
    }
}
