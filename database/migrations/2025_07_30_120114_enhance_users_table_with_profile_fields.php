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
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone')->default('UTC')->after('email_verified_at');
            $table->string('preferred_language', 5)->default('en')->after('timezone');
            $table->json('profile_data')->nullable()->after('preferred_language');
            $table->json('preferences')->nullable()->after('profile_data');
            $table->timestamp('last_active_at')->nullable()->after('preferences');
            $table->boolean('is_active')->default(true)->after('last_active_at');
            $table->string('avatar_url')->nullable()->after('is_active');
            $table->text('bio')->nullable()->after('avatar_url');
            
            // Analytics and limits tracking
            $table->integer('analyses_count')->default(0)->after('bio');
            $table->integer('projects_count')->default(0)->after('analyses_count');
            $table->timestamp('analyses_reset_at')->nullable()->after('projects_count');
            
            // User role and permissions
            $table->string('role')->default('user')->after('analyses_reset_at'); // user, admin, analyst
            $table->json('permissions')->nullable()->after('role');
            
            // Indexing for performance
            $table->index(['is_active', 'last_active_at']);
            $table->index(['role']);
            $table->index(['analyses_reset_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'last_active_at']);
            $table->dropIndex(['role']);
            $table->dropIndex(['analyses_reset_at']);
            
            $table->dropColumn([
                'timezone',
                'preferred_language',
                'profile_data',
                'preferences',
                'last_active_at',
                'is_active',
                'avatar_url',
                'bio',
                'analyses_count',
                'projects_count',
                'analyses_reset_at',
                'role',
                'permissions'
            ]);
        });
    }
};
