# AI Blockchain Analytics - Mono-repo Setup

This document describes the mono-repo structure and setup for the AI Blockchain Analytics platform built with Laravel 12 + Octane.

## 🏗️ Architecture Overview

This is a Laravel-based mono-repo that combines:
- **Backend**: Laravel 12 with Octane for high-performance API
- **Frontend**: Vue.js 3 with Inertia.js for seamless SPA experience
- **Infrastructure**: Docker containers with PostgreSQL and Redis
- **CI/CD**: GitHub Actions with automated testing and deployment

## 📁 Project Structure

```
ai-blockchain-analytics/
├── .github/                    # GitHub workflows and templates
│   ├── workflows/             # CI/CD pipelines
│   ├── ISSUE_TEMPLATE/        # Issue templates
│   ├── dependabot.yml         # Dependency management
│   └── pull_request_template.md
├── app/                       # Laravel application code
│   ├── Console/               # Artisan commands
│   ├── Http/                  # Controllers, middleware, requests
│   ├── Models/                # Eloquent models
│   ├── Providers/             # Service providers
│   └── Services/              # Business logic services
├── config/                    # Laravel configuration files
├── database/                  # Migrations, seeders, factories
├── docker/                    # Docker configuration files
├── resources/                 # Frontend assets and views
│   ├── css/                   # Stylesheets
│   ├── js/                    # Vue.js components and pages
│   └── views/                 # Blade templates
├── routes/                    # Route definitions
├── tests/                     # PHPUnit tests
├── docker-compose.yml         # Development environment
├── docker-compose.ci.yml      # CI environment
└── README.md                  # Project documentation
```

## 🚀 Quick Start

### Prerequisites

- Docker & Docker Compose
- Git
- Node.js 20+ (for local development)
- PHP 8.3+ (for local development)

### Development Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/mobin/ai-blockchain-analytics.git
   cd ai-blockchain-analytics
   ```

2. **Copy environment file**
   ```bash
   cp .env.example .env
   ```

3. **Start Docker services**
   ```bash
   docker-compose up -d
   ```

4. **Install dependencies and setup Laravel**
   ```bash
   docker-compose exec app composer install
   docker-compose exec app php artisan key:generate
   docker-compose exec app php artisan migrate --seed
   ```

5. **Install frontend dependencies**
   ```bash
   npm install
   npm run dev
   ```

### Alternative: Local Development

```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate --seed

# Start development servers
composer run dev
```

## 🔄 CI/CD Pipeline

### Workflows

1. **Main CI Pipeline** (`.github/workflows/ci.yml`)
   - Runs on every push and PR
   - Docker-based testing with PostgreSQL and Redis
   - Code style checks with Laravel Pint
   - Static analysis with Psalm
   - PHPUnit test execution

2. **Mono-repo Release** (`.github/workflows/monorepo-release.yml`)
   - Change detection for backend/frontend/docker
   - Parallel testing for affected components
   - Docker image building and publishing
   - Security scanning with Trivy
   - Automated deployment to staging/production

3. **Dependabot Auto-merge** (`.github/workflows/dependabot-auto-merge.yml`)
   - Automatic merging of minor/patch updates
   - Manual review required for major updates

### Branch Strategy

- `main/master`: Production-ready code
- `develop`: Integration branch for new features
- `feature/*`: Feature development branches
- `hotfix/*`: Emergency fixes

## 🧪 Testing Strategy

### Backend Testing
- **Unit Tests**: Test individual components and services
- **Feature Tests**: Test HTTP endpoints and user flows
- **Integration Tests**: Test database interactions and external services

### Frontend Testing
- **Component Tests**: Test Vue.js components in isolation
- **E2E Tests**: Test complete user workflows (planned)

### Running Tests

```bash
# Backend tests
composer test
# or
docker-compose exec app vendor/bin/phpunit

# Frontend tests (when implemented)
npm run test

# All tests in CI
docker-compose -f docker-compose.ci.yml up --abort-on-container-exit
```

## 📦 Package Management

### Composer (PHP)
Dependencies are managed in `composer.json`:
- Production dependencies in `require`
- Development dependencies in `require-dev`
- Custom scripts for common tasks

### NPM (JavaScript)
Frontend dependencies in `package.json`:
- Vue.js ecosystem packages
- Build tools (Vite, Tailwind CSS)
- Development tooling

### Dependabot
Automated dependency updates configured for:
- Composer packages (weekly)
- NPM packages (weekly)
- Docker images (weekly)
- GitHub Actions (weekly)

## 🐳 Docker Configuration

### Development Environment
- **app**: Laravel application with Octane
- **postgres**: PostgreSQL 16 database
- **redis**: Redis 7 for caching and queues
- **nginx**: Reverse proxy (optional)

### CI Environment
Optimized for testing with:
- Minimal resource usage
- Fast startup times
- Isolated test databases

## 🔒 Security

### Scanning
- **Trivy**: Vulnerability scanning for dependencies and Docker images
- **Dependabot**: Automated security updates
- **GitHub Security**: SARIF upload for security insights

### Best Practices
- Environment variables for sensitive data
- Separate CI and production environments
- Regular dependency updates
- Static analysis with Psalm

## 📊 Monitoring & Observability

### Development
- Laravel Pail for real-time log monitoring
- Horizon for queue monitoring
- Local debugging with Xdebug support

### Production (Planned)
- Application Performance Monitoring (APM)
- Error tracking and alerting
- Infrastructure monitoring

## 🚢 Deployment

### Staging
- Automatic deployment from `develop` branch
- Full test suite execution
- Environment parity with production

### Production
- Manual deployment from tagged releases
- Blue-green deployment strategy (planned)
- Database migration automation
- Rollback capabilities

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Run the test suite
6. Submit a pull request

### Code Standards
- PSR-12 coding standard for PHP
- ESLint configuration for JavaScript
- Automatic code formatting with Pint and Prettier

## 📚 Documentation

- **API Documentation**: Auto-generated from code comments
- **Frontend Components**: Storybook integration (planned)
- **Database Schema**: ER diagrams and documentation
- **Deployment Guide**: Step-by-step deployment instructions

## 🆘 Troubleshooting

### Common Issues

1. **Docker permission issues**
   ```bash
   sudo chown -R $USER:$USER storage bootstrap/cache
   ```

2. **Frontend build errors**
   ```bash
   rm -rf node_modules package-lock.json
   npm install
   ```

3. **Database connection issues**
   ```bash
   docker-compose down
   docker-compose up -d postgres
   ```

### Getting Help

- Check existing [GitHub Issues](https://github.com/mobin/ai-blockchain-analytics/issues)
- Create a new issue using the provided templates
- Review the documentation in this mono-repo

## 📄 License

This project is open-sourced software licensed under the [MIT license](LICENSE).

---

**Mono-repo maintained by**: Mobin  
**Last updated**: January 2025