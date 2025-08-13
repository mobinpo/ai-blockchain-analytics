<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Sentry\State\Scope;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;
use Throwable;

final class SentryService
{
    /**
     * Configure Sentry context for the current request.
     */
    public function configureContext(): void
    {
        \Sentry\configureScope(function (Scope $scope): void {
            // Set user context
            if (Auth::check()) {
                $user = Auth::user();
                $scope->setUser([
                    'id' => $user->id,
                    'email' => $user->email,
                    'subscription_tier' => $user->subscription?->name ?? 'free',
                ]);
            }

            // Set application context
            $scope->setTag('platform', 'ai-blockchain-analytics');
            $scope->setTag('version', config('app.version', '1.0.0'));
            $scope->setTag('environment', app()->environment());
            
            // Set request context
            if (request()) {
                $scope->setTag('route', request()->route()?->getName() ?? 'unknown');
                $scope->setContext('request', [
                    'url' => request()->fullUrl(),
                    'method' => request()->method(),
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }
        });
    }

    /**
     * Track blockchain operation performance.
     */
    public function trackBlockchainOperation(string $operation, callable $callback, array $context = []): mixed
    {
        if (!config('sentry.ai_blockchain.track_blockchain_operations')) {
            return $callback();
        }

        $transactionContext = new TransactionContext();
        $transactionContext->setName("blockchain.{$operation}");
        $transactionContext->setOp('blockchain');

        $transaction = \Sentry\startTransaction($transactionContext);
        \Sentry\SentrySdk::getCurrentHub()->setSpan($transaction);

        try {
            // Add custom context
            $transaction->setData($context);
            
            $result = $callback();
            
            $transaction->setStatus(\Sentry\Tracing\SpanStatus::ok());
            
            return $result;
        } catch (Throwable $e) {
            $transaction->setStatus(\Sentry\Tracing\SpanStatus::internalError());
            
            $this->captureException($e, [
                'operation' => $operation,
                'context' => $context,
            ]);
            
            throw $e;
        } finally {
            $transaction->finish();
        }
    }

    /**
     * Track sentiment analysis operations.
     */
    public function trackSentimentAnalysis(string $operation, callable $callback, array $metadata = []): mixed
    {
        if (!config('sentry.ai_blockchain.track_sentiment_analysis')) {
            return $callback();
        }

        $transactionContext = new TransactionContext();
        $transactionContext->setName("sentiment.{$operation}");
        $transactionContext->setOp('sentiment_analysis');

        $transaction = \Sentry\startTransaction($transactionContext);
        \Sentry\SentrySdk::getCurrentHub()->setSpan($transaction);

        try {
            $transaction->setData([
                'platforms' => $metadata['platforms'] ?? [],
                'text_count' => $metadata['text_count'] ?? 0,
                'date_range' => $metadata['date_range'] ?? null,
            ]);
            
            $result = $callback();
            
            $transaction->setStatus(\Sentry\Tracing\SpanStatus::ok());
            
            return $result;
        } catch (Throwable $e) {
            $transaction->setStatus(\Sentry\Tracing\SpanStatus::internalError());
            
            $this->captureException($e, [
                'operation' => $operation,
                'metadata' => $metadata,
            ]);
            
            throw $e;
        } finally {
            $transaction->finish();
        }
    }

    /**
     * Track crawler operations.
     */
    public function trackCrawlerOperation(string $platform, callable $callback, array $config = []): mixed
    {
        if (!config('sentry.ai_blockchain.track_crawler_operations')) {
            return $callback();
        }

        $transactionContext = new TransactionContext();
        $transactionContext->setName("crawler.{$platform}");
        $transactionContext->setOp('social_media_crawling');

        $transaction = \Sentry\startTransaction($transactionContext);
        \Sentry\SentrySdk::getCurrentHub()->setSpan($transaction);

        try {
            $transaction->setData([
                'platform' => $platform,
                'keywords' => $config['keywords'] ?? [],
                'max_results' => $config['max_results'] ?? 0,
            ]);
            
            $result = $callback();
            
            $transaction->setStatus(\Sentry\Tracing\SpanStatus::ok());
            $transaction->setData([
                'results_count' => is_array($result) ? count($result) : 0,
            ]);
            
            return $result;
        } catch (Throwable $e) {
            $transaction->setStatus(\Sentry\Tracing\SpanStatus::internalError());
            
            $this->captureException($e, [
                'platform' => $platform,
                'config' => $config,
            ]);
            
            throw $e;
        } finally {
            $transaction->finish();
        }
    }

    /**
     * Track OpenAI API operations.
     */
    public function trackOpenAiOperation(string $operation, callable $callback, array $parameters = []): mixed
    {
        if (!config('sentry.ai_blockchain.track_openai_operations')) {
            return $callback();
        }

        $transactionContext = new TransactionContext();
        $transactionContext->setName("openai.{$operation}");
        $transactionContext->setOp('ai_processing');

        $transaction = \Sentry\startTransaction($transactionContext);
        \Sentry\SentrySdk::getCurrentHub()->setSpan($transaction);

        try {
            // Don't log sensitive data, just metadata
            $transaction->setData([
                'operation' => $operation,
                'model' => $parameters['model'] ?? 'unknown',
                'max_tokens' => $parameters['max_tokens'] ?? 0,
                'temperature' => $parameters['temperature'] ?? 0,
            ]);
            
            $result = $callback();
            
            $transaction->setStatus(\Sentry\Tracing\SpanStatus::ok());
            
            return $result;
        } catch (Throwable $e) {
            $transaction->setStatus(\Sentry\Tracing\SpanStatus::internalError());
            
            $this->captureException($e, [
                'operation' => $operation,
                'model' => $parameters['model'] ?? 'unknown',
            ]);
            
            throw $e;
        } finally {
            $transaction->finish();
        }
    }

    /**
     * Track verification operations.
     */
    public function trackVerificationOperation(string $operation, callable $callback, array $data = []): mixed
    {
        if (!config('sentry.ai_blockchain.track_verification_operations')) {
            return $callback();
        }

        $transactionContext = new TransactionContext();
        $transactionContext->setName("verification.{$operation}");
        $transactionContext->setOp('cryptographic_verification');

        $transaction = \Sentry\startTransaction($transactionContext);
        \Sentry\SentrySdk::getCurrentHub()->setSpan($transaction);

        try {
            $transaction->setData([
                'operation' => $operation,
                'contract_address' => $data['contract_address'] ?? null,
                'verification_method' => $data['verification_method'] ?? 'signed_url',
            ]);
            
            $result = $callback();
            
            $transaction->setStatus(\Sentry\Tracing\SpanStatus::ok());
            
            return $result;
        } catch (Throwable $e) {
            $transaction->setStatus(\Sentry\Tracing\SpanStatus::internalError());
            
            $this->captureException($e, [
                'operation' => $operation,
                'data' => $data,
            ]);
            
            throw $e;
        } finally {
            $transaction->finish();
        }
    }

    /**
     * Capture exception with enhanced context.
     */
    public function captureException(Throwable $exception, array $context = []): void
    {
        \Sentry\configureScope(function (Scope $scope) use ($context): void {
            foreach ($context as $key => $value) {
                $scope->setContext($key, $value);
            }
        });

        \Sentry\captureException($exception);
    }

    /**
     * Capture custom message with context.
     */
    public function captureMessage(string $message, string $level = 'info', array $context = []): void
    {
        \Sentry\configureScope(function (Scope $scope) use ($context): void {
            foreach ($context as $key => $value) {
                $scope->setContext($key, $value);
            }
        });

        \Sentry\captureMessage($message, \Sentry\Severity::fromError($level));
    }

    /**
     * Add breadcrumb for debugging.
     */
    public function addBreadcrumb(string $message, string $category = 'default', array $data = []): void
    {
        \Sentry\addBreadcrumb(
            new \Sentry\Breadcrumb(
                \Sentry\Breadcrumb::LEVEL_INFO,
                \Sentry\Breadcrumb::TYPE_DEFAULT,
                $category,
                $message,
                $data
            )
        );
    }

    /**
     * Start a custom transaction.
     */
    public function startTransaction(string $name, string $op = 'custom'): Transaction
    {
        $transactionContext = new TransactionContext();
        $transactionContext->setName($name);
        $transactionContext->setOp($op);

        return \Sentry\startTransaction($transactionContext);
    }

    /**
     * Set user context.
     */
    public function setUser(array $userData): void
    {
        \Sentry\configureScope(function (Scope $scope) use ($userData): void {
            $scope->setUser($userData);
        });
    }

    /**
     * Set custom tag.
     */
    public function setTag(string $key, string $value): void
    {
        \Sentry\configureScope(function (Scope $scope) use ($key, $value): void {
            $scope->setTag($key, $value);
        });
    }

    /**
     * Set custom context.
     */
    public function setContext(string $key, array $context): void
    {
        \Sentry\configureScope(function (Scope $scope) use ($key, $context): void {
            $scope->setContext($key, $context);
        });
    }
}