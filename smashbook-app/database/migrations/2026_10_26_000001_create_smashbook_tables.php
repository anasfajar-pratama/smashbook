<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('kasir')->after('email');
            $table->string('phone', 30)->nullable()->after('role');
            $table->string('pin_hash')->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('pin_hash');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->index('role');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->string('entity', 80);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity', 'entity_id']);
        });

        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 30);
            $table->string('period', 20)->default('');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->unique(['prefix', 'period']);
        });

        Schema::create('membership_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->decimal('price', 15, 2)->default(0);
            $table->unsignedInteger('duration_days')->default(30);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->unsignedInteger('free_hours_per_month')->default(0);
            $table->boolean('priority_booking')->default(false);
            $table->text('benefits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->string('phone', 30)->unique();
            $table->string('email')->nullable();
            $table->string('gender', 15)->nullable();
            $table->date('birth_date')->nullable();
            $table->text('address')->nullable();
            $table->string('photo')->nullable();
            $table->foreignId('member_type_id')->nullable()->constrained('membership_types')->nullOnDelete();
            $table->date('join_date');
            $table->string('status', 30)->default('aktif');
            $table->unsignedInteger('no_show_count')->default(0);
            $table->decimal('deposit_balance', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'phone']);
        });

        Schema::create('member_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_type_id')->constrained();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('status', 20)->default('aktif');
            $table->string('invoice_code', 30)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['member_id', 'end_date']);
        });

        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('ref_type', 30)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['member_id', 'created_at']);
        });

        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 80);
            $table->string('type', 40)->default('reguler');
            $table->string('surface', 60)->nullable();
            $table->unsignedInteger('capacity')->default(4);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('court_price_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained()->cascadeOnDelete();
            $table->string('day_type', 20);
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('price_per_hour', 15, 2);
            $table->string('label', 60);
            $table->timestamps();
            $table->index(['court_id', 'day_type', 'start_time']);
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name', 80);
            $table->boolean('is_peak')->default(true);
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name', 120);
            $table->string('customer_phone', 30)->nullable();
            $table->foreignId('court_id')->constrained();
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('duration_minutes');
            $table->decimal('base_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('payment_status', 20)->default('unpaid');
            $table->string('status', 20)->default('confirmed');
            $table->string('source', 20)->default('walkin');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['booking_date', 'status']);
            $table->index(['court_id', 'booking_date']);
        });

        Schema::create('booking_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_id')->constrained();
            $table->date('slot_date');
            $table->time('slot_time');
            $table->decimal('price', 15, 2);
            $table->string('category', 30);
            $table->unique(['booking_id', 'court_id', 'slot_date', 'slot_time'], 'booking_slots_unique');
        });

        Schema::create('court_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained()->cascadeOnDelete();
            $table->date('slot_date');
            $table->time('slot_time');
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('open');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['court_id', 'slot_date', 'slot_time']);
            $table->index(['slot_date', 'status']);
        });

        Schema::create('booking_recurring', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name', 120);
            $table->string('customer_phone', 30)->nullable();
            $table->foreignId('court_id')->constrained();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->date('valid_from');
            $table->date('valid_until');
            $table->string('status', 20)->default('aktif');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('booking_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('cancelled_at')->useCurrent();
            $table->text('reason');
            $table->decimal('refund_amount', 15, 2)->default(0);
            $table->decimal('refund_pct', 5, 2)->default(0);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('contact_person', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('category', 60)->nullable();
            $table->string('payment_term', 60)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 30)->unique();
            $table->string('name', 120);
            $table->string('category', 40);
            $table->string('base_unit', 20);
            $table->string('purchase_unit', 20)->nullable();
            $table->unsignedInteger('conversion_qty')->default(1);
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('sell_price', 15, 2)->default(0);
            $table->integer('stock_qty')->default(0);
            $table->integer('min_stock')->default(0);
            $table->integer('reorder_point')->default(0);
            $table->boolean('allow_negative')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('location', 60)->nullable();
            $table->string('icon', 10)->nullable();
            $table->timestamps();
            $table->index(['category', 'is_active']);
        });

        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('shift', 10);
            $table->foreignId('cashier_id')->constrained('users');
            $table->timestamp('opened_at');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->decimal('expected_balance', 15, 2)->nullable();
            $table->decimal('physical_balance', 15, 2)->nullable();
            $table->decimal('difference', 15, 2)->nullable();
            $table->string('status', 20)->default('open');
            $table->text('notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'opened_at']);
            $table->index(['cashier_id', 'status']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_name', 120)->nullable();
            $table->string('po_type', 20)->default('product');
            $table->date('request_date');
            $table->date('expected_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('shipping_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('payment_method', 20)->default('cash');
            $table->string('priority', 20)->default('normal');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('attachment')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'request_date']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 20)->default('product');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('expense_category', 60)->nullable();
            $table->string('description', 160);
            $table->decimal('qty', 12, 2);
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_price', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->decimal('received_qty', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('category', 60);
            $table->decimal('amount', 15, 2);
            $table->unsignedTinyInteger('day_of_month')->default(1);
            $table->boolean('is_active')->default(true);
            $table->date('last_generated')->nullable();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('category', 60);
            $table->string('description', 160);
            $table->decimal('amount', 15, 2);
            $table->date('expense_date');
            $table->string('payment_method', 20)->default('cash');
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('attachment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['expense_date', 'category']);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->string('category', 40);
            $table->decimal('amount', 15, 2);
            $table->string('ref_type', 40)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable()->unique();
            $table->string('description', 160);
            $table->string('attachment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['cash_session_id', 'type']);
        });

        Schema::create('pos_sales', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('payment_status', 20)->default('paid');
            $table->string('status', 20)->default('paid');
            $table->foreignId('cashier_id')->constrained('users');
            $table->string('client_uuid', 40)->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['created_at', 'status']);
        });

        Schema::create('pos_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->integer('qty');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('cost_price_snapshot', 15, 2)->default(0);
            $table->decimal('amount', 15, 2);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('ref_type', 30);
            $table->unsignedBigInteger('ref_id');
            $table->string('method', 20);
            $table->decimal('amount', 15, 2);
            $table->timestamp('paid_at');
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference_no', 80)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['ref_type', 'ref_id']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->integer('qty');
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->integer('balance_after');
            $table->string('ref_type', 40)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('reason', 80)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'created_at']);
            $table->index(['ref_type', 'ref_id']);
        });

        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->date('opname_date');
            $table->string('status', 20)->default('draft');
            $table->decimal('total_variance_value', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->integer('system_qty');
            $table->integer('physical_qty');
            $table->integer('variance');
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('variance_value', 15, 2)->default(0);
            $table->string('reason', 80)->nullable();
            $table->unique(['stock_opname_id', 'product_id']);
        });

        Schema::create('cash_deposits_bank', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 15, 2);
            $table->string('bank', 80);
            $table->date('deposit_date');
            $table->string('reference', 80)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_deposits_bank');
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('pos_sale_items');
        Schema::dropIfExists('pos_sales');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('recurring_expenses');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('products');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('booking_cancellations');
        Schema::dropIfExists('booking_recurring');
        Schema::dropIfExists('court_slots');
        Schema::dropIfExists('booking_slots');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('court_price_rules');
        Schema::dropIfExists('courts');
        Schema::dropIfExists('deposits');
        Schema::dropIfExists('member_subscriptions');
        Schema::dropIfExists('members');
        Schema::dropIfExists('membership_types');
        Schema::dropIfExists('sequences');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('settings');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'phone', 'pin_hash', 'is_active', 'last_login_at']);
        });
    }
};
