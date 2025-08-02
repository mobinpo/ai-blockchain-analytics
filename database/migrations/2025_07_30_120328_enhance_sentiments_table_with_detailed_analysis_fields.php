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
        Schema::table('sentiments', function (Blueprint $table) {
            // Sentiment source and context
            $table->string('source_type')->default('code')->after('analysis_id'); // code, comment, documentation, community
            $table->string('source_reference')->nullable()->after('source_type'); // Line reference, URL, etc.
            $table->text('source_text')->nullable()->after('source_reference'); // Original text analyzed
            
            // Detailed sentiment breakdown
            $table->decimal('positive_score', 5, 2)->nullable()->after('magnitude'); // 0.00-1.00
            $table->decimal('negative_score', 5, 2)->nullable()->after('positive_score'); // 0.00-1.00
            $table->decimal('neutral_score', 5, 2)->nullable()->after('negative_score'); // 0.00-1.00
            $table->decimal('mixed_score', 5, 2)->nullable()->after('neutral_score'); // 0.00-1.00
            
            // Emotion detection
            $table->json('emotions')->nullable()->after('mixed_score'); // joy, anger, fear, etc. with scores
            $table->string('dominant_emotion')->nullable()->after('emotions');
            $table->decimal('emotion_intensity', 5, 2)->nullable()->after('dominant_emotion'); // 0.00-1.00
            
            // Language and processing
            $table->string('language')->default('en')->after('emotion_intensity');
            $table->decimal('language_confidence', 5, 2)->nullable()->after('language'); // 0.00-1.00
            $table->string('processing_model')->default('google')->after('language_confidence'); // google, aws, openai, custom
            $table->string('model_version')->nullable()->after('processing_model');
            
            // Blockchain-specific sentiment aspects
            $table->decimal('security_sentiment', 5, 2)->nullable()->after('model_version'); // Security-related sentiment
            $table->decimal('performance_sentiment', 5, 2)->nullable()->after('security_sentiment'); // Performance-related
            $table->decimal('usability_sentiment', 5, 2)->nullable()->after('performance_sentiment'); // UX-related
            $table->decimal('trust_sentiment', 5, 2)->nullable()->after('usability_sentiment'); // Trust/reliability
            
            // Keywords and themes
            $table->json('keywords')->nullable()->after('trust_sentiment'); // Important keywords found
            $table->json('themes')->nullable()->after('keywords'); // Main themes identified
            $table->json('entities')->nullable()->after('themes'); // Named entities (people, projects, etc.)
            
            // Context and metadata
            $table->string('context')->nullable()->after('entities'); // Additional context
            $table->integer('text_length')->nullable()->after('context'); // Length of analyzed text
            $table->integer('word_count')->nullable()->after('text_length'); // Word count
            $table->integer('sentence_count')->nullable()->after('word_count'); // Sentence count
            
            // Quality and confidence
            $table->decimal('confidence_score', 5, 2)->default(100.00)->after('sentence_count'); // 0.00-100.00
            $table->string('quality_rating')->default('good')->after('confidence_score'); // poor, fair, good, excellent
            $table->boolean('requires_review')->default(false)->after('quality_rating');
            $table->text('review_notes')->nullable()->after('requires_review');
            
            // Time-based analysis
            $table->timestamp('analyzed_at')->nullable()->after('review_notes');
            $table->integer('processing_time_ms')->nullable()->after('analyzed_at'); // Processing time in milliseconds
            
            // Comparison and trending
            $table->decimal('baseline_score', 5, 2)->nullable()->after('processing_time_ms'); // Baseline for comparison
            $table->decimal('trend_change', 5, 2)->nullable()->after('baseline_score'); // Change from baseline
            $table->string('trend_direction')->nullable()->after('trend_change'); // improving, declining, stable
            
            // External references
            $table->string('external_id')->nullable()->after('trend_direction'); // External system ID
            $table->json('external_data')->nullable()->after('external_id'); // External system data
            $table->json('tags')->nullable()->after('external_data'); // Sentiment tags
            
            // Change details to be more structured
            $table->dropColumn('details');
            $table->json('analysis_details')->nullable()->after('tags'); // Detailed analysis data
            $table->json('raw_response')->nullable()->after('analysis_details'); // Raw API response
            
            // Indexing for performance
            $table->index(['source_type', 'language']);
            $table->index(['dominant_emotion', 'emotion_intensity']);
            $table->index(['confidence_score', 'quality_rating']);
            $table->index(['requires_review']);
            $table->index(['analyzed_at']);
            $table->index(['trend_direction']);
            $table->index(['processing_model', 'model_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sentiments', function (Blueprint $table) {
            $table->dropIndex(['source_type', 'language']);
            $table->dropIndex(['dominant_emotion', 'emotion_intensity']);
            $table->dropIndex(['confidence_score', 'quality_rating']);
            $table->dropIndex(['requires_review']);
            $table->dropIndex(['analyzed_at']);
            $table->dropIndex(['trend_direction']);
            $table->dropIndex(['processing_model', 'model_version']);
            
            $table->json('details')->nullable();
            
            $table->dropColumn([
                'source_type',
                'source_reference',
                'source_text',
                'positive_score',
                'negative_score',
                'neutral_score',
                'mixed_score',
                'emotions',
                'dominant_emotion',
                'emotion_intensity',
                'language',
                'language_confidence',
                'processing_model',
                'model_version',
                'security_sentiment',
                'performance_sentiment',
                'usability_sentiment',
                'trust_sentiment',
                'keywords',
                'themes',
                'entities',
                'context',
                'text_length',
                'word_count',
                'sentence_count',
                'confidence_score',
                'quality_rating',
                'requires_review',
                'review_notes',
                'analyzed_at',
                'processing_time_ms',
                'baseline_score',
                'trend_change',
                'trend_direction',
                'external_id',
                'external_data',
                'tags',
                'analysis_details',
                'raw_response'
            ]);
        });
    }
};
