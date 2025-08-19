<?php

declare(strict_types=1);

namespace App\Contracts;

interface ContractExamplesRepositoryInterface
{
    /**
     * Get popular/featured smart contracts for examples
     */
    public function getPopularContracts(int $limit = 10): array;

    /**
     * Get contracts by category (defi, lending, token, etc.)
     */
    public function getContractsByCategory(string $category, int $limit = 5): array;

    /**
     * Get recently analyzed contracts
     */
    public function getRecentContracts(int $limit = 10): array;

    /**
     * Get verified high-quality contract examples
     */
    public function getVerifiedExamples(): array;
}
