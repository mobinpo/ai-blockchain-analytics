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

    /**
     * Get chain ID for this network
     */
    public function getChainId(): int;

    /**
     * Get native currency symbol (ETH, BNB, MATIC, etc.)
     */
    public function getNativeCurrency(): string;

    /**
     * Get block explorer web URL
     */
    public function getExplorerUrl(): string;

    /**
     * Detect contract address format and validate for this chain
     */
    public function detectAndValidateAddress(string $address): array;

    /**
     * Get transaction details by hash
     */
    public function getTransaction(string $txHash): array;

    /**
     * Get block details by number or hash
     */
    public function getBlock(string $blockIdentifier): array;

    /**
     * Get account balance and transaction count
     */
    public function getAccount(string $address): array;

    /**
     * Get contract transaction history
     */
    public function getContractTransactions(string $contractAddress, int $limit = 100): array;

    /**
     * Get gas price recommendations
     */
    public function getGasPrice(): array;
}