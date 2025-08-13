<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Explorers\AbstractBlockchainExplorer;
use App\Services\BlockchainExplorerFactory;
use App\Models\ContractCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Carbon\Carbon;

/**
 * Service to fetch verified Solidity source code via Etherscan/BscScan APIs
 * 
 * Features:
 * - Multi-chain support (Ethereum, BSC, Polygon)
 * - Comprehensive caching with TTL optimization
 * - Contract verification validation
 * - Source code parsing and analysis
 * - Batch processing capabilities
 * - Rate limiting and retry logic
 */
final class VerifiedSourceFetcher
{
    private BlockchainExplorerFactory $explorerFactory;
    private const CACHE_TTL = 86400; // 24 hours
    private const RATE_LIMIT_DELAY = 200; // milliseconds
    private const MAX_RETRY_ATTEMPTS = 3;

    // Supported networks and their explorers
    private const SUPPORTED_NETWORKS = [
        'ethereum' => 'etherscan',
        'bsc' => 'bscscan', 
        'polygon' => 'polygonscan'
    ];

    public function __construct(BlockchainExplorerFactory $explorerFactory)
    {
        $this->explorerFactory = $explorerFactory;
    }

    /**
     * Fetch verified source code for a single contract
     */
    public function fetchVerifiedSource(
        string $contractAddress,
        string $network = 'ethereum',
        bool $forceRefresh = false
    ): array {
        $this->validateInput($contractAddress, $network);
        
        $cacheKey = $this->getCacheKey($contractAddress, $network);
        
        // Check cache first unless forcing refresh
        if (!$forceRefresh && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if ($this->isCacheValid($cached)) {
                Log::debug('Source code retrieved from cache', [
                    'contract' => $contractAddress,
                    'network' => $network
                ]);
                return $cached;
            }
        }

        // Apply rate limiting
        $this->applyRateLimit($network);

        try {
            Log::info('Fetching verified source code', [
                'contract' => $contractAddress,
                'network' => $network,
                'force_refresh' => $forceRefresh
            ]);

            $explorer = $this->explorerFactory->getExplorer($network);
            
            // First check if contract is verified
            if (!$this->isContractVerified($contractAddress, $explorer)) {
                throw new InvalidArgumentException(
                    "Contract {$contractAddress} is not verified on {$network}"
                );
            }

            // Fetch source code with retry logic
            $sourceData = $this->fetchWithRetry($explorer, $contractAddress);
            
            // Process and enhance the data
            $processedData = $this->processSourceData($sourceData, $contractAddress, $network);
            
            // Cache the result
            $this->cacheResult($cacheKey, $processedData);
            
            // Store in database for persistence
            $this->storeInDatabase($processedData);
            
            Log::info('Successfully fetched and cached verified source', [
                'contract' => $contractAddress,
                'network' => $network,
                'files_count' => count($processedData['source_files']),
                'total_lines' => $processedData['statistics']['total_lines']
            ]);

            return $processedData;

        } catch (\Exception $e) {
            Log::error('Failed to fetch verified source code', [
                'contract' => $contractAddress,
                'network' => $network,
                'error' => $e->getMessage()
            ]);
            
            // Try to return stale cache if available
            $staleData = Cache::get($cacheKey . '_stale');
            if ($staleData) {
                Log::warning('Returning stale cached data', [
                    'contract' => $contractAddress,
                    'network' => $network
                ]);
                return array_merge($staleData, ['is_stale' => true]);
            }
            
            throw $e;
        }
    }

