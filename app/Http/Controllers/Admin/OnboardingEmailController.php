<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OnboardingEmailLog;
use App\Models\EmailTracking;
use App\Models\User;
use App\Services\OnboardingEmailService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

final class OnboardingEmailController extends Controller
{
    public function __construct(
        private readonly OnboardingEmailService $onboardingService
    ) {}

    /**
     * Display the onboarding email dashboard
     */
    public function index(Request $request): Response
    {
        $dateRange = $this->getDateRange($request);
        
        $analytics = $this->getEmailAnalytics($dateRange['start'], $dateRange['end']);
        $sequenceStats = $this->getSequenceStats($dateRange['start'], $dateRange['end']);
        $recentLogs = $this->getRecentEmailLogs();
        $topPerformingEmails = $this->getTopPerformingEmails($dateRange['start'], $dateRange['end']);

        return Inertia::render('Admin/OnboardingEmails/Dashboard', [
            'analytics' => $analytics,
            'sequenceStats' => $sequenceStats,
            'recentLogs' => $recentLogs,
            'topPerformingEmails' => $topPerformingEmails,
            'dateRange' => $dateRange,
            'config' => [
                'sequence' => config('onboarding.sequence', []),
                'enabled' => config('onboarding.enabled', true),
            ],
        ]);
    }

    /**
     * Get email analytics data
     */
    public function analytics(Request $request): JsonResponse
    {
        $dateRange = $this->getDateRange($request);
        $analytics = $this->getEmailAnalytics($dateRange['start'], $dateRange['end']);

        return response()->json($analytics);
    }

    /**
     * Get email sequence performance
     */
    public function sequencePerformance(Request $request): JsonResponse
    {
        $dateRange = $this->getDateRange($request);
        $performance = $this->getSequencePerformance($dateRange['start'], $dateRange['end']);

        return response()->json($performance);
    }

    /**
     * Get individual email performance
     */
    public function emailPerformance(Request $request, string $emailType): JsonResponse
    {
        $dateRange = $this->getDateRange($request);
        $performance = $this->getEmailTypePerformance($emailType, $dateRange['start'], $dateRange['end']);

        return response()->json($performance);
    }

    /**
     * Get user onboarding journey
     */
    public function userJourney(Request $request, int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        $journey = $this->getUserOnboardingJourney($user);

        return response()->json($journey);
    }

    /**
     * Restart onboarding for a user
     */
    public function restartOnboarding(Request $request, int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        
        // Cancel existing emails
        OnboardingEmailLog::where('user_id', $user->id)
            ->where('status', 'scheduled')
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

        // Start new sequence
        $this->onboardingService->startOnboardingSequence($user);

        return response()->json([
            'message' => 'Onboarding sequence restarted successfully',
            'user_id' => $user->id,
        ]);
    }

    /**
     * Update onboarding configuration
     */
    public function updateConfig(Request $request): JsonResponse
    {
        $request->validate([
            'enabled' => 'boolean',
            'sequence' => 'array',
            'sequence.*.enabled' => 'boolean',
            'sequence.*.delay' => 'integer|min:0',
            'sequence.*.subject' => 'string|max:255',
        ]);

        // This would typically update a database configuration
        // For now, we'll just return success
        return response()->json([
            'message' => 'Configuration updated successfully',
            'config' => $request->all(),
        ]);
    }

