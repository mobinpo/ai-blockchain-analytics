<?php

declare(strict_types=1);

namespace App\Contracts;

interface BlockchainExplorerInterface
{
    /**
     * Get the explorer name (e.g., 'etherscan', 'bscscan')
     */
    public function getName(): string;

    /**
     * Get the supported network name (e.g., 'ethereum', 'bsc')
     */
    public function getNetwork(): string;

    /**
     * Get the base API URL for this explorer
     */
    public function getApiUrl(): string;

    /**
     * Check if the explorer is properly configured
     */
    public function isConfigured(): bool;

    /**
     * Fetch contract source code
     */
    public function getContractSource(string $contractAddress): array;

    /**
     * Fetch contract ABI
     */
    public function getContractAbi(string $contractAddress): array;

    /**
     * Fetch contract creation transaction details
     */
    public function getContractCreation(string $contractAddress): array;

    /**
     * Check if contract is verified
     */
    public function isContractVerified(string $contractAddress): bool;

    /**
     * Get explorer-specific rate limit information
     */
    public function getRateLimit(): int;

    /**
     * Get explorer-specific timeout setting
     */
    public function getTimeout(): int;

    /**
     * Validate contract address format for this network
     */
    public function validateAddress(string $address): bool;

    /**
     * Make a raw API request to the explorer
     */
    public function makeRequest(string $endpoint, array $params = []): array;

    /**
     * Get explorer web URL for a contract
     */
    public function getContractUrl(string $contractAddress): string;

    /**
     * Get available API endpoints for this explorer
     */
    public function getAvailableEndpoints(): array;
}