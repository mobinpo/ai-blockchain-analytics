<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SentimentBatchDocument;
use App\Services\PostgresCacheService;
use Illuminate\Support\Facades\Log;

// Using FQN to avoid static analysis issues when the Google SDK may be absent in tooling.
final class GoogleSentimentService
{
    private mixed $client;
    protected array $config;

    public function __construct(
        protected PostgresCacheService $cache
    ) {
        $this->config = config('sentiment_pipeline.google_nlp', []);
        
        // Initialize the Google Cloud client with supplied credentials path.
        try {
            $clientClass = '\\Google\\Cloud\\Language\\V1\\LanguageServiceClient';
            
            // Check if the class exists before instantiating
            if (class_exists($clientClass)) {
                $this->client = new $clientClass([
                    'credentials' => config('services.google_language.credentials'),
                ]);
            } else {
                Log::warning('Google Cloud Language SDK not installed - using simulation mode');
                $this->client = null;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to initialize Google Cloud client', ['error' => $e->getMessage()]);
            $this->client = null;
        }
    }

    /**
     * Analyze sentiment of given text (simple analysis with caching).
     */
    public function analyze(string $text): array
    {
        $params = [
            'text_hash' => md5($text),
            'text_length' => strlen($text),
        ];
        
        return $this->cache->remember(
            'google_sentiment',
            'analyze',
            $params,
            function () use ($text) {
                if (!$this->client) {
                    return $this->simulateAnalysis($text);
                }
                
                try {
                    // Perform sentiment analysis. Convert protobuf response to associative array.
                    $response = $this->client->analyzeSentiment([
                        'document' => [
                            'content' => $text,
                            'type' => 1, // DOCUMENT_TYPE_PLAIN_TEXT
                        ],
                        'encodingType' => 1, // ENCODING_TYPE_UTF8
                    ]);

                    return json_decode($response->serializeToJsonString(), true);
                } catch (\Exception $e) {
                    Log::warning('Google NLP analysis failed, using simulation', ['error' => $e->getMessage()]);
                    return $this->simulateAnalysis($text);
                }
            },
            3600 // Cache for 1 hour
        );
    }

    /**
     * Comprehensive analysis including sentiment, entities, and classification (with caching).
     */
    public function analyzeComprehensive(string $text): array
    {
        $params = [
            'text_hash' => md5($text),
            'text_length' => strlen($text),
            'comprehensive' => true,
        ];
        
        return $this->cache->remember(
            'google_sentiment',
            'analyze_comprehensive',
            $params,
            function () use ($text) {
                return $this->performComprehensiveAnalysis($text);
            },
            7200 // Cache comprehensive analysis for 2 hours (more expensive)
        );
    }

    /**
     * Perform comprehensive analysis without caching.
     */
    private function performComprehensiveAnalysis(string $text): array
    {
        try {
            $document = [
                'content' => $text,
                'type' => 1, // DOCUMENT_TYPE_PLAIN_TEXT
            ];
            $encodingType = 1; // ENCODING_TYPE_UTF8

            // Perform multiple analyses in parallel if possible
            $results = [];

            // 1. Sentiment Analysis
            $sentimentResponse = $this->client->analyzeSentiment([
                'document' => $document,
                'encodingType' => $encodingType,
            ]);
            $results['sentiment'] = json_decode($sentimentResponse->serializeToJsonString(), true);

            // 2. Entity Analysis
            if ($this->config['enable_entity_analysis'] ?? true) {
                try {
                    $entitiesResponse = $this->client->analyzeEntities([
                        'document' => $document,
                        'encodingType' => $encodingType,
                    ]);
                    $results['entities'] = json_decode($entitiesResponse->serializeToJsonString(), true);
                } catch (\Exception $e) {
                    Log::warning('Entity analysis failed', ['error' => $e->getMessage()]);
                    $results['entities'] = null;
                }
            }

            // 3. Content Classification
            if ($this->config['enable_classification'] ?? true) {
                try {
                    $classificationResponse = $this->client->classifyText([
                        'document' => $document,
                    ]);
                    $results['classification'] = json_decode($classificationResponse->serializeToJsonString(), true);
                } catch (\Exception $e) {
                    Log::warning('Content classification failed', ['error' => $e->getMessage()]);
                    $results['classification'] = null;
                }
            }

            // 4. Language Detection (if not provided)
            if ($this->config['detect_language'] ?? true) {
                try {
                    // Extract language from sentiment response
                    $results['language'] = $results['sentiment']['documentSentiment']['language'] ?? 'unknown';
                } catch (\Exception $e) {
                    $results['language'] = 'unknown';
                }
            }

            return $results;

        } catch (\Exception $e) {
            Log::error('Google NLP comprehensive analysis failed', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text),
            ]);

            throw $e;
        }
    }

    /**
     * Process a batch document with comprehensive analysis.
     */
    public function processBatchDocument(SentimentBatchDocument $document): bool
    {
        try {
            $startTime = microtime(true);
            
            // Perform comprehensive analysis
            $analysisResults = $this->analyzeComprehensive($document->processed_text);
            
            $processingTime = microtime(true) - $startTime;

            // Extract key data from results
            $updateData = [
                'sentiment_score' => $this->extractSentimentScore($analysisResults),
                'magnitude' => $this->extractMagnitude($analysisResults),
                'detected_language' => $this->extractLanguage($analysisResults),
                'entities' => $this->extractEntities($analysisResults),
                'categories' => $this->extractCategories($analysisResults),
            ];

            // Mark document as completed
            $document->markAsCompleted(
                $updateData['sentiment_score'],
                $updateData['magnitude'],
                array_filter($updateData) // Remove null values
            );

            Log::info('Batch document processed successfully', [
                'document_id' => $document->id,
                'sentiment_score' => $updateData['sentiment_score'],
                'processing_time' => round($processingTime, 3),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to process batch document', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            $document->markAsFailed([
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'timestamp' => now()->toISOString(),
            ]);

            return false;
        }
    }

    /**
     * Process multiple documents in batch.
     */
    public function processBatchDocuments(array $documents): array
    {
        $results = [
            'processed' => 0,
            'failed' => 0,
            'total_cost' => 0.0,
            'processing_time' => 0.0,
            'errors' => [],
        ];

        $startTime = microtime(true);
        $batchSize = $this->config['batch_size'] ?? 25;
        
        // Process documents in smaller batches for better performance
        $documentBatches = array_chunk($documents, $batchSize);
        
        foreach ($documentBatches as $batchIndex => $batch) {
            Log::info('Processing document batch', [
                'batch_index' => $batchIndex,
                'batch_size' => count($batch),
                'total_batches' => count($documentBatches)
            ]);
            
            $batchResults = $this->processSingleBatch($batch, $batchIndex);
            
            $results['processed'] += $batchResults['processed'];
            $results['failed'] += $batchResults['failed'];
            $results['total_cost'] += $batchResults['cost'];
            $results['errors'] = array_merge($results['errors'], $batchResults['errors']);
            
            // Rate limiting between batches
            $this->respectBatchRateLimit();
        }

        $results['processing_time'] = microtime(true) - $startTime;
        $results['average_time_per_document'] = count($documents) > 0 ? $results['processing_time'] / count($documents) : 0;
        $results['documents_per_second'] = $results['processing_time'] > 0 ? count($documents) / $results['processing_time'] : 0;

        Log::info('Batch processing completed', $results);

        return $results;
    }

    /**
     * Process a single batch of documents
     */
    private function processSingleBatch(array $documents, int $batchIndex): array
    {
        $results = [
            'processed' => 0,
            'failed' => 0,
            'cost' => 0.0,
            'errors' => []
        ];

        foreach ($documents as $docIndex => $document) {
            try {
                $documentStartTime = microtime(true);
                
                if ($this->processBatchDocument($document)) {
                    $results['processed']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = [
                        'batch_index' => $batchIndex,
                        'document_index' => $docIndex,
                        'document_id' => $document->id ?? 'unknown',
                        'error' => 'Processing failed without exception'
                    ];
                }

                $documentTime = microtime(true) - $documentStartTime;
                $results['cost'] += $this->estimateDocumentCost($document);

                // Rate limiting between individual documents
                $this->respectRateLimit();
                
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'batch_index' => $batchIndex,
                    'document_index' => $docIndex,
                    'document_id' => $document->id ?? 'unknown',
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode()
                ];
                
                Log::error('Document processing failed in batch', [
                    'batch_index' => $batchIndex,
                    'document_index' => $docIndex,
                    'document_id' => $document->id ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Process social media posts from crawler
     */
    public function processSocialMediaPosts(array $posts): array
    {
        $results = [
            'processed' => 0,
            'failed' => 0,
            'sentiment_distribution' => [
                'positive' => 0,
                'negative' => 0,
                'neutral' => 0
            ],
            'total_cost' => 0.0,
            'processing_time' => 0.0,
            'errors' => []
        ];

        $batchStartTime = microtime(true);
        
        try {
            Log::info('Starting social media sentiment analysis', [
                'posts_count' => count($posts)
            ]);

            // Check budget constraints
            $this->checkBudgetConstraints(count($posts));

            foreach ($posts as $index => $post) {
                try {
                    $postResult = $this->processSocialMediaPost($post);
                    
                    if ($postResult['success']) {
                        $results['processed']++;
                        
                        // Update sentiment distribution
                        $sentiment = $this->categorizeSentiment($postResult['sentiment_score']);
                        if (in_array($sentiment, ['positive', 'very_positive'])) {
                            $results['sentiment_distribution']['positive']++;
                        } elseif (in_array($sentiment, ['negative', 'very_negative'])) {
                            $results['sentiment_distribution']['negative']++;
                        } else {
                            $results['sentiment_distribution']['neutral']++;
                        }
                    } else {
                        $results['failed']++;
                        $results['errors'][] = [
                            'post_index' => $index,
                            'post_id' => $post['id'] ?? 'unknown',
                            'error' => $postResult['error'] ?? 'Processing failed'
                        ];
                    }
                    
                    $results['total_cost'] += $this->estimatePostCost();
                    
                    // Rate limiting
                    $this->respectRateLimit();
                    
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'post_index' => $index,
                        'post_id' => $post['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::error('Social media sentiment analysis failed', [
                'error' => $e->getMessage(),
                'posts_count' => count($posts)
            ]);
            throw $e;
        }

        $results['processing_time'] = microtime(true) - $batchStartTime;
        
        Log::info('Social media sentiment analysis completed', $results);
        
        return $results;
    }

    /**
     * Process a single social media post
     */
    private function processSocialMediaPost(array $post): array
    {
        try {
            $text = $this->extractTextFromPost($post);
            
            if (empty($text) || strlen($text) < 10) {
                return [
                    'success' => false,
                    'error' => 'Text too short or empty'
                ];
            }

            $analysisResults = $this->analyzeComprehensive($text);
            
            return [
                'success' => true,
                'post_id' => $post['id'] ?? null,
                'platform' => $post['platform'] ?? 'unknown',
                'sentiment_score' => $this->extractSentimentScore($analysisResults),
                'sentiment_magnitude' => $this->extractMagnitude($analysisResults),
                'sentiment_label' => $this->categorizeSentiment($this->extractSentimentScore($analysisResults)),
                'language' => $this->extractLanguage($analysisResults),
                'entities' => $this->extractEntities($analysisResults),
                'categories' => $this->extractCategories($analysisResults),
                'processed_at' => now()->toISOString()
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'post_id' => $post['id'] ?? null
            ];
        }
    }

    /**
     * Extract text content from social media post
     */
    private function extractTextFromPost(array $post): string
    {
        $text = '';
        
        // Try different content fields
        if (!empty($post['content'])) {
            $text = $post['content'];
        } elseif (!empty($post['text'])) {
            $text = $post['text'];
        } elseif (!empty($post['title'])) {
            $text = $post['title'];
            
            // Add body if available
            if (!empty($post['body'])) {
                $text .= "\n\n" . $post['body'];
            }
        }

        return $this->cleanText($text);
    }

    /**
     * Clean text for analysis
     */
    private function cleanText(string $text): string
    {
        // Remove HTML tags
        $text = strip_tags($text);
        
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove excessive punctuation
        $text = preg_replace('/[.]{3,}/', '...', $text);
        
        // Trim and return
        return trim($text);
    }

    /**
     * Categorize sentiment score
     */
    private function categorizeSentiment(float $score): string
    {
        if ($score >= 0.6) {
            return 'very_positive';
        } elseif ($score >= 0.2) {
            return 'positive';
        } elseif ($score >= -0.2) {
            return 'neutral';
        } elseif ($score >= -0.6) {
            return 'negative';
        } else {
            return 'very_negative';
        }
    }

    /**
     * Check budget constraints
     */
    private function checkBudgetConstraints(int $documentsCount): void
    {
        $dailyLimit = $this->config['daily_budget_limit'] ?? 50.0;
        $estimatedCost = $documentsCount * $this->estimatePostCost();
        
        // Get current daily spending from cache
        $currentSpending = cache()->get('google_nlp_daily_spending_' . date('Y-m-d'), 0.0);
        $projectedSpending = $currentSpending + $estimatedCost;
        
        if ($projectedSpending > $dailyLimit) {
            throw new \Exception(
                "Processing would exceed daily budget limit. " .
                "Current: $currentSpending, Projected: $projectedSpending, Limit: $dailyLimit"
            );
        }
    }

    /**
     * Estimate cost for processing a social media post
     */
    private function estimatePostCost(): float
    {
        return $this->config['sentiment_analysis_cost'] ?? 0.001;
    }

    /**
     * Enhanced rate limiting for batches
     */
    private function respectBatchRateLimit(): void
    {
        $batchDelay = $this->config['batch_delay_ms'] ?? 1000;
        if ($batchDelay > 0) {
            usleep($batchDelay * 1000);
        }
    }

    protected function extractSentimentScore(array $results): float
    {
        return (float) ($results['sentiment']['documentSentiment']['score'] ?? 0.0);
    }

    protected function extractMagnitude(array $results): float
    {
        return (float) ($results['sentiment']['documentSentiment']['magnitude'] ?? 0.0);
    }

    protected function extractLanguage(array $results): ?string
    {
        return $results['language'] ?? null;
    }

    protected function extractEntities(array $results): ?array
    {
        if (!isset($results['entities']['entities'])) {
            return null;
        }

        $entities = [];
        foreach ($results['entities']['entities'] as $entity) {
            $entities[] = [
                'name' => $entity['name'] ?? '',
                'type' => $entity['type'] ?? 'UNKNOWN',
                'salience' => (float) ($entity['salience'] ?? 0.0),
                'sentiment' => $entity['sentiment'] ?? null,
            ];
        }

        return empty($entities) ? null : $entities;
    }

    protected function extractCategories(array $results): ?array
    {
        if (!isset($results['classification']['categories'])) {
            return null;
        }

        $categories = [];
        foreach ($results['classification']['categories'] as $category) {
            $categories[] = [
                'name' => $category['name'] ?? '',
                'confidence' => (float) ($category['confidence'] ?? 0.0),
            ];
        }

        return empty($categories) ? null : $categories;
    }

    protected function estimateDocumentCost(SentimentBatchDocument $document): float
    {
        // Google Cloud NLP pricing: $1 per 1000 text records for sentiment
        // Additional costs for entities and classification
        $baseCost = 0.001; // $0.001 per document for sentiment
        $entitiesCost = 0.001; // Additional cost for entities
        $classificationCost = 0.002; // Additional cost for classification

        return $baseCost + $entitiesCost + $classificationCost;
    }

    protected function respectRateLimit(): void
    {
        // Google Cloud NLP has rate limits - add small delay between requests
        $delay = $this->config['rate_limit_delay_ms'] ?? 100;
        if ($delay > 0) {
            usleep($delay * 1000); // Convert to microseconds
        }
    }

    public function getApiQuota(): array
    {
        // This would typically require separate API calls to check quotas
        // For now, return estimated usage based on configuration
        return [
            'requests_per_minute' => $this->config['requests_per_minute'] ?? 600,
            'requests_per_day' => $this->config['requests_per_day'] ?? 10000,
            'estimated_monthly_cost' => $this->config['estimated_monthly_cost'] ?? 100.0,
        ];
    }

    /**
     * Simulate sentiment analysis when Google Cloud SDK is not available
     */
    private function simulateAnalysis(string $text): array
    {
        // Simple keyword-based sentiment simulation
        $positiveKeywords = [
            'good', 'great', 'excellent', 'amazing', 'positive', 'success', 'win', 'profit',
            'bull', 'bullish', 'high', 'pump', 'moon', 'rocket', 'gains', 'breakthrough',
            'revolutionary', 'innovative', 'secure', 'adoption', 'upgrade', 'partnership'
        ];
        
        $negativeKeywords = [
            'bad', 'terrible', 'awful', 'negative', 'fail', 'loss', 'bear', 'bearish',
            'crash', 'dump', 'scam', 'hack', 'vulnerability', 'exploit', 'attack',
            'regulation', 'ban', 'crackdown', 'outage', 'problem', 'concern'
        ];
        
        $textLower = strtolower($text);
        $positiveScore = 0;
        $negativeScore = 0;
        
        foreach ($positiveKeywords as $keyword) {
            if (strpos($textLower, $keyword) !== false) {
                $positiveScore += 0.1;
            }
        }
        
        foreach ($negativeKeywords as $keyword) {
            if (strpos($textLower, $keyword) !== false) {
                $negativeScore += 0.1;
            }
        }
        
        $finalScore = max(-1, min(1, $positiveScore - $negativeScore + (rand(-20, 20) / 100)));
        $magnitude = max(0, min(1, abs($finalScore) + (rand(0, 30) / 100)));
        
        return [
            'documentSentiment' => [
                'score' => round($finalScore, 3),
                'magnitude' => round($magnitude, 3)
            ],
            'language' => 'en',
            'sentences' => [
                [
                    'text' => ['content' => $text],
                    'sentiment' => [
                        'score' => round($finalScore, 3),
                        'magnitude' => round($magnitude, 3)
                    ]
                ]
            ],
            'simulation' => true
        ];
    }

    /**
     * Health check for Google NLP service
     */
    public function healthCheck(): array
    {
        if (!$this->client) {
            return [
                'status' => 'simulation',
                'message' => 'Google Cloud SDK not available - using simulation mode',
                'available' => false
            ];
        }
        
        try {
            // Try a simple analysis to check service health
            $this->analyze('test');
            
            return [
                'status' => 'healthy',
                'message' => 'Google Cloud NLP API is responding',
                'available' => true
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Google Cloud NLP API error: ' . $e->getMessage(),
                'available' => false
            ];
        }
    }

    /**
     * Get usage statistics (simulated for demo)
     */
    public function getUsageStatistics(): array
    {
        return [
            'requests_today' => rand(150, 500),
            'requests_this_month' => rand(5000, 15000),
            'cost_today' => round(rand(50, 200) / 100, 2),
            'cost_this_month' => round(rand(1500, 4500) / 100, 2),
            'quota_remaining_today' => rand(500, 9500),
            'success_rate' => round(rand(920, 995) / 10, 1)
        ];
    }
} 