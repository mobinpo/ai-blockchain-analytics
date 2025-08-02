# Stripe Sandbox Integration Setup

## Overview
Your AI Blockchain Analytics platform is fully integrated with Stripe for SaaS billing using Laravel Cashier.

## Current Status ✅
- ✅ Laravel Cashier (v15.7.1) installed
- ✅ Stripe configuration set up in `config/services.php`
- ✅ User model with Billable trait
- ✅ Subscription plans model and database structure
- ✅ Comprehensive webhook handling
- ✅ Subscription management controllers
- ✅ Frontend pricing page with plan selection
- ✅ Database migrations and seeders

## Stripe Configuration

### Environment Variables (.env)
```bash
# Stripe Test Keys (replace with your own)
STRIPE_KEY=pk_test_your_stripe_publishable_key_here
STRIPE_SECRET=sk_test_your_stripe_secret_key_here
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_endpoint_secret_here

# Cashier Configuration
CASHIER_CURRENCY=usd
CASHIER_CURRENCY_LOCALE=en_US
CASHIER_LOGGER=stack
```

### Required Setup Steps

#### 1. Create Products and Prices in Stripe Dashboard
You need to create the following products and prices in your Stripe dashboard:

**Products:**
- Starter Plan
- Professional Plan  
- Enterprise Plan

**Prices for each product:**
- Monthly subscription
- Yearly subscription (with discount)

Then update your `.env` file with the price IDs:
```bash
STRIPE_PRICE_STARTER_MONTHLY=price_1xxxxxx
STRIPE_PRICE_STARTER_YEARLY=price_1xxxxxx
STRIPE_PRICE_PROFESSIONAL_MONTHLY=price_1xxxxxx
STRIPE_PRICE_PROFESSIONAL_YEARLY=price_1xxxxxx
STRIPE_PRICE_ENTERPRISE_MONTHLY=price_1xxxxxx
STRIPE_PRICE_ENTERPRISE_YEARLY=price_1xxxxxx
```

#### 2. Set Up Webhook Endpoints
In your Stripe dashboard, create a webhook endpoint pointing to:
```
https://yourdomain.com/stripe/webhook
```

Select these events:
- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `customer.subscription.trial_will_end`
- `invoice.payment_succeeded`
- `invoice.payment_failed`
- `payment_method.attached`
- `customer.updated`
- `setup_intent.succeeded`

Copy the webhook signing secret and update your `.env`:
```bash
STRIPE_WEBHOOK_SECRET=whsec_your_actual_webhook_secret
```

#### 3. Test the Integration

**Available Routes:**
- `/pricing` - Public pricing page
- `/subscription` - Subscription management (authenticated)
- `/subscription/invoices` - Invoice history
- `/subscription/payment-methods` - Payment method management

**Testing Flow:**
1. Visit `/pricing` to see all available plans
2. Register/login and select a plan
3. Complete Stripe checkout process
4. Manage subscription via `/subscription`

## Database Structure

### Subscription Plans
The following plans are pre-configured in the database:

**Starter ($29/month, $290/year)**
- 10 analyses per month
- 3 projects
- Basic features
- 14-day free trial

**Professional ($99/month, $990/year)**
- 100 analyses per month  
- 15 projects
- AI insights, real-time monitoring
- 14-day free trial

**Enterprise ($299/month, $2990/year)**
- 1000 analyses per month
- Unlimited projects
- All features + dedicated support
- 30-day free trial

## Key Features Implemented

### Frontend Integration
- Responsive pricing page with monthly/yearly toggle
- Plan comparison table
- Integration with authentication system
- Stripe Elements for secure payment collection

### Backend Integration
- Complete subscription lifecycle management
- Webhook event handling for all Stripe events
- Invoice generation and management
- Payment method management
- Prorated plan changes
- Trial period support

### Security
- Webhook signature verification
- Secure payment processing via Stripe
- No sensitive card data stored locally

## Next Steps for Production

1. **Replace test keys** with live Stripe keys
2. **Update webhook URL** to production domain
3. **Test all subscription flows** thoroughly
4. **Set up monitoring** for failed payments and webhooks
5. **Configure email notifications** for subscription events

## Support

For issues with the Stripe integration:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Check Stripe dashboard for webhook delivery status
3. Use `php artisan horizon:status` to verify queue processing