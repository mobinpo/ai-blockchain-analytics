<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_badges', function (Blueprint $table) {
            $table->id();
            
            // Core verification data
            $table->string('contract_address', 42)->index(); // Ethereum address
            $table->string('user_id', 100)->index();
            $table->text('verification_token')->nullable(); // Store the signed token
            
            // Verification status
            $table->timestamp('verified_at')->nullable()->index();
            $table->string('verification_method', 50)->default('signed_url');
            
            // Metadata and context
            $table->json('metadata')->nullable(); // Store project info, links, etc.
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            
            // Revocation support
            $table->timestamp('revoked_at')->nullable()->index();
            $table->string('revoked_reason', 500)->nullable();
            
            // Expiration support
            $table->timestamp('expires_at')->nullable()->index();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['contract_address', 'verified_at']);
            $table->index(['user_id', 'verified_at']);
            $table->index(['verified_at', 'revoked_at']);
            
            // Unique constraint: one active verification per contract
            $table->unique(['contract_address'], 'unique_active_verification');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_badges');
    }
};