# 🛡️ Sentiment Shield - API Tokens & Configuration Guide

## 📋 **COMPLETE TOKEN SETUP GUIDE**

All your environment files have been updated with the new **Sentiment Shield** branding! Here's where to get all the API tokens and values you need:

---

## 🔑 **Essential API Keys & Tokens**

### **🤖 AI & Machine Learning Services**

#### **1. OpenAI API (Required for Smart Contract Analysis)**
- **Where to get**: https://platform.openai.com/api-keys
- **Environment variables**:
  ```env
  OPENAI_API_KEY=sk-proj-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
  OPENAI_ORGANIZATION=org-xxxxxxxxxxxxxxxxxxxxxxxx
  ```
- **Cost**: Pay-per-use (typically $0.002/1K tokens for GPT-4)
- **Setup Steps**:
  1. Create account at https://platform.openai.com/
  2. Add payment method
  3. Go to API Keys section
  4. Create new secret key
  5. Copy the key (starts with `sk-proj-`)

#### **2. Google Cloud Natural Language API (Required for Sentiment Analysis)**
- **Where to get**: https://console.cloud.google.com/
- **Environment variables**:
  ```env
  GOOGLE_APPLICATION_CREDENTIALS=/path/to/service-account-key.json
  GOOGLE_CLOUD_PROJECT_ID=your-project-id
  GOOGLE_CLOUD_REGION=us-central1
  ```
- **Cost**: $1.00 per 1,000 units for sentiment analysis
- **Setup Steps**:
  1. Create project at https://console.cloud.google.com/
  2. Enable Natural Language API
  3. Create service account
  4. Download JSON credentials file
  5. Upload to your server in `/var/www/html/storage/app/`

---

## 🌐 **Blockchain Explorer APIs (Free Tier Available)**

### **3. Ethereum - Etherscan**
- **Where to get**: https://etherscan.io/apis
- **Environment variable**: `ETHERSCAN_API_KEY=your_etherscan_api_key_here`
- **Cost**: Free tier (5 calls/sec), Pro plans from $99/month
- **Setup**: Register → Account → API Keys → Create

### **4. Binance Smart Chain - BscScan**
- **Where to get**: https://bscscan.com/apis
- **Environment variable**: `BSCSCAN_API_KEY=your_bscscan_api_key_here`
- **Cost**: Free tier (5 calls/sec)
- **Setup**: Register → Account → API Keys → Create

### **5. Polygon - PolygonScan**
- **Where to get**: https://polygonscan.com/apis
- **Environment variable**: `POLYGONSCAN_API_KEY=your_polygonscan_api_key_here`
- **Cost**: Free tier (5 calls/sec)
- **Setup**: Register → Account → API Keys → Create

### **6. Arbitrum - Arbiscan**
- **Where to get**: https://arbiscan.io/apis
- **Environment variable**: `ARBISCAN_API_KEY=your_arbiscan_api_key_here`
- **Cost**: Free tier (5 calls/sec)
- **Setup**: Register → Account → API Keys → Create

### **7. Optimism - Optimistic Etherscan**
- **Where to get**: https://optimistic.etherscan.io/apis
- **Environment variable**: `OPTIMISTIC_ETHERSCAN_API_KEY=your_optimistic_etherscan_api_key_here`
- **Cost**: Free tier (5 calls/sec)
- **Setup**: Register → Account → API Keys → Create

### **8. Avalanche - Snowtrace**
- **Where to get**: https://snowtrace.io/apis
- **Environment variable**: `SNOWTRACE_API_KEY=your_snowtrace_api_key_here`
- **Cost**: Free tier (5 calls/sec)
- **Setup**: Register → Account → API Keys → Create

### **9. Fantom - FtmScan**
- **Where to get**: https://ftmscan.com/apis
- **Environment variable**: `FTMSCAN_API_KEY=your_ftmscan_api_key_here`
- **Cost**: Free tier (5 calls/sec)
- **Setup**: Register → Account → API Keys → Create

---

## 💰 **Payment Processing (Stripe)**

### **10. Stripe Payment Gateway**
- **Where to get**: https://dashboard.stripe.com/apikeys
- **Environment variables**:
  ```env
  STRIPE_KEY=pk_live_YOUR_PUBLISHABLE_KEY_HERE
  STRIPE_SECRET=sk_live_YOUR_SECRET_KEY_HERE
  STRIPE_WEBHOOK_SECRET=whsec_YOUR_WEBHOOK_SECRET_HERE
  ```
- **Cost**: 2.9% + 30¢ per transaction
- **Setup Steps**:
  1. Create account at https://stripe.com/
  2. Complete business verification
  3. Go to Developers → API Keys
  4. Get publishable and secret keys
  5. Set up webhook endpoint at `https://sentimentshield.app/stripe/webhook`

