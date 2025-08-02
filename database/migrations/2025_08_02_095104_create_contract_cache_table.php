<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_cache', function (Blueprint $table) {
            $table->id();
            $table->string('network', 50)->index();
            $table->string('contract_address', 42)->index();
            $table->string('cache_type', 50)->index(); // 'source', 'abi', 'creation'
            $table->string('contract_name')->nullable();
            $table->string('compiler_version')->nullable();
            $table->boolean('optimization_used')->default(false);
            $table->integer('optimization_runs')->default(0);
            $table->text('constructor_arguments')->nullable();
            $table->string('evm_version', 50)->nullable();
            $table->text('library')->nullable();
            $table->string('license_type', 100)->nullable();
            $table->boolean('proxy')->default(false);
            $table->string('implementation', 42)->nullable();
            $table->text('swarm_source')->nullable();
            $table->longText('source_code')->nullable();
            $table->json('parsed_sources')->nullable();
            $table->json('abi')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->string('creator_address', 42)->nullable();
            $table->string('creation_tx_hash', 66)->nullable();
            $table->json('metadata')->nullable(); // For additional explorer-specific data
            $table->timestamp('fetched_at');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            // Composite unique index for network + address + cache_type
            $table->unique(['network', 'contract_address', 'cache_type'], 'contract_cache_unique');
            
            // Index for cleanup queries
            $table->index(['expires_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_cache');
    }
};