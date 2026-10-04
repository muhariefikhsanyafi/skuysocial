<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['sqlite', 'mysql'], true)) {
            throw new RuntimeException('Points wallets are supported only on SQLite and MySQL.');
        }

        Schema::create('point_wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->bigInteger('balance')->default(0);
            $table->timestamps();
        });

        Schema::create('point_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->constrained('point_wallets')->restrictOnDelete();
            $table->enum('type', ['credit', 'debit']);
            $table->bigInteger('amount');
            $table->string('reason_code', 64);
            $table->string('reference_type', 64);
            $table->string('reference_key', 128);
            $table->string('idempotency_key', 128);
            $table->timestamp('created_at');

            $table->unique(
                ['wallet_id', 'idempotency_key'],
                'point_transactions_wallet_id_idempotency_unique',
            );
            $table->unique(
                ['wallet_id', 'reference_type', 'reference_key'],
                'point_transactions_wallet_reference_unique',
            );
            $table->index(
                ['wallet_id', 'created_at', 'id'],
                'point_transactions_wallet_history_index',
            );
        });

        if ($driver === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER point_wallets_balance_nonnegative_insert
                BEFORE INSERT ON point_wallets
                FOR EACH ROW WHEN NEW.balance < 0
                BEGIN
                    SELECT RAISE(ABORT, 'point wallet balance cannot be negative');
                END
            SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER point_wallets_balance_nonnegative_update
                BEFORE UPDATE OF balance ON point_wallets
                FOR EACH ROW WHEN NEW.balance < 0
                BEGIN
                    SELECT RAISE(ABORT, 'point wallet balance cannot be negative');
                END
            SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER point_transactions_amount_valid_insert
                BEFORE INSERT ON point_transactions
                FOR EACH ROW WHEN NEW.amount = 0
                    OR NOT ((NEW.type = 'credit' AND NEW.amount > 0)
                        OR (NEW.type = 'debit' AND NEW.amount < 0))
                BEGIN
                    SELECT RAISE(ABORT, 'point transaction amount does not match its type');
                END
            SQL);
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE point_wallets ADD CONSTRAINT point_wallets_balance_nonnegative CHECK (balance >= 0)');
            DB::statement("ALTER TABLE point_transactions ADD CONSTRAINT point_transactions_amount_valid CHECK (amount <> 0 AND ((type = 'credit' AND amount > 0) OR (type = 'debit' AND amount < 0)))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS point_transactions_amount_valid_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS point_wallets_balance_nonnegative_update');
            DB::unprepared('DROP TRIGGER IF EXISTS point_wallets_balance_nonnegative_insert');
        }

        Schema::dropIfExists('point_transactions');
        Schema::dropIfExists('point_wallets');
    }
};
