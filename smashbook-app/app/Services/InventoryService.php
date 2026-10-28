<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private AuditLogger $audit,
    ) {}

    public function move(
        Product $product,
        string $type,
        int $qty,
        float $unitCost = 0,
        ?string $refType = null,
        ?int $refId = null,
        ?string $reason = null,
        ?string $notes = null,
        ?User $user = null,
        ?string $directionLabel = null
    ): InventoryMovement {
        if (! in_array($type, ['in', 'out', 'adjust'], true)) {
            throw new \InvalidArgumentException('Tipe movement tidak valid.');
        }

        if ($type !== 'adjust' && $qty <= 0) {
            throw ValidationException::withMessages(['qty' => 'Qty harus lebih dari nol.']);
        }

        if ($type === 'adjust' && $qty === 0) {
            throw ValidationException::withMessages(['qty' => 'Qty penyesuaian tidak boleh nol.']);
        }

        return DB::transaction(function () use ($product, $type, $qty, $unitCost, $refType, $refId, $reason, $notes, $user) {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $stock = (int) $locked->stock_qty;
            $oldCost = (float) $locked->cost_price;

            $newStock = match ($type) {
                'in' => $stock + $qty,
                'out' => $stock - $qty,
                'adjust' => $stock + $qty,
            };

            if ($newStock < 0 && ! $locked->allow_negative) {
                throw ValidationException::withMessages([
                    'stock' => 'Stok '.$locked->name.' tidak mencukupi (tersedia '.$stock.' '.$locked->base_unit.').',
                ]);
            }

            $movementQty = $type === 'adjust' ? $qty : $qty;
            $cost = $unitCost > 0 ? $unitCost : $oldCost;

            if ($type === 'in' && $newStock > 0) {
                $cost = round((($stock * $oldCost) + ($qty * ($unitCost > 0 ? $unitCost : $oldCost))) / $newStock, 2);
                $locked->cost_price = $cost;
            }

            $locked->stock_qty = $newStock;
            $locked->save();

            $movement = InventoryMovement::create([
                'product_id' => $locked->id,
                'type' => $type,
                'qty' => $movementQty,
                'unit_cost' => $cost,
                'balance_after' => $newStock,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $user?->id ?? auth()->id(),
            ]);

            return $movement->load('product', 'creator');
        });
    }

    public function stockIn(Product $product, int $qty, float $unitCost, ?string $refType, ?int $refId, ?string $notes, ?User $user = null, ?string $reason = null): InventoryMovement
    {
        return $this->move($product, 'in', $qty, $unitCost, $refType, $refId, $reason ?? 'stok_masuk', $notes, $user);
    }

    public function stockOut(Product $product, int $qty, ?string $refType, ?int $refId, ?string $reason, ?string $notes = null, ?User $user = null): InventoryMovement
    {
        return $this->move($product, 'out', $qty, 0, $refType, $refId, $reason, $notes, $user);
    }

    public function openingStock(Product $product, int $qty, float $unitCost, ?User $user = null): ?InventoryMovement
    {
        if ($qty <= 0) {
            return null;
        }

        return $this->stockIn($product, $qty, $unitCost, 'opening', null, 'Stok awal saat produk dibuat', $user, 'stok_awal');
    }

    public function stockValue(): float
    {
        return (float) Product::query()
            ->where('is_active', true)
            ->where('allow_negative', false)
            ->get()
            ->sum(fn ($product) => (float) $product->stock_qty * (float) $product->cost_price);
    }

    public function finalizeOpname(array $counts, array $reasons, User $user, ?string $notes = null): StockOpname
    {
        return DB::transaction(function () use ($counts, $reasons, $user, $notes) {
            $opname = StockOpname::create([
                'code' => $this->numbers->next('OPN', now()->format('Ym'), 2),
                'opname_date' => now()->toDateString(),
                'status' => 'draft',
                'created_by' => $user->id,
                'notes' => $notes,
            ]);

            $totalVariance = 0.0;
            $count = 0;

            foreach ($counts as $productId => $physical) {
                if ($physical === null || $physical === '') {
                    continue;
                }

                $product = Product::query()->lockForUpdate()->find((int) $productId);
                if (! $product) {
                    continue;
                }

                $system = (int) $product->stock_qty;
                $physical = (int) $physical;
                $variance = $physical - $system;
                $unitCost = (float) $product->cost_price;
                $varianceValue = $variance * $unitCost;

                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'product_id' => $product->id,
                    'system_qty' => $system,
                    'physical_qty' => $physical,
                    'variance' => $variance,
                    'unit_cost' => $unitCost,
                    'variance_value' => $varianceValue,
                    'reason' => $reasons[$productId] ?? null,
                ]);

                if ($variance !== 0) {
                    $this->move(
                        $product,
                        'adjust',
                        $variance,
                        $unitCost,
                        'opname',
                        $opname->id,
                        $reasons[$productId] ?? 'Hasil stock opname',
                        'Opname '.$opname->code,
                        $user
                    );
                }

                $totalVariance += $varianceValue;
                $count++;
            }

            $opname->update([
                'status' => 'finalized',
                'total_variance_value' => $totalVariance,
                'finalized_by' => $user->id,
                'finalized_at' => now(),
            ]);

            $this->audit->log('stock.opname', 'stock_opname', $opname->id, null, [
                'items' => $count,
                'total_variance_value' => $totalVariance,
            ], $notes);

            return $opname->load('items.product');
        });
    }
}
