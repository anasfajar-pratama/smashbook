<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\StockOpname;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request, InventoryService $inventory)
    {
        $tab = $request->input('tab', 'daftar');
        $search = trim((string) $request->input('q'));
        $category = $request->input('category');
        $status = $request->input('status');

        $products = Product::query()
            ->when($search !== '', fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%');
            }))
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($status === 'low', fn ($q) => $q->where('allow_negative', false)->whereColumn('stock_qty', '<=', 'min_stock')->where('stock_qty', '>', 0))
            ->when($status === 'out', fn ($q) => $q->where('allow_negative', false)->where('stock_qty', '<=', 0))
            ->when($status === 'safe', fn ($q) => $q->whereColumn('stock_qty', '>', 'min_stock'))
            ->orderBy('category')->orderBy('name')
            ->get();

        $categories = Product::query()->distinct()->orderBy('category')->pluck('category');
        $allProducts = Product::query()->where('is_active', true)->orderBy('name')->get();
        $movements = InventoryMovement::query()->with(['product', 'creator'])->latest()->limit(40)->get();
        $opnames = StockOpname::query()->with(['creator', 'finalizer'])->latest()->limit(10)->get();
        $lowStock = Product::query()->lowStock()->orderBy('stock_qty')->get();
        $lastOpname = StockOpname::query()->where('status', 'finalized')->latest('finalized_at')->first();

        $stats = [
            'stock_value' => $inventory->stockValue(),
            'sku_count' => Product::query()->where('is_active', true)->count(),
            'low_count' => $lowStock->count(),
            'out_count' => Product::query()->where('allow_negative', false)->where('stock_qty', '<=', 0)->where('is_active', true)->count(),
            'last_opname' => $lastOpname,
        ];

        $cardProduct = $request->filled('card')
            ? Product::query()->with(['movements' => fn ($q) => $q->with('creator')->limit(60)])->find($request->integer('card'))
            : null;

        return view('inventory.index', compact(
            'tab', 'products', 'categories', 'movements', 'opnames', 'lowStock',
            'stats', 'search', 'category', 'status', 'cardProduct', 'allProducts'
        ));
    }

    public function storeProduct(Request $request, InventoryService $inventory)
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:30', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:40'],
            'base_unit' => ['required', 'string', 'max:20'],
            'purchase_unit' => ['nullable', 'string', 'max:20'],
            'conversion_qty' => ['nullable', 'integer', 'min:1'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sell_price' => ['required', 'numeric', 'min:0'],
            'stock_qty' => ['nullable', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'reorder_point' => ['nullable', 'integer', 'min:0'],
            'allow_negative' => ['nullable', 'boolean'],
            'location' => ['nullable', 'string', 'max:60'],
            'icon' => ['nullable', 'string', 'max:10'],
        ]);

        $opening = (int) ($data['stock_qty'] ?? 0);
        $data['stock_qty'] = 0;
        $data['conversion_qty'] = $data['conversion_qty'] ?? 1;
        $data['allow_negative'] = $request->boolean('allow_negative');

        $product = Product::create($data);

        if ($opening > 0) {
            $inventory->openingStock($product, $opening, (float) $data['cost_price'], $request->user());
        }

        return back()->with('success', 'Barang '.$product->name.' ditambahkan dengan stok awal '.$opening.'.');
    }

    public function updateProduct(Request $request, Product $product, InventoryService $inventory)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:40'],
            'base_unit' => ['required', 'string', 'max:20'],
            'purchase_unit' => ['nullable', 'string', 'max:20'],
            'conversion_qty' => ['nullable', 'integer', 'min:1'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sell_price' => ['required', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'reorder_point' => ['nullable', 'integer', 'min:0'],
            'allow_negative' => ['nullable', 'boolean'],
            'location' => ['nullable', 'string', 'max:60'],
            'icon' => ['nullable', 'string', 'max:10'],
        ]);

        $data['conversion_qty'] = $data['conversion_qty'] ?? 1;
        $data['allow_negative'] = $request->boolean('allow_negative');
        $product->update($data);

        return back()->with('success', 'Master barang diperbarui. Stok tidak diubah langsung — gunakan stok masuk/keluar.');
    }

    public function stockIn(Request $request, InventoryService $inventory)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'source' => ['required', 'in:pembelian,retur,koreksi,hibah'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $inventory->stockIn($product, (int) $data['qty'], (float) $data['unit_cost'], $data['source'], null, $data['notes'] ?? null, $request->user());

        return redirect()->route('inventory.index', ['tab' => 'masuk'])->with('success', 'Stok masuk dicatat dan HPP rata-rata diperbarui.');
    }

    public function stockOut(Request $request, InventoryService $inventory)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'in:pemakaian_internal,rusak,kedaluwarsa,hilang,salah_catat,komplimen'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $inventory->stockOut($product, (int) $data['qty'], 'manual', null, $data['reason'], $data['notes'] ?? null, $request->user());

        return redirect()->route('inventory.index', ['tab' => 'keluar'])->with('success', 'Stok keluar dicatat.');
    }

    public function opname(Request $request, InventoryService $inventory)
    {
        $data = $request->validate([
            'counts' => ['required', 'array'],
            'counts.*' => ['nullable', 'integer', 'min:0'],
            'reasons' => ['nullable', 'array'],
            'reasons.*' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $nonEmpty = array_filter($data['counts'], fn ($value) => $value !== null && $value !== '');

        if (empty($nonEmpty)) {
            return back()->with('error', 'Isi minimal satu qty fisik untuk opname.');
        }

        $opname = $inventory->finalizeOpname($data['counts'], $data['reasons'] ?? [], $request->user(), $data['notes'] ?? null);

        return redirect()->route('inventory.index', ['tab' => 'opname'])->with('success', 'Opname '.$opname->code.' difinalisasi. Selisih nilai: '.rupiah($opname->total_variance_value).'.');
    }
}
