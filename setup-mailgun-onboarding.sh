#!/bin/bash

# =============================================================================
# Mailgun Onboarding Email Flow Setup Script
# =============================================================================
# This script helps you set up the complete Mailgun onboarding email flow
# for the AI Blockchain Analytics platform.

set -e

echo "🚀 AI Blockchain Analytics - Mailgun Onboarding Setup"
echo "============================================================="

# Function to add or update environment variable
add_env_var() {
    local var_name="$1"
    local var_value="$2"
    local env_file="${3:-.env}"
    
    if grep -q "^${var_name}=" "$env_file" 2>/dev/null; then
        # Update existing variable
        sed -i "s/^${var_name}=.*/${var_name}=${var_value}/" "$env_file"
        echo "✅ Updated ${var_name} in ${env_file}"
    else
        # Add new variable
        echo "${var_name}=${var_value}" >> "$env_file"
        echo "✅ Added ${var_name} to ${env_file}"
    fi
}

# Check if .env exists
if [ ! -f ".env" ]; then
    echo "❌ .env file not found. Please create one from .env.example first."
    echo "   Run: cp .env.example .env"
    exit 1
fi

echo ""
echo "📧 Setting up Mail Configuration..."

# Add mail configuration
cat >> .env << 'EOF'

# =============================================================================
# Mail Configuration (Mailgun Onboarding)
# =============================================================================

# Mail Driver Configuration
MAIL_MAILER=mailgun
MAIL_FROM_ADDRESS=welcome@ai-blockchain-analytics.com
MAIL_FROM_NAME="AI Blockchain Analytics Team"

# Mailgun Configuration
# Get these from: https://app.mailgun.com/app/domains
MAILGUN_DOMAIN=your-mailgun-domain.mailgun.org
MAILGUN_SECRET=your-mailgun-secret-key
MAILGUN_ENDPOINT=api.mailgun.net

# Onboarding Email Settings
ONBOARDING_ENABLED=true
ONBOARDING_FROM_EMAIL=welcome@ai-blockchain-analytics.com
ONBOARDING_FROM_NAME="AI Blockchain Analytics Team"
ONBOARDING_REPLY_TO=support@ai-blockchain-analytics.com

# Email Queue Configuration
ONBOARDING_QUEUE=emails
ONBOARDING_QUEUE_CONNECTION=redis

# Email Tracking and Analytics
ONBOARDING_TRACK_OPENS=true
ONBOARDING_TRACK_CLICKS=true
ONBOARDING_TRACK_UNSUBSCRIBES=true
ONBOARDING_TRACK_CONVERSIONS=true

# Webhook Configuration (for Mailgun event tracking)
MAILGUN_WEBHOOK_SIGNING_KEY=your-webhook-signing-key

# For Development/Testing (uncomment to use log driver instead)
# MAIL_MAILER=log

EOF

echo "✅ Mail configuration added to .env file"

echo ""
echo "🔧 Next Steps:"
echo "============================================================="
echo "1. 📝 Get Mailgun Credentials:"
echo "   - Sign up at https://mailgun.com"
echo "   - Add and verify your domain"
echo "   - Get your API key from the dashboard"
echo "   - Update MAILGUN_DOMAIN and MAILGUN_SECRET in .env"
echo ""
echo "2. 🗄️  Run Database Migrations:"
echo "   docker compose exec app php artisan migrate"
echo ""
echo "3. 🔄 Start Queue Workers:"
echo "   docker compose exec app php artisan queue:work --queue=emails"
echo ""
echo "4. 🧪 Test the Setup:"
echo "   docker compose exec app php artisan onboarding:test --dry-run"
echo "   docker compose exec app php artisan onboarding:test --email=your@email.com"
echo ""
echo "5. 🌐 Configure Webhooks (Optional but Recommended):"
echo "   - URL: https://yourdomain.com/api/webhooks/mailgun/events"
echo "   - Events: delivered, opened, clicked, unsubscribed, complained, bounced"
echo ""
echo "6. 📊 Monitor Email Performance:"
echo "   - Check logs: docker compose exec app tail -f storage/logs/laravel.log"
echo "   - Database tracking: onboarding_email_logs table"
echo ""

echo "✅ Setup complete! Your Mailgun onboarding flow is ready to use."
echo ""
echo "💡 Pro Tips:"
echo "   - Use the log driver (MAIL_MAILER=log) for development"
echo "   - Test with dry-run mode first"
echo "   - Monitor bounce rates and unsubscribes"
echo "   - Customize email templates in resources/views/emails/onboarding/"
echo ""
echo "🎉 Happy onboarding! Your users will love the automated email flow!"
