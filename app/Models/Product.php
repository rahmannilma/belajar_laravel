<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'barcode',
        'description',
        'purchase_price',
        'selling_price',
        'stock',
        'min_stock',
        'image',
        'is_active',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->sku)) {
                $product->sku = 'SKU-'.strtoupper(Str::random(8));
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'product_material')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function branchStocks(): HasMany
    {
        return $this->hasMany(ProductBranchStock::class);
    }

    public function getStockForBranch(?int $branchId): ?float
    {
        if (! $branchId) {
            return $this->stock;
        }

        $branchStock = $this->branchStocks()->where('branch_id', $branchId)->first();

        return $branchStock?->stock ?? $this->stock;
    }

    public function hasMaterials(): bool
    {
        return $this->materials()->count() > 0;
    }

    public static function updateStocksFromMaterial(Material $material, ?int $branchId = null): void
    {
        // Find all products that use this material
        $products = self::whereHas('materials', function ($q) use ($material) {
            $q->where('material_id', $material->id);
        })->get();

        // Get branch IDs - filter by material's branch owner
        if ($branchId) {
            $branchIds = [$branchId];
        } else {
            // Get branches that have this material in stock
            $branchIds = $material->branchStocks()->pluck('branch_id')->toArray();
        }

        foreach ($products as $product) {
            foreach ($branchIds as $branchId) {
                // Update stock for each branch
                $stock = $product->calculateStockFromMaterials($branchId);
                $product->branchStocks()->updateOrCreate(
                    ['branch_id' => $branchId],
                    ['stock' => $stock]
                );
            }
        }
    }

    public function calculateStockFromMaterials(int $branchId): ?float
    {
        $materialIds = $this->materials()->pluck('materials.id')->toArray();

        if (empty($materialIds)) {
            return null;
        }

        $productMaterialPivots = DB::table('product_material')
            ->where('product_id', $this->id)
            ->whereIn('material_id', $materialIds)
            ->pluck('quantity', 'material_id')
            ->toArray();

        $materials = Material::whereIn('id', $materialIds)
            ->with(['branchStocks' => function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            }])
            ->get();

        $minStock = PHP_FLOAT_MAX;

        foreach ($materials as $material) {
            $branchStock = $material->branchStocks->first();

            // If no branch stock, fall back to main stock field
            $stock = $branchStock?->stock ?? ($material->stock ?? 0);

            if ($stock <= 0) {
                return 0;
            }

            $quantity = $productMaterialPivots[$material->id] ?? 1;
            $possibleProducts = floor($stock / $quantity);

            if ($possibleProducts < $minStock) {
                $minStock = $possibleProducts;
            }
        }

        return $minStock === PHP_FLOAT_MAX ? 0 : (int) $minStock;
    }

    public function isLowStock(?int $branchId = null): bool
    {
        $stock = $this->getStockForBranch($branchId);

        return $stock <= $this->min_stock;
    }

    public function getProfitAttribute(): float
    {
        return (float) $this->selling_price - (float) $this->purchase_price;
    }

    public function getProfitPercentAttribute(): float
    {
        if ((float) $this->purchase_price == 0) {
            return 0;
        }

        return ($this->profit / (float) $this->purchase_price) * 100;
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock', '<=', 'min_stock');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%");
        });
    }
}
