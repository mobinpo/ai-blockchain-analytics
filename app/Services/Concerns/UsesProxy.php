<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\PendingRequest;

trait UsesProxy
{
    /**
     * Get configured HTTP client with proxy settings if enabled.
     */
    protected function getHttpClient(): PendingRequest
    {
        $client = Http::withOptions([
            'verify' => false, // Disable SSL verification if needed for proxy
        ]);

        // Get proxy configuration from config
        $proxyConfig = $this->getProxyConfig();
        
        // Configure proxy if enabled
        if ($proxyConfig['enabled'] ?? false) {
            $proxyUrl = $proxyConfig['url'] ?? null;
            
            if ($proxyUrl) {
                $client = $client->withOptions([
                    'proxy' => [
                        'http' => $proxyUrl,
                        'https' => $proxyUrl,
                    ],
                    'curl' => [
                        CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5,
                        CURLOPT_PROXY => str_replace('socks5://', '', $proxyUrl),
                    ],
                ]);
                
                Log::info('HTTP client using proxy', [
                    'service' => static::class,
                    'proxy' => $proxyUrl
                ]);
            }
        }

        return $client;
    }

    /**
     * Get proxy configuration for the service.
     * Override this method in services that need custom proxy config.
     */
    protected function getProxyConfig(): array
    {
        // Default to general proxy configuration
        return config('app.proxy', [
            'enabled' => env('PROXY_ENABLED', true),
            'url' => env('PROXY_URL', 'socks5://192.168.1.32:8086'),
        ]);
    }
}