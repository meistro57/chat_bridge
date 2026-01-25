# Changelog

All notable changes to Chat Bridge will be documented in this file.

## [0.8.0] - 2026-01-24

### 🧾 Documentation
- Documented the current application version in README
- Added `APP_VERSION` to environment templates and config
- Included `APP_VERSION` in Docker environment defaults

## [Latest] - 2026-01-20

### 🎉 Major Features Added

#### 🔧 **System Diagnostics Dashboard**
- Added comprehensive admin diagnostics panel at `/admin/system`
- 8 diagnostic actions: Health Check, Fix Permissions, Clear Caches, Optimize, Validate AI, Check Database, Run Tests, Fix Code Style
- Real-time system information panel
- Web-based execution of maintenance tasks
- Beautiful UI with action cards and live output console

#### 🔭 **Laravel Telescope Integration**
- Installed and configured Laravel Telescope for application monitoring
- Enabled dark theme (`Telescope::night()`)
- Admin-only access with role-based gate
- Complete request/response tracking
- Exception monitoring
- Database query profiling
- Job and queue monitoring
- Accessible at `/telescope`

#### 🐛 **Laravel Debugbar Integration**
- Installed Laravel Debugbar for real-time profiling
- Query profiling with execution times
- Memory usage tracking
- Timeline visualization
- Route and view information
- Auto-disabled in production

#### 🎨 **Enhanced Dark Theme**
- Custom "Midnight Glass" design system
- Added glassmorphic UI components
- Custom CSS utilities (`.glass-panel`, `.input-dark`, `.btn-dark`, etc.)
- Gradient text effects (blue, purple, emerald)
- Custom dark scrollbars
- Glow effects for interactive elements
- 15+ new Tailwind CSS utility classes
- Deep zinc-950 background for OLED displays
- Frosted glass effects with backdrop blur

#### 📊 **Analytics Dashboard**
- Complete analytics system at `/analytics`
- 7-day activity trend charts (Recharts)
- Top persona statistics
- Advanced query system with filters
- CSV export (up to 1000 records)
- Real-time metrics display

#### 🔑 **API Key Validation System**
- Real-time API key testing
- Visual status indicators (validated/invalid/untested)
- Error tracking and display
- Last validated timestamps
- Test endpoint at `/api-keys/{id}/test`

#### 👥 **Username Login Support**
- Changed login to accept username OR email
- Removed @ requirement from login form
- Updated validation rules
- Updated UI label to "Email or Username"
- Default admin user now uses "admin" as username

#### 📝 **Enhanced Logging**
- Comprehensive conversation lifecycle logging
- Structured logging with context
- Turn-by-turn execution timing
- Error tracking throughout the system

### 🏗️ Infrastructure Improvements

#### 🐳 **Docker Optimizations**
- Fixed Reverb container configuration
- Added `SKIP_MIGRATIONS` flag for services
- Improved docker-entrypoint.sh script
- Better environment variable handling
- All containers stable and functional

#### 📚 **Documentation Overhaul**
- **README.md** - Completely redesigned with:
  - Centered header with badges
  - Feature showcase tables
  - Tech stack grid layout
  - Admin tools documentation
  - Quick stats section
  - Visual hierarchy improvements
  - 900+ lines of comprehensive documentation
- **FEATURES.md** - New comprehensive feature list:
  - 200+ features documented
  - Organized by category
  - Detailed descriptions
  - Use cases and examples
  - 391 lines of feature documentation
- Updated all existing documentation
- Added proper navigation links
- Improved code examples

### 🔒 Security Updates

- API key encryption maintained
- Role-based access for admin features
- Telescope admin-only gate
- Proper authorization checks
- CSRF protection maintained

### 🎯 Performance

- Maintains sub-10ms vector search
- Efficient database queries
- Optimized asset building
- Redis caching operational
- Queue system stable

### 🐛 Bug Fixes

