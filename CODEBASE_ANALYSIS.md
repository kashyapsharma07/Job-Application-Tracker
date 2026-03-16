# JobTracker Codebase Analysis
**Generated:** March 16, 2026 | **Version:** 1.0.0

---

## Table of Contents
1. [Executive Summary](#executive-summary)
2. [Existing Features](#existing-features)
3. [Database Schema & Relationships](#database-schema--relationships)
4. [API/Model Methods](#apimodel-methods)
5. [UI/UX Components & Pages](#uiux-components--pages)
6. [Business Logic & Workflows](#business-logic--workflows)
7. [Feature Implementation Status](#feature-implementation-status)
8. [Gaps & Missing Features](#gaps--missing-features)
9. [Technical Stack](#technical-stack)

---

## Executive Summary

**JobTracker** is a full-featured job application management system built with PHP 8.0+ and MySQL 8.0. It enables job seekers to:
- Track job applications across multiple statuses (Wishlist → Applied → Interviewing → Offer/Rejected)
- Manage multiple resume versions and link them to applications
- Schedule reminders with automated email notifications
- View analytics with conversion funnels and application trends
- Organize events in a calendar view
- Manage user profiles and notification preferences

**Current Status:** Core features fully implemented with advanced analytics layer recently added.

---

## Existing Features

### 1. **Authentication & User Management** ✅ COMPLETE
- **Registration & Login** with bcrypt password hashing
- **Session Management** with 24-hour lifetime and HTTP-only cookies
- **CSRF Protection** via token validation
- **Profile Management** (name, job title, avatar support)
- **Notification Preferences** (email alerts, interview reminders, marketing comms)
- **Password Reset** (token-based infrastructure in place)
- **User Account Deletion** capability

### 2. **Application Management** ✅ COMPLETE
- **Full CRUD Operations** on job applications
- **Multi-view Display:**
  - Kanban board view (drag-and-drop ready)
  - List view with filters
  - Detailed application view
- **Application Fields:**
  - Company name, job title, job URL
  - Job type (remote, hybrid, onsite, not_specified)
  - Application status (wishlist, applied, interviewing, offer, rejected)
  - Salary range
  - Linked resume version
  - Rich notes/description
  - Applied date
  
- **Filtering & Search:**
  - Filter by status
  - Text search across company name and job title
- **Status Tracking:**
  - Automatic event logging on status changes
  - Status update from detail view

### 3. **Resume Management** ✅ COMPLETE
- **Upload Multiple Resume Versions** (PDF/DOCX only)
- **File Storage** in `/public/uploads/resumes/`
- **Version Labeling** for custom naming (v1, final, tailored, etc.)
- **Default Resume** marking for quick linking
- **Resume Metadata:**
  - Original filename, stored filename
  - File size tracking
  - Upload date/version created at
- **Resume Linking** to applications
- **Delete with File Cleanup** functionality

### 4. **Calendar & Event Management** ✅ COMPLETE
- **Interactive Monthly Calendar** with navigation
- **Event Types:**
  - Status changes (automatic)
  - Interviews
  - Deadlines
  - Follow-ups
  - Notes
  - Offers
- **Timeline View** per application with event history
- **Date Mapping** for visual organization
- **Upcoming Events List** with 5-item limit
- **Reminder Integration** in calendar display (recent enhancement)

### 5. **Reminder System** ✅ COMPLETE
- **Schedule Reminders** with title, description, date/time
- **Link to Applications** (optional, can be standalone)
- **Status Tracking:** Pending, Sent, Overdue
- **Automated Email Delivery** via cron job (every 5 minutes)
- **User Notification Preferences** (respects interview_reminders flag)
- **Email Template** with rich HTML formatting
- **Reminder Management:** Create, view, delete

### 6. **Analytics Dashboard** ✅ COMPLETE (Recent Enhancement)
- **Status Breakdown:**
  - Total applications count
  - Counts per status (Wishlist, Applied, Interviewing, Offer, Rejected)
  - Visual badges
  
- **Time-Series Analytics:**
  - Applications by month (last 6 months)
  - Applications by week (last 12 weeks)
  - Applications by year (last 3 years)
  - Chart.js visualization with switchable time views
  
- **Advanced Metrics:**
  - Conversion funnel (Applied → Interviewed → Offer)
  - Top companies by application count
  - Interview rate percentage
  - Success rate (offer percentage)
  - Best day of week (by progression rate)
  - This month's application count
  - Stalled applications (7+ days without update)
  
- **Insight Chips:**
  - Warning alerts for stalled applications
  - Positive reinforcement for above-average interview rates
  - Contextual tips based on application history
  - Best application submission day

### 7. **Settings & Preferences** ✅ COMPLETE
- **Profile Information:**
  - Name, email, job title editing
  - Avatar placeholder with initials
  
- **Notification Preferences:**
  - Email alerts toggle
  - Interview reminders toggle
  - Marketing communications toggle
  
- **Security:**
  - Password change with complexity validation:
    - Minimum 8 characters
    - Must include uppercase, lowercase, number, symbol

### 8. **Navigation & UI** ✅ COMPLETE
- **Responsive Sidebar Navigation**
  - Dashboard, Applications, Calendar, Analytics, Resumes, Settings
  - User card with plan display
  - Logout button
  
- **Top Bar:**
  - Global application search
  - Quick "Add New Application" button
  - Reminders icon link
  
- **Flash Messages** for success/error feedback
- **Bootstrap 5.3** + custom CSS styling
- **Dark mode compatible** CSS variables

---

## Database Schema & Relationships

### Table: `users`
```sql
id (PK, INT)
name, email (UNIQUE), password_hash
job_title, avatar
plan ENUM('free', 'premium')
email_alerts, interview_reminders, marketing_comms TINYINT(1)
created_at, updated_at (TIMESTAMPS)
```
**Purpose:** User accounts and preferences
**Indexes:** UNIQUE on email

---

### Table: `applications`
```sql
id (PK, INT)
user_id (FK → users)
company, job_title, job_url
job_type ENUM('remote', 'hybrid', 'onsite', 'not_specified')
status ENUM('wishlist', 'applied', 'interviewing', 'offer', 'rejected')
salary_range, notes TEXT
resume_id (FK → resumes, nullable)
applied_at DATE
created_at, updated_at TIMESTAMPS
```
**Purpose:** Core job application tracking
**Relationships:**
- 1 user → many applications (ON DELETE CASCADE)
- 1 resume → many applications (ON DELETE SET NULL)
- 1 application → many events
**Indexes:** user_id, status

---

### Table: `resumes`
```sql
id (PK, INT)
user_id (FK → users)
filename (stored name), original_name: VARCHAR(255)
file_size INT
version_label VARCHAR(100)
is_default TINYINT(1)
created_at TIMESTAMP
```
**Purpose:** Resume version management
**Relationships:**
- 1 user → many resumes (ON DELETE CASCADE)
**Indexes:** user_id

---

### Table: `application_events`
```sql
id (PK, INT)
application_id (FK → applications)
event_type ENUM('status_change', 'interview', 'follow_up', 'deadline', 'note', 'offer')
title VARCHAR(200), description TEXT
event_date DATETIME
created_at TIMESTAMP
```
**Purpose:** Timeline/audit trail per application
**Relationships:**
- 1 application → many events (ON DELETE CASCADE)
**Indexes:** application_id, event_date

---

### Table: `reminders`
```sql
id (PK, INT)
user_id (FK → users), application_id (FK → applications, nullable)
title VARCHAR(200), description TEXT
remind_at DATETIME
sent TINYINT(1), sent_at TIMESTAMP NULL
created_at TIMESTAMP
```
**Purpose:** Scheduled reminders with email tracking
**Relationships:**
- 1 user → many reminders (ON DELETE CASCADE)
- 1 application → many reminders (optional, ON DELETE CASCADE)
**Indexes:** remind_at, sent (composite)

---

### Table: `password_resets`
```sql
id (PK, INT)
user_id (FK → users)
token VARCHAR(64, UNIQUE)
expires_at, used TINYINT(1)
created_at TIMESTAMP
```
**Purpose:** Password reset token management
**Status:** Infrastructure in place, password reset page not yet implemented
**Indexes:** token (UNIQUE)

---

## API/Model Methods

### **User Model** (`src/models/User.php`)

| Method | Signature | Returns | Purpose |
|--------|-----------|---------|---------|
| `findByEmail()` | `(string $email): ?array` | User record or null | Login lookups |
| `findById()` | `(int $id): ?array` | User record or null | Profile retrieval |
| `create()` | `(array $data): int` | User ID | New user registration |
| `update()` | `(int $id, array $data): bool` | Success boolean | Profile updates |
| `updatePassword()` | `(int $id, string $newPassword): bool` | Success boolean | Password change |
| `verifyPassword()` | `(string $password, string $hash): bool` | True/false | Auth validation |
| `emailExists()` | `(string $email, int $excludeId=0): bool` | True/false | Duplicate check |
| `delete()` | `(int $id): bool` | Success boolean | Account deletion |

---

### **Application Model** (`src/models/Application.php`)

| Method | Signature | Returns | Purpose |
|--------|-----------|---------|---------|
| **Core CRUD** |
| `getAll()` | `(int $userId, array $filters): array` | Application array | List with filter/search |
| `getById()` | `(int $id, int $userId): ?array` | Single app or null | Detailed view |
| `create()` | `(int $userId, array $data): int` | App ID | New application |
| `update()` | `(int $id, int $userId, array $data): bool` | Success bool | Edit application |
| `delete()` | `(int $id, int $userId): bool` | Success bool | Delete application |
| `updateStatus()` | `(int $id, int $userId, string $status): bool` | Success bool | Update status + log |
| **Analytics** |
| `countByStatus()` | `(int $userId): array` | Status → count map | Status distribution |
| `countByMonth()` | `(int $userId, int $months=6): array` | Month → count array | Time-series data |
| `countByWeek()` | `(int $userId, int $weeks=12): array` | Week → count array | Weekly breakdown |
| `countByYear()` | `(int $userId, int $years=3): array` | Year → count array | Yearly summary |
| `topCompanies()` | `(int $userId, int $limit=5): array` | Company array | Top employers |
| `conversionFunnel()` | `(int $userId): array` | 3-step funnel | Applied → Interviewed → Offer |
| `stalledApps()` | `(int $userId, int $days=7): array` | App array | Inactive 7+ days |
| `bestDayOfWeek()` | `(int $userId): ?string` | Day name or null | Highest progression day |
| `countThisMonth()` | `(int $userId): int` | Count integer | Current month apps |
| **Events/Timeline** |
| `addEvent()` | `(int $appId, string $type, string $title, ?string $desc, ?string $date): int` | Event ID | Log event |
| `getEvents()` | `(int $appId): array` | Event array | App timeline |
| `getCalendarEvents()` | `(int $userId, string $year, string $month): array` | Event array | Month view |
| `getUpcomingEvents()` | `(int $userId, int $limit=5): array` | Event array | Next events |

---

### **Resume Model** (`src/models/Resume.php`)

| Method | Signature | Returns | Purpose |
|--------|-----------|---------|---------|
| `getAll()` | `(int $userId): array` | Resume array | List all versions |
| `getById()` | `(int $id, int $userId): ?array` | Resume or null | Retrieve version |
| `create()` | `(int $userId, array $data): int` | Resume ID | Upload new version |
| `delete()` | `(int $id, int $userId): ?string` | Filename or null | Delete + unlink |
| `setDefault()` | `(int $id, int $userId): bool` | Success bool | Mark as default |

---

### **Reminder Model** (`src/models/Reminder.php`)

| Method | Signature | Returns | Purpose |
|--------|-----------|---------|---------|
| `getAll()` | `(int $userId): array` | Reminder array | All user reminders |
| `getPending()` | `(): array` | Reminder array | Due reminders (for cron) |
| `create()` | `(int $userId, array $data): int` | Reminder ID | New reminder |
| `markSent()` | `(int $id): bool` | Success bool | Update sent flag |
| `delete()` | `(int $id, int $userId): bool` | Success bool | Delete reminder |

---

### **Auth Helper** (`src/helpers/Auth.php`)

| Method | Signature | Returns | Purpose |
|--------|-----------|---------|---------|
| `start()` | `(): void` | N/A | Initialize session |
| `login()` | `(array $user): void` | N/A | Create session |
| `logout()` | `(): void` | N/A | Destroy session |
| `check()` | `(): bool` | True/false | Verify logon |
| `id()` | `(): ?int` | User ID or null | Current user |
| `user()` | `(): array` | User credentials | Session data |
| `require()` | `(): void` | N/A | Force login (redirect if not) |
| `csrfToken()` | `(): string` | Token string | Get or generate |
| `verifyCsrf()` | `(string $token): bool` | True/false | Validate token |

---

### **Mailer Helper** (`src/helpers/Mailer.php`)

| Method | Signature | Returns | Purpose |
|--------|-----------|---------|---------|
| `send()` | `(string $to, string $toName, string $subject, string $htmlBody): bool` | Success bool | Send email |
| `reminderEmail()` | `(array $reminder): string` | HTML string | Format reminder email |

---

## UI/UX Components & Pages

### **Public Pages (`public/`):**

#### 1. **Login** (`login.php`) ✅
- Email/password form
- CSRF protection
- Error display
- Link to registration
- Redirects authenticated users to dashboard
- Bootstrap auth card layout

#### 2. **Register** (`register.php`) ✅ 
- Name, email, password fields
- Password confirmation
- Validation error messages
- Link to login
- Terms acceptance (optional)
- Account creation flow

#### 3. **Dashboard** (`dashboard.php`) ✅ FEATURE-RICH
- **Stats Cards:**
  - Total applications
  - Interviewing count
  - Offers received
  - Success rate (%)
  
- **Kanban Preview:**
  - 4 columns (Wishlist, Applied, Interviewing, Offer)
  - 3-card preview per column
  - Company initials avatars
  - Job title + company name
  - Applied date
  - Click-to-detail navigation
  
- **Upcoming Events Sidebar:**
  - 5-item event list
  - Event type badges
  - Date/time display
  - Link to calendar
  
- **Stage Breakdown (Pie Chart):**
  - Visual status distribution
  - Click through to filter
  
- **Recent Activity:**
  - Last 6 applications
  - Quick visual scanning

#### 4. **Applications** (`applications.php`) ✅ DUAL VIEW
- **Tab Controls:**
  - All/Wishlist/Applied/Interviewing/Offer/Rejected
  - Badge counts per tab
  
- **View Toggle:**
  - Kanban view (default)
  - List view
  
- **Kanban View:**
  - Drag-and-drop ready structure
  - Column headers with status color dots
  - Card preview with company logo letter
  - Quick edit/delete buttons
  
- **List View:**
  - Table format
  - Sortable columns
  - Inline status update
  - Edit/delete actions
  
- **Modal Form:**
  - Company, job title (required)
  - Job URL, job type dropdown
  - Status dropdown
  - Salary range, notes textarea
  - Resume picker dropdown
  - Applied date picker
  - AJAX form submission

#### 5. **Application Detail** (`application-detail.php`) ✅
- **Header Card:**
  - Company logo (initial)
  - Job title + status badge
  - Company name
  - Job type, salary, applied date
  - Job posting link
  - Edit button
  
- **Notes Section:**
  - Preformatted text display
  - Collapsible (if empty, hidden)
  
- **Timeline:**
  - Event history reverse-chronological
  - Event type label (colored)
  - Title, description, date
  - Add event modal
  
- **Resume Card:**
  - Original filename
  - Version label
  - File icon indicator
  
- **Status Update Panel:**
  - 5 quick-status buttons
  - Visual feedback on current status

#### 6. **Calendar** (`calendar.php`) ✅ COMPREHENSIVE
- **Month Navigation:**
  - Previous/next buttons
  - "Today" button
  - Current month/year display
  
- **Calendar Grid:**
  - 7-column week layout
  - Previous/next month days (grayed)
  - Event dots on dates
  - Click to date detail
  
- **Event Dots:**
  - Color-coded by type (interview, deadline, follow_up, note, offer)
  - Multiple events per day
  - Hover tooltips (optional)
  
- **Upcoming Events List:**
  - 5 most recent events
  - Merged application events + reminders (recent enhancement)
  - Type-specific badges
  - Link to detail

#### 7. **Analytics** (`analytics.php`) ✅ ADVANCED
- **Insight Chips:**
  - Stalled application warnings
  - Interview rate positives
  - Best day of week tips
  - Offer celebration messages
  
- **Conversion Funnel:**
  - 3-step visualization (Applied → Interviewed → Offer)
  - Absolute counts per step
  - Color progression
  
- **Applications Over Time Chart:**
  - Switchable: week/month/year views
  - Chart.js bar chart
  - Axis labels, legend
  
- **Top Companies:**
  - Card grid (5 companies)
  - Company name, app count
  - Status badge
  
- **Recent Activity:**
  - Last 6 applications table
  - Company, job title, status, date

#### 8. **Reminders** (`reminders.php`) ✅
- **Empty State:**
  - Icon + message when no reminders
  - Create button
  
- **Reminder Table:**
  - Title + snippet of description
  - Linked application (company + job title)
  - Remind at datetime
  - Status badge (Pending/Sent/Overdue)
  - Delete button
  
- **New Reminder Modal:**
  - Title (required)
  - Application picker (optional)
  - Description textarea
  - Date/time picker
  - Submit button

#### 9. **Resumes** (`resumes.php`) ✅
- **Empty State:**
  - Icon + message
  - Upload button
  
- **Resume Cards Grid:**
  - PDF icon indicator
  - Original filename
  - Version label badge
  - File size display
  - Upload date
  - "Set Default" action
  - Delete action (with confirmation)
  
- **Upload Modal:**
  - File input (PDF/DOCX only)
  - Version label field
  - "Set as default" checkbox
  - Upload button
  - Progress indicator

#### 10. **Settings** (`settings.php`) ✅
- **Profile Section:**
  - User avatar (initial)
  - Name field
  - Job title field
  - Email field (with duplicate check)
  - Save button
  
- **Notifications Section:**
  - Email alerts checkbox
  - Interview reminders checkbox
  - Marketing communications checkbox
  - Save button
  
- **Security Section:**
  - Current password field
  - New password field
  - Confirm password field
  - Password complexity display
  - Save button

#### 11. **Index** (`index.php`) ✅
- Redirect to dashboard (authenticated) or login (guest)

#### 12. **Logout** (`logout.php`) ✅
- Destroy session
- Redirect to login

---

### **View Partials:**

#### **Header** (`views/partials/header.php`)
- HTML/HEAD boilerplate
- Font imports (Sora, Inter)
- Bootstrap 5.3 + Icons CDN
- Custom CSS link
- Sidebar structure
- Topbar structure
- Flash message display
- JavaScript init

#### **Footer** (`views/partials/footer.php`)
- Bootstrap JS bundle
- Chart.js library
- Custom app.js
- Page-specific script blocks

---

## Business Logic & Workflows

### **Workflow 1: New Job Application**
```
1. User clicks "Add New Application"
2. Modal form opens with fields
3. Required validation: company, job title
4. Form submit (AJAX POST) → applications.php
5. Application::create() called
6. Automatic event logged: "Application created" + status
7. User redirected to applications page with success flash
8. New app appears in Kanban board
```

### **Workflow 2: Track Application Progress**
```
1. User views application detail page
2. Reviews existing events in timeline
3. Clicks "Add Event" to log interview/deadline/follow-up
4. Modal form appears for event details
5. Event logged with date/type/title/description
6. Appears in timeline, sorted reverse-chronological
7. Calendar auto-populates with event date
```

### **Workflow 3: Status Transition**
```
1. User updates application status (Kanban drag or detail quick-update)
2. AJAX POST with new status
3. Application::updateStatus() called
4. Automatic event logged: "Status updated: X → Y"
5. UI updates immediately (Kanban card moves)
6. Analytics recalculated on dashboard refresh
```

### **Workflow 4: Resume Version Management**
```
1. User uploads resume PDF/DOCX
2. File validated (size, MIME type)
3. Stored as: resume_{user_id}_{uniqid}.{ext}
4. Original filename preserved in DB
5. Version label stored for user reference
6. Can mark as "default" (clears old default)
7. When creating application, user selects resume version
8. Resume link stored in applications.resume_id
```

### **Workflow 5: Reminder & Auto Email**
```
1. User creates reminder with title, optional app link, remind_at datetime
2. Reminder inserted in DB (sent=0)
3. Every 5 minutes, cron job (send_reminders.php) runs
4. Reminder::getPending() fetches due reminders (remind_at <= NOW, sent=0)
5. User preference check: interview_reminders=1 required
6. Mailer::reminderEmail() generates HTML email
7. Mailer::send() dispatches via PHPMailer (SMTP) or fallback mail()
8. Reminder::markSent() updates sent=1, sent_at=NOW
9. Cron logs success/failure
```

### **Workflow 6: Analytics Calculation**
```
User views /analytics.php:
1. ApplicationModel::countByStatus() → status breakdown
2. ApplicationModel::countByMonth() → 6-month trend
3. ApplicationModel::topCompanies() → top 5 employers
4. ApplicationModel::conversionFunnel() → 3-step funnel
5. ApplicationModel::stalledApps() → apps inactive 7+ days
6. ApplicationModel::bestDayOfWeek() → highest progression day
7. Computed metrics: interview rate, offer rate, success rate
8. Insight chips generated based on thresholds
9. Chart.js data JSON encoded for frontend rendering
10. Time view (week/month/year) switchable via GET param
```

### **Workflow 7: User Settings Update**
```
1. User visits /settings.php
2. Three sections: Profile, Notifications, Security
3. Profile update: name, job_title (no email change here)
4. Notifications: toggles for email_alerts, interview_reminders, marketing_comms
5. Security: current password validation, new password complexity check
6. Complexity rules: 8+ chars, must include uppercase, lowercase, digit, symbol
7. Password hashed with bcrypt (cost 12)
8. Flash success message on save
9. Profile changes reflected in sidebar user card immediately
```

---

## Feature Implementation Status

### **Fully Implemented** ✅

| Feature | Status | Notes |
|---------|--------|-------|
| User Authentication | ✅ | Registration, login, logout, CSRF protection |
| Application CRUD | ✅ | Create, read, update, delete with full field support |
| Application Status Tracking | ✅ | 5 status states with automatic event logging |
| Resume Management | ✅ | Upload, version labeling, set default, delete |
| Application-Resume Linking | ✅ | One resume per application, optional |
| Event Timeline | ✅ | Per-application event history, 6 event types |
| Calendar View | ✅ | Monthly calendar, event dots, upcoming list |
| Reminders | ✅ | Create, schedule, view, delete, status tracking |
| Email Reminders (Cron) | ✅ | Automated via cron job, PHPMailer + fallback |
| Kanban Board | ✅ | Visual 4-column layout, ready for drag-drop implementation |
| List View | ✅ | Table format with filter/search, inline actions |
| User Profile Settings | ✅ | Name, job title, avatar placeholder, plan display |
| Notification Preferences | ✅ | Email alerts, interview reminders, marketing comms toggles |
| Password Change | ✅ | Current password validation, complexity enforcement |
| Sidebar Navigation | ✅ | 6 main pages + settings, responsive layout |
| Top Bar Search | ✅ | JavaScript-based search placeholder |
| Flash Messages | ✅ | Success/error notifications via sessions |
| Analytics Dashboard | ✅ | Status breakdown, time-series, funnel, insights |
| Conversion Funnel | ✅ | 3-step visualization (Applied → Interviewed → Offer) |
| Time-Series Charts | ✅ | Week/month/year selectable views with Chart.js |
| Stalled Application Detection | ✅ | Identifies apps inactive 7+ days |
| Best Day of Week | ✅ | Calculates highest progression day |
| Interview Rate Calculation | ✅ | Percentage of apps in interviewing/offer states |
| Success Rate Metric | ✅ | Offer percentage of total applications |
| Calendar-Reminder Integration | ✅ | Reminders display on calendar with application events |

### **Partially Implemented** ⚠️

| Feature | Status | Notes |
|---------|--------|-------|
| Password Reset | ⚠️ | DB schema in place, token generation ready, but password-reset.php page not implemented |
| Drag-and-Drop | ⚠️ | Kanban structure ready, JavaScript drag-drop handlers not wired up |
| Global Search | ⚠️ | Top bar search input exists, JavaScript binding incomplete |
| Avatar Upload | ⚠️ | Schema supports avatar filename, upload form not implemented |

### **Not Started** ❌

| Feature | Status | Alternative |
|---------|--------|-------------|
| Interview Notes | ❌ | Use application notes or event descriptions |
| Job Posting Scraping | ❌ | Manual entry required |
| Email Notifications on Status | ❌ | Reminders serve similar purpose |
| Activity Feed | ❌ | Recent activity visible on dashboard |
| Bulk Operations | ❌ | Individual operations available |
| Export (CSV/PDF) | ❌ | Database direct access available |
| Mobile App | ❌ | Web-responsive; mobile-optimized sufficient |
| API Endpoints | ❌ | Internal controllers only |
| Dark Mode | ❌ | CSS variables support light theme |
| Multi-language (i18n) | ❌ | English only |

---

## Gaps & Missing Features

### **High Priority (Quick Wins)**

1. **Drag-and-Drop Kanban** ⚡
   - Kanban structure present, need event listeners
   - Would enable visual status updates without modals
   - Suggested: vanilla JS or Sortable.js library

2. **Password Reset Flow** 🔐
   - Infrastructure in place (password_resets table)
   - Need: forgot-password.php page + email logic
   - Required: token generation, expiry validation, reset link email

3. **Global Search** 🔍
   - Input field ready in topbar
   - Need: AJAX endpoint to search applications + results display
   - Could search: company, job title, notes, salary range

4. **Avatar Upload** 👤
   - Schema supports avatar, no upload UI
   - Could add to settings.php
   - Store in /public/uploads/avatars/

### **Medium Priority (Features)**

5. **Interview Notes/Feedback** 📝
   - Event descriptions partially serve this
   - Could create dedicated "interview_notes" table
   - Track interviewer, questions, impressions, follow-up items

6. **Job Posting Integration** 🔗
   - Save job description/requirements?
   - Could add rich text field for pasting job posting
   - Enable keyword matching for resume tailoring

7. **Bulk Operations** ✅✅✅
   - Bulk status update (select multiple, change status)
   - Bulk delete (with confirmation)
   - Bulk resume linking
   - Checkbox selection in list/kanban view

8. **Custom Filters & Saved Views** 🎨
   - Save filter combinations (e.g., "Remote + Remote-first companies")
   - Smart views ("Offers I haven't accepted yet")
   - Filter by job type, salary range, date range

9. **Salary Analytics** 💰
   - Average salary by company
   - Salary by job type (remote vs onsite)
   - Salary range distribution histogram

10. **Email Notifications on Events** 📧
    - Email when reminder is due (already implemented)
    - Optional: email on status change by user
    - Optional: daily digest of upcoming events

### **Lower Priority (Nice-to-Have)**

11. **Export Functionality** 📊
    - CSV export of applications (company, status, salary, date)
    - PDF report with analytics summary
    - iCal export of calendar events

12. **Integrations** 🔌
    - LinkedIn API (pull job postings)
    - Google Calendar (sync events)
    - Slack notifications

13. **Mobile Optimization** 📱
    - Responsive design mostly present
    - Could optimize smaller screens
    - Mobile-friendly date/time pickers

14. **Collaboration Features** 👥
    - Share applications with recruiter
    - Feedback comments from mentors
    - Shared analytics/insights

15. **Interview Preparation** 🎯
    - Interview questions library
    - Company research templates
    - Practice interview scheduler

16. **Dark Mode** 🌙
    - CSS variables in place for easy theming
    - Add user preference toggle
    - System preference detection

17. **Multi-language** 🌐
    - Localization strings
    - Arabic/Spanish/French translations

---

## Technical Stack

### **Backend**
- **Language:** PHP 8.0+
- **Architecture:** MVC-inspired, class-based OOP
- **Database:** MySQL 8.0 with PDO
- **Session Management:** PHP Sessions (24-hour lifetime)
- **Hashing:** bcrypt (PASSWORD_BCRYPT, cost 12)
- **Email:** PHPMailer (SMTP) with php mail() fallback
- **Template Engine:** None (raw PHP templates)

### **Frontend**
- **HTML5** with semantic structure
- **CSS3** with custom design system (variables, flexbox, grid)
- **JavaScript:** Vanilla (no framework, uses Bootstrap JS for modals)
- **Framework:** Bootstrap 5.3 (components, grid, utilities)
- **Icons:** Bootstrap Icons (CDN)
- **Charts:** Chart.js 4 (for analytics)
- **Typography:** 
  - Sora (headings, 400/500/600/700)
  - Inter (body text, 400/500)

### **Server Requirements**
- PHP 8.0+ with extensions:
  - pdo_mysql
  - fileinfo
  - mbstring
  - openssl
- MySQL 8.0+
- Apache/Nginx with mod_rewrite

### **Security**
- CSRF tokens on all POST forms
- bcrypt password hashing with cost 12
- HTTP-only session cookies
- SameSite=Lax cookie policy
- Prepared statements (PDO parameterized)
- HTML escaping with htmlspecialchars()
- File upload validation (MIME type, size, extension)

### **File Structure**
```
jobtracker/
├── config/           # Configuration & database
├── cron/            # Automated tasks (reminder emails)
├── public/          # Web root (served files)
│   ├── css/         # Stylesheets
│   ├── js/          # JavaScript
│   └── uploads/     # User-generated content
├── sql/             # Database schema
├── src/
│   ├── helpers/     # Auth, Mailer utilities
│   └── models/      # Data layer (User, Application, Resume, Reminder)
└── views/           # Template partials (header, footer)
```

---

## Recommendations for Enhancement

### **Quick Wins (2-4 hours)**
1. Implement password reset flow (use existing infrastructure)
2. Wire up global search with AJAX
3. Add avatar upload to settings
4. Implement Kanban drag-and-drop with Sortable.js

### **Medium-Term (1-2 weeks)**
1. Custom filters & saved views
2. Bulk operations for applications
3. Interview notes feature
4. Email notifications on status change

### **Long-Term (1+ month)**
1. Job posting integration & parsing
2. Salary analytics dashboard
3. Export functionality (CSV/PDF)
4. Mobile app or PWA wrapper
5. LinkedIn/job board API integrations

---

**Document Version:** 1.0 | Generated: March 16, 2026
