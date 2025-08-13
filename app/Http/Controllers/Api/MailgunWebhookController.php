<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnboardingEmailLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class MailgunWebhookController extends Controller
{
    /**
     * Handle Mailgun webhook events for email tracking
     */
    public function handle(Request $request): JsonResponse
    {
        // Verify webhook signature
        if (!$this->verifyWebhookSignature($request)) {
            Log::warning('Invalid Mailgun webhook signature', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $eventData = $request->input('event-data', []);
        $eventType = $eventData['event'] ?? 'unknown';
        
        Log::info('Mailgun webhook received', [
            'event' => $eventType,
            'recipient' => $eventData['recipient'] ?? 'unknown',
            'timestamp' => $eventData['timestamp'] ?? now()
        ]);

        try {
            $this->processWebhookEvent($eventType, $eventData);
            
            return response()->json(['status' => 'success']);
            
        } catch (\Exception $e) {
            Log::error('Failed to process Mailgun webhook', [
                'event' => $eventType,
                'error' => $e->getMessage(),
                'data' => $eventData
            ]);
            
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    private function verifyWebhookSignature(Request $request): bool
    {
        $signingKey = config('onboarding.webhooks.signing_key');
        
        if (!$signingKey) {
            Log::warning('Mailgun webhook signing key not configured');
            return true; // Allow in development
        }

        $token = $request->input('signature.token');
        $timestamp = $request->input('signature.timestamp');
        $signature = $request->input('signature.signature');

        if (!$token || !$timestamp || !$signature) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $timestamp . $token, $signingKey);
        
        return hash_equals($signature, $expectedSignature);
    }

    private function processWebhookEvent(string $eventType, array $eventData): void
    {
        $recipient = $eventData['recipient'] ?? null;
        $messageId = $eventData['message']['headers']['message-id'] ?? null;
        
        if (!$recipient || !$messageId) {
            return;
        }

        $user = User::where('email', $recipient)->first();
        if (!$user) {
            Log::warning('User not found for email event', [
                'recipient' => $recipient,
                'event' => $eventType
            ]);
            return;
        }

        // Extract email type from message variables
        $variables = $eventData['user-variables'] ?? [];
        $emailType = $variables['email_type'] ?? 'unknown';

        switch ($eventType) {
            case 'delivered':
                $this->handleDelivered($user, $emailType, $eventData);
                break;
                
            case 'opened':
                $this->handleOpened($user, $emailType, $eventData);
                break;
                
            case 'clicked':
                $this->handleClicked($user, $emailType, $eventData);
                break;
                
            case 'unsubscribed':
                $this->handleUnsubscribed($user, $emailType, $eventData);
                break;
                
            case 'complained':
                $this->handleComplained($user, $emailType, $eventData);
                break;
                
            case 'bounced':
                $this->handleBounced($user, $emailType, $eventData);
                break;
        }
    }

    private function handleDelivered(User $user, string $emailType, array $eventData): void
    {
        OnboardingEmailLog::where('user_id', $user->id)
            ->where('email_type', $emailType)
            ->where('status', 'sent')
            ->update([
                'status' => 'delivered',
                'delivered_at' => now(),
                'metadata' => array_merge(
                    OnboardingEmailLog::where('user_id', $user->id)
                        ->where('email_type', $emailType)
                        ->value('metadata') ?? [],
                    ['delivered_event' => $eventData]
                )
            ]);

        Log::info('Email delivered', [
            'user_id' => $user->id,
            'email_type' => $emailType
        ]);
    }

    private function handleOpened(User $user, string $emailType, array $eventData): void
    {
        // Update user's last email interaction
        $user->update(['last_email_opened_at' => now()]);

        // Log the open event
        OnboardingEmailLog::where('user_id', $user->id)
            ->where('email_type', $emailType)
            ->update([
                'metadata' => array_merge(
                    OnboardingEmailLog::where('user_id', $user->id)
                        ->where('email_type', $emailType)
                        ->value('metadata') ?? [],
                    [
                        'opened_at' => now()->toISOString(),
                        'opened_event' => $eventData
                    ]
                )
            ]);

        Log::info('Email opened', [
            'user_id' => $user->id,
            'email_type' => $emailType
        ]);
    }

    private function handleClicked(User $user, string $emailType, array $eventData): void
    {
        $clickedUrl = $eventData['url'] ?? 'unknown';
        
        // Update user's last email interaction
        $user->update(['last_email_clicked_at' => now()]);

        // Log the click event
        OnboardingEmailLog::where('user_id', $user->id)
            ->where('email_type', $emailType)
            ->update([
                'metadata' => array_merge(
                    OnboardingEmailLog::where('user_id', $user->id)
                        ->where('email_type', $emailType)
                        ->value('metadata') ?? [],
                    [
                        'clicked_at' => now()->toISOString(),
                        'clicked_url' => $clickedUrl,
                        'clicked_event' => $eventData
                    ]
                )
            ]);

        Log::info('Email link clicked', [
            'user_id' => $user->id,
            'email_type' => $emailType,
            'url' => $clickedUrl
        ]);
    }

    private function handleUnsubscribed(User $user, string $emailType, array $eventData): void
    {
        // Disable onboarding emails for this user
        $user->update([
            'onboarding_emails_enabled' => false,
            'unsubscribed_at' => now()
        ]);

        Log::info('User unsubscribed from onboarding emails', [
            'user_id' => $user->id,
            'email_type' => $emailType
        ]);
    }

    private function handleComplained(User $user, string $emailType, array $eventData): void
    {
        // Mark user as complained and disable emails
        $user->update([
            'onboarding_emails_enabled' => false,
            'complained_at' => now()
        ]);

        Log::warning('User complained about email', [
            'user_id' => $user->id,
            'email_type' => $emailType
        ]);
    }

    private function handleBounced(User $user, string $emailType, array $eventData): void
    {
        $bounceType = $eventData['severity'] ?? 'unknown';
        
        // For permanent bounces, disable emails
        if ($bounceType === 'permanent') {
            $user->update([
                'onboarding_emails_enabled' => false,
                'email_bounced' => true,
                'email_bounced_at' => now()
            ]);
        }

        Log::warning('Email bounced', [
            'user_id' => $user->id,
            'email_type' => $emailType,
            'bounce_type' => $bounceType
        ]);
    }
}