<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class OnboardingEmailSeeder extends Seeder
{
    public function run(): void
    {
        // Create onboarding email campaign
        $campaignId = DB::table('email_campaigns')->insertGetId([
            'name' => 'User Onboarding Sequence',
            'type' => 'onboarding',
            'description' => 'Automated email sequence for new user onboarding',
            'configuration' => json_encode([
                'total_emails' => 5,
                'duration_days' => 7,
                'trigger' => 'user_registration',
                'frequency' => 'sequence'
            ]),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create email sequences
        $sequences = [
            [
                'name' => 'Welcome Email',
                'template' => 'onboarding.welcome',
                'subject' => '🚀 Welcome to AI Blockchain Analytics!',
                'delay_minutes' => 0,
                'order' => 1,
            ],
            [
                'name' => 'Getting Started Guide',
                'template' => 'onboarding.getting-started',
                'subject' => '📊 Ready to analyze your first smart contract?',
                'delay_minutes' => 60, // 1 hour
                'order' => 2,
            ],
            [
                'name' => 'First Analysis Follow-up',
                'template' => 'onboarding.first-analysis',
                'subject' => '🎯 How\'s your smart contract analysis going?',
                'delay_minutes' => 1440, // 24 hours
                'order' => 3,
                'conditions' => json_encode([
                    'check_analysis_status' => true,
                    'send_different_content_based_on_usage' => true
                ])
            ],
            [
                'name' => 'Advanced Features',
                'template' => 'onboarding.advanced-features',
                'subject' => '🚀 Unlock powerful advanced features!',
                'delay_minutes' => 4320, // 3 days
                'order' => 4,
                'conditions' => json_encode([
                    'require_user_activity' => true
                ])
            ],
            [
                'name' => 'Feedback Request',
                'template' => 'onboarding.feedback',
                'subject' => '💭 How has your experience been so far?',
                'delay_minutes' => 10080, // 7 days
                'order' => 5,
            ],
        ];

        foreach ($sequences as $sequence) {
            DB::table('email_sequences')->insert(array_merge($sequence, [
                'campaign_id' => $campaignId,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $this->command->info('Successfully seeded onboarding email campaign with 5 sequences');
        
        // Create sample email preferences for demo
        $this->createSamplePreferences();
    }

    private function createSamplePreferences(): void
    {
        // Create default preferences template
        DB::table('user_email_preferences')->insert([
            'user_id' => 1, // Assuming first user exists
            'marketing_emails' => true,
            'product_updates' => true,
            'security_alerts' => true,
            'onboarding_emails' => true,
            'weekly_digest' => true,
            'frequency' => 'normal',
            'last_updated' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Created sample email preferences');
    }
}