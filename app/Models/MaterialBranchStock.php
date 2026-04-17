<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialBranchStock extends Model
{
    protected $table = 'material_branch_stock';

    protected $fillable = [
        'material_id',
        'branch_id',
        'stock',
    ];

    protected $casts = [
        'stock' => 'decimal:2',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
