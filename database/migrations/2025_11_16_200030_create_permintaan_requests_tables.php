<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('permintaan_tarik_saldo', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('rekening_id')
                ->constrained('rekening')
                ->cascadeOnDelete()
                ->index()
                ->name('fk_permintaan_tarik_saldo_rekening');
            $table->foreignUlid('requested_by_rekening_id')
                ->nullable()
                ->constrained('rekening')
                ->nullOnDelete()
                ->index()
                ->name('fk_permintaan_tarik_saldo_requested_rekening');
            $table->foreignUlid('requested_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->index()
                ->name('fk_permintaan_tarik_saldo_requested_user');
            $table->decimal('amount', 15, 2);
            $table->string('jenis', 50)->default('tunai');
            $table->text('catatan')->nullable();
            $table->string('status', 32)->default('menunggu_konfirmasi')->index();
            $table->timestamp('requested_at')->nullable()->index();
            $table->foreignUlid('confirmed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->index()
                ->name('fk_permintaan_tarik_saldo_confirmed_by');
            $table->timestamp('confirmed_at')->nullable();
            $table->text('keterangan_admin')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('source', 64)->default('mobile_banking')->index();
            $table->string('qr_token', 128)->unique();
            $table->timestamp('qr_token_expires_at')->nullable();
            $table->string('processed_via', 32)->nullable();
            $table->json('meta')->nullable();
            $table->foreignUlid('final_withdraw_request_id')
                ->nullable()
                ->constrained('withdraw_requests')
                ->nullOnDelete()
                ->index()
                ->name('fk_permintaan_tarik_saldo_final_withdraw');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('permintaan_setor_sampah', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('rekening_id')
                ->constrained('rekening')
                ->cascadeOnDelete()
                ->index()
                ->name('fk_permintaan_setor_sampah_rekening');
            $table->foreignUlid('requested_by_rekening_id')
                ->nullable()
                ->constrained('rekening')
                ->nullOnDelete()
                ->index()
                ->name('fk_permintaan_setor_sampah_requested_rekening');
            $table->foreignUlid('requested_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->index()
                ->name('fk_permintaan_setor_sampah_requested_user');
            $table->string('jenis_setoran', 32)->default('rekening');
            $table->date('tanggal_setor')->nullable();
            $table->decimal('total_berat', 12, 4)->default(0);
            $table->decimal('total_saldo_dihasilkan', 15, 2)->default(0);
            $table->bigInteger('total_poin_dihasilkan')->default(0);
            $table->boolean('calculation_performed')->default(false);
            $table->json('detail_items')->nullable();
            $table->json('meta')->nullable();
            $table->string('status', 32)->default('menunggu_konfirmasi')->index();
            $table->timestamp('requested_at')->nullable()->index();
            $table->foreignUlid('confirmed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->index()
                ->name('fk_permintaan_setor_sampah_confirmed_by');
            $table->timestamp('confirmed_at')->nullable();
            $table->text('keterangan_admin')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('source', 64)->default('mobile_banking')->index();
            $table->string('qr_token', 128)->unique();
            $table->timestamp('qr_token_expires_at')->nullable();
            $table->string('processed_via', 32)->nullable();
            $table->foreignUlid('final_setor_sampah_id')
                ->nullable()
                ->constrained('setor_sampah')
                ->nullOnDelete()
                ->index()
                ->name('fk_permintaan_setor_sampah_final');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permintaan_setor_sampah');
        Schema::dropIfExists('permintaan_tarik_saldo');
    }
};
