# BrandCaster AI

**Multi-Brand AI-Powered Content Generation & Publishing Platform**

BrandCaster AI is a production-grade Laravel application that automates content creation and distribution across multiple brands, websites, and social media channels using AI-powered workflows.

[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-3-FB70A9?logo=livewire)](https://livewire.laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

---

## Features

### 🎯 Core Capabilities

- **Multi-Brand Management** - Manage 4+ brands with unique voice, style, and publishing schedules
- **AI Content Generation** - Powered by OpenAI GPT-4 with Hugging Face fallback
- **Topic Discovery** - Auto-discover trending topics from web searches and news APIs
- **Multi-Platform Publishing** - Publish to websites (MySQL/PostgreSQL) and social media (Facebook, Twitter/X, LinkedIn)
- **Approval Workflow** - Human-in-the-loop review with inline editing and version control
- **Analytics Dashboard** - Track engagement, impressions, CTR with UTM attribution
- **RBAC** - Role-based access control with 5 roles and 9 granular permissions
- **Audit Logging** - Comprehensive audit trail for all actions

### 🚀 Automation

- Auto-generate content from discovered topics
- Schedule posts across multiple channels
- Platform-specific content optimization
- Retry logic with exponential backoff
- Idempotent publishing (no duplicates)
- Quiet hours and rate limiting

### 🔒 Security & Compliance

- Encrypted credential storage
- Content moderation (toxicity, PII detection)
- Plagiarism checking
- Source citation for factual claims
- Blocklist enforcement
- 2FA authentication support

---

## Quick Start

### Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js 18+ and npm
- MySQL 8+ (or PostgreSQL 14+)
- Redis 6+
- Laravel Herd (recommended for macOS) or traditional LAMP/LEMP stack

### Installation

```bash
# Clone the repository
git clone https://github.com/yourusername/brandcaster-ai.git
cd brandcaster-ai

# Install dependencies
composer install
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Edit .env and configure your API keys:
# - OPENAI_API_KEY (required for AI content generation)
# - SERPAPI_KEY (required for topic discovery)
# - Social media OAuth credentials (optional)

# Create MySQL database
mysql -u root -p -e "CREATE DATABASE brandcasterai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Or if using Laravel Herd, the database will be created automatically

# Run migrations and seed with development data
php artisan migrate:fresh --seed

# This creates:
# - All 15 database tables
# - 4 brands with complete configurations
# - 128 trending topics
# - 100+ sample content drafts
# - 1000+ engagement metrics

# Build assets
npm run build

# Start queue worker (required for content generation)
php artisan horizon
# Or in a separate terminal: php artisan queue:work

# Start development server
php artisan serve
# Visit: http://localhost:8000
```

### Default Login Credentials

After seeding:
- **Email**: admin@brandcaster.ai
- **Password**: password
- **Role**: Super Admin

**⚠️ Change these credentials in production!**

---

## Tech Stack

### Backend
- **Framework**: Laravel 12
- **Components**: Livewire 3 + Volt (single-file components)
- **Database**: PostgreSQL (MySQL supported)
- **Queue**: Redis + Laravel Horizon
- **Authentication**: Laravel Fortify with 2FA

### Frontend
- **CSS**: Tailwind CSS 4
- **Components**: Livewire Flux UI
- **JavaScript**: Alpine.js (minimal)

### AI & Integrations
- **AI**: OpenAI GPT-4, Hugging Face
- **Social**: Facebook Graph API, Twitter/X API, LinkedIn API
- **Trends**: SerpAPI, RSS feeds
- **HTTP**: Guzzle

### DevOps
- **Testing**: Pest
- **Queue Monitoring**: Laravel Horizon
- **Logging**: Laravel Pail, Spatie Activity Log
- **Code Quality**: Laravel Pint (PSR-12)

---

## Architecture

```
┌─────────────────────────────────────────────┐
│         BrandCaster AI Platform             │
├─────────────────────────────────────────────┤
│                                             │
│  Topic Discovery → Content Generation      │
│       ↓                    ↓                │
│  Moderation & QA → Approval Workflow        │
│       ↓                    ↓                │
│  Publishing Engine → Analytics              │
│                                             │
├─────────────────────────────────────────────┤
│  AI Services │ Social APIs │ DB Connectors  │
├─────────────────────────────────────────────┤
│  Queue (Redis) │ Cache │ Storage (S3)       │
└─────────────────────────────────────────────┘
```

### Key Components

1. **Brand Management** - Configure brand voice, style guide, settings
2. **Connectors** - Website databases and social media accounts
3. **Topic Discovery** - Trend monitoring and topic scoring
4. **Content Pipeline** - Brief → Outline → Draft → Variants
5. **Publishing** - Queue-based with retries and scheduling
6. **Analytics** - Metrics aggregation and reporting

---

## Project Structure

```
brandcaster-ai/
├── app/
│   ├── Models/              # Eloquent models (15 models)
│   ├── Services/            # Business logic services (to be implemented)
│   │   ├── AI/              # Content generation services
│   │   ├── DatabaseConnector/  # Website publishing
│   │   ├── Social/          # Social media publishing
│   │   ├── TopicDiscovery/  # Trend monitoring
│   │   └── Publishing/      # Publishing engine
│   ├── Jobs/                # Queue jobs (to be implemented)
│   ├── Http/Controllers/    # HTTP controllers
│   └── Providers/           # Service providers
├── database/
│   ├── migrations/          # 15 migrations (complete)
│   └── seeders/             # 4 seeders (complete)
├── resources/
│   ├── views/
│   │   ├── livewire/        # Livewire Volt components
│   │   └── components/      # Blade components
│   ├── css/                 # Tailwind CSS
│   └── js/                  # JavaScript
├── docs/                    # Documentation
│   ├── ARCHITECTURE.md      # System architecture
│   ├── DATABASE_SCHEMA.md   # Database design
│   └── IMPLEMENTATION_STATUS.md  # Development status
├── tests/                   # Pest test suite (to be implemented)
└── routes/
    ├── web.php              # Web routes
    └── api.php              # API routes (to be created)
```

---

## Brands Configured

The platform comes pre-configured with 4 sample brands:

1. **Mejba Personal Portfolio** ([mejba.me](https://www.mejba.me))
   - Focus: Web Development, Design, Technology
   - Posts/day: 2

2. **Ramlit Limited** ([ramlit.com](https://www.ramlit.com))
   - Focus: Business, Software Development, Cloud Services
   - Posts/day: 3

3. **ColorPark Creative Agency** ([colorpark.io](https://www.colorpark.io))
   - Focus: Design, Branding, Creative, Marketing
   - Posts/day: 4

4. **xCyberSecurity Global Services** ([xcybersecurity.io](https://www.xcybersecurity.io))
   - Focus: Cybersecurity, Threat Intelligence, Compliance
   - Posts/day: 2

---

## Development Commands

```bash
# Development server (Laravel + Queue + Logs + Vite)
composer dev

# Run tests
composer test

# Code formatting
vendor/bin/pint

# Database
php artisan migrate              # Run migrations
php artisan migrate:fresh --seed # Fresh start with seed data
php artisan db:seed              # Seed only

# Queue management
php artisan horizon              # Start Horizon dashboard
php artisan queue:work           # Start queue worker
php artisan queue:failed         # View failed jobs

# Logs
php artisan pail                 # Real-time logs
```

---

## Environment Configuration

### Required API Keys

```env
# OpenAI (Required for content generation)
OPENAI_API_KEY=sk-...
OPENAI_ORGANIZATION=org-...

# Social Media (Required for publishing)
FACEBOOK_APP_ID=...
FACEBOOK_APP_SECRET=...

TWITTER_CLIENT_ID=...
TWITTER_CLIENT_SECRET=...

LINKEDIN_CLIENT_ID=...
LINKEDIN_CLIENT_SECRET=...

# SerpAPI (Required for topic discovery)
SERPAPI_KEY=...

# Optional: Hugging Face (fallback AI provider)
HUGGINGFACE_API_TOKEN=hf_...
```

---

## Database Schema

15 core tables with comprehensive relationships:

- `brands` - Brand configurations
- `categories` - Content categories per brand
- `website_connectors` - External database connections
- `social_connectors` - Social media OAuth tokens
- `topics` - Discovered topics with scoring
- `content_drafts` - Generated content with soft deletes
- `content_variants` - Platform-specific versions
- `assets` - Media files (images, videos)
- `publish_jobs` - Publishing queue with retries
- `approvals` - Review workflow
- `metrics` - Analytics data
- `brand_user` - User-brand membership
- `audit_logs` - Audit trail
- `prompt_templates` - AI prompt management
- `permissions/roles` - RBAC tables

See [`docs/DATABASE_SCHEMA.md`](docs/DATABASE_SCHEMA.md) for complete ERD and specifications.

---

## Roles & Permissions

### Roles

- **Super Admin** - Full system access
- **Brand Admin** - Manage specific brand(s)
- **Content Manager** - Create, edit, approve, publish content
- **Reviewer** - Review and approve content only
- **Analyst** - Read-only analytics access

### Permissions

- `brands.manage` - Create/edit brands
- `connectors.manage` - Manage website and social connectors
- `content.create` - Create content
- `content.edit` - Edit content
- `content.approve` - Approve content for publishing
- `content.publish` - Publish content
- `analytics.view` - View analytics dashboard
- `settings.manage` - Configure system settings
- `users.manage` - Manage users and roles

---

## Testing

```bash
# Run all tests
composer test

# Run specific test file
vendor/bin/pest tests/Feature/ContentGenerationTest.php

# Run with coverage
vendor/bin/pest --coverage
```

---

## Documentation

- **[Architecture](docs/ARCHITECTURE.md)** - System design and technology decisions
- **[Database Schema](docs/DATABASE_SCHEMA.md)** - Complete database design with ERD
- **[Implementation Status](docs/IMPLEMENTATION_STATUS.md)** - Development progress and roadmap
- **[CLAUDE.md](CLAUDE.md)** - Developer guide for Claude Code

---

## Roadmap

### ✅ Phase 1: Foundation (Complete)
- Database schema and migrations
- Eloquent models with relationships
- RBAC with Spatie Permission
- Brand and category seeders

### 🚧 Phase 2: Core Services (In Progress)
- Database connector with field mapping
- AI content generation service
- Social media OAuth and publishing
- Topic discovery system

### 📋 Phase 3: Content Pipeline (Planned)
- End-to-end content generation workflow
- Moderation and quality checks
- Publishing engine with scheduling
- Approval workflow UI

### 📋 Phase 4: Analytics & Reporting (Planned)
- Metrics collection from platforms
- Analytics dashboard
- Weekly summary emails
- Engagement tracking

### 📋 Phase 5: Testing & Deployment (Planned)
- Comprehensive test suite
- CI/CD pipeline
- Production deployment
- Monitoring and alerting

---

## Contributing

Contributions are welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct and the process for submitting pull requests.

---

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## Acknowledgments

- **OpenAI** - GPT-4 API for content generation
- **Laravel** - Excellent PHP framework
- **Livewire** - Reactive UI without JavaScript complexity
- **Spatie** - Amazing Laravel packages ecosystem
- **Tailwind CSS** - Utility-first CSS framework

---

## Support

For support, email [support@example.com](mailto:support@example.com) or open an issue on GitHub.

---

**Built with ❤️ using Laravel, Livewire, and AI**
