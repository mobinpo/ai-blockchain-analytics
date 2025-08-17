# Browser Testing Roadmap - AI Blockchain Analytics

## Quick Start
1. Visit `http://localhost:8003/` → Landing page
2. Click "Get Started" → `/register` (submit form)
3. Check email → Click verification link → `/verify-email/{id}/{hash}`
4. Visit `/login` (submit credentials)
5. Access `/dashboard` → Main authenticated interface

## Testing Dependencies & Order

### Phase 1: Public Routes (No Authentication)
All public routes can be tested first as they have no authentication dependencies.

### Phase 2: Authentication Flow (Sequential)
Must be completed in exact order as each step enables the next.

### Phase 3: Authenticated Routes (Requires Login)
Can be tested in any order after authentication is complete.

### Phase 4: Admin Routes (Requires Auth + Verified)
Requires both authentication and email verification.

### Phase 5: Internal UI Routes (Horizon/Telescope)
Requires special authorization middleware.

---

## Detailed Testing Routes

### 🌐 **Public Routes** (No Authentication Required)

#### Landing & Marketing Pages
- **GET** `/` → Landing page with live contract analyzer demo
  - **Button**: "Analyze Contract" → POST `/api/contracts/analyze-demo`
  - **Button**: "Get Started" → GET `/register`
  - **Button**: "Login" → GET `/login`

- **GET** `/welcome` → Welcome page
  - **Link**: Laravel version display
  - **Link**: Login/Register links

- **GET** `/pricing` → Subscription plans
  - **Button**: "Get Started" → GET `/register`
  - **Button**: "Login" → GET `/login`

#### Public Demo Pages
- **GET** `/projects` → Project showcase
- **GET** `/security` → Security features
- **GET** `/sentiment` → Sentiment analysis info
- **GET** `/sentiment-dashboard` → Public sentiment dashboard
- **GET** `/sentiment-price-chart` → Public price charts
- **GET** `/css-test` → CSS testing page

#### Public Verification & Badge Pages
- **GET** `/verification/badge-demo?contract=0x1234...` → Badge demo
- **GET** `/verification/badge` → Badge display page
- **GET** `/verification/generator` → Badge generator
- **GET** `/verification/badge/{token}` → Show specific badge
- **GET** `/verification/verify/{token}` → Verify specific badge
- **GET** `/verification/embed/{token}` → Embed badge
- **GET** `/enhanced-verification/verify/{token}` → Enhanced verification
- **GET** `/enhanced-verification/status/{contractAddress}` → Verification status
- **GET** `/enhanced-verification/badge/{contractAddress}` → Get badge HTML

#### Public PDF Routes
- **GET** `/pdf/test` → Test PDF generation
- **GET** `/pdf/test-dashboard` → Test dashboard PDF
- **GET** `/pdf/test-engines` → Test PDF engines
- **GET** `/enhanced-pdf/preview` → PDF preview
- **GET** `/enhanced-pdf/download/{filename}` → Download PDF

#### Public API Endpoints (No Auth)
- **GET** `/api/health` → Health check
- **POST** `/api/contracts/analyze-demo` → Demo contract analysis
- **GET** `/api/famous-contracts` → Famous contracts list
- **GET** `/api/sentiment/available-coins` → Available coins
- **GET** `/api/sentiment-price/data` → Sentiment price data
- **GET** `/api/verification/stats` → Verification statistics

### 🔐 **Authentication Flow** (Sequential Order Required)

#### Step 1: Registration
- **GET** `/register` → Registration form
  - **Form Submit**: POST `/register` → Create account

#### Step 2: Email Verification  
- **GET** `/verify-email` → Email verification prompt
  - **Button**: "Resend Verification" → POST `/email/verification-notification`
- **GET** `/verify-email/{id}/{hash}` → Email verification link (from email)

#### Step 3: Login
- **GET** `/login` → Login form
  - **Form Submit**: POST `/login` → Authenticate user

#### Step 4: Password Reset (Optional)
- **GET** `/forgot-password` → Password reset form
  - **Form Submit**: POST `/forgot-password` → Send reset email
- **GET** `/reset-password/{token}` → Password reset form
  - **Form Submit**: POST `/reset-password` → Reset password

#### Step 5: Logout
- **POST** `/logout` → End session

### 🏠 **Authenticated Routes** (Requires Login)

#### Main Dashboard & Navigation
- **GET** `/dashboard` → Main dashboard
  - **Displays**: Statistics, recent analyses, critical findings
  - **Component**: RealTimeMonitor, BlockchainExplorer, SecurityChart

#### Demo & Feature Pages
- **GET** `/demo` → North Star demo dashboard
- **GET** `/sentiment-timeline-demo` → Sentiment timeline demo  
- **GET** `/pdf-generation-demo` → PDF generation demo
- **GET** `/verification-badge-demo` → Verification badge demo
- **GET** `/sentiment-chart-demo` → Sentiment chart demo (requires verified email)

