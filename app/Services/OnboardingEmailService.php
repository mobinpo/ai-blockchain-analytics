<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\OnboardingEmailLog;
use App\Jobs\SendOnboardingEmail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

final class OnboardingEmailService
{
    public function startOnboardingSequence(User $user): void
    {
        if (!config('onboarding.enabled', true)) {
            Log::info("Onboarding disabled, skipping sequence for user {$user->id}");
            return;
        }

        // Check if user should be skipped
        if ($this->shouldSkipOnboarding($user)) {
            Log::info("Skipping onboarding for user {$user->id} based on criteria");
            return;
        }

        Log::info("Starting onboarding sequence for user {$user->id}");

        $sequence = config('onboarding.sequence', []);
        
        foreach ($sequence as $emailType => $config) {
            if (!($config['enabled'] ?? true)) {
                continue;
            }

            $this->scheduleOnboardingEmail($user, $emailType, $config);
        }
    }

    public function scheduleOnboardingSequence(User $user): void
    {
        $this->startOnboardingSequence($user);
    }

    public function scheduleOnboardingEmail(User $user, string $emailType, array $config): void
    {
        $delay = $config['delay'] ?? 0; // delay in minutes
        
        // Check if this email has already been sent
        if ($this->hasEmailBeenSent($user, $emailType)) {
            Log::info("Email {$emailType} already sent to user {$user->id}, skipping");
            return;
        }

        // Create log entry
        OnboardingEmailLog::create([
            'user_id' => $user->id,
            'email_type' => $emailType,
            'scheduled_at' => now()->addMinutes($delay),
            'status' => 'scheduled',
            'config' => $config
        ]);

        // Queue the email
        SendOnboardingEmail::dispatch($user, $emailType, $config)
            ->delay(now()->addMinutes($delay))
            ->onQueue(config('onboarding.queue.queue_name', 'default'));

        Log::info("Scheduled {$emailType} email for user {$user->id} with {$delay} minute delay");
    }

    public function hasEmailBeenSent(User $user, string $emailType): bool
    {
        return OnboardingEmailLog::where('user_id', $user->id)
            ->where('email_type', $emailType)
            ->whereIn('status', ['sent', 'delivered'])
            ->exists();
    }

    public function markEmailAsSent(User $user, string $emailType): void
    {
        OnboardingEmailLog::where('user_id', $user->id)
            ->where('email_type', $emailType)
            ->where('status', 'scheduled')
            ->update([
                'status' => 'sent',
                'sent_at' => now()
            ]);

        Log::info("Marked {$emailType} email as sent for user {$user->id}");
    }

    public function markEmailAsFailed(User $user, string $emailType, string $error): void
    {
        OnboardingEmailLog::where('user_id', $user->id)
            ->where('email_type', $emailType)
            ->where('status', 'scheduled')
            ->update([
                'status' => 'failed',
                'error_message' => $error,
                'failed_at' => now()
            ]);

        Log::error("Email {$emailType} failed for user {$user->id}: {$error}");
    }

    public function getOnboardingProgress(User $user): array
    {
        $sequence = config('onboarding.sequence', []);
        $logs = OnboardingEmailLog::where('user_id', $user->id)
            ->get()
            ->keyBy('email_type');

        $progress = [];
        
        foreach ($sequence as $emailType => $config) {
            $log = $logs->get($emailType);
            
            $progress[$emailType] = [
                'name' => $config['subject'] ?? $emailType,
                'scheduled_at' => $log?->scheduled_at,
                'sent_at' => $log?->sent_at,
                'status' => $log?->status ?? 'not_scheduled',
                'delay_minutes' => $config['delay'] ?? 0,
            ];
        }

        return $progress;
    }

    public function getEmailVariables(User $user, string $emailType): array
    {
        $baseUrl = config('app.url');
        
        $variables = [
            'user' => $user,
            'analyzeUrl' => $baseUrl,
            'dashboardUrl' => $baseUrl . '/dashboard',
            'docsUrl' => $baseUrl . '/docs',
            'supportUrl' => $baseUrl . '/support',
            'communityUrl' => $baseUrl . '/community',
            'analyzerUrl' => $baseUrl . '/analyzer',
            'tutorialsUrl' => $baseUrl . '/tutorials',
            'tutorialUrl' => $baseUrl . '/tutorial',
            'unsubscribeUrl' => $baseUrl . '/unsubscribe?token=' . $this->generateUnsubscribeToken($user),
        ];

        // Email-specific variables
        switch ($emailType) {
            case 'tutorial':
                $variables['tutorialUrl'] = $baseUrl . '/tutorial';
                break;
                
            case 'features':
                $variables['featuresUrl'] = $baseUrl . '/features';
                break;
                
            case 'tips':
                $variables['securityGuideUrl'] = $baseUrl . '/security-guide';
                break;
                
            case 'feedback':
                $variables['surveyUrl'] = 'https://forms.gle/your-survey-link';
                $variables['callUrl'] = 'https://calendly.com/your-booking-link';
                break;
                
            case 'live_analyzer_welcome':
            case 'live_analyzer_next_steps':
            case 'live_analyzer_conversion':
                $variables['liveAnalyzerUrl'] = $baseUrl . '/#live-analyzer';
                $variables['projectsUrl'] = $baseUrl . '/projects';
                $variables['reportsUrl'] = $baseUrl . '/reports';
                break;
                
            case 'security_alert':
                $variables['securityUrl'] = $baseUrl . '/security';
                $variables['alertsUrl'] = $baseUrl . '/alerts';
                break;
        }

        return $variables;
    }

