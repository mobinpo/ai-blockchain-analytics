<?php

/**
 * AI Blockchain Analytics - Onboarding Email Configuration v0.9.0
 * 
 * Comprehensive email automation for user onboarding and engagement
 * Optimized for blockchain analytics platform with Mailgun integration
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Onboarding Configuration
    |--------------------------------------------------------------------------
    */

    'enabled' => env('ONBOARDING_ENABLED', true),
    'from_email' => env('ONBOARDING_FROM_EMAIL', env('MAIL_FROM_ADDRESS', 'welcome@ai-blockchain-analytics.com')),
    'from_name' => env('ONBOARDING_FROM_NAME', 'AI Blockchain Analytics Team'),
    
    /*
    |--------------------------------------------------------------------------
    | Mailgun Configuration
    |--------------------------------------------------------------------------
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'tracking' => env('MAILGUN_TRACKING', true),
        'click_tracking' => env('MAILGUN_CLICK_TRACKING', true),
        'open_tracking' => env('MAILGUN_OPEN_TRACKING', true),
        'tags' => [
            'onboarding',
            'ai-blockchain-analytics',
            'v0.9.0'
        ],
        'test_mode' => env('MAILGUN_TEST_MODE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */

    'queue' => [
        'queue_name' => env('ONBOARDING_QUEUE', 'emails'),
        'connection' => env('ONBOARDING_QUEUE_CONNECTION', 'redis'),
        'retry_attempts' => 3,
        'retry_delay' => 300, // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Sequence Configuration
    |--------------------------------------------------------------------------
    | 
    | Comprehensive onboarding sequence optimized for user engagement
    | and conversion. Each email is strategically timed and personalized.
    */

    'sequence' => [

        // === IMMEDIATE WELCOME SERIES ===

        'welcome' => [
            'enabled' => true,
            'delay' => 0, // Send immediately
            'subject' => '🚀 Welcome to AI Blockchain Analytics - Your Smart Contract Security Journey Begins!',
            'template' => 'emails.onboarding.welcome',
            'priority' => 'high',
            'tags' => ['welcome', 'immediate'],
            'personalization' => [
                'include_user_stats' => false,
                'include_platform_stats' => true,
                'include_quick_start' => true,
            ],
        ],

        'quick_start' => [
            'enabled' => true,
            'delay' => 30, // 30 minutes after registration
            'subject' => '⚡ Quick Start: Analyze Your First Contract in 60 Seconds',
            'template' => 'emails.onboarding.quick-start',
            'priority' => 'high',
            'tags' => ['quick-start', 'tutorial'],
            'personalization' => [
                'include_famous_contracts' => true,
                'include_video_tutorial' => true,
                'include_live_analyzer_link' => true,
            ],
        ],

        // === EDUCATIONAL SERIES (Days 1-3) ===

        'tutorial' => [
            'enabled' => true,
            'delay' => 1440, // 24 hours (1 day)
            'subject' => '📚 Master Smart Contract Analysis: Step-by-Step Tutorial',
            'template' => 'emails.onboarding.tutorial',
            'priority' => 'normal',
            'tags' => ['education', 'tutorial'],
            'personalization' => [
                'include_progress_tracker' => true,
                'include_interactive_examples' => true,
                'include_certification_path' => false,
            ],
        ],

        'security_insights' => [
            'enabled' => true,
            'delay' => 2880, // 48 hours (2 days)
            'subject' => '🔒 Security Insights: Learn from Real DeFi Exploits ($570M+ Analyzed)',
            'template' => 'emails.onboarding.security-insights',
            'priority' => 'normal',
            'tags' => ['security', 'education'],
            'personalization' => [
                'include_exploit_case_studies' => true,
                'include_prevention_tips' => true,
                'include_industry_stats' => true,
            ],
        ],

        'features' => [
            'enabled' => true,
            'delay' => 4320, // 72 hours (3 days)
            'subject' => '🌟 Discover Advanced Features: Gas Optimization, Multi-Chain, & More',
            'template' => 'emails.onboarding.features',
            'priority' => 'normal',
            'tags' => ['features', 'advanced'],
            'personalization' => [
                'include_feature_matrix' => true,
                'include_comparison_chart' => false,
                'include_upgrade_path' => true,
            ],
        ],

        // === ENGAGEMENT SERIES (Week 1) ===

        'first_analysis_followup' => [
            'enabled' => true,
            'delay' => 10080, // 7 days (1 week)
            'subject' => '📊 How was your first analysis? Here\'s what to do next...',
            'template' => 'emails.onboarding.first-analysis-followup',
            'priority' => 'normal',
            'tags' => ['followup', 'engagement'],
            'conditions' => [
                'has_analyzed' => true, // Only send if user has analyzed contracts
            ],
            'personalization' => [
                'include_analysis_summary' => true,
                'include_improvement_tips' => true,
                'include_next_steps' => true,
            ],
        ],

        'no_analysis_nudge' => [
            'enabled' => true,
            'delay' => 10080, // 7 days (1 week)
            'subject' => '🚀 Still haven\'t tried our analyzer? Here\'s a 2-minute demo...',
            'template' => 'emails.onboarding.no-analysis-nudge',
            'priority' => 'normal',
            'tags' => ['nudge', 'conversion'],
            'conditions' => [
                'has_analyzed' => false, // Only send if user hasn't analyzed contracts
            ],
            'personalization' => [
                'include_demo_video' => true,
                'include_famous_examples' => true,
                'include_success_stories' => true,
            ],
        ],

        // === VALUE DEMONSTRATION (Week 2) ===

        'case_studies' => [
            'enabled' => true,
            'delay' => 20160, // 14 days (2 weeks)
            'subject' => '💡 Case Study: How We Prevented a $50M Smart Contract Exploit',
            'template' => 'emails.onboarding.case-studies',
            'priority' => 'normal',
            'tags' => ['case-study', 'value-demo'],
            'personalization' => [
                'include_detailed_analysis' => true,
                'include_prevention_steps' => true,
                'include_industry_impact' => true,
            ],
        ],

        'community' => [
            'enabled' => true,
            'delay' => 30240, // 21 days (3 weeks)
            'subject' => '👥 Join 10,000+ Smart Contract Developers in Our Community',
            'template' => 'emails.onboarding.community',
            'priority' => 'normal',
            'tags' => ['community', 'social'],
            'personalization' => [
                'include_community_stats' => true,
                'include_recent_discussions' => true,
                'include_expert_amas' => false,
            ],
        ],

        // === RETENTION & FEEDBACK (Month 1) ===

        'tips' => [
            'enabled' => true,
            'delay' => 43200, // 30 days (1 month)
            'subject' => '🎯 Pro Tips: 5 Advanced Security Patterns Every Developer Should Know',
            'template' => 'emails.onboarding.tips',
            'priority' => 'normal',
            'tags' => ['tips', 'advanced', 'retention'],
            'personalization' => [
                'include_advanced_patterns' => true,
                'include_code_examples' => true,
                'include_best_practices' => true,
            ],
        ],

        'feedback' => [
            'enabled' => true,
            'delay' => 50400, // 35 days
            'subject' => '💬 Quick Question: How can we make AI Blockchain Analytics better for you?',
            'template' => 'emails.onboarding.feedback',
            'priority' => 'normal',
            'tags' => ['feedback', 'survey'],
            'personalization' => [
                'include_usage_stats' => true,
                'include_survey_link' => true,
                'include_calendar_booking' => true,
            ],
        ],

        // === SPECIAL SEQUENCES ===

        'live_analyzer_welcome' => [
            'enabled' => true,
            'delay' => 0, // Immediate for live analyzer users
            'subject' => '🎉 Thanks for trying our Live Analyzer! Here\'s what you can do next...',
            'template' => 'emails.onboarding.live-analyzer-welcome',
            'priority' => 'high',
            'tags' => ['live-analyzer', 'conversion'],
            'conditions' => [
                'used_live_analyzer' => true,
            ],
            'personalization' => [
                'include_analysis_results' => true,
                'include_registration_benefits' => true,
                'include_upgrade_path' => true,
            ],
        ],

        'live_analyzer_next_steps' => [
            'enabled' => true,
            'delay' => 1440, // 24 hours after live analyzer usage
            'subject' => '🚀 Ready to unlock the full power of smart contract analysis?',
            'template' => 'emails.onboarding.live-analyzer-next-steps',
            'priority' => 'normal',
            'tags' => ['live-analyzer', 'conversion'],
            'conditions' => [
                'used_live_analyzer' => true,
                'is_registered' => false,
            ],
            'personalization' => [
                'include_feature_comparison' => true,
                'include_success_metrics' => true,
                'include_limited_time_offer' => false,
            ],
        ],

        'security_alert' => [
            'enabled' => true,
            'delay' => 0, // Immediate for critical security findings
            'subject' => '🚨 URGENT: Critical Security Vulnerability Detected in Your Contract',
            'template' => 'emails.onboarding.security-alert',
            'priority' => 'critical',
            'tags' => ['security-alert', 'critical'],
            'conditions' => [
                'has_critical_findings' => true,
            ],
            'personalization' => [
                'include_vulnerability_details' => true,
                'include_immediate_actions' => true,
                'include_expert_consultation' => true,
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Personalization Settings
    |--------------------------------------------------------------------------
    */

    'personalization' => [
        'include_user_name' => true,
        'include_registration_date' => true,
        'include_platform_stats' => true,
        'include_user_progress' => true,
        'dynamic_content' => true,
        'a_b_testing' => env('ONBOARDING_AB_TESTING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics & Tracking
    |--------------------------------------------------------------------------
    */

    'analytics' => [
        'track_opens' => true,
        'track_clicks' => true,
        'track_unsubscribes' => true,
        'track_bounces' => true,
        'track_complaints' => true,
        'google_analytics' => env('ONBOARDING_GA_TRACKING', false),
        'mixpanel' => env('ONBOARDING_MIXPANEL_TRACKING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Unsubscribe & Preferences
    |--------------------------------------------------------------------------
    */

    'unsubscribe' => [
        'allow_granular' => true, // Allow users to unsubscribe from specific email types
        'one_click' => true, // Support one-click unsubscribe
        'confirmation_required' => false,
        'feedback_collection' => true,
        'resubscribe_allowed' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting & Delivery
    |--------------------------------------------------------------------------
    */

    'delivery' => [
        'max_emails_per_hour' => 1000,
        'max_emails_per_day' => 5000,
        'respect_user_timezone' => true,
        'optimal_send_hours' => [9, 10, 11, 14, 15, 16], // 9 AM - 4 PM
        'avoid_weekends' => false,
        'retry_failed_emails' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | A/B Testing Configuration
    |--------------------------------------------------------------------------
    */

    'ab_testing' => [
        'enabled' => env('ONBOARDING_AB_TESTING', false),
        'tests' => [
            'welcome_subject_line' => [
                'variants' => [
                    'A' => '🚀 Welcome to AI Blockchain Analytics - Your Smart Contract Security Journey Begins!',
                    'B' => '🔒 Secure Your Smart Contracts - Welcome to AI Blockchain Analytics!',
                ],
                'traffic_split' => 50, // 50/50 split
            ],
            'quick_start_timing' => [
                'variants' => [
                    'A' => 30, // 30 minutes
                    'B' => 120, // 2 hours
                ],
                'traffic_split' => 50,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Customization
    |--------------------------------------------------------------------------
    */

    'content' => [
        'platform_name' => 'AI Blockchain Analytics',
        'platform_url' => env('APP_URL', 'https://ai-blockchain-analytics.com'),
        'support_email' => env('SUPPORT_EMAIL', 'support@ai-blockchain-analytics.com'),
        'company_name' => 'AI Blockchain Analytics Inc.',
        'company_address' => '123 Blockchain Ave, Crypto City, CC 12345',
        'social_links' => [
            'twitter' => 'https://twitter.com/aiblockchain',
            'linkedin' => 'https://linkedin.com/company/ai-blockchain-analytics',
            'github' => 'https://github.com/ai-blockchain-analytics',
            'discord' => 'https://discord.gg/aiblockchain',
        ],
        'branding' => [
            'logo_url' => env('APP_URL') . '/images/logo.png',
            'primary_color' => '#3B82F6',
            'secondary_color' => '#8B5CF6',
            'accent_color' => '#10B981',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Success Metrics & KPIs
    |--------------------------------------------------------------------------
    */

    'metrics' => [
        'target_open_rate' => 35, // 35%
        'target_click_rate' => 8, // 8%
        'target_conversion_rate' => 15, // 15% of recipients should analyze a contract
        'target_retention_rate' => 60, // 60% should still be active after 30 days
        'benchmark_against' => 'saas_industry', // Industry benchmarks
    ],

];