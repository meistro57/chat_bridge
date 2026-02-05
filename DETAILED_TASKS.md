# 📋 Detailed Task Breakdown - Chat Bridge

**Generated**: February 5, 2026
**Roadmap Status**: v1.1 ~80% complete, v1.2 ready to begin

This document provides a comprehensive breakdown of all remaining tasks to complete the roadmap, organized by priority and with detailed implementation steps.

---

## 🔥 CRITICAL - Complete v1.1 (Finish Q1 2026)

### 1. ⭐ User Avatar Upload
**Priority**: HIGH | **Effort**: 4-6 hours | **Dependencies**: None

#### Implementation Steps:
1. **Database Migration**
   - [x] Create migration: `php artisan make:migration add_avatar_to_users_table`
   - [x] Add `avatar` string column (nullable, stores path)
   - [x] Run migration

2. **Backend Controller**
   - [x] Update `ProfileController.php`:
     - [x] Add `updateAvatar()` method
     - [x] Validate file (image, max 2MB)
     - [x] Store in `storage/app/public/avatars`
     - [x] Delete old avatar if exists
     - [x] Update user model

3. **Frontend UI**
   - [x] Create `resources/js/Pages/Profile/Partials/UpdateAvatarForm.jsx`
   - [x] Add file input with preview
   - [ ] Add cropping functionality (optional, use react-easy-crop)
   - [x] Display current avatar with fallback
   - [x] Add delete avatar button

4. **Storage Configuration**
   - [ ] Run `php artisan storage:link`
   - [ ] Configure filesystem disk in `config/filesystems.php`
   - [x] Add avatar accessor to User model

5. **Testing**
   - [x] Update `tests/Feature/ProfileEnhancedTest.php`
   - [x] Test avatar upload
   - [x] Test avatar update
   - [x] Test avatar deletion
   - [x] Test file validation

**Files to Create/Modify**:
- `database/migrations/*_add_avatar_to_users_table.php`
- `app/Http/Controllers/ProfileController.php`
- `resources/js/Pages/Profile/Partials/UpdateAvatarForm.jsx`
- `app/Models/User.php`
- `tests/Feature/ProfileEnhancedTest.php`

---

### 2. 📧 Weekly Usage Summary Notification
**Priority**: HIGH | **Effort**: 3-4 hours | **Dependencies**: Mail configuration

#### Implementation Steps:
1. **Create Notification Class**
   - [ ] Run: `php artisan make:notification WeeklyUsageSummaryNotification`
   - [ ] Implement `toMail()` method
   - [ ] Include stats:
     - [ ] Conversations started this week
     - [ ] Total messages sent
     - [ ] Total tokens used
     - [ ] Most used personas
     - [ ] Comparison with previous week

2. **Create Scheduled Command**
   - [ ] Run: `php artisan make:command SendWeeklyUsageSummaries`
   - [ ] Query active users (activity in last 7 days)
   - [ ] Calculate weekly stats per user
   - [ ] Dispatch notification (queued)
   - [ ] Log send count

3. **Schedule Configuration**
   - [ ] Add to `routes/console.php`:
     ```php
     Schedule::command('summaries:weekly')->weekly()->mondays()->at('09:00');
     ```

4. **User Preference Check**
   - [ ] Add `weekly_summary` to notification preferences
   - [ ] Update `NotificationPreferencesForm.jsx`
   - [ ] Check preference before sending

5. **Email Template**
   - [ ] Create Markdown mail template
   - [ ] Add charts/visualizations (optional)
   - [ ] Include action button to dashboard

6. **Testing**
   - [ ] Create `tests/Feature/WeeklyUsageSummaryTest.php`
   - [ ] Test command execution
   - [ ] Test notification sending
   - [ ] Test preference checking
   - [ ] Test stats calculation

**Files to Create/Modify**:
- `app/Notifications/WeeklyUsageSummaryNotification.php`
- `app/Console/Commands/SendWeeklyUsageSummaries.php`
- `routes/console.php`
- `resources/views/emails/weekly-usage-summary.blade.php` (optional)
- `resources/js/Pages/Profile/Partials/NotificationPreferencesForm.jsx`
- `tests/Feature/WeeklyUsageSummaryTest.php`

---

### 3. 🚨 Admin Alert Notifications
**Priority**: MEDIUM | **Effort**: 4-5 hours | **Dependencies**: Admin role

#### Implementation Steps:

#### A. User Registration Alerts
1. **Create Notification**
   - [ ] Run: `php artisan make:notification NewUserRegisteredNotification`
   - [ ] Include: user name, email, registration date
   - [ ] Add action button to view user in admin panel