- Fixed file permissions issues (config/ai.php)
- Resolved Reverb container startup issues
- Fixed PersonaSeeder to use correct admin user
- Resolved duplicate migration issues
- Fixed login form validation

### 📦 Dependencies Added

- `laravel/telescope:^5.16` - Application monitoring
- `barryvdh/laravel-debugbar:^3.16` - Debug profiling
- Plus 38 additional dev dependencies for testing

### 🗂️ New Files

#### Controllers
- `app/Http/Controllers/Admin/SystemController.php` - System diagnostics
- `app/Http/Controllers/AnalyticsController.php` - Analytics dashboard

#### Frontend Pages
- `resources/js/Pages/Admin/System.jsx` - Diagnostics UI
- `resources/js/Pages/Analytics/Index.jsx` - Analytics dashboard
- `resources/js/Pages/Dashboard.jsx` - Enhanced dashboard

#### Styling
- `resources/css/app.css` - Enhanced with dark theme utilities

#### Documentation
- `FEATURES.md` - Complete feature list
- `CHANGELOG.md` - This file

#### Configuration
- `config/debugbar.php` - Debugbar configuration
- `config/telescope.php` - Telescope configuration
- `app/Providers/TelescopeServiceProvider.php` - Telescope service provider

### 🔄 Modified Files

#### Routes
- `routes/web.php` - Added admin system routes

#### Database
- `database/seeders/PersonaSeeder.php` - Fixed admin user lookup
- `database/seeders/DatabaseSeeder.php` - Changed admin email to "admin"
- Added Telescope migrations

#### Authentication
- `app/Http/Requests/Auth/LoginRequest.php` - Accept username or email
- `resources/js/Pages/Auth/Login.jsx` - Updated form fields

#### Controllers
- `app/Http/Controllers/PersonaController.php` - Shared persona access
- `app/Http/Controllers/ChatController.php` - Enhanced logging

#### Providers
- `bootstrap/providers.php` - Added TelescopeServiceProvider

### 📊 Statistics

- **Total Lines of Code**: Significantly increased
- **Documentation**: 1,300+ lines
- **Features**: 200+
- **Admin Tools**: 8 diagnostic actions
- **AI Providers**: 8 supported
- **Pre-configured Personas**: 56
- **Test Coverage**: Comprehensive
- **Docker Services**: 6 containers
- **Dependencies**: 93+ packages

### 🚀 Deployment Status

- ✅ All Docker containers running
- ✅ Application accessible at http://localhost:8002
- ✅ Telescope accessible at http://localhost:8002/telescope
- ✅ System Diagnostics at http://localhost:8002/admin/system
- ✅ Debugbar active in development
- ✅ All personas seeded (56)
- ✅ Admin user created (username: admin, password: password)

### 🎨 UI/UX Improvements

- Midnight Glass dark theme fully implemented
- Glassmorphic design system
- Beautiful gradient accents
- Smooth animations and transitions
- Custom scrollbars
- Enhanced hover effects
- Improved visual hierarchy
- Better color contrast
- Accessible design

### 🔧 Developer Experience

- Telescope for debugging
- Debugbar for profiling
- System diagnostics panel
- Web-based test runner
- Code style fixer
- Comprehensive logging
- Docker development environment
- Hot module replacement (Vite)

### 📈 What's Next

See [ROADMAP.md](ROADMAP.md) for upcoming features!

**Planned:**
- Multi-language support
- Mobile app
- Voice conversations
- Plugin system
- Advanced analytics
- Team collaboration

---

## How to Update

```bash
# Pull latest changes
git pull origin main

# Install new dependencies
composer install
npm install

# Run migrations
php artisan migrate --force

# Build assets
npm run build

# Clear caches
php artisan optimize:clear

# Restart Docker (if using Docker)
docker compose down
docker compose up -d
```

---

**Note**: This changelog represents a massive update to the Chat Bridge platform, adding enterprise-grade debugging tools, comprehensive system administration features, and a complete visual overhaul with the Midnight Glass dark theme.