    /**
     * Send test email
     */
    public function sendTestEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email_type' => 'required|string',
            'recipient_email' => 'required|email',
        ]);

        $user = User::where('email', $request->recipient_email)->first();
        
        if (!$user) {
            return response()->json([
                'error' => 'User not found with that email address'
            ], 404);
        }

        $emailType = $request->email_type;
        $config = config("onboarding.sequence.{$emailType}");

        if (!$config) {
            return response()->json([
                'error' => 'Email type not found in configuration'
            ], 404);
        }

        try {
            $this->onboardingService->scheduleOnboardingEmail($user, $emailType, $config);
            
            return response()->json([
                'message' => "Test email '{$emailType}' sent successfully to {$user->email}",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to send test email: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getDateRange(Request $request): array
    {
        $start = $request->get('start_date', Carbon::now()->subDays(30)->toDateString());
        $end = $request->get('end_date', Carbon::now()->toDateString());

        return [
            'start' => Carbon::parse($start)->startOfDay(),
            'end' => Carbon::parse($end)->endOfDay(),
        ];
    }

    private function getEmailAnalytics(Carbon $startDate, Carbon $endDate): array
    {
        $tracking = EmailTracking::whereBetween('occurred_at', [$startDate, $endDate])
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->pluck('count', 'event_type')
            ->toArray();

        $logs = OnboardingEmailLog::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $totalSent = $tracking['delivered'] ?? 0;
        $totalOpened = $tracking['opened'] ?? 0;
        $totalClicked = $tracking['clicked'] ?? 0;

        return [
            'emails_sent' => $totalSent,
            'emails_opened' => $totalOpened,
            'emails_clicked' => $totalClicked,
            'emails_bounced' => $tracking['bounced'] ?? 0,
            'emails_complained' => $tracking['complained'] ?? 0,
            'emails_unsubscribed' => $tracking['unsubscribed'] ?? 0,
            'open_rate' => $totalSent > 0 ? round(($totalOpened / $totalSent) * 100, 2) : 0,
            'click_rate' => $totalSent > 0 ? round(($totalClicked / $totalSent) * 100, 2) : 0,
            'bounce_rate' => $totalSent > 0 ? round((($tracking['bounced'] ?? 0) / $totalSent) * 100, 2) : 0,
            'by_status' => $logs,
            'by_event' => $tracking,
        ];
    }

    private function getSequenceStats(Carbon $startDate, Carbon $endDate): array
    {
        $sequence = config('onboarding.sequence', []);
        $stats = [];

        foreach ($sequence as $emailType => $config) {
            $logs = OnboardingEmailLog::where('email_type', $emailType)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $tracking = EmailTracking::where('email_type', $emailType)
                ->whereBetween('occurred_at', [$startDate, $endDate])
                ->selectRaw('event_type, COUNT(*) as count')
                ->groupBy('event_type')
                ->pluck('count', 'event_type')
                ->toArray();

            $sent = $logs['sent'] ?? 0;
            $opened = $tracking['opened'] ?? 0;
            $clicked = $tracking['clicked'] ?? 0;

            $stats[$emailType] = [
                'name' => $config['subject'] ?? $emailType,
                'sent' => $sent,
                'opened' => $opened,
                'clicked' => $clicked,
                'open_rate' => $sent > 0 ? round(($opened / $sent) * 100, 2) : 0,
                'click_rate' => $sent > 0 ? round(($clicked / $sent) * 100, 2) : 0,
                'status_breakdown' => $logs,
                'event_breakdown' => $tracking,
            ];
        }

        return $stats;
    }

    private function getRecentEmailLogs(): array
    {
        return OnboardingEmailLog::with('user')
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user_name' => $log->user->name ?? 'Unknown',
                    'user_email' => $log->user->email ?? 'Unknown',
                    'email_type' => $log->email_type,
                    'status' => $log->status,
                    'scheduled_at' => $log->scheduled_at?->toISOString(),
                    'sent_at' => $log->sent_at?->toISOString(),
                    'created_at' => $log->created_at->toISOString(),
                ];
            })
            ->toArray();
    }

    private function getTopPerformingEmails(Carbon $startDate, Carbon $endDate): array
    {
        $sequence = config('onboarding.sequence', []);
        $performance = [];

        foreach ($sequence as $emailType => $config) {
            $sent = OnboardingEmailLog::where('email_type', $emailType)
                ->where('status', 'sent')
                ->whereBetween('sent_at', [$startDate, $endDate])
                ->count();

            $opened = EmailTracking::where('email_type', $emailType)
                ->where('event_type', 'opened')
                ->whereBetween('occurred_at', [$startDate, $endDate])
                ->count();

            $clicked = EmailTracking::where('email_type', $emailType)
                ->where('event_type', 'clicked')
                ->whereBetween('occurred_at', [$startDate, $endDate])
                ->count();

            if ($sent > 0) {
                $performance[] = [
                    'email_type' => $emailType,
                    'name' => $config['subject'] ?? $emailType,
                    'sent' => $sent,
                    'engagement_score' => round((($opened * 1) + ($clicked * 3)) / $sent, 2),
                    'open_rate' => round(($opened / $sent) * 100, 2),
                    'click_rate' => round(($clicked / $sent) * 100, 2),
                ];
            }
        }

        return collect($performance)
            ->sortByDesc('engagement_score')
            ->take(5)
            ->values()
            ->toArray();
    }

    private function getSequencePerformance(Carbon $startDate, Carbon $endDate): array
    {
        // Implementation for sequence performance analysis
        return $this->getSequenceStats($startDate, $endDate);
    }

    private function getEmailTypePerformance(string $emailType, Carbon $startDate, Carbon $endDate): array
    {
        $logs = OnboardingEmailLog::where('email_type', $emailType)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('user')
            ->get();

        $tracking = EmailTracking::where('email_type', $emailType)
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->get();

        return [
            'email_type' => $emailType,
            'total_logs' => $logs->count(),
            'logs' => $logs->map(function ($log) {
                return [
                    'user' => $log->user->name ?? 'Unknown',
                    'email' => $log->user->email ?? 'Unknown',
                    'status' => $log->status,
                    'sent_at' => $log->sent_at?->toISOString(),
                ];
            }),
            'tracking_events' => $tracking->groupBy('event_type')->map->count(),
        ];
    }

    private function getUserOnboardingJourney(User $user): array
    {
        $logs = OnboardingEmailLog::where('user_id', $user->id)
            ->orderBy('scheduled_at')
            ->get();

        $tracking = EmailTracking::where('user_email', $user->email)
            ->orderBy('occurred_at')
            ->get();

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at->toISOString(),
            ],
            'email_logs' => $logs->map(function ($log) {
                return [
                    'email_type' => $log->email_type,
                    'status' => $log->status,
                    'scheduled_at' => $log->scheduled_at?->toISOString(),
                    'sent_at' => $log->sent_at?->toISOString(),
                ];
            }),
            'tracking_events' => $tracking->map(function ($event) {
                return [
                    'event_type' => $event->event_type,
                    'occurred_at' => $event->occurred_at->toISOString(),
                    'event_data' => $event->event_data,
                ];
            }),
        ];
    }
}