2. **Create Event Listener**
   - [ ] Run: `php artisan make:listener NotifyAdminsOfNewRegistration`
   - [ ] Listen to `Registered` event
   - [ ] Query all admin users
   - [ ] Dispatch notification to each admin

3. **Register Listener**
   - [ ] Add to `bootstrap/app.php` or `EventServiceProvider`

#### B. System Error Alerts
1. **Create Notification**
   - [ ] Run: `php artisan make:notification SystemErrorNotification`
   - [ ] Include: error message, stack trace, context
   - [ ] Add action button to Telescope

2. **Create Exception Handler Hook**
   - [ ] Update `bootstrap/app.php` exception handling
   - [ ] Check if error is critical
   - [ ] Notify admins for critical errors
   - [ ] Rate limit (max 1 per 5 minutes)

3. **Admin Preference**
   - [ ] Add admin notification preferences
   - [ ] Allow admins to opt-in/out
   - [ ] Allow error severity filtering

#### C. Testing
- [ ] Create `tests/Feature/AdminNotificationTest.php`
- [ ] Test registration notification
- [ ] Test error notification
- [ ] Test admin preference checking
- [ ] Test rate limiting

**Files to Create/Modify**:
- `app/Notifications/NewUserRegisteredNotification.php`
- `app/Notifications/SystemErrorNotification.php`
- `app/Listeners/NotifyAdminsOfNewRegistration.php`
- `bootstrap/app.php`
- `tests/Feature/AdminNotificationTest.php`

---

## 📊 HIGH PRIORITY - Begin v1.2 (Q2 2026)

### 4. 📈 Conversation Analytics Dashboard
**Priority**: HIGH | **Effort**: 3-4 days | **Dependencies**: Recharts

#### Implementation Steps:
1. **Backend Analytics Service**
   - [x] Create `app/Services/AnalyticsService.php`
   - [x] Methods:
     - [x] `getConversationMetrics()` - avg length, completion rate
     - [x] `getTokenUsageByProvider()` - per provider breakdown
     - [x] `getCostEstimation()` - calculate costs
     - [x] `getPersonaPopularity()` - usage frequency
     - [x] `getTrendData()` - time series data

2. **Controller & Routes**
   - [x] Create `app/Http/Controllers/AnalyticsController.php`
   - [x] Endpoints:
     - [x] `GET /analytics` - main dashboard
     - [x] `GET /analytics/metrics` - JSON metrics
     - [x] `POST /analytics/export` - CSV/Excel export

3. **Frontend Dashboard**
   - [x] Create `resources/js/Pages/Analytics/Index.jsx`
   - [x] Components:
     - [x] Overview cards (total convos, messages, tokens, cost)
     - [x] Line chart - conversations over time
     - [x] Bar chart - persona popularity
     - [x] Pie chart - provider usage
     - [x] Bar chart - token usage by provider
     - [x] Table - recent conversations

4. **Export Functionality**
   - [x] Install Laravel Excel: `composer require maatwebsite/excel`
   - [x] Create export class
   - [x] Support CSV and Excel formats
   - [x] Include all relevant fields

5. **Database Optimization**
   - [x] Add indexes for analytics queries
   - [ ] Consider materialized views (PostgreSQL)
   - [x] Add caching for expensive queries

6. **Testing**
   - [x] Create `tests/Feature/AnalyticsTest.php`
   - [x] Test metric calculations
   - [x] Test export functionality
   - [ ] Test performance with large datasets

**Files to Create/Modify**:
- `app/Services/AnalyticsService.php`
- `app/Http/Controllers/AnalyticsController.php`
- `resources/js/Pages/Analytics/Index.jsx`
- `app/Exports/ConversationsExport.php`
- `routes/web.php`
- `tests/Feature/AnalyticsTest.php`

**Tech Stack**: Recharts, Laravel Excel, Redis caching

---

### 5. 🔍 Advanced Search & Filtering
**Priority**: HIGH | **Effort**: 2-3 days | **Dependencies**: Existing embeddings

#### Implementation Steps:
1. **Backend Search Service**
   - [ ] Create `app/Services/SearchService.php`
   - [ ] Methods:
     - [ ] `fullTextSearch()` - text search across messages
     - [ ] `vectorSimilaritySearch()` - semantic search using Qdrant
     - [ ] `filterConversations()` - status, date, persona filters
     - [ ] `filterMessages()` - persona, content filters
     - [ ] `saveSearchQuery()` - save user searches

2. **Controller & Routes**
   - [ ] Create `app/Http/Controllers/SearchController.php`
   - [ ] Endpoints:
     - [ ] `POST /search/messages` - message search
     - [ ] `POST /search/conversations` - conversation search
     - [ ] `GET /search/saved` - saved searches
     - [ ] `POST /search/save` - save search

