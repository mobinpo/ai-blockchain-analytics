<?php

namespace App\Services;

use App\Models\ContractCache;
use App\Models\ApiUsageTracking;
use App\Models\CacheWarmingQueue;
use App\Services\BlockchainExplorerFactory;
use App\Services\ChainExplorerManager;
use App\Services\SolidityCleanerService;
use App\Services\CacheOptimizationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SourceCodeFetchingService
{
    private BlockchainExplorerFactory $explorerFactory;
    private ChainExplorerManager $chainManager;
    private int $cacheTtl;
    private bool $enableDatabaseCache;
    private ?SolidityCleanerService $cleanerService;
    private CacheOptimizationService $cacheOptimizer;

    public function __construct(
        BlockchainExplorerFactory $explorerFactory,
        ?ChainExplorerManager $chainManager = null,
        int $cacheTtl = 3600, // 1 hour default
        bool $enableDatabaseCache = true,
        ?SolidityCleanerService $cleanerService = null,
        ?CacheOptimizationService $cacheOptimizer = null
    ) {
        $this->explorerFactory = $explorerFactory;
        $this->chainManager = $chainManager ?? new ChainExplorerManager();
        $this->cacheTtl = $cacheTtl;
        $this->enableDatabaseCache = $enableDatabaseCache;
        $this->cleanerService = $cleanerService;
        $this->cacheOptimizer = $cacheOptimizer ?? new CacheOptimizationService();
    }

    /**
     * Fetch verified Solidity source code for a contract (Cache-First Strategy)
     */
    public function fetchSourceCode(string $contractAddress, string $network = 'ethereum', bool $forceRefresh = false): array
    {
        $contractAddress = $this->normalizeAddress($contractAddress);
        $startTime = microtime(true);

        // Step 1: Try optimized PostgreSQL cache first (with stale fallback)
        if (!$forceRefresh && $this->enableDatabaseCache) {
            $cached = $this->cacheOptimizer->getCachedDataWithFallback($network, $contractAddress, 'source');
            if ($cached) {
                Log::debug("Source code found in optimized cache", [
                    'address' => $contractAddress,
                    'network' => $network,
                    'cache_quality' => $cached['cache_quality_score'] ?? 'unknown',
                    'api_fetch_count' => $cached['api_fetch_count'] ?? 0,
                    'stale_used' => isset($cached['stale_extension_count']) && $cached['stale_extension_count'] > 0
                ]);
                
                // Update memory cache for faster subsequent access
                $cacheKey = "source_code_{$network}_{$contractAddress}";
                Cache::put($cacheKey, $cached, $this->cacheTtl);
                
                return $cached;
            }
        }

        // Step 2: Try memory cache (faster but less persistent)
        if (!$forceRefresh) {
            $cacheKey = "source_code_{$network}_{$contractAddress}";
            if (Cache::has($cacheKey)) {
                Log::debug("Source code found in memory cache", [
                    'address' => $contractAddress,
                    'network' => $network
                ]);
                return Cache::get($cacheKey);
            }
        }

        // Step 3: Fetch from blockchain explorer using ChainExplorerManager (last resort)
        try {
            Log::info("Fetching source code from blockchain explorer", [
                'address' => $contractAddress,
                'network' => $network,
                'force_refresh' => $forceRefresh
            ]);

            $apiStartTime = microtime(true);
            $sourceData = $this->chainManager->executeWithRetry($network, function($explorer) use ($contractAddress) {
                return $explorer->getContractSource($contractAddress);
            });
            $apiEndTime = microtime(true);
            $apiResponseTime = round(($apiEndTime - $apiStartTime) * 1000);

            // Get the explorer that was actually used
            $explorer = $this->chainManager->getExplorer($network);

            // Record successful API usage
            ApiUsageTracking::recordSuccess(
                $network,
                $explorer->getName(),
                'contract/getsourcecode',
                $apiResponseTime,
                $contractAddress,
                ['force_refresh' => $forceRefresh]
            );
            
            if (empty($sourceData) || !isset($sourceData['source_code'])) {
                throw new \Exception('Contract source code not available or not verified');
            }

            // Process and enrich the source data
            $processedData = $this->processSourceData($sourceData, $contractAddress, $network, $explorer->getName());

            // Determine cache priority based on contract characteristics
            $priority = $this->determineCachePriority($processedData);
            
            // Calculate optimal TTL to minimize API calls
            $optimalTTL = $this->cacheOptimizer->calculateOptimalTTL($processedData, 'source');

            // Cache the results using enhanced caching system
            $this->cacheSourceDataEnhanced($processedData, $contractAddress, $network, $priority, $optimalTTL);

            $totalTime = round((microtime(true) - $startTime) * 1000);

            Log::info("Successfully fetched and cached source code", [
                'address' => $contractAddress,
                'network' => $network,
                'contracts_count' => count($processedData['contracts']),
                'total_lines' => $processedData['statistics']['total_lines'],
                'cache_priority' => $priority,
                'total_time_ms' => $totalTime,
                'api_time_ms' => $apiResponseTime
            ]);

            return $processedData;

        } catch (\Exception $e) {
            // Record failed API usage
            try {
                $explorer = $this->chainManager->getExplorer($network);
                ApiUsageTracking::recordFailure(
                    $network,
                    $explorer->getName(),
                    'contract/getsourcecode',
                    get_class($e),
                    $e->getMessage(),
                    isset($apiResponseTime) ? $apiResponseTime : null,
                    $contractAddress
                );
            } catch (\Exception $explorerException) {
                // Log explorer manager error but continue with the original error
                Log::warning("Failed to get explorer for error logging: {$explorerException->getMessage()}");
            }

            // Record error in cache if entry exists
            if ($this->enableDatabaseCache) {
                $existingCache = ContractCache::where('network', $network)
                    ->where('contract_address', $contractAddress)
                    ->where('cache_type', 'source')
                    ->first();
                    
                if ($existingCache) {
                    $existingCache->recordError($e->getMessage());
                }
            }

            Log::error("Failed to fetch source code", [
                'address' => $contractAddress,
                'network' => $network,
                'error' => $e->getMessage(),
                'total_time_ms' => round((microtime(true) - $startTime) * 1000)
            ]);
            
            throw $e;
        }
    }

    /**
     * Fetch source code for multiple contracts in batch
     */
    public function fetchMultipleSourceCodes(array $contracts, string $network = 'ethereum'): array
    {
        $results = [];
        $errors = [];

        foreach ($contracts as $address) {
            try {
                $results[$address] = $this->fetchSourceCode($address, $network);
            } catch (\Exception $e) {
                $errors[$address] = $e->getMessage();
                Log::warning("Failed to fetch source for contract in batch", [
                    'address' => $address,
                    'network' => $network,
                    'error' => $e->getMessage()
                ]);
            }

            // Add delay to respect rate limits
            usleep(200000); // 200ms delay
        }

        Log::info("Batch source code fetch completed", [
            'network' => $network,
            'total_contracts' => count($contracts),
            'successful' => count($results),
            'failed' => count($errors)
        ]);

        return [
            'results' => $results,
            'errors' => $errors,
            'summary' => [
                'total' => count($contracts),
                'successful' => count($results),
                'failed' => count($errors),
                'success_rate' => count($contracts) > 0 ? (count($results) / count($contracts)) * 100 : 0
            ]
        ];
    }

    /**
     * Check if contract source code is available and verified
     */
    public function isSourceCodeAvailable(string $contractAddress, string $network = 'ethereum'): bool
    {
        try {
            return $this->chainManager->executeWithRetry($network, function($explorer) use ($contractAddress) {
                return $explorer->isContractVerified($contractAddress);
            });
        } catch (\Exception $e) {
            Log::warning("Failed to check source code availability", [
                'address' => $contractAddress,
                'network' => $network,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get source code statistics and metadata
     */
    public function getSourceCodeInfo(string $contractAddress, string $network = 'ethereum'): array
    {
        $sourceData = $this->fetchSourceCode($contractAddress, $network);
        
        return [
            'address' => $contractAddress,
            'network' => $network,
            'is_verified' => true,
            'compiler_version' => $sourceData['compiler_version'] ?? 'Unknown',
            'optimization_enabled' => $sourceData['optimization_enabled'] ?? false,
            'optimization_runs' => $sourceData['optimization_runs'] ?? 0,
            'contracts_count' => count($sourceData['contracts']),
            'main_contract' => $sourceData['main_contract'] ?? null,
            'license' => $sourceData['license'] ?? 'Unknown',
            'statistics' => $sourceData['statistics'],
            'last_updated' => $sourceData['last_updated'] ?? null,
            'source_available' => !empty($sourceData['contracts'])
        ];
    }

    /**
     * Search for specific patterns in source code
     */
    public function searchInSourceCode(string $contractAddress, string $network, array $patterns): array
    {
        $sourceData = $this->fetchSourceCode($contractAddress, $network);
        $matches = [];

        foreach ($sourceData['contracts'] as $contractName => $contractData) {
            $sourceCode = $contractData['source'];
            
            foreach ($patterns as $patternName => $pattern) {
                if (preg_match_all($pattern, $sourceCode, $patternMatches, PREG_OFFSET_CAPTURE)) {
                    foreach ($patternMatches[0] as $match) {
                        $matches[] = [
                            'contract' => $contractName,
                            'pattern' => $patternName,
                            'match' => $match[0],
                            'position' => $match[1],
                            'line' => substr_count(substr($sourceCode, 0, $match[1]), "\n") + 1
                        ];
                    }
                }
            }
        }

        return [
            'address' => $contractAddress,
            'network' => $network,
            'patterns_searched' => array_keys($patterns),
            'total_matches' => count($matches),
            'matches' => $matches
        ];
    }

    /**
     * Get source code with syntax highlighting preparation
     */
    public function getSourceCodeForDisplay(string $contractAddress, string $network = 'ethereum'): array
    {
        $sourceData = $this->fetchSourceCode($contractAddress, $network);
        
        $displayData = [
            'address' => $contractAddress,
            'network' => $network,
            'contracts' => []
        ];

        foreach ($sourceData['contracts'] as $contractName => $contractData) {
            $lines = explode("\n", $contractData['source']);
            
            $displayData['contracts'][$contractName] = [
                'name' => $contractName,
                'lines' => array_map(function($line, $index) {
                    return [
                        'number' => $index + 1,
                        'content' => $line,
                        'trimmed' => trim($line)
                    ];
                }, $lines, array_keys($lines)),
                'line_count' => count($lines),
                'is_main_contract' => $contractName === ($sourceData['main_contract'] ?? null)
            ];
        }

        return $displayData;
    }

    /**
     * Clear cache for specific contract
     */
    public function clearCache(string $contractAddress, string $network = 'ethereum'): bool
    {
        $contractAddress = $this->normalizeAddress($contractAddress);
        $cacheKey = "source_code_{$network}_{$contractAddress}";

        // Clear memory cache
        Cache::forget($cacheKey);

        // Clear database cache
        if ($this->enableDatabaseCache) {
            ContractCache::where('contract_address', $contractAddress)
                ->where('network', $network)
                ->where('cache_type', 'source')
                ->delete();
        }

        Log::info("Cleared source code cache", [
            'address' => $contractAddress,
            'network' => $network
        ]);

        return true;
    }

    /**
     * Process raw source data from explorer
     */
    private function processSourceData(array $rawData, string $address, string $network, string $explorer): array
    {
        // The rawData already comes processed from parseSourceCodeResponse
        // Extract the necessary information 
        $sourceCode = $rawData['source_code'] ?? '';
        $parsedSources = $rawData['parsed_sources'] ?? [];
        
        // If no parsed sources, create from raw source code
        if (empty($parsedSources) && !empty($sourceCode)) {
            $contractName = $rawData['contract_name'] ?? 'Unknown';
            $parsedSources = [
                $contractName => [
                    'name' => $contractName,
                    'source' => $sourceCode
                ]
            ];
        }

        // Calculate statistics
        $statistics = $this->calculateSourceStatistics($parsedSources);

        return [
            'address' => $address,
            'network' => $network,
            'explorer' => $explorer,
            'contracts' => $parsedSources,
            'main_contract' => $this->detectMainContract($parsedSources, $rawData['contract_name'] ?? null),
            'compiler_version' => $rawData['compiler_version'] ?? 'Unknown',
            'optimization_enabled' => $rawData['optimization_used'] ?? false,
            'optimization_runs' => $rawData['optimization_runs'] ?? 0,
            'license' => $rawData['license_type'] ?? $this->extractLicense($parsedSources),
            'constructor_arguments' => $rawData['constructor_arguments'] ?? '',
            'library_used' => $rawData['library'] ?? '',
            'is_verified' => $rawData['is_verified'] ?? true,
            'proxy' => $rawData['proxy'] ?? false,
            'implementation' => $rawData['implementation'] ?? null,
            'evm_version' => $rawData['evm_version'] ?? 'default',
            'abi' => $rawData['abi'] ?? null,
            'statistics' => $statistics,
            'raw_data' => $rawData,
            'fetched_at' => $rawData['fetched_at'] ?? now()->toISOString(),
            'last_updated' => now()->toISOString()
        ];
    }

    /**
     * Check if source code is in JSON format (multi-file)
     */
    private function isJsonSourceCode(string $sourceCode): bool
    {
        return Str::startsWith(trim($sourceCode), '{') && Str::endsWith(trim($sourceCode), '}');
    }

    /**
     * Parse JSON source code format
     */
    private function parseJsonSourceCode(string $jsonSource): array
    {
        try {
            $decoded = json_decode($jsonSource, true);
            $contracts = [];

            if (isset($decoded['sources'])) {
                // Standard JSON format
                foreach ($decoded['sources'] as $filename => $fileData) {
                    $contractName = basename($filename, '.sol');
                    $contracts[$contractName] = [
                        'name' => $contractName,
                        'source' => $fileData['content'] ?? '',
                        'filename' => $filename
                    ];
                }
            } else {
                // Alternative JSON format
                foreach ($decoded as $filename => $content) {
                    if (is_string($content)) {
                        $contractName = basename($filename, '.sol');
                        $contracts[$contractName] = [
                            'name' => $contractName,
                            'source' => $content,
                            'filename' => $filename
                        ];
                    }
                }
            }

            return $contracts;
        } catch (\Exception $e) {
            Log::warning('Failed to parse JSON source code, treating as single contract', [
                'error' => $e->getMessage()
            ]);
            return ['Unknown' => ['name' => 'Unknown', 'source' => $jsonSource]];
        }
    }

    /**
     * Calculate source code statistics
     */
    private function calculateSourceStatistics(array $contracts): array
    {
        $totalLines = 0;
        $totalChars = 0;
        $totalContracts = count($contracts);
        $functions = [];
        $events = [];
        $modifiers = [];

        foreach ($contracts as $contract) {
            $source = $contract['source'];
            $lines = explode("\n", $source);
            $totalLines += count($lines);
            $totalChars += strlen($source);

            // Count functions, events, modifiers
            preg_match_all('/function\s+(\w+)/', $source, $functionMatches);
            preg_match_all('/event\s+(\w+)/', $source, $eventMatches);
            preg_match_all('/modifier\s+(\w+)/', $source, $modifierMatches);

            $functions = array_merge($functions, $functionMatches[1] ?? []);
            $events = array_merge($events, $eventMatches[1] ?? []);
            $modifiers = array_merge($modifiers, $modifierMatches[1] ?? []);
        }

        return [
            'total_lines' => $totalLines,
            'total_characters' => $totalChars,
            'total_contracts' => $totalContracts,
            'functions_count' => count($functions),
            'events_count' => count($events),
            'modifiers_count' => count($modifiers),
            'functions' => array_unique($functions),
            'events' => array_unique($events),
            'modifiers' => array_unique($modifiers)
        ];
    }

    /**
     * Detect main contract from multiple contracts
     */
    private function detectMainContract(array $contracts, ?string $hintName = null): ?string
    {
        if ($hintName && isset($contracts[$hintName])) {
            return $hintName;
        }

        // Find the largest contract (most likely the main one)
        $largest = null;
        $maxSize = 0;

        foreach ($contracts as $name => $contract) {
            $size = strlen($contract['source']);
            if ($size > $maxSize) {
                $maxSize = $size;
                $largest = $name;
            }
        }

        return $largest;
    }

    /**
     * Extract license from source code
     */
    private function extractLicense(array $contracts): string
    {
        foreach ($contracts as $contract) {
            if (preg_match('/SPDX-License-Identifier:\s*([^\s\*\/]+)/', $contract['source'], $matches)) {
                return trim($matches[1]);
            }
        }
        return 'Unknown';
    }

    /**
     * Determine cache priority based on contract characteristics
     */
    private function determineCachePriority(array $data): string
    {
        $priority = 'medium'; // Default
        
        // High priority for proxy contracts (more likely to be updated)
        if ($data['proxy'] ?? false) {
            $priority = 'high';
        }
        
        // High priority for large contracts (expensive to re-fetch)
        $totalLines = $data['statistics']['total_lines'] ?? 0;
        if ($totalLines > 1000) {
            $priority = 'high';
        }
        
        // Critical priority for very large or complex contracts
        if ($totalLines > 5000 || count($data['contracts'] ?? []) > 10) {
            $priority = 'critical';
        }
        
        // Lower priority for small, simple contracts
        if ($totalLines < 100 && count($data['contracts'] ?? []) === 1) {
            $priority = 'low';
        }
        
        return $priority;
    }

    /**
     * Enhanced cache storage with analytics and quality scoring
     */
    private function cacheSourceDataEnhanced(array $data, string $address, string $network, string $priority, int $ttl = null): void
    {
        $cacheKey = "source_code_{$network}_{$address}";
        $effectiveTTL = $ttl ?? $this->cacheTtl;
        
        // Memory cache (fast access) - use shorter TTL for memory
        $memoryTTL = min($effectiveTTL, 3600); // Max 1 hour in memory
        Cache::put($cacheKey, $data, $memoryTTL);

        // Enhanced PostgreSQL cache
        if ($this->enableDatabaseCache) {
            $cacheData = [
                'contract_name' => $data['main_contract'] ?? 'Unknown',
                'compiler_version' => $data['compiler_version'] ?? 'Unknown',
                'optimization_used' => $data['optimization_enabled'] ?? false,
                'optimization_runs' => $data['optimization_runs'] ?? 0,
                'constructor_arguments' => $data['constructor_arguments'] ?? '',
                'evm_version' => $data['evm_version'] ?? 'default',
                'library' => $data['library_used'] ?? '',
                'license_type' => $data['license'] ?? 'Unknown',
                'proxy' => $data['proxy'] ?? false,
                'implementation' => $data['implementation'] ?? null,
                'source_code' => $data['raw_data']['source_code'] ?? '',
                'parsed_sources' => $data['contracts'] ?? [],
                'abi' => $data['abi'] ?? null,
                'is_verified' => $data['is_verified'] ?? true,
                'source_complete' => !empty($data['contracts']),
                'abi_complete' => !empty($data['abi']),
                'source_file_count' => count($data['contracts'] ?? []),
                'source_line_count' => $data['statistics']['total_lines'] ?? 0,
                'metadata' => $data
            ];

            ContractCache::storeContractDataEnhanced(
                $network,
                $address,
                'source',
                $cacheData,
                $effectiveTTL, // Use optimal TTL
                $priority,
                true // fromApi = true
            );
        }
    }

    /**
     * Queue contract for cache warming
     */
    public function queueForCacheWarming(
        string $contractAddress, 
        string $network = 'ethereum', 
        string $priority = 'medium'
    ): bool {
        try {
            CacheWarmingQueue::queueContract(
                $network,
                $contractAddress,
                CacheWarmingQueue::CACHE_TYPE_SOURCE,
                $priority
            );
            
            Log::info("Contract queued for cache warming", [
                'address' => $contractAddress,
                'network' => $network,
                'priority' => $priority
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to queue contract for cache warming", [
                'address' => $contractAddress,
                'network' => $network,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Batch queue contracts for cache warming
     */
    public function batchQueueForCacheWarming(
        array $contractAddresses,
        string $network = 'ethereum',
        string $priority = 'medium'
    ): array {
        $results = ['queued' => 0, 'failed' => 0, 'errors' => []];
        
        foreach ($contractAddresses as $address) {
            if ($this->queueForCacheWarming($address, $network, $priority)) {
                $results['queued']++;
            } else {
                $results['failed']++;
                $results['errors'][] = $address;
            }
        }
        
        Log::info("Batch cache warming queue completed", [
            'network' => $network,
            'total_contracts' => count($contractAddresses),
            'queued' => $results['queued'],
            'failed' => $results['failed']
        ]);
        
        return $results;
    }

    /**
     * Get comprehensive cache statistics
     */
    public function getComprehensiveCacheStatistics(): array
    {
        if (!$this->enableDatabaseCache) {
            return ['database_cache_disabled' => true];
        }

        // Get basic cache stats
        $basicStats = ContractCache::getCacheEfficiencyStats();
        
        // Get analytics data
        $analytics = class_exists('App\Models\ContractCacheAnalytics') 
            ? \App\Models\ContractCacheAnalytics::getCurrentPerformanceSummary()
            : ['analytics_disabled' => true];
            
        // Get queue stats
        $queueStats = CacheWarmingQueue::getQueueStats();
        
        // Get API usage stats for last 24 hours
        $apiStats = ApiUsageTracking::getUsageStats(
            now()->subDay(),
            now()
        );

        return [
            'cache_efficiency' => $basicStats,
            'performance_analytics' => $analytics,
            'warming_queue' => $queueStats,
            'api_usage_24h' => $apiStats,
            'system_health' => [
                'cache_hit_rate' => $analytics['today']['hit_rate'] ?? 0,
                'api_calls_saved_today' => $analytics['today']['api_calls_saved'] ?? 0,
                'pending_warming_jobs' => $queueStats['pending'] ?? 0,
                'recent_api_success_rate' => $apiStats['success_rate'] ?? 0
            ]
        ];
    }

    /**
     * Get source code cleaned and optimized for AI prompt input
     */
    public function getCleanedSourceCodeForPrompt(string $contractAddress, string $network = 'ethereum', array $cleaningOptions = []): array
    {
        // First fetch the source code
        $sourceData = $this->fetchSourceCode($contractAddress, $network);
        
        if (!$this->cleanerService) {
            return [
                'error' => 'Solidity cleaner service not available',
                'original_data' => $sourceData
            ];
        }

        $results = [];
        $totalOriginalSize = 0;
        $totalCleanedSize = 0;

        // Clean each contract's source code
        foreach ($sourceData['contracts'] as $contractName => $contractData) {
            $sourceCode = $contractData['source'] ?? '';
            
            if (empty($sourceCode)) {
                continue;
            }

            $cleaningResult = $this->cleanerService->cleanForPrompt($sourceCode);
            
            $results[$contractName] = [
                'original_source' => $sourceCode,
                'cleaned_source' => $cleaningResult['cleaned_code'],
                'statistics' => $cleaningResult['statistics'],
                'metadata' => $cleaningResult['metadata']
            ];

            $totalOriginalSize += $cleaningResult['statistics']['original_size'];
            $totalCleanedSize += $cleaningResult['statistics']['cleaned_size'];
        }

        // Calculate overall statistics
        $overallStats = [
            'total_contracts' => count($results),
            'total_original_size' => $totalOriginalSize,
            'total_cleaned_size' => $totalCleanedSize,
            'total_size_reduction' => $totalOriginalSize - $totalCleanedSize,
            'overall_reduction_percent' => $totalOriginalSize > 0 ? round((($totalOriginalSize - $totalCleanedSize) / $totalOriginalSize) * 100, 2) : 0,
            'prompt_ready' => true
        ];

        Log::info('Source code cleaned for prompt input', [
            'address' => $contractAddress,
            'network' => $network,
            'contracts_processed' => count($results),
            'size_reduction_percent' => $overallStats['overall_reduction_percent']
        ]);

        return [
            'address' => $contractAddress,
            'network' => $network,
            'original_data' => $sourceData,
            'cleaned_contracts' => $results,
            'overall_statistics' => $overallStats,
            'main_contract_cleaned' => $results[$sourceData['main_contract']] ?? null,
            'prompt_optimized' => true
        ];
    }

    /**
     * Get the main contract's cleaned source code as a string ready for prompt input
     */
    public function getMainContractCleanedForPrompt(string $contractAddress, string $network = 'ethereum'): array
    {
        $cleanedData = $this->getCleanedSourceCodeForPrompt($contractAddress, $network);
        
        if (isset($cleanedData['error'])) {
            return $cleanedData;
        }

        $mainContractData = $cleanedData['main_contract_cleaned'];
        
        if (!$mainContractData) {
            return [
                'error' => 'Main contract not found or could not be cleaned',
                'available_contracts' => array_keys($cleanedData['cleaned_contracts'])
            ];
        }

        return [
            'address' => $contractAddress,
            'network' => $network,
            'cleaned_source' => $mainContractData['cleaned_source'],
            'statistics' => $mainContractData['statistics'],
            'metadata' => $mainContractData['metadata'],
            'prompt_ready' => true,
            'size_reduction_percent' => $mainContractData['statistics']['size_reduction_percent']
        ];
    }

    /**
     * Get multiple contracts cleaned for batch prompt processing
     */
    public function getBatchCleanedSourceForPrompt(array $contractAddresses, string $network = 'ethereum'): array
    {
        $results = [];
        $errors = [];
        $overallStats = [
            'total_processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'total_size_reduction' => 0,
            'average_reduction_percent' => 0
        ];

        foreach ($contractAddresses as $address) {
            try {
                $result = $this->getMainContractCleanedForPrompt($address, $network);
                
                if (isset($result['error'])) {
                    $errors[$address] = $result['error'];
                    $overallStats['failed']++;
                } else {
                    $results[$address] = $result;
                    $overallStats['successful']++;
                    $overallStats['total_size_reduction'] += $result['statistics']['size_reduction_percent'];
                }
                
                $overallStats['total_processed']++;
                
                // Rate limiting
                usleep(200000); // 200ms delay
                
            } catch (\Exception $e) {
                $errors[$address] = $e->getMessage();
                $overallStats['failed']++;
                $overallStats['total_processed']++;
            }
        }

        // Calculate average reduction
        if ($overallStats['successful'] > 0) {
            $overallStats['average_reduction_percent'] = round($overallStats['total_size_reduction'] / $overallStats['successful'], 2);
        }

        return [
            'results' => $results,
            'errors' => $errors,
            'statistics' => $overallStats,
            'network' => $network,
            'batch_processed' => true
        ];
    }

    /**
     * Get from database cache (legacy method for compatibility)
     */
    private function getFromDatabaseCache(string $address, string $network): ?array
    {
        return ContractCache::getCachedData($network, $address, 'source');
    }

    /**
     * Normalize contract address
     */
    private function normalizeAddress(string $address): string
    {
        return Str::lower(trim($address));
    }

    /**
     * Get cache statistics (legacy method - use getComprehensiveCacheStatistics for full data)
     */
    public function getCacheStatistics(): array
    {
        if (!$this->enableDatabaseCache) {
            return ['database_cache_disabled' => true];
        }

        $stats = ContractCache::getCacheEfficiencyStats();
        
        return [
            'total_cached' => $stats['total_entries'],
            'active_cached' => $stats['active_entries'],
            'expired_cached' => $stats['expired_entries'],
            'cache_hit_rate' => 100, // Will be calculated from analytics
            'networks_cached' => [], // Legacy field
            'cache_ttl_hours' => $this->cacheTtl / 3600,
            'average_quality_score' => $stats['average_quality_score'],
            'api_calls_saved' => $stats['total_api_calls_saved']
        ];
    }
}