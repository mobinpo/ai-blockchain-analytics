# 🎯 Stripe Sandbox Integration - Complete Setup Guide

## Overview

Your AI Blockchain Analytics platform now has a fully integrated Stripe sandbox billing system with Laravel Cashier. This guide will walk you through the complete setup and testing process.

## ✅ What's Already Configured

### Backend Integration
- ✅ **Laravel Cashier v15.7** - Latest version installed
- ✅ **User Model** - Billable trait configured
- ✅ **Database Migrations** - All Cashier tables created
- ✅ **Subscription Plans** - 3-tier pricing structure
- ✅ **Billing Controller** - Complete subscription management
- ✅ **Usage Tracking** - Monitor API calls, tokens, and analyses
- ✅ **Webhook Handling** - Stripe event processing
- ✅ **Environment Configuration** - Sandbox keys configured

### Frontend Components
- ✅ **Billing Dashboard** - Subscription overview and usage
- ✅ **Plans Page** - Interactive plan selection with Stripe Elements
- ✅ **Payment Forms** - Secure payment method collection
- ✅ **Usage Visualization** - Progress bars and billing history

### Developer Tools
- ✅ **Setup Script** - Automated Stripe product creation
- ✅ **Test Script** - Integration testing and validation
- ✅ **Test Cards** - Complete test card collection

---

## 🚀 Quick Start Guide

### Step 1: Get Your Stripe Keys