### **11. Stripe Price IDs (Subscription Plans)**
- **Where to create**: https://dashboard.stripe.com/products
- **Environment variables**:
  ```env
  STRIPE_PRICE_STARTER_MONTHLY=price_xxxxxxxxxxxxxxxxxxxxxxxx
  STRIPE_PRICE_PROFESSIONAL_MONTHLY=price_xxxxxxxxxxxxxxxxxxxxxxxx
  STRIPE_PRICE_ENTERPRISE_MONTHLY=price_xxxxxxxxxxxxxxxxxxxxxxxx
  ```
- **Setup**: Create products for your subscription tiers

---

## 📧 **Email Services**

### **12. Mailgun (Recommended for Production)**
- **Where to get**: https://app.mailgun.com/mg/dashboard
- **Environment variables**:
  ```env
  MAILGUN_DOMAIN=mg.sentimentshield.app
  MAILGUN_SECRET=key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
  MAILGUN_ENDPOINT=api.mailgun.net
  ```
- **Cost**: Free tier (10,000 emails/month), then $35/month
- **Setup Steps**:
  1. Create account at https://mailgun.com/
  2. Add domain `mg.sentimentshield.app`
  3. Add DNS records to your domain
  4. Get API key from Settings → API Keys

---

## 📊 **Monitoring & Analytics**

### **13. Sentry (Error Tracking)**
- **Where to get**: https://sentry.io/
- **Environment variable**: `SENTRY_LARAVEL_DSN=https://xxxxx@xxxxx.ingest.sentry.io/xxxxx`
- **Cost**: Free tier (5,000 events/month), Pro from $26/month
- **Setup Steps**:
  1. Create account at https://sentry.io/
  2. Create new Laravel project
  3. Get DSN from Settings → Client Keys

### **14. CoinGecko API (Cryptocurrency Data)**
- **Where to get**: https://www.coingecko.com/en/api/pricing
- **Environment variable**: `COINGECKO_API_KEY=CG-xxxxxxxxxxxxxxxxxxxxxxxx`
- **Cost**: Free tier (10-50 calls/min), Pro from $129/month
- **Setup**: Create account → API → Get key

---

## 🔐 **Security & Secrets**

### **15. Laravel App Key**
- **Generate command**: `php artisan key:generate --show`
- **Environment variable**: `APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`
- **Setup**: Run command and copy the generated key

### **16. Verification Secret Key**
- **Generate command**: `openssl rand -base64 32`
- **Environment variable**: `VERIFICATION_SECRET_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`
- **Setup**: Run command and copy the generated key

---

## 🌍 **Domain & DNS Configuration**

### **17. Domain Setup for sentimentshield.app**
- **DNS Records needed**:
  ```
  A     @                 your_server_ip
  A     www               your_server_ip
  CNAME mg                mailgun_cname_value
  TXT   @                 mailgun_txt_verification
  MX    @                 mxa.mailgun.org (priority 10)
  MX    @                 mxb.mailgun.org (priority 10)
  ```

### **18. SSL Certificate**
- **Free option**: Let's Encrypt via Certbot
- **Commands**:
  ```bash
  sudo certbot --nginx -d sentimentshield.app -d www.sentimentshield.app
  ```

---

## 📋 **Priority Setup Order**

### **🔥 Critical (Must Have)**
1. **OpenAI API** - Core functionality
2. **Laravel App Key** - Security
3. **Database Password** - Change from default
4. **Domain SSL** - Security & SEO

### **⚡ High Priority**
5. **Etherscan API** - Ethereum support
6. **Mailgun** - User communications
7. **Stripe** - Payment processing
8. **Sentry** - Error monitoring

### **📈 Medium Priority**
9. **Google Cloud NLP** - Enhanced sentiment
10. **Other blockchain APIs** - Multi-chain support
11. **CoinGecko** - Price data

---

## 💡 **Cost Estimates (Monthly)**

### **Minimal Setup (Free Tier)**
- OpenAI: ~$20-50 (usage-based)
- Blockchain APIs: Free
- Mailgun: Free (up to 10k emails)
- Sentry: Free
- **Total: ~$20-50/month**

### **Production Setup**
- OpenAI: ~$100-300
- Mailgun: $35
- Stripe: 2.9% of revenue
- Sentry Pro: $26
- CoinGecko Pro: $129
- **Total: ~$290-490/month + % of revenue**

---

## 🚀 **Ready to Launch!**

Once you've configured these keys, your **Sentiment Shield** platform will be fully operational and ready for GITEX! 

**Pro Tip**: Start with the critical items and add others as you scale. Most have generous free tiers perfect for getting started! 🛡️
