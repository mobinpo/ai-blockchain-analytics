<?php

declare(strict_types=1);

namespace App\Services\SocialCrawler;

interface CrawlerInterface
{
    /**
     * Crawl platform with given parameters
     *
     * @param array $params
     * @return array Array of post data
     */
    public function crawl(array $params): array;

    /**
     * Search for specific keywords
     *
     * @param array $params
     * @return array Array of post data
     */
    public function search(array $params): array;

    /**
     * Get posts from specific user/account
     *
     * @param string $username
     * @param array $params
     * @return array Array of post data
     */
    public function getUserPosts(string $username, array $params = []): array;

    /**
     * Test API connectivity and credentials
     *
     * @return bool
     */
    public function testConnection(): bool;

    /**
     * Get rate limit status
     *
     * @return array
     */
    public function getRateLimitStatus(): array;

    /**
     * Get platform name
     *
     * @return string
     */
    public function getPlatformName(): string;
}