1. **Sign up for Stripe** (if you haven't already):
   ```
   https://dashboard.stripe.com/register
   ```

2. **Get your test API keys**:
   - Go to: https://dashboard.stripe.com/test/apikeys
   - Copy your **Publishable key** (pk_test_...)
   - Copy your **Secret key** (sk_test_...)

### Step 2: Configure Environment

Update your `.env` file with your actual Stripe keys:

```bash
# Replace these with your actual Stripe test keys
STRIPE_KEY=pk_test_your_actual_publishable_key_here
STRIPE_SECRET=sk_test_your_actual_secret_key_here
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret_here

# Cashier Configuration (already set)
CASHIER_CURRENCY=usd
CASHIER_CURRENCY_LOCALE=en_US
CASHIER_LOGGER=stack
```

### Step 3: Create Stripe Products

Run the automated setup script:

```bash
# Navigate to your project directory
cd /home/mobin/PhpstormProjects/ai_blockchain_analytics

# Run the Stripe setup script
php scripts/setup-stripe-sandbox.php
```

**What this script does:**
- Creates 3 products (Starter, Professional, Enterprise)
- Creates monthly and yearly pricing for each plan
- Generates the price IDs for your `.env` file
- Provides webhook configuration details

### Step 4: Update Environment with Price IDs

After running the setup script, update your `.env` with the generated price IDs:

```bash
# Copy these from the setup script output
STRIPE_PRICE_STARTER_MONTHLY=price_1xxxxxxx
STRIPE_PRICE_STARTER_YEARLY=price_1xxxxxxx
STRIPE_PRICE_PROFESSIONAL_MONTHLY=price_1xxxxxxx
STRIPE_PRICE_PROFESSIONAL_YEARLY=price_1xxxxxxx
STRIPE_PRICE_ENTERPRISE_MONTHLY=price_1xxxxxxx
STRIPE_PRICE_ENTERPRISE_YEARLY=price_1xxxxxxx
```

### Step 5: Configure Webhooks

1. **Go to Stripe Dashboard**:
   ```
   https://dashboard.stripe.com/test/webhooks
   ```

2. **Create a new webhook endpoint**:
   - Endpoint URL: `https://yourdomain.com/stripe/webhook`
   - For local testing: `https://your-ngrok-url.ngrok.io/stripe/webhook`

3. **Select these events**:
   ```
   customer.subscription.created
   customer.subscription.updated
   customer.subscription.deleted
   customer.subscription.trial_will_end
   invoice.payment_succeeded
   invoice.payment_failed
   payment_method.attached
   customer.updated
   setup_intent.succeeded
   ```

4. **Copy the webhook secret** and add it to your `.env`:
   ```bash
   STRIPE_WEBHOOK_SECRET=whsec_your_actual_webhook_secret_here
   ```

### Step 6: Test the Integration

Run the test script to verify everything is working:

```bash
php scripts/test-stripe-integration.php
```

---

## 💳 Test Payment Methods

Use these test cards for different scenarios:

### Successful Payments
```
Visa: 4242424242424242
Visa Debit: 4000056655665556
Mastercard: 5555555555554444
```

### Failed Payments
```
Generic Decline: 4000000000000002
Insufficient Funds: 4000000000009995
```

**All test cards use:**
- Expiry: Any future date (e.g., 12/25)
- CVC: Any 3 digits (e.g., 123)
- ZIP: Any 5 digits (e.g., 12345)

---

## 🎯 Subscription Plans

### Starter Plan - $29/month ($290/year)
- **Analysis Limit**: 10 per month
- **API Calls**: 1,000 per month
- **AI Tokens**: 50,000 per month
- **Projects**: 5
- **Features**: Basic reporting, vulnerability scanning

### Professional Plan - $99/month ($990/year)
- **Analysis Limit**: 100 per month
- **API Calls**: 10,000 per month
- **AI Tokens**: 500,000 per month
- **Projects**: 25
- **Features**: Advanced reporting, team collaboration, webhooks

### Enterprise Plan - $299/month ($2,990/year)
- **Analysis Limit**: 1,000 per month
- **API Calls**: 100,000 per month
- **AI Tokens**: 5,000,000 per month
- **Projects**: Unlimited
- **Features**: SSO, compliance reporting, dedicated support

---

## 🧪 Testing Workflows

### 1. User Registration and Trial
```bash
# Create a new user account
# User automatically gets 14-day trial
# No payment method required for trial
```

### 2. Plan Upgrade
```bash
# Navigate to /billing/plans
# Select a plan and billing interval
# Enter test payment information
# Complete subscription
```

### 3. Usage Tracking
```bash
# Perform API calls or analyses
# Check /billing dashboard for usage updates
# Verify overage calculations
```

### 4. Subscription Management
```bash
# Test plan changes (upgrade/downgrade)
# Test cancellation and reactivation
# Test payment method updates
```

### 5. Webhook Testing
```bash
# Monitor webhook deliveries in Stripe Dashboard
# Test subscription status changes
# Verify local webhook processing
```

---

## 🔧 API Endpoints

### Billing Management
```
GET    /billing              # Billing dashboard
GET    /billing/plans        # View available plans
POST   /billing/subscribe    # Create subscription
PUT    /billing/subscription # Update subscription
DELETE /billing/subscription # Cancel subscription
```

### Payment Methods
```
GET    /billing/payment-methods        # List payment methods
POST   /billing/payment-methods        # Add payment method
PUT    /billing/payment-methods/default # Set default method
DELETE /billing/payment-methods        # Remove payment method
```

### Usage & History
```
GET    /billing/usage        # Current usage statistics
GET    /billing/history      # Billing history
```

---

## 🔒 Security Features

### Data Protection
- **PCI Compliance**: Stripe handles all payment data
- **No Card Storage**: Cards never touch your servers
- **Webhook Verification**: All webhook events verified
- **HTTPS Required**: SSL/TLS for all transactions

### Access Control
- **Authentication Required**: All billing routes protected
- **User Isolation**: Users only see their own billing data
- **Role-Based Access**: Admin features separated

---

## 📊 Monitoring & Analytics

### Stripe Dashboard
- **Real-time Metrics**: Revenue, subscriptions, churn
- **Customer Insights**: Payment history, subscription lifecycle
- **Webhook Logs**: Event delivery and processing status

### Application Metrics
- **Usage Tracking**: API calls, tokens, analyses per user
- **Billing Events**: Subscription changes, payment status
- **Performance**: Webhook processing times, error rates

---

## 🚨 Troubleshooting

### Common Issues

#### 1. Webhook Not Receiving Events
```bash
# Check webhook URL is accessible
curl -X POST https://yourdomain.com/stripe/webhook

# Verify webhook secret in .env
echo $STRIPE_WEBHOOK_SECRET

# Check Laravel logs
tail -f storage/logs/laravel.log
```

#### 2. Payment Method Collection Failing
```bash
# Verify Stripe publishable key
echo $STRIPE_KEY

# Check browser console for Stripe.js errors
# Ensure HTTPS in production
```

#### 3. Subscription Not Updating
```bash
# Check database for subscription records
docker compose exec app php artisan tinker
>>> App\Models\User::first()->subscriptions

# Verify webhook events in Stripe Dashboard
```

### Debug Commands
```bash
# Test Stripe connection
php artisan tinker
>>> Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
>>> Stripe\Account::retrieve();

# Check user subscription status
>>> $user = App\Models\User::find(1);
>>> $user->subscribed();
>>> $user->subscription();

# View recent webhook events
>>> $events = Stripe\Event::all(['limit' => 10]);
```

---

## 🌟 Next Steps

### Production Deployment
1. **Replace test keys** with live Stripe keys
2. **Update webhook URL** to production domain
3. **Configure SSL certificate** for secure payments
4. **Set up monitoring** for payment failures
5. **Implement tax collection** if required

### Advanced Features
1. **Metered Billing**: Charge based on actual usage
2. **Multiple Payment Methods**: Support ACH, SEPA, etc.
3. **Multi-currency**: Support international customers
4. **Invoice Customization**: Add company branding
5. **Dunning Management**: Handle failed payment retries

### Compliance
1. **PCI DSS**: Maintain compliance standards
2. **GDPR**: Implement data protection measures
3. **SOX**: Financial reporting compliance
4. **Regional Requirements**: Local payment regulations

---

## 📚 Resources

### Documentation
- [Laravel Cashier](https://laravel.com/docs/11.x/billing)
- [Stripe API Documentation](https://stripe.com/docs/api)
- [Stripe Testing Guide](https://stripe.com/docs/testing)

### Support
- **Stripe Support**: https://support.stripe.com/
- **Laravel Community**: https://laravel.com/community
- **Project Issues**: Check project repository

---

## ✅ Completion Checklist

- [ ] Stripe account created
- [ ] API keys configured in `.env`
- [ ] Products and prices created in Stripe
- [ ] Webhook endpoint configured
- [ ] Test payments completed successfully
- [ ] User subscription flow tested
- [ ] Billing dashboard functional
- [ ] Usage tracking verified
- [ ] Error handling tested

---

**🎉 Congratulations!** Your Stripe sandbox integration is complete and ready for testing. The AI Blockchain Analytics platform now has a fully functional SaaS billing system.

For any issues or questions, refer to the troubleshooting section or consult the provided documentation links.
