<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * All money columns are integer kobo (₦1 = 100). Never store naira as floats.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->bigInteger('balance')->default(0); // cache of the ledger, kobo
            $table->string('currency', 3)->default('NGN');
            $table->timestamps();
        });

        // Append-only. Rows are never updated or deleted.
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained();
            $table->enum('type', ['credit', 'debit']);
            $table->unsignedBigInteger('amount');
            $table->string('reference')->unique(); // HP-1001, HP-1001-R, HP-1001-C, PSK_xxx
            $table->string('reason', 40); // purchase, refund, funding, commission, manual_credit, manual_debit
            $table->string('description')->nullable();
            $table->bigInteger('balance_after');
            $table->foreignId('transaction_id')->nullable()->index();
            $table->foreignId('created_by')->nullable(); // admin for manual entries
            $table->timestamp('created_at')->useCurrent();
            $table->index(['wallet_id', 'id']);
        });

        Schema::create('virtual_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('bank');
            $table->string('account_number', 20)->unique();
            $table->string('account_name');
            $table->string('provider', 20)->default('paystack');
            $table->string('provider_ref')->nullable(); // Paystack customer_code
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // kaduna-electric, mtn, mtn-data
            $table->string('name');
            $table->string('short_name', 12);
            $table->enum('category', ['electricity', 'airtime', 'data', 'tv']);
            $table->string('provider', 20)->default('vtpass'); // primary route
            $table->string('fallback_provider', 20)->nullable(); // baxi
            $table->decimal('commission_rate', 5, 2)->default(0); // % VTpass pays us
            $table->unsignedInteger('fee')->default(0); // kobo, charged to customers
            $table->unsignedBigInteger('min_amount')->default(100_000);
            $table->unsignedBigInteger('max_amount')->default(50_000_000);
            $table->boolean('active')->default(true);
            $table->json('meta')->nullable(); // provider-specific ids, e.g. baxi service_type
            $table->timestamps();
        });

        Schema::create('saved_meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('service_code');
            $table->string('meter_number', 13);
            $table->enum('meter_type', ['prepaid', 'postpaid']);
            $table->string('customer_name');
            $table->string('address')->nullable();
            $table->string('label')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'service_code', 'meter_number']);
        });

        Schema::create('agent_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('commission_tier', ['bronze', 'silver', 'gold'])->default('bronze');
            $table->string('business_name');
            $table->string('location')->nullable();
            $table->timestamps();
        });

        Schema::create('bulk_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('service_code');
            $table->unsignedInteger('rows_total');
            $table->unsignedInteger('rows_done')->default(0);
            $table->enum('status', ['queued', 'running', 'done'])->default('queued');
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique(); // HP-100123, shown to customers
            $table->string('request_id')->unique(); // sent to the provider; idempotency key upstream
            $table->string('idempotency_key')->nullable(); // from the client, stops double taps
            $table->foreignId('user_id')->constrained();
            $table->foreignId('batch_id')->nullable()->constrained('bulk_batches');
            $table->string('service_code');
            $table->enum('category', ['electricity', 'airtime', 'data', 'tv']);
            $table->string('channel', 10)->default('app'); // app, web, agent, bulk
            $table->string('target'); // meter or phone number
            $table->string('meter_type', 10)->nullable();
            $table->string('variation_code')->nullable(); // data plan
            $table->string('variation_name')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 11)->nullable(); // where the token SMS goes
            $table->unsignedBigInteger('amount'); // face value
            $table->unsignedInteger('fee')->default(0);
            $table->unsignedBigInteger('commission')->default(0); // what the provider pays us
            $table->unsignedBigInteger('agent_commission')->default(0); // our share passed to the agent
            $table->enum('status', ['pending', 'success', 'failed', 'refunded'])->default('pending');
            $table->string('provider', 20);
            $table->string('provider_ref')->nullable();
            $table->string('token')->nullable();
            $table->string('units')->nullable();
            $table->string('failure_reason')->nullable();
            $table->unsignedSmallInteger('requery_count')->default(0);
            $table->timestamp('next_requery_at')->nullable();
            $table->timestamp('escalated_at')->nullable(); // pending too long, needs a human
            $table->timestamp('completed_at')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['status', 'next_requery_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('funding_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20)->default('paystack');
            $table->string('event');
            $table->string('reference')->unique(); // replaying the webhook hits this and stops
            $table->foreignId('user_id')->nullable()->constrained();
            $table->unsignedBigInteger('amount')->default(0);
            $table->enum('status', ['credited', 'ignored', 'checkout'])->default('credited');
            $table->json('payload');
            $table->timestamps();
        });

        // Web checkout: pay by card/transfer without an app, vend once Paystack confirms.
        Schema::create('checkout_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained();
            $table->string('service_code');
            $table->string('target');
            $table->string('meter_type', 10)->nullable();
            $table->string('variation_code')->nullable();
            $table->string('customer_name')->nullable();
            $table->unsignedBigInteger('amount');
            $table->unsignedInteger('fee')->default(0);
            $table->enum('status', ['awaiting_payment', 'paid', 'abandoned'])->default('awaiting_payment');
            $table->foreignId('transaction_id')->nullable()->constrained();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('action');
            $table->nullableMorphs('subject');
            $table->json('data')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('reconciliations', function (Blueprint $table) {
            $table->id();
            $table->date('for_date');
            $table->string('provider', 20)->default('vtpass');
            $table->unsignedInteger('matched')->default(0);
            $table->unsignedInteger('missing_ours')->default(0);
            $table->unsignedInteger('missing_theirs')->default(0);
            $table->unsignedInteger('mismatched')->default(0);
            $table->bigInteger('difference')->default(0); // kobo, theirs minus ours
            $table->json('details')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('system_checks', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // ledger, provider_balance
            $table->boolean('ok');
            $table->json('data')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['name', 'id']);
        });
    }

    public function down(): void
    {
        foreach (['system_checks', 'reconciliations', 'audit_logs', 'checkout_orders', 'funding_events', 'transactions',
            'bulk_batches', 'agent_profiles', 'saved_meters', 'services', 'virtual_accounts', 'ledger_entries', 'wallets'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
