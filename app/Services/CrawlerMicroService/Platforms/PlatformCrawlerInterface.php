<?php

declare(strict_types=1);

namespace App\Services\CrawlerMicroService\Platforms;

interface PlatformCrawlerInterface
{
    /**
     * Main crawl method for the platform
     */
    public function crawl(array $options = []): array;

    /**
     * Search for content by keywords
     */
    public function searchByKeywords(array $keywords, array $channels = null): array;
}