    /**
     * Check if a contract is verified on a specific network
     */
    public function isVerified(string $contractAddress, string $network = 'ethereum'): bool
    {
        $this->validateInput($contractAddress, $network);
        
        try {
            $explorer = $this->explorerFactory->getExplorer($network);
            return $this->isContractVerified($contractAddress, $explorer);
        } catch (\Exception $e) {
            Log::debug('Verification check failed', [
                'contract' => $contractAddress,
                'network' => $network,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Find verified contract across multiple networks
     */
    public function findVerifiedAcrossChains(string $contractAddress): array
    {
        $this->validateContractAddress($contractAddress);
        
        $results = [];
        
        foreach (self::SUPPORTED_NETWORKS as $network => $explorer) {
            try {
                $isVerified = $this->isVerified($contractAddress, $network);
                $explorerInstance = $this->explorerFactory->getExplorer($network);
                
                $results[$network] = [
                    'verified' => $isVerified,
                    'explorer' => $explorer,
                    'url' => $explorerInstance->getContractUrl($contractAddress)
                ];
                
                if ($isVerified) {
                    Log::info('Contract found verified', [
                        'contract' => $contractAddress,
                        'network' => $network
                    ]);
                }
            } catch (\Exception $e) {
                $results[$network] = [
                    'verified' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'contract_address' => $contractAddress,
            'networks' => $results,
            'verified_on' => collect($results)->where('verified', true)->keys()->toArray(),
            'total_networks_checked' => count($results)
        ];
    }

    /**
     * Batch fetch verified source code for multiple contracts
     */
    public function batchFetchVerified(
        array $contractAddresses,
        string $network = 'ethereum',
        bool $skipUnverified = true
    ): array {
        $this->validateNetwork($network);
        
        $results = [];
        $errors = [];
        $skipped = [];
        $total = count($contractAddresses);
        
        Log::info('Starting batch verified source fetch', [
            'total_contracts' => $total,
            'network' => $network,
            'skip_unverified' => $skipUnverified
        ]);

        foreach ($contractAddresses as $index => $address) {
            try {
                $this->validateContractAddress($address);
                
                // Check verification first if skipping unverified
                if ($skipUnverified && !$this->isVerified($address, $network)) {
                    $skipped[] = $address;
                    Log::debug('Skipped unverified contract', ['contract' => $address]);
                    continue;
                }
                
                $result = $this->fetchVerifiedSource($address, $network);
                $results[$address] = $result;
                
            } catch (InvalidArgumentException $e) {
                if ($skipUnverified && str_contains($e->getMessage(), 'not verified')) {
                    $skipped[] = $address;
                } else {
                    $errors[$address] = $e->getMessage();
                }
            } catch (\Exception $e) {
                $errors[$address] = $e->getMessage();
                Log::error('Batch fetch error', [
                    'contract' => $address,
                    'error' => $e->getMessage()
                ]);
            }

            // Rate limiting between requests
            if ($index < $total - 1) {
                usleep(self::RATE_LIMIT_DELAY * 1000);
            }
        }

        $successful = count($results);
        $failed = count($errors);
        $skippedCount = count($skipped);

        Log::info('Batch fetch completed', [
            'successful' => $successful,
            'failed' => $failed,
            'skipped' => $skippedCount,
            'total' => $total
        ]);

        return [
            'successful' => $results,
            'failed' => $errors,
            'skipped' => $skipped,
            'summary' => [
                'total' => $total,
                'successful' => $successful,
                'failed' => $failed,
                'skipped' => $skippedCount,
                'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0
            ]
        ];
    }

    /**
     * Get contract metadata without full source code
     */
    public function getContractMetadata(string $contractAddress, string $network = 'ethereum'): array
    {
        $this->validateInput($contractAddress, $network);
        
        $explorer = $this->explorerFactory->getExplorer($network);
        $sourceData = $explorer->getContractSource($contractAddress);
        
        return [
            'contract_address' => $contractAddress,
            'network' => $network,
            'contract_name' => $sourceData['contract_name'] ?? 'Unknown',
            'compiler_version' => $sourceData['compiler_version'] ?? 'Unknown',
            'optimization_used' => $sourceData['optimization_used'] ?? false,
            'optimization_runs' => $sourceData['optimization_runs'] ?? 0,
            'evm_version' => $sourceData['evm_version'] ?? 'default',
            'license_type' => $sourceData['license_type'] ?? 'Unknown',
            'is_proxy' => $sourceData['proxy'] ?? false,
            'implementation_address' => $sourceData['implementation'] ?? null,
            'is_verified' => $sourceData['is_verified'] ?? false,
            'has_constructor_args' => !empty($sourceData['constructor_arguments'] ?? ''),
            'explorer_url' => $explorer->getContractUrl($contractAddress),
            'fetched_at' => now()->toISOString()
        ];
    }

    /**
     * Analyze source code structure and complexity
     */
    public function analyzeSourceStructure(array $sourceData): array
    {
        $analysis = [
            'contract_count' => count($sourceData['source_files']),
            'contracts' => [],
            'imports' => [],
            'pragma_versions' => [],
            'licenses' => [],
            'security_patterns' => [],
            'complexity_metrics' => []
        ];

        foreach ($sourceData['source_files'] as $filename => $content) {
            $fileAnalysis = $this->analyzeSourceFile($content);
            
            $analysis['contracts'] = array_merge($analysis['contracts'], $fileAnalysis['contracts']);
            $analysis['imports'] = array_merge($analysis['imports'], $fileAnalysis['imports']);
            $analysis['pragma_versions'] = array_merge($analysis['pragma_versions'], $fileAnalysis['pragma_versions']);
            $analysis['licenses'] = array_merge($analysis['licenses'], $fileAnalysis['licenses']);
        }

        // Remove duplicates
        $analysis['imports'] = array_unique($analysis['imports']);
        $analysis['pragma_versions'] = array_unique($analysis['pragma_versions']);
        $analysis['licenses'] = array_unique($analysis['licenses']);
        
        // Calculate complexity metrics
        $analysis['complexity_metrics'] = [
            'total_contracts' => count($analysis['contracts']),
            'unique_imports' => count($analysis['imports']),
            'external_dependencies' => $this->countExternalDependencies($analysis['imports']),
            'average_file_size' => $this->calculateAverageFileSize($sourceData['source_files']),
            'total_lines' => $sourceData['statistics']['total_lines'] ?? 0
        ];

        return $analysis;
    }

    /**
     * Get cache statistics
     */
    public function getCacheStatistics(): array
    {
        $stats = [
            'memory_cache' => [
                'total_keys' => 0,
                'estimated_size_mb' => 0
            ],
            'database_cache' => [
                'total_contracts' => 0,
                'verified_contracts' => 0,
                'networks' => [],
                'oldest_entry' => null,
                'newest_entry' => null
            ]
        ];

        try {
            // Database cache statistics
            $dbStats = ContractCache::where('source_code', '!=', null)
                ->selectRaw('
                    COUNT(*) as total,
                    COUNT(CASE WHEN is_verified = true THEN 1 END) as verified,
                    MIN(created_at) as oldest,
                    MAX(updated_at) as newest,
                    ARRAY_AGG(DISTINCT network) as networks
                ')
                ->first();

            if ($dbStats) {
                $stats['database_cache'] = [
                    'total_contracts' => $dbStats->total ?? 0,
                    'verified_contracts' => $dbStats->verified ?? 0,
                    'networks' => $dbStats->networks ?? [],
                    'oldest_entry' => $dbStats->oldest,
                    'newest_entry' => $dbStats->newest
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get cache statistics', ['error' => $e->getMessage()]);
        }

        return $stats;
    }

    private function validateInput(string $contractAddress, string $network): void
    {
        $this->validateContractAddress($contractAddress);
        $this->validateNetwork($network);
    }

    private function validateContractAddress(string $address): void
    {
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            throw new InvalidArgumentException("Invalid contract address format: {$address}");
        }
    }

    private function validateNetwork(string $network): void
    {
        if (!array_key_exists($network, self::SUPPORTED_NETWORKS)) {
            $supported = implode(', ', array_keys(self::SUPPORTED_NETWORKS));
            throw new InvalidArgumentException("Unsupported network: {$network}. Supported: {$supported}");
        }
    }

    private function getCacheKey(string $contractAddress, string $network): string
    {
        return "verified_source_{$network}_" . strtolower($contractAddress);
    }

    private function isCacheValid(array $cached): bool
    {
        if (!isset($cached['cached_at'])) {
            return false;
        }
        
        $cachedAt = Carbon::parse($cached['cached_at']);
        return $cachedAt->addSeconds(self::CACHE_TTL)->isFuture();
    }

    private function applyRateLimit(string $network): void
    {
        $rateLimitKey = "rate_limit_verified_{$network}";
        $lastRequest = Cache::get($rateLimitKey);
        
        if ($lastRequest) {
            $timeSinceLastRequest = (microtime(true) - $lastRequest) * 1000;
            if ($timeSinceLastRequest < self::RATE_LIMIT_DELAY) {
                $sleepTime = self::RATE_LIMIT_DELAY - $timeSinceLastRequest;
                usleep($sleepTime * 1000);
            }
        }
        
        Cache::put($rateLimitKey, microtime(true), 60);
    }

    private function isContractVerified(string $contractAddress, AbstractBlockchainExplorer $explorer): bool
    {
        try {
            $sourceData = $explorer->getContractSource($contractAddress);
            return !empty($sourceData['source_code']) && 
                   $sourceData['source_code'] !== 'Contract source code not verified' &&
                   ($sourceData['is_verified'] ?? false);
        } catch (\Exception $e) {
            Log::debug('Contract verification check failed', [
                'contract' => $contractAddress,
                'explorer' => $explorer->getName(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function fetchWithRetry(AbstractBlockchainExplorer $explorer, string $contractAddress): array
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= self::MAX_RETRY_ATTEMPTS; $attempt++) {
            try {
                return $explorer->getContractSource($contractAddress);
            } catch (\Exception $e) {
                $lastException = $e;
                
                Log::warning('Source fetch attempt failed', [
                    'contract' => $contractAddress,
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
                ]);
                
                if ($attempt < self::MAX_RETRY_ATTEMPTS) {
                    sleep($attempt); // Exponential backoff
                }
            }
        }
        
        throw $lastException;
    }

    private function processSourceData(array $rawData, string $contractAddress, string $network): array
    {
        // Parse source files
        $sourceFiles = $this->parseSourceFiles($rawData);
        
        // Calculate statistics
        $statistics = $this->calculateStatistics($sourceFiles);
        
        // Detect main contract
        $mainContract = $this->detectMainContract($sourceFiles, $rawData['contract_name'] ?? null);
        
        return [
            'contract_address' => $contractAddress,
            'network' => $network,
            'explorer' => self::SUPPORTED_NETWORKS[$network],
            'contract_name' => $rawData['contract_name'] ?? 'Unknown',
            'compiler_version' => $rawData['compiler_version'] ?? 'Unknown',
            'optimization_used' => $rawData['optimization_used'] ?? false,
            'optimization_runs' => $rawData['optimization_runs'] ?? 0,
            'constructor_arguments' => $rawData['constructor_arguments'] ?? '',
            'evm_version' => $rawData['evm_version'] ?? 'default',
            'library' => $rawData['library'] ?? '',
            'license_type' => $rawData['license_type'] ?? 'Unknown',
            'is_proxy' => $rawData['proxy'] ?? false,
            'implementation' => $rawData['implementation'] ?? null,
            'is_verified' => true,
            'source_files' => $sourceFiles,
            'main_contract' => $mainContract,
            'abi' => $rawData['abi'] ?? null,
            'statistics' => $statistics,
            'cached_at' => now()->toISOString(),
            'fetched_at' => now()->toISOString()
        ];
    }

    private function parseSourceFiles(array $rawData): array
    {
        $sourceCode = $rawData['source_code'] ?? '';
        $parsedSources = $rawData['parsed_sources'] ?? [];
        
        if (!empty($parsedSources)) {
            return $parsedSources;
        }
        
        // Handle single file source
        if (!empty($sourceCode)) {
            $contractName = $rawData['contract_name'] ?? 'Contract';
            return [
                "{$contractName}.sol" => $sourceCode
            ];
        }
        
        return [];
    }

    private function calculateStatistics(array $sourceFiles): array
    {
        $totalLines = 0;
        $totalChars = 0;
        $functions = [];
        $events = [];
        $modifiers = [];
        
        foreach ($sourceFiles as $filename => $content) {
            $lines = explode("\n", $content);
            $totalLines += count($lines);
            $totalChars += strlen($content);
            
            // Extract functions, events, modifiers
            preg_match_all('/function\s+(\w+)/', $content, $funcMatches);
            preg_match_all('/event\s+(\w+)/', $content, $eventMatches);
            preg_match_all('/modifier\s+(\w+)/', $content, $modMatches);
            
            $functions = array_merge($functions, $funcMatches[1] ?? []);
            $events = array_merge($events, $eventMatches[1] ?? []);
            $modifiers = array_merge($modifiers, $modMatches[1] ?? []);
        }
        
        return [
            'total_files' => count($sourceFiles),
            'total_lines' => $totalLines,
            'total_characters' => $totalChars,
            'functions_count' => count(array_unique($functions)),
            'events_count' => count(array_unique($events)),
            'modifiers_count' => count(array_unique($modifiers)),
            'estimated_size_kb' => round($totalChars / 1024, 2)
        ];
    }

    private function detectMainContract(array $sourceFiles, ?string $hintName = null): ?string
    {
        if ($hintName && array_key_exists("{$hintName}.sol", $sourceFiles)) {
            return $hintName;
        }
        
        // Find the largest file (likely main contract)
        $largest = null;
        $maxSize = 0;
        
        foreach ($sourceFiles as $filename => $content) {
            $size = strlen($content);
            if ($size > $maxSize) {
                $maxSize = $size;
                $largest = basename($filename, '.sol');
            }
        }
        
        return $largest;
    }

    private function cacheResult(string $cacheKey, array $data): void
    {
        // Cache with TTL
        Cache::put($cacheKey, $data, self::CACHE_TTL);
        
        // Also store as stale backup
        Cache::put($cacheKey . '_stale', $data, self::CACHE_TTL * 7); // 7 days for stale
    }

    private function storeInDatabase(array $data): void
    {
        try {
            ContractCache::updateOrCreate(
                [
                    'contract_address' => strtolower($data['contract_address']),
                    'network' => $data['network']
                ],
                [
                    'source_code' => json_encode($data['source_files']),
                    'is_verified' => true,
                    'compiler_version' => $data['compiler_version'],
                    'contract_name' => $data['contract_name'],
                    'abi' => $data['abi'],
                    'updated_at' => now()
                ]
            );
        } catch (\Exception $e) {
            Log::warning('Failed to store verified source in database', [
                'contract' => $data['contract_address'],
                'error' => $e->getMessage()
            ]);
        }
    }

    private function analyzeSourceFile(string $sourceCode): array
    {
        return [
            'contracts' => $this->extractContractNames($sourceCode),
            'imports' => $this->extractImports($sourceCode),
            'pragma_versions' => $this->extractPragmaVersions($sourceCode),
            'licenses' => $this->extractLicenses($sourceCode)
        ];
    }

    private function extractContractNames(string $sourceCode): array
    {
        preg_match_all('/(?:contract|interface|library)\s+(\w+)/', $sourceCode, $matches);
        return $matches[1] ?? [];
    }

    private function extractImports(string $sourceCode): array
    {
        preg_match_all('/import\s+["\']([^"\']+)["\']/', $sourceCode, $matches);
        return $matches[1] ?? [];
    }

    private function extractPragmaVersions(string $sourceCode): array
    {
        preg_match_all('/pragma\s+solidity\s+([^;]+);/', $sourceCode, $matches);
        return $matches[1] ?? [];
    }

    private function extractLicenses(string $sourceCode): array
    {
        preg_match_all('/SPDX-License-Identifier:\s*([^\s\*\/]+)/', $sourceCode, $matches);
        return $matches[1] ?? [];
    }

    private function countExternalDependencies(array $imports): int
    {
        return count(array_filter($imports, function ($import) {
            return !str_starts_with($import, './') && !str_starts_with($import, '../');
        }));
    }

    private function calculateAverageFileSize(array $sourceFiles): int
    {
        if (empty($sourceFiles)) {
            return 0;
        }
        
        $totalSize = array_sum(array_map('strlen', $sourceFiles));
        return round($totalSize / count($sourceFiles));
    }
}