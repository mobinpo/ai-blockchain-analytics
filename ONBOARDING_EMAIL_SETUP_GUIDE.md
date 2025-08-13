# 📧 Onboarding Email Flow Setup Guide (Mailgun)

## ✅ Implementation Complete

Your AI Blockchain Analytics platform now has a complete onboarding email flow using Mailgun! Here's everything that's been implemented:

### 🎯 Features Implemented

#### 📨 **Email Sequence (5 Emails)**
1. **Welcome Email** - Immediate (0 minutes)
2. **Tutorial Email** - 1 hour later (60 minutes)  
3. **Features Email** - 24 hours later (1440 minutes)
4. **Tips Email** - 3 days later (4320 minutes)
5. **Feedback Email** - 7 days later (10080 minutes)

#### 🛠 **Core Components**
- ✅ Mailgun SDK integration with Laravel Mailer
- ✅ Email templates with professional design
- ✅ Queue-based email sending system
- ✅ User preference management
- ✅ Unsubscribe functionality
- ✅ Email delivery tracking & analytics
- ✅ API endpoints for management
- ✅ Event-driven registration triggers
- ✅ Comprehensive error handling & retry logic

## 🚀 Quick Start

### 1. Configure Mailgun

Copy the example environment file:
```bash
cp .env.mailgun.example .env.mailgun
```

