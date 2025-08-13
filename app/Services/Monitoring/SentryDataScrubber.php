<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use Sentry\Event;
use Illuminate\Support\Str;

class SentryDataScrubber
{
    private array $scrubFields;

    public function __construct()
    {
        $this->scrubFields = config('sentry.ai_blockchain.context.scrub_fields', []);
    }

    /**
     * Scrub sensitive data from Sentry event.
     */
    public function scrubEvent(Event $event): Event
    {
        // Scrub request data
        $this->scrubRequestData($event);
        
        // Scrub extra context
        $this->scrubExtraContext($event);
        
        // Scrub breadcrumbs
        $this->scrubBreadcrumbs($event);
        
        // Scrub exception data
        $this->scrubExceptionData($event);

        return $event;
    }

    /**
     * Scrub sensitive data from request context.
     */
    private function scrubRequestData(Event $event): void
    {
        $request = $event->getRequest();
        if (!$request) {
            return;
        }

        // Scrub request data
        if ($data = $request['data'] ?? null) {
            $request['data'] = $this->scrubArray($data);
        }

        // Scrub headers
        if ($headers = $request['headers'] ?? null) {
            $request['headers'] = $this->scrubHeaders($headers);
        }

        // Scrub cookies
        if ($cookies = $request['cookies'] ?? null) {
            $request['cookies'] = $this->scrubArray($cookies);
        }

        // Scrub query string
        if ($queryString = $request['query_string'] ?? null) {
            $request['query_string'] = $this->scrubQueryString($queryString);
        }

        $event->setRequest($request);
    }

    /**
     * Scrub sensitive data from extra context.
     */
    private function scrubExtraContext(Event $event): void
    {
        $extra = $event->getExtra();
        $scrubbed = $this->scrubArray($extra);
        
        foreach ($scrubbed as $key => $value) {
            $event->setExtra($key, $value);
        }
    }

    /**
     * Scrub sensitive data from breadcrumbs.
     */
    private function scrubBreadcrumbs(Event $event): void
    {
        $breadcrumbs = $event->getBreadcrumbs();
        
        foreach ($breadcrumbs as $breadcrumb) {
            if ($data = $breadcrumb->getData()) {
                $breadcrumb->setData($this->scrubArray($data));
            }
            
            if ($metadata = $breadcrumb->getMetadata()) {
                $breadcrumb->setMetadata($this->scrubArray($metadata));
            }
        }
    }

    /**
     * Scrub sensitive data from exception context.
     */
    private function scrubExceptionData(Event $event): void
    {
        $exceptions = $event->getExceptions();
        
        foreach ($exceptions as $exception) {
            // Scrub stack trace frames for sensitive data
            $stacktrace = $exception->getStacktrace();
            if ($stacktrace) {
                $frames = $stacktrace->getFrames();
                foreach ($frames as $frame) {
                    if ($vars = $frame->getVars()) {
                        $frame->setVars($this->scrubArray($vars));
                    }
                }
            }
        }
    }

    /**
     * Scrub an array recursively.
     */
    private function scrubArray(array $data): array
    {
        $scrubbed = [];
        
        foreach ($data as $key => $value) {
            if ($this->shouldScrubKey($key)) {
                $scrubbed[$key] = '[Scrubbed]';
            } elseif (is_array($value)) {
                $scrubbed[$key] = $this->scrubArray($value);
            } elseif (is_string($value) && $this->containsSensitiveData($value)) {
                $scrubbed[$key] = $this->scrubString($value);
            } else {
                $scrubbed[$key] = $value;
            }
        }
        
        return $scrubbed;
    }

    /**
     * Scrub sensitive headers.
     */
    private function scrubHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'cookie',
            'x-api-key',
            'x-auth-token',
            'x-csrf-token',
            'x-stripe-signature',
        ];

        $scrubbed = [];
        
        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $sensitiveHeaders)) {
                $scrubbed[$key] = '[Scrubbed]';
            } else {
                $scrubbed[$key] = $value;
            }
        }
        
        return $scrubbed;
    }

    /**
     * Scrub sensitive data from query string.
     */
    private function scrubQueryString(string $queryString): string
    {
        parse_str($queryString, $params);
        $scrubbed = $this->scrubArray($params);
        return http_build_query($scrubbed);
    }

    /**
     * Check if a key should be scrubbed.
     */
    private function shouldScrubKey(string $key): bool
    {
        $lowerKey = strtolower($key);
        
        foreach ($this->scrubFields as $field) {
            if (Str::contains($lowerKey, strtolower($field))) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if a string contains sensitive data patterns.
     */
    private function containsSensitiveData(string $value): bool
    {
        // Check for common sensitive patterns
        $patterns = [
            '/sk_live_[a-zA-Z0-9]+/',  // Stripe secret key
            '/pk_live_[a-zA-Z0-9]+/',  // Stripe publishable key
            '/rk_live_[a-zA-Z0-9]+/',  // Stripe restricted key
            '/eyJ[a-zA-Z0-9_\-]+/',    // JWT tokens
            '/[A-Za-z0-9]{32,}/',      // API keys (32+ chars)
            '/-----BEGIN [A-Z ]+-----/', // Private keys
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scrub sensitive patterns from a string.
     */
    private function scrubString(string $value): string
    {
        $patterns = [
            '/sk_live_[a-zA-Z0-9]+/' => '[Stripe Secret Key]',
            '/pk_live_[a-zA-Z0-9]+/' => '[Stripe Publishable Key]',
            '/rk_live_[a-zA-Z0-9]+/' => '[Stripe Restricted Key]',
            '/eyJ[a-zA-Z0-9_\-]+/' => '[JWT Token]',
            '/[A-Za-z0-9]{32,}/' => '[API Key]',
            '/-----BEGIN [A-Z ]+-----.*?-----END [A-Z ]+-----/s' => '[Private Key]',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value);
        }

        return $value;
    }

    /**
     * Add custom field to scrub list.
     */
    public function addScrubField(string $field): void
    {
        if (!in_array($field, $this->scrubFields)) {
            $this->scrubFields[] = $field;
        }
    }

    /**
     * Get current scrub fields.
     */
    public function getScrubFields(): array
    {
        return $this->scrubFields;
    }
}