#### Profile & Settings
- **GET** `/profile` → Profile edit page
  - **Form Submit**: PATCH `/profile` → Update profile
  - **Button**: DELETE `/profile` → Delete account

#### Billing & Subscription Management
- **GET** `/billing` → Billing dashboard
  - **Button**: "View Plans" → GET `/billing/plans`
  - **Button**: "View Usage" → GET `/billing/usage`
  - **Button**: "Billing History" → GET `/billing/history`

- **GET** `/billing/plans` → Available plans
  - **Button**: "Subscribe" → POST `/billing/subscribe`

- **GET** `/billing/usage` → Usage metrics

- **GET** `/billing/history` → Billing history
  - **Button**: "Download Invoice" → GET `/billing/invoices/{invoice}/download`

- **GET** `/billing/payment-methods` → Payment methods
  - **Button**: "Add Payment Method" → POST `/billing/payment-methods`
  - **Button**: "Set as Default" → PUT `/billing/payment-methods/default`
  - **Button**: "Delete" → DELETE `/billing/payment-methods`

- **POST** `/billing/subscribe` → Subscribe to plan
- **PUT** `/billing/subscription` → Update subscription
- **DELETE** `/billing/subscription` → Cancel subscription
- **POST** `/billing/subscription/resume` → Resume subscription

#### Sentiment Analysis
- **GET** `/sentiment-analysis` → Main sentiment page
  - **Button**: "View Chart" → GET `/sentiment-analysis/chart`
  - **Button**: "Platform Analysis" → GET `/sentiment-analysis/platform`
  - **Button**: "View Trends" → GET `/sentiment-analysis/trends`
  - **Button**: "Correlations" → GET `/sentiment-analysis/correlations`

#### PDF Generation
- **Button**: "Generate Dashboard PDF" → POST `/pdf/dashboard`
- **Button**: "Generate Sentiment PDF" → POST `/pdf/sentiment`
- **Button**: "Generate Crawler PDF" → POST `/pdf/crawler`
- **Button**: "PDF Statistics" → GET `/pdf/statistics`
- **Button**: "Cleanup PDFs" → DELETE `/pdf/cleanup`
- **Button**: "Engine Info" → GET `/pdf/engine-info`

#### Enhanced PDF Generation
- **Button**: "Generate Preview Token" → POST `/enhanced-pdf/preview/token`
- **Button**: "Generate from Route" → POST `/enhanced-pdf/generate/route`
- **Button**: "Generate from Component" → POST `/enhanced-pdf/generate/component`
- **Button**: "Generate Sentiment Timeline PDF" → POST `/enhanced-pdf/generate/sentiment-timeline`
- **Button**: "Generate Dashboard PDF" → POST `/enhanced-pdf/generate/dashboard`
- **Button**: "Get Status" → GET `/enhanced-pdf/status`
- **Button**: "List Files" → GET `/enhanced-pdf/files`
- **Button**: "Cleanup Files" → DELETE `/enhanced-pdf/cleanup`

#### Enhanced Verification
- **GET** `/enhanced-verification/manage` → Verification management
  - **Button**: "Generate URL" → POST `/enhanced-verification/generate`
  - **Button**: "My Verifications" → GET `/enhanced-verification/my-verifications`
  - **Button**: "Revoke" → POST `/enhanced-verification/revoke`
  - **Button**: "Batch Generate" → POST `/enhanced-verification/batch/generate`
  - **Button**: "Statistics" → GET `/enhanced-verification/stats`

- **GET** `/enhanced-verification/demo` → Enhanced verification demo

#### Email Preferences
- **GET** `/email/preferences` → Email preferences page
  - **Form Submit**: PATCH `/email/preferences` → Update preferences
  - **Button**: "Generate PDF" → POST `/email/preferences/pdf`
  - **Button**: "Generate with Engine" → POST `/email/preferences/pdf/{engine}`
  - **Button**: "Download PDF" → GET `/email/preferences/pdf/download/{filename}`

#### Public Email Routes (No Auth)
- **GET** `/email/unsubscribe` → Unsubscribe page
- **POST** `/email/unsubscribe` → Process unsubscribe
- **POST** `/email/resubscribe` → Resubscribe
- **GET** `/email/tracking/pixel` → Email tracking pixel

### 🔧 **Admin Routes** (Requires Auth + Verified Email)

