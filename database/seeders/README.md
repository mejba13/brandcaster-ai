# Database Seeders

Comprehensive database seeders for BrandCaster AI application.

## Overview

The seeding system provides two modes:

1. **Production Mode**: Minimal seeders for production environment
2. **Development Mode**: Comprehensive seeders with realistic test data

## Quick Start

### Development Environment

```bash
# Fresh database with all development data
php artisan migrate:fresh --seed

# Or run development seeder specifically
php artisan db:seed --class=DevelopmentSeeder
```

### Production Environment

```bash
# Production seeders only
php artisan db:seed
```

## Available Seeders

### Core Seeders (Production)

#### 1. RolesAndPermissionsSeeder
Creates roles and permissions for RBAC:
- **Roles**: super_admin, brand_admin, content_manager, reviewer, analyst
- **Permissions**: 9 granular permissions for content management

#### 2. BrandSeeder
Creates 4 initial brands:
- Mejba Personal Portfolio (https://www.mejba.me)
- Ramlit Limited (https://www.ramlit.com)
- ColorPark Creative Agency (https://www.colorpark.io)
- xCyberSecurity Global Services (https://www.xcybersecurity.io)

Each brand includes:
- Brand voice configuration (tone, style, audience)
- Style guide (dos, don'ts, blocklist)
- Settings (auto_approve, posts_per_day, timezone)

#### 3. CategorySeeder
Creates categories for each brand:
- Web Design & UI/UX
- Software Development
- Cloud Computing
- Cybersecurity

#### 4. UserSeeder
Creates initial users:
- Super Admin (admin@brandcaster.ai / password)
- Brand-specific admins and managers

### Development Seeders

#### 5. WebsiteConnectorSeeder
Creates database connectors for each brand:
- MySQL connection configurations
- Field mapping for WordPress-style schema
- Status workflow mapping

#### 6. SocialConnectorSeeder
Creates social media connectors:
- Facebook Pages
- Twitter/X accounts
- LinkedIn company pages

Each connector includes:
- OAuth tokens (test data)
- Platform settings
- Rate limits

#### 7. TopicSeeder
Creates 8 trending topics per category:
- Realistic titles based on industry trends
- Generated descriptions
- Relevant keywords
- Source URLs
- Confidence scores (65-98%)
- Various statuses (discovered, queued, used, expired)

**Total: ~128 topics** (4 brands × 4 categories × 8 topics)

#### 8. ContentDraftSeeder
Creates content drafts in various states:
- Generated from topics
- Complete HTML body content
- SEO metadata
- Multiple statuses (pending_review, approved, published, rejected)
- Platform-specific variants

**Includes:**
- ~100+ content drafts
- ~400+ content variants (website, Facebook, Twitter, LinkedIn)

#### 9. PublishJobSeeder
Creates publishing jobs:
- Published jobs for completed content
- Scheduled jobs for approved content
- Realistic publish results
- External IDs for tracking

#### 10. MetricSeeder
Creates engagement metrics:
- Daily metrics for published content
- Realistic impressions, clicks, likes, shares, comments
- Decay patterns over time
- Platform-specific engagement rates

**Generates:**
- 1000+ metric records with time-series data

## Development Data Summary

After running `DevelopmentSeeder`, your database will contain:

| Resource | Count | Description |
|----------|-------|-------------|
| Brands | 4 | Complete brand configurations |
| Categories | 16 | 4 per brand |
| Users | 5+ | Various roles assigned |
| Website Connectors | 4 | One per brand |
| Social Connectors | 12 | 3 platforms per brand |
| Topics | 128 | Realistic trending topics |
| Content Drafts | 100+ | Various statuses |
| Content Variants | 400+ | Platform-specific content |
| Publish Jobs | 400+ | Published and scheduled |
| Metrics | 1000+ | Engagement data |

## Login Credentials

### Development Environment

**Super Admin:**
- Email: `admin@brandcaster.ai`
- Password: `password`

**Brand Admins:**
- Email: `admin@{brand-domain}`
- Password: `password`

## Testing Different Scenarios

### Test Content Generation

```bash
# Generate content for a specific brand
php artisan content:generate-and-publish --brand=mejba --limit=2

# Generate and auto-approve
php artisan content:generate-and-publish --brand=mejba --auto-approve

# Generate for immediate publishing
php artisan content:generate-and-publish --brand=mejba --immediate
```

### Test Topic Discovery

```bash
# Discover topics for a brand
php artisan topics:discover --brand=mejba --limit=20

# Discover for all brands
php artisan topics:discover
```

### Test Content Scheduling

```bash
# Create 7-day schedule
php artisan content:schedule --brand=mejba --days=7

# Preview without creating
php artisan content:schedule --brand=mejba --dry-run
```

### Test Publishing

```bash
# Publish a specific draft
php artisan content:publish {draft_id}

# Publish all approved drafts
php artisan content:publish --all-approved

# Publish to specific platform
php artisan content:publish {draft_id} --platform=facebook
```

## Customizing Seeders

### Adding More Topics

Edit `TopicSeeder.php` and add to the `$topicTemplates` array:

```php
'your-category-slug' => [
    'Your Topic Title 1',
    'Your Topic Title 2',
    // ...
],
```

### Changing Brand Configurations

Edit `BrandSeeder.php` to modify:
- Brand voice settings
- Style guide rules
- Automation settings
- Posting schedules

### Adjusting Data Volume

To create more/less data, modify the counts in:
- `TopicSeeder.php` - Topics per category
- `ContentDraftSeeder.php` - Drafts per brand
- `MetricSeeder.php` - Days of metrics

## Seeding Order

The seeders **must** run in this order to satisfy dependencies:

1. RolesAndPermissionsSeeder
2. BrandSeeder
3. CategorySeeder
4. UserSeeder
5. WebsiteConnectorSeeder
6. SocialConnectorSeeder
7. TopicSeeder
8. ContentDraftSeeder
9. PublishJobSeeder
10. MetricSeeder

The `DevelopmentSeeder` handles this order automatically.

## Resetting Database

### Complete Reset

```bash
# Drop all tables, run migrations, and seed
php artisan migrate:fresh --seed
```

### Selective Reset

```bash
# Reset specific table and re-seed
php artisan migrate:refresh --path=database/migrations/2025_11_01_174929_create_topics_table.php
php artisan db:seed --class=TopicSeeder
```

## Performance Considerations

- **Development seeding** takes ~30-60 seconds
- **Production seeding** takes ~5-10 seconds
- Generates ~2000+ database records
- Suitable for development and testing
- **Do not run** `DevelopmentSeeder` in production

## Troubleshooting

### Foreign Key Constraint Errors

If you see foreign key errors:

```bash
# Ensure you're running fresh migrations
php artisan migrate:fresh --seed
```

### Memory Limit Issues

For large datasets, increase PHP memory:

```bash
php -d memory_limit=512M artisan db:seed --class=DevelopmentSeeder
```

### Encryption Errors

Ensure `APP_KEY` is set in `.env`:

```bash
php artisan key:generate
```

## CI/CD Integration

### GitHub Actions Example

```yaml
- name: Seed Database
  run: |
    php artisan migrate --force
    php artisan db:seed --force --class=DevelopmentSeeder
```

### Docker Example

```dockerfile
# In your Dockerfile or docker-compose
RUN php artisan migrate:fresh --seed --force
```

## Contributing

When adding new seeders:

1. Create seeder in `database/seeders/`
2. Add to `DevelopmentSeeder.php`
3. Update this README
4. Test with `migrate:fresh --seed`
5. Ensure proper dependency order

## License

Part of BrandCaster AI - All rights reserved.
