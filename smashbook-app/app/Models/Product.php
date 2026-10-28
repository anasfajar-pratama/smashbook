<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sell_price' => 'decimal:2',
            'stock_qty' => 'integer',
            'min_stock' => 'integer',
            'reorder_point' => 'integer',
            'allow_negative' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class)->latest('created_at');
    }

    public function saleItems()
    {
        return $this->hasMany(PosSaleItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->where('is_active', true)
            ->where('allow_negative', false)
            ->whereColumn('stock_qty', '<=', 'min_stock');
    }

    public function isLowStock(): bool
    {
        return ! $this->allow_negative && $this->stock_qty <= $this->min_stock;
    }

    public function marginPct(): float
    {
        return $this->sell_price > 0
            ? (($this->sell_price - $this->cost_price) / $this->sell_price) * 100
            : 0;
    }
}
