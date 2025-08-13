<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OnboardingEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class OnboardingEmailController extends Controller
{
    public function __construct(
        private readonly OnboardingEmailService $onboardingService
    ) {}

    public function getProgress(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $progress = $this->onboardingService->getOnboardingProgress($user);

        return response()->json([
            'user_id' => $user->id,
            'onboarding_enabled' => $user->onboarding_emails_enabled,
            'progress' => $progress
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'onboarding_emails_enabled' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $user->update([
            'onboarding_emails_enabled' => $request->input('onboarding_emails_enabled')
        ]);

        return response()->json([
            'message' => 'Preferences updated successfully',
            'onboarding_emails_enabled' => $user->onboarding_emails_enabled
        ]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Invalid parameters',
                'messages' => $validator->errors()
            ], 422);
        }

        $success = $this->onboardingService->unsubscribeUser(
            $request->input('token'),
            $request->input('email')
        );

        if (!$success) {
            return response()->json([
                'error' => 'Invalid unsubscribe link'
            ], 400);
        }

        return response()->json([
            'message' => 'Successfully unsubscribed from onboarding emails'
        ]);
    }

    public function getStatistics(): JsonResponse
    {
        $stats = $this->onboardingService->getStatistics();

        return response()->json($stats);
    }

    public function resendEmail(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'email_type' => 'required|string|in:welcome,tutorial,features,tips,feedback'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Invalid email type',
                'messages' => $validator->errors()
            ], 422);
        }

        $emailType = $request->input('email_type');
        $sequence = config('onboarding.sequence', []);
        $config = $sequence[$emailType] ?? null;

        if (!$config) {
            return response()->json([
                'error' => 'Email type not found in configuration'
            ], 404);
        }

        // Force resend by removing the existing log entry
        $user->onboardingEmailLogs()
            ->where('email_type', $emailType)
            ->delete();

        // Schedule the email immediately
        $this->onboardingService->scheduleOnboardingEmail($user, $emailType, $config);

        return response()->json([
            'message' => "Email '{$emailType}' has been scheduled for resending"
        ]);
    }

    public function testEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'email_type' => 'required|string|in:welcome,tutorial,features,tips,feedback'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Invalid parameters',
                'messages' => $validator->errors()
            ], 422);
        }

        $emailType = $request->input('email_type');
        $email = $request->input('email');

        // Create a temporary user object for testing
        $testUser = new \App\Models\User([
            'name' => 'Test User',
            'email' => $email,
            'onboarding_emails_enabled' => true
        ]);
        $testUser->id = 99999; // Fake ID for testing

        $sequence = config('onboarding.sequence', []);
        $config = $sequence[$emailType] ?? null;

        if (!$config) {
            return response()->json([
                'error' => 'Email type not found'
            ], 404);
        }

        try {
            // Get email variables
            $variables = $this->onboardingService->getEmailVariables($testUser, $emailType);
            
            return response()->json([
                'message' => 'Email test data generated successfully',
                'email_type' => $emailType,
                'recipient' => $email,
                'subject' => $config['subject'],
                'template' => $config['template'],
                'variables' => $variables
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate test email',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function webhook(Request $request): JsonResponse
    {
        // Handle Mailgun webhooks for email delivery status
        $eventType = $request->input('event');
        $recipient = $request->input('recipient');
        $messageId = $request->input('Message-Id');

        // Verify webhook signature (implement Mailgun signature verification)
        // This is a basic implementation - in production, verify the webhook signature

        switch ($eventType) {
            case 'delivered':
                // Update email log status to delivered
                // You would need to track message IDs to update the correct log entry
                break;
                
            case 'opened':
                // Track email opens for analytics
                break;
                
            case 'clicked':
                // Track link clicks for analytics
                break;
                
            case 'failed':
            case 'rejected':
                // Handle failed emails
                break;
        }

        return response()->json(['status' => 'ok']);
    }
}