    private function generateUnsubscribeToken(User $user): string
    {
        return hash_hmac('sha256', $user->id . '|' . $user->email, config('app.key'));
    }

    public function unsubscribeUser(string $token, string $email): bool
    {
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            return false;
        }

        $validToken = $this->generateUnsubscribeToken($user);
        
        if (!hash_equals($validToken, $token)) {
            return false;
        }

        // Mark user as unsubscribed from onboarding emails
        $user->update(['onboarding_emails_enabled' => false]);
        
        // Cancel any pending onboarding emails
        OnboardingEmailLog::where('user_id', $user->id)
            ->where('status', 'scheduled')
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now()
            ]);

        Log::info("User {$user->id} unsubscribed from onboarding emails");
        
        return true;
    }

    public function getStatistics(): array
    {
        $totalUsers = User::count();
        $usersWithOnboarding = OnboardingEmailLog::distinct('user_id')->count();
        
        $emailStats = OnboardingEmailLog::selectRaw('
            email_type,
            status,
            COUNT(*) as count
        ')
        ->groupBy(['email_type', 'status'])
        ->get()
        ->groupBy('email_type');

        $sequence = config('onboarding.sequence', []);
        
        $stats = [
            'total_users' => $totalUsers,
            'users_in_onboarding' => $usersWithOnboarding,
            'completion_rate' => $totalUsers > 0 ? round(($usersWithOnboarding / $totalUsers) * 100, 2) : 0,
            'emails' => []
        ];

        foreach ($sequence as $emailType => $config) {
            $emailData = $emailStats->get($emailType, collect());
            
            $stats['emails'][$emailType] = [
                'name' => $config['subject'] ?? $emailType,
                'scheduled' => $emailData->where('status', 'scheduled')->sum('count'),
                'sent' => $emailData->where('status', 'sent')->sum('count'),
                'failed' => $emailData->where('status', 'failed')->sum('count'),
                'cancelled' => $emailData->where('status', 'cancelled')->sum('count'),
            ];
        }

        return $stats;
    }

    private function shouldSkipOnboarding(User $user): bool
    {
        // Skip for admin accounts
        if (str_contains($user->email, 'admin@') || str_contains($user->email, 'test@')) {
            return true;
        }

        // Skip for demo/system accounts
        if (in_array($user->email, [
            'demo@blockchain-analytics.com',
            'live-analysis@blockchain-analytics.com',
            'system@blockchain-analytics.com'
        ])) {
            return true;
        }

        // Skip if user has already disabled onboarding emails
        if (!$user->onboarding_emails_enabled) {
            return true;
        }

        // Skip if user has complained or bounced
        if ($user->complained_at || $user->email_bounced) {
            return true;
        }

        return false;
    }

    public function getUserPersonalizationData(User $user, string $emailType): array
    {
        $data = [
            'user' => $user,
            'hasAnalyzed' => $this->hasUserAnalyzedContracts($user),
            'analysisCount' => $this->getUserAnalysisCount($user),
            'registrationDate' => $user->created_at,
            'daysSinceRegistration' => $user->created_at->diffInDays(now()),
        ];

        // Email-specific personalization
        switch ($emailType) {
            case 'first_analysis':
                $data['analysisResults'] = $this->getLatestAnalysisResults($user);
                $data['criticalFindings'] = $this->getCriticalFindings($user);
                break;
                
            case 'feedback':
                $data['userStats'] = $this->getUserAnalyticsStats($user);
                break;
        }

        return $data;
    }

    private function hasUserAnalyzedContracts(User $user): bool
    {
        return $user->projects()->where('status', '!=', 'draft')->exists();
    }

    private function getUserAnalysisCount(User $user): int
    {
        return $user->projects()->where('status', '!=', 'draft')->count();
    }

    private function getLatestAnalysisResults(User $user): ?array
    {
        $latestProject = $user->projects()
            ->where('status', '!=', 'draft')
            ->latest()
            ->first();

        if (!$latestProject) {
            return null;
        }

        return [
            'riskScore' => $latestProject->risk_score ?? 50,
            'findingsCount' => $latestProject->analyses()->sum('findings_count') ?? 0,
            'gasEfficiency' => rand(75, 95), // Placeholder
            'contractAddress' => $latestProject->main_contract_address,
        ];
    }

    private function getCriticalFindings(User $user): array
    {
        // Get critical findings from user's analyses
        $findings = $user->projects()
            ->with(['analyses.findings' => function($query) {
                $query->where('severity', 'critical')->limit(3);
            }])
            ->get()
            ->pluck('analyses')
            ->flatten()
            ->pluck('findings')
            ->flatten()
            ->map(function($finding) {
                return [
                    'title' => $finding->title,
                    'description' => $finding->description,
                ];
            })
            ->toArray();

        return array_slice($findings, 0, 2);
    }

    private function getUserAnalyticsStats(User $user): array
    {
        $projects = $user->projects()->where('status', '!=', 'draft')->get();
        
        return [
            'analysesCount' => $projects->count(),
            'vulnerabilitiesFound' => $projects->sum(function($project) {
                return $project->analyses->sum('findings_count');
            }),
            'gasOptimizations' => $projects->count() * rand(3, 8), // Placeholder
        ];
    }
}