3. **Frontend UI**
   - [ ] Create `resources/js/Pages/Search/Index.jsx`
   - [ ] Features:
     - [ ] Search input with autocomplete
     - [ ] Filter sidebar (status, date, persona, provider)
     - [ ] Result highlighting
     - [ ] Semantic search toggle
     - [ ] Save search button
     - [ ] Saved searches dropdown

4. **Database Tables**
   - [ ] Create `saved_searches` migration
   - [ ] Fields: user_id, query, filters, name

5. **Qdrant Integration**
   - [ ] Use existing `EmbeddingService.php`
   - [ ] Implement similarity threshold
   - [ ] Rank results by relevance

6. **Testing**
   - [ ] Create `tests/Feature/SearchTest.php`
   - [ ] Test full-text search
   - [ ] Test vector search
   - [ ] Test filters
   - [ ] Test saved searches

**Files to Create/Modify**:
- `app/Services/SearchService.php`
- `app/Http/Controllers/SearchController.php`
- `resources/js/Pages/Search/Index.jsx`
- `database/migrations/*_create_saved_searches_table.php`
- `app/Models/SavedSearch.php`
- `tests/Feature/SearchTest.php`

---

### 6. 📝 Conversation Templates
**Priority**: HIGH | **Effort**: 2 days | **Dependencies**: None

#### Implementation Steps:
1. **Database Schema**
   - [x] Create `conversation_templates` migration
   - [x] Fields:
     - [x] name, description, category
     - [x] starter_message, max_rounds
     - [x] persona_a_id, persona_b_id
     - [x] is_public, user_id

2. **Model & Relationships**
   - [x] Create `ConversationTemplate` model
   - [x] Relationships to User and Personas
   - [x] Scopes: public, private, byCategory

3. **Controller & Routes**
   - [x] Create `app/Http/Controllers/ConversationTemplateController.php`
   - [x] CRUD endpoints
   - [x] `POST /templates/{id}/use` - start from template

4. **Frontend Pages**
   - [x] Create `resources/js/Pages/Templates/Index.jsx`
   - [x] Create `resources/js/Pages/Templates/Create.jsx`
   - [x] Create `resources/js/Pages/Templates/Edit.jsx`
   - [x] Features:
     - [x] Template library (grid view)
     - [x] Category filter
     - [x] Public/private toggle
     - [x] Quick start button
     - [x] Clone template option

5. **Integration with Chat Create**
   - [x] Update `resources/js/Pages/Chat/Create.jsx`
   - [x] Add "Start from Template" button
   - [x] Auto-populate form from template

6. **Seeder**
   - [x] Create template seeder with examples:
     - [x] Debate template
     - [x] Brainstorming template
     - [x] Interview template
     - [x] Story writing template

7. **Testing**
   - [x] Create `tests/Feature/ConversationTemplateTest.php`
   - [x] Test CRUD operations
   - [x] Test public/private access
   - [x] Test template usage

**Files to Create/Modify**:
- `database/migrations/*_create_conversation_templates_table.php`
- `app/Models/ConversationTemplate.php`
- `app/Http/Controllers/ConversationTemplateController.php`
- `resources/js/Pages/Templates/{Index,Create,Edit}.jsx`
- `resources/js/Pages/Chat/Create.jsx`
- `database/seeders/ConversationTemplateSeeder.php`
- `tests/Feature/ConversationTemplateTest.php`

---

## 🔧 TECHNICAL DEBT - Ongoing Priority

### 7. 🏗️ Refactor ConversationService
**Priority**: MEDIUM | **Effort**: 1-2 days | **Dependencies**: None

#### Issues:
- Service is too large (~500+ lines)
- Multiple responsibilities (orchestration, turn generation, state management)
- Difficult to test and maintain

#### Refactoring Plan:
1. **Extract Turn Generator**
   - [ ] Create `app/Services/TurnGeneratorService.php`
   - [ ] Move turn generation logic
   - [ ] Move AI provider calls

2. **Extract State Manager**
   - [ ] Create `app/Services/ConversationStateManager.php`
   - [ ] Move status updates
   - [ ] Move round tracking
   - [ ] Move stop detection

3. **Extract Message Handler**
   - [ ] Create `app/Services/MessageHandler.php`
   - [ ] Move message creation
   - [ ] Move embedding generation
   - [ ] Move broadcast logic

4. **Simplify ConversationService**
   - [ ] Keep only orchestration logic
   - [ ] Inject new services
   - [ ] Reduce to ~150 lines

5. **Update Tests**
   - [ ] Update existing tests
   - [ ] Add unit tests for new services
   - [ ] Maintain 100% coverage

