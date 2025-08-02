# PostgreSQL Database Schema - AI Blockchain Analytics

## Overview
Comprehensive database schema for AI-powered blockchain analytics platform with support for users, projects, analyses, findings, and sentiments.

## Database Information
- **Database**: `ai_blockchain_analytics` 
- **Engine**: PostgreSQL 16.9
- **Total Size**: 736.00 KB
- **Tables**: 16 tables

## Core Schema Tables

### 1. Users Table (`users`)
**Purpose**: User authentication, profiles, and subscription management
**Size**: 112.00 KB

#### Key Fields:
- `id` - Primary key
- `name`, `email`, `password` - Basic auth fields
- `timezone`, `preferred_language` - Localization
- `profile_data`, `preferences` - JSON user settings
- `last_active_at`, `is_active` - Activity tracking
- `analyses_count`, `projects_count` - Usage tracking
- `analyses_reset_at` - Billing period tracking
- `role` - user, admin, analyst
- `permissions` - JSON role permissions
- **Stripe Integration**: `stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at`

#### Indexes:
- `is_active`, `last_active_at`
- `role`
- `analyses_reset_at`

---

### 2. Projects Table (`projects`)
**Purpose**: Blockchain project management and organization
**Size**: 56.00 KB

#### Key Fields:
- `id` - Primary key
- `user_id` - Foreign key to users
- `name`, `description` - Basic project info
- `blockchain_network` - ethereum, polygon, bsc, etc.
- `project_type` - smart_contract, defi, nft, dao, etc.
- `contract_addresses` - JSON array of contract addresses
- `main_contract_address`, `token_address`, `token_symbol` - Primary contract info
- `metadata`, `website_url`, `github_url`, `social_links` - Project metadata
- `analyses_count`, `critical_findings_count` - Analysis tracking
- `average_sentiment_score`, `last_analyzed_at` - Results tracking
- `status` - active, archived, analyzing
- `is_public`, `monitoring_enabled` - Visibility and monitoring
- `risk_level`, `risk_score`, `risk_updated_at` - Risk assessment
- `tags`, `category` - Categorization

#### Indexes:
- `blockchain_network`, `project_type`
- `status`, `is_public`
- `risk_level`
- `last_analyzed_at`
- `main_contract_address`

---

### 3. Analyses Table (`analyses`)
**Purpose**: Comprehensive tracking of all analysis operations
**Size**: 72.00 KB

#### Key Fields:
- `id` - Primary key
- `project_id` - Foreign key to projects
- `engine` - Analysis engine (security, sentiment, etc.)
- `analysis_type` - full, quick, deep, custom
- `target_type` - contract, transaction, address, token
- `target_address` - The address being analyzed
- `configuration` - JSON analysis parameters
- `version`, `priority` - Engine version and priority
- `status` - pending, running, completed, failed, cancelled, archived
- `started_at`, `completed_at`, `failed_at` - Execution timing
- `duration_seconds`, `error_message`, `error_details` - Error tracking
- `findings_count`, `critical_findings_count`, `high_findings_count` - Results summary
- `sentiment_score`, `risk_score` - Calculated scores
- `gas_analyzed`, `transactions_analyzed`, `contracts_analyzed` - Performance metrics
- `triggered_by`, `triggered_by_user_id` - Execution context
- `verified`, `verified_by_user_id`, `verified_at` - Quality assurance
- `archived`, `archived_at`, `expires_at` - Data retention

#### Indexes:
- `status`, `priority`
- `engine`, `analysis_type`
- `target_type`, `target_address`
- `started_at`, `completed_at`
- `triggered_by`, `triggered_by_user_id`
- `verified`, `archived`
- `expires_at`

---

### 4. Findings Table (`findings`)
**Purpose**: Detailed security findings and vulnerability tracking
**Size**: 80.00 KB

#### Key Fields:
- `id` - Primary key
- `analysis_id` - Foreign key to analyses
- `severity` - critical, high, medium, low, info
- `category` - security, performance, gas, logic, compliance
- `subcategory` - reentrancy, overflow, access_control, etc.
- `finding_type` - vulnerability, optimization, warning, info
- `title`, `description` - Finding details
- `cwe_id`, `owasp_category` - Security standards mapping
- `cvss_score`, `attack_vector`, `attack_complexity` - CVSS scoring
- `contract_address`, `function_name`, `function_signature` - Code location
- `line`, `line_start`, `line_end`, `code_snippet` - Code references
- `gas_cost`, `economic_impact`, `currency` - Economic assessment
- `likelihood`, `impact` - Risk assessment
- `exploitation_scenario`, `business_impact` - Impact analysis
- `recommendation`, `remediation_effort`, `fix_code` - Remediation guidance
- `detection_method`, `confidence_score` - Detection metadata
- `false_positive`, `false_positive_reason` - Quality control
- `status` - open, confirmed, fixed, ignored, duplicate
- `assigned_to_user_id`, `acknowledged_at`, `fixed_at` - Workflow tracking
- `duplicate_of_id`, `similarity_hash` - Deduplication