#### Cache Management Dashboard
- **GET** `/admin/cache` → Cache management dashboard
  - **Button**: "View Statistics" → GET `/admin/cache/statistics`
  - **Button**: "View Entries" → GET `/admin/cache/entries`
  - **Button**: "Invalidate Cache" → POST `/admin/cache/invalidate`
  - **Button**: "Cleanup Cache" → POST `/admin/cache/cleanup`
  - **Button**: "Warm Cache" → POST `/admin/cache/warm`
  - **Button**: "Export Data" → GET `/admin/cache/export`
  - **Button**: "Health Check" → GET `/admin/cache/health`
  - **Button**: "View Metrics" → GET `/admin/cache/metrics`

- **GET** `/admin/cache/entries/{cacheId}` → View specific cache entry
  - **Button**: "Invalidate Entry" → POST `/admin/cache/entries/{cacheId}/invalidate`

### 🔍 **Laravel Horizon** (Internal UI)
Base URL: `/horizon` (requires `Laravel\Horizon\Http\Middleware\Authenticate`)

#### Main Dashboard
- **GET** `/horizon` → Horizon dashboard
  - **Internal API Calls** (driven by Horizon UI):
    - GET `/horizon/api/stats` → Dashboard statistics
    - GET `/horizon/api/workload` → Workload information
    - GET `/horizon/api/masters` → Master supervisors

#### Job Management
- **UI Navigation**: "Jobs" → Various job endpoints:
  - GET `/horizon/api/jobs/pending` → Pending jobs
  - GET `/horizon/api/jobs/completed` → Completed jobs  
  - GET `/horizon/api/jobs/failed` → Failed jobs
  - GET `/horizon/api/jobs/silenced` → Silenced jobs
  - GET `/horizon/api/jobs/{id}` → Specific job details
  - POST `/horizon/api/jobs/retry/{id}` → Retry job

#### Batches
- **UI Navigation**: "Batches" → Batch management:
  - GET `/horizon/api/batches` → List batches
  - GET `/horizon/api/batches/{id}` → Specific batch
  - POST `/horizon/api/batches/retry/{id}` → Retry batch

#### Monitoring & Metrics
- **UI Navigation**: "Monitoring" → Monitoring features:
  - GET `/horizon/api/monitoring` → Monitoring dashboard
  - POST `/horizon/api/monitoring` → Add monitoring
  - GET `/horizon/api/monitoring/{tag}` → Tag-specific monitoring
  - DELETE `/horizon/api/monitoring/{tag}` → Remove monitoring

- **UI Navigation**: "Metrics" → Performance metrics:
  - GET `/horizon/api/metrics/jobs` → Job metrics
  - GET `/horizon/api/metrics/jobs/{id}` → Specific job metrics
  - GET `/horizon/api/metrics/queues` → Queue metrics
  - GET `/horizon/api/metrics/queues/{id}` → Specific queue metrics

### 🔭 **Laravel Telescope** (Internal UI)
Base URL: `/telescope` (requires `App\Http\Middleware\EnhancedTelescopeAuthorize`)

#### Main Dashboard
- **GET** `/telescope` → Telescope dashboard
  - **Internal API Calls** (driven by Telescope UI):
    - Requests, Commands, Jobs, Logs, Dumps, Queries, etc.
    - All internal API calls are handled by Telescope's internal routing

### 🎯 **API Routes That Trigger from UI Buttons**

#### Contract Analysis APIs
- **POST** `/api/contracts/analyze` → Triggered by "Analyze Contract" button
- **POST** `/api/contracts/analyze-demo` → Demo analysis (Landing page)
- **POST** `/api/contract/quick-info` → Quick contract info
- **GET** `/api/contract/networks` → Supported networks
- **GET** `/api/contract/popular` → Popular contracts

#### Sentiment Analysis APIs
- **GET** `/api/sentiment/price-correlation` → Price correlation data
- **GET** `/api/sentiment/available-coins` → Available coins list
- **GET** `/api/sentiment/summary` → Sentiment summary
- **GET** `/api/sentiment-charts/data` → Chart data
- **POST** `/api/sentiment-charts/data` → Chart data with filters
- **GET** `/api/sentiment-price-timeline` → Timeline data
- **GET** `/api/sentiment-price-timeline/demo` → Demo timeline data

#### PDF Generation APIs (Authenticated)
- **POST** `/api/pdf/dashboard` → Generate dashboard PDF
- **POST** `/api/pdf/sentiment` → Generate sentiment PDF
- **POST** `/api/pdf/crawler` → Generate crawler PDF
- **GET** `/api/pdf/statistics` → PDF statistics
- **DELETE** `/api/pdf/cleanup` → Cleanup PDFs
- **POST** `/api/pdf/test` → Test PDF generation
- **GET** `/api/pdf/status` → Service status