Get your credentials from [Mailgun Dashboard](https://app.mailgun.com/) and update:
```env
MAILGUN_DOMAIN=your-domain.mailgun.org
MAILGUN_SECRET=key-1234567890abcdef1234567890abcdef
MAIL_MAILER=mailgun
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="AI Blockchain Analytics"
```

### 2. Setup Database

Run the migrations:
```bash
php artisan migrate
```

### 3. Configure Queue Worker

Start the queue worker to process emails:
```bash
php artisan queue:work --queue=onboarding-emails
```

Or add to supervisor config:
```ini
[program:onboarding-emails]
command=php /path/to/your/app/artisan queue:work --queue=onboarding-emails
autostart=true
autorestart=true
```

### 4. Test the System

```bash
# Test all components
php artisan onboarding:test

# Test specific email type
php artisan onboarding:test --email=your@email.com --type=welcome

# Test different email types
php artisan onboarding:test --type=tutorial
php artisan onboarding:test --type=features
php artisan onboarding:test --type=tips
php artisan onboarding:test --type=feedback
```

## 📁 File Structure

```
app/
├── Console/Commands/
│   └── TestOnboardingEmails.php          # Testing command
├── Http/Controllers/Api/
│   └── OnboardingEmailController.php     # API endpoints
├── Jobs/
│   └── SendOnboardingEmail.php          # Email queue job
├── Listeners/
│   └── StartUserOnboardingSequence.php  # Registration listener
├── Models/
│   ├── OnboardingEmailLog.php           # Email logging model
│   └── User.php                         # Updated with relationship
├── Providers/
│   └── AppServiceProvider.php           # Event registration
└── Services/
    └── OnboardingEmailService.php       # Core email service

config/
└── onboarding.php                       # Configuration file

database/migrations/
├── 2025_08_08_080353_create_onboarding_email_logs_table.php
└── 2025_08_08_080524_add_onboarding_emails_enabled_to_users_table.php

resources/views/emails/
├── layout.blade.php                     # Email layout template
└── onboarding/
    ├── welcome.blade.php               # Welcome email
    ├── tutorial.blade.php              # Tutorial email  
    ├── features.blade.php              # Features email
    ├── tips.blade.php                  # Tips email
    └── feedback.blade.php              # Feedback email
```

## 🔧 API Endpoints

### Authenticated Routes (`/api/onboarding/`)
```php
GET    /progress                  # Get user's onboarding progress
PUT    /preferences              # Update email preferences  
POST   /resend                   # Resend specific email
GET    /statistics               # Get onboarding statistics
POST   /test                     # Test email generation
```

### Public Routes
```php
POST   /unsubscribe              # Unsubscribe from emails
POST   /webhook                  # Mailgun webhook handler
```

## 📊 Usage Examples

### API Usage

**Get onboarding progress:**
```javascript
fetch('/api/onboarding/progress', {
    headers: { 'Authorization': 'Bearer ' + token }
})
.then(response => response.json())
.then(data => console.log(data.progress));
```

**Update email preferences:**
```javascript
fetch('/api/onboarding/preferences', {
    method: 'PUT',
    headers: { 
        'Authorization': 'Bearer ' + token,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({ onboarding_emails_enabled: false })
});
```

**Resend specific email:**
```javascript
fetch('/api/onboarding/resend', {
    method: 'POST',
    headers: { 
        'Authorization': 'Bearer ' + token,
        'Content-Type': 'application/json' 
    },
    body: JSON.stringify({ email_type: 'welcome' })
});
```

### Service Usage

```php
use App\Services\OnboardingEmailService;

// Start onboarding for new user
$onboardingService->startOnboardingSequence($user);

// Check if email was sent
$wasSent = $onboardingService->hasEmailBeenSent($user, 'welcome');

// Get user progress
$progress = $onboardingService->getOnboardingProgress($user);

// Unsubscribe user
$success = $onboardingService->unsubscribeUser($token, $email);
```

## 🎨 Email Customization

### Modify Email Templates

Templates are in `resources/views/emails/onboarding/`:

1. **Layout** (`emails/layout.blade.php`) - Shared design
2. **Welcome** (`onboarding/welcome.blade.php`) - First email
3. **Tutorial** (`onboarding/tutorial.blade.php`) - How-to guide  
4. **Features** (`onboarding/features.blade.php`) - Platform features
5. **Tips** (`onboarding/tips.blade.php`) - Best practices
6. **Feedback** (`onboarding/feedback.blade.php`) - Survey request

### Customize Email Sequence

Edit `config/onboarding.php`:

```php
'sequence' => [
    'welcome' => [
        'delay' => 0,  // Send immediately
        'subject' => 'Your custom subject',
        'template' => 'emails.onboarding.custom-welcome',
        'enabled' => true,
    ],
    'custom_email' => [
        'delay' => 2880, // 2 days
        'subject' => 'New custom email',
        'template' => 'emails.onboarding.custom',
        'enabled' => true,
    ]
]
```

## 📈 Analytics & Tracking

### Mailgun Tracking Features
- ✅ Email opens tracking
- ✅ Link click tracking  
- ✅ Delivery confirmations
- ✅ Bounce handling
- ✅ Campaign tagging

### Database Logging
All email events are logged in `onboarding_email_logs` table:
- Scheduled, sent, delivered, failed, cancelled statuses
- Error messages for debugging
- Timing information
- Configuration snapshots

## 🔍 Monitoring & Debugging

### View Email Logs
```bash
# View recent logs
tail -f storage/logs/laravel.log | grep onboarding

# Check queue status
php artisan queue:status

# View failed jobs
php artisan queue:failed
```

### Database Queries
```sql
-- Check user onboarding status
SELECT * FROM onboarding_email_logs WHERE user_id = 1;

-- Email performance stats
SELECT 
    email_type,
    COUNT(*) as total,
    COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent,
    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed
FROM onboarding_email_logs 
GROUP BY email_type;
```

## 🚨 Troubleshooting

### Common Issues

1. **Emails not sending**
   - Check Mailgun credentials in `.env`
   - Verify queue worker is running
   - Check `failed_jobs` table

2. **Template not found errors**
   - Run `php artisan onboarding:test` to verify templates
   - Check file paths in `resources/views/emails/onboarding/`

3. **Database connection errors**
   - Run migrations: `php artisan migrate`
   - Check database configuration

4. **User not receiving emails**
   - Check `onboarding_emails_enabled` flag on user
   - Verify email address is correct
   - Check spam folder

## 🎯 Next Steps

1. **Set up Mailgun domain** and DNS records
2. **Configure webhooks** in Mailgun dashboard  
3. **Customize email templates** with your branding
4. **Set up monitoring** for email delivery rates
5. **A/B test** different email content and timing

## 🔗 Related Documentation

- [Mailgun Documentation](https://documentation.mailgun.com/)
- [Laravel Mail Documentation](https://laravel.com/docs/mail)
- [Laravel Queues Documentation](https://laravel.com/docs/queues)

---

**🎉 Your onboarding email flow is now ready to welcome new users with professional, automated email sequences!**