#### Indexes:
- `severity`, `category`
- `status`, `assigned_to_user_id`
- `contract_address`, `function_name`
- `false_positive`, `duplicate_of_id`
- `finding_type`, `subcategory`
- `cvss_score`, `likelihood`, `impact`
- `similarity_hash`
- `acknowledged_at`, `fixed_at`

---

### 5. Sentiments Table (`sentiments`)
**Purpose**: Advanced sentiment analysis and emotion tracking
**Size**: 72.00 KB

#### Key Fields:
- `id` - Primary key
- `analysis_id` - Foreign key to analyses
- `source_type` - code, comment, documentation, community
- `source_reference`, `source_text` - Source context
- `score`, `magnitude` - Overall sentiment scores
- `positive_score`, `negative_score`, `neutral_score`, `mixed_score` - Detailed breakdown
- `emotions`, `dominant_emotion`, `emotion_intensity` - Emotion detection
- `language`, `language_confidence` - Language detection
- `processing_model`, `model_version` - AI model tracking
- `security_sentiment`, `performance_sentiment`, `usability_sentiment`, `trust_sentiment` - Domain-specific sentiments
- `keywords`, `themes`, `entities` - Content analysis
- `context`, `text_length`, `word_count`, `sentence_count` - Text metadata
- `confidence_score`, `quality_rating` - Quality metrics
- `requires_review`, `review_notes` - Manual review flags
- `analyzed_at`, `processing_time_ms` - Processing metrics
- `baseline_score`, `trend_change`, `trend_direction` - Trending analysis
- `analysis_details`, `raw_response` - Detailed results

#### Indexes:
- `source_type`, `language`
- `dominant_emotion`, `emotion_intensity`
- `confidence_score`, `quality_rating`
- `requires_review`
- `analyzed_at`
- `trend_direction`
- `processing_model`, `model_version`

---

## Subscription Management Tables

### 6. Subscription Plans (`subscription_plans`)
**Purpose**: Stripe-integrated subscription plan management
- Plan tiers: Starter, Professional, Enterprise
- Monthly/yearly billing with discounts
- Feature limits and analysis quotas

### 7. Subscriptions (`subscriptions`)
**Purpose**: Laravel Cashier subscription tracking
- Stripe subscription management
- Billing cycle tracking
- Plan changes and cancellations

### 8. Subscription Items (`subscription_items`)
**Purpose**: Individual subscription item tracking
- Per-item billing details
- Usage-based billing support

---

## System Tables

### 9. Cache Tables (`cache`, `cache_locks`)
**Purpose**: Application-level caching
- Performance optimization
- Session management

### 10. Job Management (`jobs`, `failed_jobs`, `job_batches`)
**Purpose**: Background job processing
- Analysis queue management
- Failed job tracking
- Batch job processing

### 11. User Management (`password_reset_tokens`, `sessions`)
**Purpose**: Authentication and session management
- Password reset functionality
- Active session tracking

---

## Relationships

```
users (1) -> (many) projects
projects (1) -> (many) analyses
analyses (1) -> (many) findings
analyses (1) -> (many) sentiments

users (1) -> (many) subscriptions
subscription_plans (1) -> (many) subscriptions

findings (self-referential) -> duplicate_of_id
users -> findings (assigned_to_user_id)
users -> analyses (triggered_by_user_id, verified_by_user_id)
```

## Key Features

### 🔐 **Security & Compliance**
- OWASP Top 10 mapping
- CWE (Common Weakness Enumeration) tracking
- CVSS scoring system
- Attack vector analysis

### 📊 **Advanced Analytics**
- Multi-engine analysis support
- Real-time sentiment tracking
- Risk scoring algorithms
- Performance metrics

### 🚀 **Scalability**
- Comprehensive indexing strategy
- JSON fields for flexible metadata
- Archival and retention policies
- Queue-based processing

### 💼 **Enterprise Features**
- Role-based access control
- Multi-tenant architecture
- Audit trails and verification
- Integration-ready APIs

## Performance Optimizations

- **Strategic Indexing**: 30+ indexes for optimal query performance
- **JSON Storage**: Flexible metadata storage without schema changes
- **Archival System**: Automated data lifecycle management
- **Queue Processing**: Background analysis processing
- **Caching Layer**: Redis-backed caching for frequent queries

## Data Retention

- **Analyses**: Configurable expiration dates
- **Findings**: Permanent retention with archival flags
- **Sentiments**: Trend-based retention
- **User Data**: GDPR-compliant deletion support