#### Vue PDF APIs (Authenticated)
- **POST** `/api/vue-pdf/generate` → Generate from Vue component
- **POST** `/api/vue-pdf/sentiment-dashboard` → Generate sentiment dashboard PDF
- **POST** `/api/vue-pdf/batch` → Batch generate PDFs
- **GET** `/api/vue-pdf/stats` → Generation statistics
- **POST** `/api/vue-pdf/test` → Test generation

#### Verification Badge APIs
- **POST** `/api/verification/generate` → Generate verification (Auth)
- **POST** `/api/verification/verify` → Verify badge (Public)
- **GET** `/api/verification/statistics` → Get statistics (Public)
- **POST** `/api/verification/revoke` → Revoke badge (Auth)
- **GET** `/api/verification/status` → Check status (Public)
- **GET** `/api/verification/badge` → Get badge HTML (Public)

#### Cache Management APIs (Authenticated)
- **GET** `/api/cache/stats` → Cache statistics
- **POST** `/api/cache/warm` → Warm cache
- **POST** `/api/cache/cleanup` → Cleanup cache
- **DELETE** `/api/cache/clear` → Clear cache
- **GET** `/api/cache/entry` → Get cache entry
- **POST** `/api/cache/entry` → Set cache entry
- **DELETE** `/api/cache/entry` → Delete cache entry

#### Streaming & Job APIs (Authenticated)
- **POST** `/api/streaming/start` → Start streaming analysis
- **GET** `/api/streaming/{analysis}/status` → Get analysis status
- **GET** `/api/streaming/{analysis}/results` → Get analysis results
- **POST** `/api/streaming/{analysis}/cancel` → Cancel analysis
- **GET** `/api/streaming/{analysis}/stream` → Stream results

- **POST** `/api/openai-streaming/start` → Start OpenAI streaming job
- **GET** `/api/openai-streaming/{jobId}/sse` → Server-sent events
- **GET** `/api/openai-streaming/{jobId}/status` → Job status
- **POST** `/api/openai-streaming/{jobId}/cancel` → Cancel job

#### Source Code & Explorer APIs (Public)
- **GET** `/api/source-code/fetch` → Fetch source code
- **GET** `/api/source-code/abi` → Get contract ABI
- **GET** `/api/source-code/verify` → Verify contract
- **GET** `/api/source-code/networks` → Supported networks
- **POST** `/api/smart-explorer/detect` → Detect blockchain
- **POST** `/api/smart-explorer/contract/source` → Get contract source

#### Social Media & Crawler APIs (Authenticated)
- **GET** `/api/social-media` → List social media posts
- **POST** `/api/social-media/crawl` → Start crawling
- **GET** `/api/social-media/stats` → Get statistics
- **POST** `/api/crawler/start` → Start crawler job
- **GET** `/api/crawler/stats` → Get crawler stats

### 🎪 **External Webhooks** (No Browser Testing)
These are external callback URLs, not testable via browser:

- **POST** `/stripe/webhook` → Stripe webhook handler
- **POST** `/webhooks/mailgun` → Mailgun webhook handler
- **POST** `/api/webhooks/mailgun/events` → Mailgun events webhook

---

## Why This Order?

### Authentication Dependency Chain
1. **Public Routes First**: No dependencies, test basic functionality
2. **Registration → Verification → Login**: Sequential flow required
3. **Authenticated Routes**: Requires step 2 completion
4. **Admin Routes**: Requires both authentication + email verification
5. **Internal UIs**: Special middleware authorization

### Test Data Prerequisites
- **Test User**: Created during registration flow
- **Verified Email**: Required for admin routes and some features  
- **Sample Data**: Use provided demo endpoints for testing
- **Contract Addresses**: Use famous contracts like:
  - `0xE592427A0AEce92De3Edee1F18E0157C05861564` (Uniswap V3)
  - `0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2` (Aave V3)
- **File Downloads**: PDFs generated will be available for download
- **Tokens & IDs**: Generated dynamically during testing (save for later steps)

### Environment Setup
- **Base URL**: `http://localhost:8003`
- **Docker**: Application runs in Docker container
- **Database**: PostgreSQL with sample data
- **Redis**: Required for Horizon queue management
- **Storage**: Local filesystem for PDF storage

### Special Considerations
- **CSRF Tokens**: Required for POST requests from UI
- **Rate Limiting**: Some routes have throttling
- **Signed URLs**: Verification routes use signed URLs
- **File Downloads**: PDFs and reports generate files
- **Background Jobs**: Some operations queue jobs (check via Horizon)
- **Monitoring**: Telescope tracks all requests for debugging

### Testing Browser Compatibility
- **Modern Browsers**: Chrome, Firefox, Safari, Edge
- **JavaScript Required**: Vue.js/Inertia.js application
- **Responsive Design**: Mobile and desktop layouts
- **File Uploads**: Contract analysis may support file uploads
- **Download Handling**: PDF downloads and file exports