**Files to Create/Modify**:
- `app/Services/TurnGeneratorService.php`
- `app/Services/ConversationStateManager.php`
- `app/Services/MessageHandler.php`
- `app/Services/ConversationService.php`
- `tests/Unit/TurnGeneratorServiceTest.php`
- `tests/Unit/ConversationStateManagerTest.php`
- `tests/Unit/MessageHandlerTest.php`

---

### 8. ⚡ Add Missing Database Indexes
**Priority**: MEDIUM | **Effort**: 2-3 hours | **Dependencies**: None

#### Recommended Indexes:
1. **Conversations Table**
   - [ ] Index on `status` (for filtering)
   - [ ] Index on `user_id, created_at` (for user dashboard)
   - [ ] Index on `user_id, status` (for active conversations)

2. **Messages Table**
   - [ ] Index on `conversation_id, created_at` (for ordering)
   - [ ] Index on `persona_id` (for analytics)
   - [ ] Full-text index on `content` (PostgreSQL)

3. **Personas Table**
   - [ ] Index on `user_id, created_at`
   - [ ] Index on `name` (for search)

4. **Api Keys Table**
   - [ ] Index on `user_id, provider`

#### Implementation:
- [ ] Create migration: `php artisan make:migration add_performance_indexes`
- [ ] Test query performance before/after
- [ ] Update documentation

**Files to Create**:
- `database/migrations/*_add_performance_indexes.php`

---

### 9. 💅 Add Loading Skeletons
**Priority**: LOW | **Effort**: 1-2 days | **Dependencies**: None

#### Pages to Update:
1. **Dashboard**
   - [ ] Skeleton for stats cards
   - [ ] Skeleton for conversation list

2. **Chat Index**
   - [ ] Skeleton for conversation grid

3. **Chat Create**
   - [ ] Skeleton for model loading

4. **Personas Index**
   - [ ] Skeleton for persona cards

5. **Analytics**
   - [ ] Skeleton for charts
   - [ ] Skeleton for tables

#### Implementation:
- [ ] Create reusable skeleton components:
  - [ ] `resources/js/Components/Skeletons/CardSkeleton.jsx`
  - [ ] `resources/js/Components/Skeletons/ListSkeleton.jsx`
  - [ ] `resources/js/Components/Skeletons/ChartSkeleton.jsx`
- [ ] Use Tailwind animate-pulse
- [ ] Show during data loading

**Files to Create/Modify**:
- `resources/js/Components/Skeletons/*.jsx`
- Update all pages with loading states

---

## 📅 Recommended Implementation Order

### Phase 1: Complete v1.1 (This Week)
1. User Avatar Upload (4-6 hours)
2. Weekly Usage Summary (3-4 hours)
3. Admin Alerts (4-5 hours)

**Total Effort**: ~2 days

---

### Phase 2: Start v1.2 - Analytics & Search (Next 2 Weeks)
1. Conversation Analytics Dashboard (3-4 days)
2. Advanced Search & Filtering (2-3 days)

**Total Effort**: ~5-7 days

---

### Phase 3: v1.2 - Templates & Debt (Following Week)
1. Conversation Templates (2 days)
2. Refactor ConversationService (1-2 days)
3. Add Database Indexes (2-3 hours)

**Total Effort**: ~3-4 days

---

### Phase 4: Polish & Optimize (Ongoing)
1. Add Loading Skeletons (1-2 days)
2. Additional performance optimizations
3. Bug fixes and refinements

**Total Effort**: ~2-3 days

---

## 🎯 Success Criteria

### v1.1 Complete When:
- [x] Admin Panel fully functional with all CRUD operations
- [ ] Profile management includes avatar upload
- [ ] Email notifications include all planned types
- [ ] All tests passing
- [ ] Documentation updated

### v1.2 Complete When:
- [ ] Analytics dashboard shows comprehensive metrics
- [ ] Search supports full-text and semantic search
- [ ] Templates can be created, shared, and used
- [ ] API documentation complete (Scribe)
- [ ] Performance targets met (<200ms response time)

---

## 📊 Estimated Total Remaining Effort

| Phase | Effort | Priority |
|-------|--------|----------|
| Complete v1.1 | 2 days | 🔥 CRITICAL |
| Start v1.2 (Analytics + Search) | 5-7 days | ⭐ HIGH |
| v1.2 (Templates + Refactoring) | 3-4 days | ⭐ HIGH |
| Polish & Optimize | 2-3 days | 💡 MEDIUM |

**Total**: ~12-16 days of focused development

---

## 📝 Notes

- All tasks include comprehensive testing requirements
- Follow Laravel Boost guidelines for all development
- Use existing code conventions and patterns
- Run `vendor/bin/pint --dirty` before committing
- Update tests for any modified functionality
- Document new features in README.md

---

**Generated by Claude Code Agent**
**Session**: claude/review-roadmap-tasks-q8yIk
