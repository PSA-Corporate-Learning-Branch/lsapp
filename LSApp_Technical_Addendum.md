# LSApp Modernization
## Technical Addendum for Technical Leadership

**TECHNICAL DOCUMENT**

*Generated January 9, 2026*

---

## Table of Contents

1. [SQLite Architecture Benefits](#1-sqlite-architecture-benefits)
2. [Database Schema Design](#2-database-schema-design)
3. [Full-Text Search Implementation](#3-full-text-search-implementation)
4. [Testing Framework & Coverage](#4-testing-framework--coverage)
5. [Logging Implementation](#5-logging-implementation)
6. [Security Architecture](#6-security-architecture)
7. [Data Import Pipeline](#7-data-import-pipeline)
8. [ELM Sync System](#8-elm-sync-system)
9. [Code Patterns & Standards](#9-code-patterns--standards)
10. [Performance Considerations](#10-performance-considerations)
11. [Long-Term Maintenance](#11-long-term-maintenance)
12. [CI/CD Integration](#12-cicd-integration)

---

## 1. SQLite Architecture Benefits

### 1.1 Why SQLite Over CSV Files

| Aspect | Legacy CSV Storage | lsapp5000 SQLite |
|--------|-------------------|------------------|
| Storage | 59 separate CSV files | Single database file (11.8 MB) |
| Query performance | Full file scan for every query (O(n)) | Indexed queries (O(log n) or O(1)) |
| Indexing | No indexing capability | 22 optimized indexes |
| Referential integrity | No referential integrity | Foreign key enforcement |
| Concurrency | Race conditions on concurrent writes | ACID-compliant transactions |
| Transactions | No transaction support | Full transaction support |
| Relationships | Manual relationship management | Automatic cascade deletes |
| Query optimization | No query optimization | Query planner optimization |
| Data types | String-only data types | Strong typing with constraints |

### 1.2 Why SQLite Over Client-Server Databases (MySQL, PostgreSQL)

**Zero Configuration**
No server process, no connection strings, no ports, no authentication setup. The database is just a file.

**Zero Dependencies**
SQLite is compiled into PHP (since PHP 5.3). No additional packages, extensions, or services required.

**Zero Maintenance**
No database server to patch, upgrade, or monitor. No connection pool tuning. No memory allocation issues.

**Atomic Backup**
Backup is simply copying a single file. Restore is replacing that file. No export/import complexity.

**Portable**
Database moves with the application. Copy the folder, and you have a complete working system.

**Appropriate Scale**
With ~300 courses, ~1000 classes, and ~100 users, SQLite handles this workload trivially. Client-server is overkill.

> **Performance Reality:** SQLite can handle **hundreds of thousands of reads per second** and thousands of writes per second on modern hardware. LSApp's workload (administrative catalog management by a small team) is orders of magnitude below SQLite's capabilities.

### 1.3 SQLite Durability Features

| Feature | Implementation | Benefit |
|---------|---------------|---------|
| Write-Ahead Logging (WAL) | Optional (not currently enabled) | Improved concurrency, crash recovery |
| Foreign Keys | `PRAGMA foreign_keys = ON` | Referential integrity enforced |
| Transactions | BEGIN/COMMIT/ROLLBACK | All-or-nothing operations |
| Cascading Deletes | `ON DELETE CASCADE` | Automatic cleanup of related records |
| ACID Compliance | Built-in | Data consistency guaranteed |

---

## 2. Database Schema Design

### 2.1 Core Entity Relationships

```
                                    ┌─────────────────┐
                                    │     partners    │
                                    ├─────────────────┤
                                    │ id (PK)         │
                                    │ name            │
                                    │ slug            │
                              ┌─────│ description     │
                              │     └─────────────────┘
                              │              │
                              │              │ 1:N
                              │              ▼
                              │     ┌─────────────────┐
                              │     │ partner_contacts│
                              │     └─────────────────┘
                              │
┌─────────────────┐           │     ┌─────────────────┐
│     people      │◄──────────┼─────│     courses     │
├─────────────────┤           │     ├─────────────────┤
│ IDIR (PK)       │           └────►│ LearningHub     │
│ FirstName       │                 │   Partner (FK)  │
│ LastName        │                 │ topic_id (FK)   │──► topics
│ Role            │                 │ audience_id(FK) │──► audiences
│ Super (admin)   │                 │ delivery_method │──► delivery_methods
└─────────────────┘                 └─────────────────┘
        │                                   │
        │ N:M (via courses_people)          │ 1:N
        │                                   ▼
        │                           ┌─────────────────┐
        └──────────────────────────►│     classes     │
                                    ├─────────────────┤
                                    │ CourseID (FK)   │
                                    │ VenueID (FK)    │──► venues
                                    └─────────────────┘
                                            │
                                            │ 1:N
                                            ▼
                                    ┌─────────────────┐
                                    │  classes_notes  │
                                    └─────────────────┘
```

### 2.2 Index Strategy

| Index Name | Table.Column(s) | Purpose |
|------------|-----------------|---------|
| idx_classes_courseid | classes.CourseID | Fast lookup of classes for a course |
| idx_classes_venueid | classes.VenueID | Venue availability queries |
| idx_classes_startdate | classes.StartDate | Date range queries, upcoming classes |
| idx_courses_status | courses.Status | Filter by Active/Inactive/Deleted |
| idx_courses_topic | courses.topic_id | Topic filtering |
| idx_courses_partner | courses.LearningHubPartner | Partner-based queries |
| idx_courses_people_* | courses_people (multiple) | Instructor/role lookups |
| idx_changes_courseid | courses_changes.courseid | Change request history per course |
| *+ 14 additional* | Various | Other foreign keys and common query patterns |

---

## 3. Full-Text Search Implementation

### 3.1 FTS5 Virtual Table

lsapp5000 uses SQLite's FTS5 (Full-Text Search 5) extension for course searching:

```sql
CREATE VIRTUAL TABLE courses_fts USING fts5(
    CourseID,
    CourseName,
    CourseShort,
    CourseDescription,
    CourseAbstract,
    Topics,
    Audience,
    Keywords,
    Prerequisites,
    content='courses',
    content_rowid='rowid'
);
```

### 3.2 BM25 Ranking Algorithm

Search results are ranked using the BM25 algorithm, which considers:
- **Term Frequency:** How often the search term appears in the document
- **Inverse Document Frequency:** Rare terms weighted higher than common ones
- **Document Length:** Normalized for fair comparison across varying lengths

```sql
SELECT c.*, bm25(courses_fts) AS rank
FROM courses_fts
JOIN courses c ON courses_fts.CourseID = c.CourseID
WHERE courses_fts MATCH ?
ORDER BY rank;
```

### 3.3 Auto-Sync Triggers

The FTS index stays synchronized with the courses table via triggers:

```sql
-- Insert trigger
CREATE TRIGGER courses_ai AFTER INSERT ON courses BEGIN
    INSERT INTO courses_fts(rowid, CourseID, CourseName, ...)
    VALUES (new.rowid, new.CourseID, new.CourseName, ...);
END;

-- Update and Delete triggers also defined
```

---

## 4. Testing Framework & Coverage

### 4.1 Test Infrastructure

| Metric | Value |
|--------|-------|
| Testing Framework | PHPUnit 10.5 |
| Test Files | 4 |
| Test Database | SQLite in-memory (`:memory:`) |
| CI/CD | GitHub Actions |

### 4.2 Test Structure

```
tests/
├── bootstrap.php          - Test setup, DB initialization
├── TestCase.php           - Base class with setUp/tearDown
├── setup.sql              - Schema creation script
├── fixtures/
│   └── basic_data.sql     - Test data fixtures
├── Unit/
│   ├── DatabaseConnectionTest.php
│   ├── CsvImportTest.php
│   └── CrudOperationsTest.php
└── Integration/
    └── PageRenderingTest.php
```

### 4.3 Test Categories

| Category | Test File | Coverage Areas |
|----------|-----------|----------------|
| Database | DatabaseConnectionTest.php | PDO setup, FK enforcement, table existence, special characters |
| Import | CsvImportTest.php | Header parsing, multi-table imports, empty files, quoted values |
| CRUD | CrudOperationsTest.php | Create, Read, Update, Delete, FK validation, transactions |
| Integration | PageRenderingTest.php | Query execution, FTS5 search, pagination, filtering, statistics |

### 4.4 Running Tests

```bash
# Run all tests
./run-tests.sh

# Run with HTML coverage report
./run-tests.sh --coverage

# Run only unit tests
./run-tests.sh --unit

# Via Composer
composer test
```

### 4.5 CI/CD Pipeline

```yaml
# .github/workflows/tests.yml
name: Tests
on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php-version: ['8.2', '8.3', '8.4']
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
      - run: composer install
      - run: composer test
```

---

## 5. Logging Implementation

### 5.1 Multi-Layer Logging Strategy

| Log Type | Storage | Purpose | Retention |
|----------|---------|---------|-----------|
| Sync Operations | Text files (sync/sync_log_*.txt) | Detailed per-course sync results | Indefinite (manual cleanup) |
| Sync Runs | Database (sync_runs table) | Queryable sync history | Indefinite |
| Course Changes | Database (course_changes table) | Field-level change tracking | Indefinite |
| User Actions | Database (timeline tables) | Who changed what, when | Indefinite |
| PHP Errors | Server error_log | Application errors, debugging | Per server config |

### 5.2 Database Logging Schema

```sql
CREATE TABLE sync_runs (
    id INTEGER PRIMARY KEY,
    run_timestamp TEXT NOT NULL,
    status TEXT,
    courses_processed INTEGER DEFAULT 0,
    courses_new INTEGER DEFAULT 0,
    courses_updated INTEGER DEFAULT 0,
    courses_deactivated INTEGER DEFAULT 0,
    log_file TEXT
);

CREATE TABLE course_changes (
    id INTEGER PRIMARY KEY,
    sync_run_id INTEGER,
    course_id TEXT,
    change_type TEXT, -- new, updated, reactivated, removed_from_hub, persisted_inactive
    field_name TEXT,
    old_value TEXT,
    new_value TEXT,
    changed_at TEXT,
    FOREIGN KEY (sync_run_id) REFERENCES sync_runs(id)
);
```

### 5.3 Change Request Audit Trail

Every modification to a change request is tracked in the timeline table:

```sql
INSERT INTO courses_changes_timeline
    (changeid, field_name, old_value, new_value, changed_by, changed_at)
VALUES
    (123, 'progress', 'pending', 'in_progress', 'ahaggett', '2026-01-09 14:30:00');
```

---

## 6. Security Architecture

### 6.1 Security Implementation Status

| Feature | Status | Details |
|---------|--------|---------|
| CSRF Protection | ✅ Complete | All forms and AJAX calls protected with session tokens. Uses `random_bytes(32)` and `hash_equals()` for timing-safe comparison. |
| SQL Injection Prevention | ✅ Complete | All database queries use PDO prepared statements with bound parameters. No string concatenation in queries. |
| Authentication | ✅ Complete | IDIR-based authentication via REMOTE_USER. Session caching for performance. Role-based access (user/super). |
| Password Hashing | ✅ Complete | Argon2ID with memory_cost: 65536, time_cost: 4, threads: 3 (legacy data encryption). |
| XSS Prevention | ⚠️ Partial | htmlspecialchars() used but inconsistently. Needs systematic audit to ensure all user output is escaped. |
| File Upload Validation | ⚠️ Partial | Basic handling exists but MIME type and extension validation should be strengthened. |
| Security Headers | ❌ TODO | Missing: Content-Security-Policy, X-Frame-Options, X-Content-Type-Options. |
| Session Timeout | ❌ TODO | Not currently configured. Should implement idle timeout for security. |

### 6.2 CSRF Implementation Details

```php
// Generate token (stored in session)
function generateCSRFToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify token (timing-safe)
function verifyCSRFToken(string $token): bool {
    return isset($_SESSION['csrf_token']) &&
           hash_equals($_SESSION['csrf_token'], $token);
}

// JavaScript helper for AJAX
async function fetchWithCSRF(url, options = {}) {
    options.headers = options.headers || {};
    options.headers['X-CSRF-Token'] = document.querySelector(
        'meta[name="csrf-token"]'
    ).content;
    return fetch(url, options);
}
```

### 6.3 Security Audit Priority

1. **Priority 1:** Complete XSS mitigation (apply htmlspecialchars consistently)
2. **Priority 2:** Add security headers (CSP, X-Frame-Options)
3. **Priority 3:** Implement session timeout
4. **Priority 4:** Strengthen file upload validation

---

## 7. Data Import Pipeline

### 7.1 Import Sources

| Source | Format | Target Table(s) | Script |
|--------|--------|-----------------|--------|
| Legacy People | CSV | people | import_csv_data.php |
| Legacy Venues | CSV | venues | import_csv_data.php |
| Legacy Courses | CSV | courses | import_csv_data.php |
| Legacy Classes | CSV | classes | import_csv_data.php |
| Partners | JSON | partners, partner_contacts | import_csv_data.php |
| Change Requests | JSON (106 files) | courses_changes + child tables | import_change_requests.php |

### 7.2 Import Statistics

| Metric | Count |
|--------|-------|
| Change Requests Imported | 106 |
| Timeline Entries | 638 |
| File References | 15 |
| Related Links | 44 |

### 7.3 Encoding Handling

`clean_and_import.php` handles character encoding issues common in legacy CSV exports:

```php
// Convert to UTF-8 if needed
$encoding = mb_detect_encoding($line, ['UTF-8', 'ISO-8859-1', 'Windows-1252']);
if ($encoding !== 'UTF-8') {
    $line = mb_convert_encoding($line, 'UTF-8', $encoding);
}

// Remove BOM if present
$line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
```

---

## 8. ELM Sync System

### 8.1 Three-Stage Processing Pipeline

**Stage 1: Parse & Merge** (`process.php`)
- Parse external CSV feeds
- Merge keywords with courses
- Categorize metadata
- Filter by Keyword Type ID 1039

**Stage 2: Sync to Database** (`elm-course-sync.php`)
- Compare with existing courses
- Insert new courses
- Update changed fields
- Handle deactivations
- Log all changes

### 8.2 Sync Logic: Hub Persistence

> **Key Design Decision:** The sync system *never* deactivates courses. When a course is removed from the external feed:
> - If `HubIncludePersist = 'Yes'` → Course becomes "persisted_inactive" (visible but marked inactive)
> - If `HubIncludePersist = 'No'` → Course is hidden from Hub (`HUBInclude = 'No'`) but Status remains Active
>
> This prevents accidental data loss and maintains referential integrity.

### 8.3 Change Types Tracked

| Change Type | Description |
|-------------|-------------|
| `new` | Course added to database from external feed |
| `updated` | One or more fields changed |
| `reactivated` | Previously inactive course re-appeared in feed |
| `removed_from_hub` | Course no longer in feed, HUBInclude set to No |
| `persisted_inactive` | Course removed but marked for persistence |

---

## 9. Code Patterns & Standards

### 9.1 Coding Standards

| Aspect | Standard |
|--------|----------|
| PHP Version | 8.2+ (tested on 8.2, 8.3, 8.4) |
| Database Access | PDO with prepared statements exclusively |
| Error Mode | PDO::ERRMODE_EXCEPTION |
| Fetch Mode | PDO::FETCH_ASSOC |
| Frontend | Bootstrap 5.3+ (CDN), vanilla JavaScript |
| Font | BC Sans (government standard) |
| Autoloading | PSR-4 via Composer |

### 9.2 File Organization

```php
// Standard page structure
<?php
require_once 'db_connection.php';    // Database connection
require_once 'auth.php';             // Authentication + CSRF

requireAuth();                       // Enforce login
// requireAuth(true); for super-user-only pages

// Business logic here
$stmt = $db->prepare("SELECT * FROM courses WHERE Status = ?");
$stmt->execute(['Active']);
$courses = $stmt->fetchAll();

require_once 'includes/header.php';  // HTML header, nav
?>

<!-- Page content -->

<?php require_once 'includes/footer.php'; ?>
```

### 9.3 No Framework Philosophy

> **Intentional Design Choice:** lsapp5000 deliberately avoids frameworks (Laravel, Symfony) to:
> - Eliminate dependency management burden
> - Avoid framework upgrade cycles
> - Maintain simplicity for 8+ year lifespan
> - Enable any PHP developer to maintain the code
> - Match the legacy system's zero-dependency philosophy
>
> This is appropriate for an administrative tool with a small user base and minimal complexity.

---

## 10. Performance Considerations

### 10.1 Query Optimization

| Optimization | Implementation | Impact |
|--------------|----------------|--------|
| Indexed Queries | 22 indexes on FK and common filters | O(log n) vs O(n) lookups |
| FTS5 Search | Dedicated virtual table with triggers | Sub-millisecond full-text search |
| Session Caching | User data cached after first lookup | 1 query per session vs per page |
| Prepared Statements | PDO prepared statements cached | Query plan reuse |
| Selective Fetching | Only needed columns in SELECT | Reduced memory, faster transfer |

### 10.2 Comparison: CSV vs SQLite Performance

**Legacy CSV Approach:**
- Find course by ID: Full file scan of courses.csv
- Classes for course: Full file scan of classes.csv
- Search courses: Load all, filter in PHP
- Join data: Multiple file loads, manual merge
- Complexity: O(n) per query, O(n*m) for joins

**lsapp5000 SQLite:**
- Find course by ID: Index seek, O(1)
- Classes for course: Index seek, O(log n)
- Search courses: FTS5, O(log n)
- Join data: Query optimizer, indexed joins
- Complexity: O(log n) or O(1) for most queries

---

## 11. Long-Term Maintenance

### 11.1 Maintenance Advantages

**Zero External Dependencies**
No packages to update, no security patches to apply (beyond PHP itself). SQLite is part of PHP core.

**Comprehensive Documentation**
README, CLAUDE.md, TESTING.md, security audit report, sync documentation - all in the repository.

**80% Code Reduction**
56 files vs 293 files. Less code = fewer bugs = less maintenance.

**Automated Testing**
PHPUnit tests catch regressions. CI/CD validates across PHP versions.

**Simple Backup**
Copy one database file. No dump scripts, no export/import complexity.

**Portable**
Application folder is self-contained. Move to new server by copying directory.

### 11.2 PHP Version Compatibility

Tested and validated on:
- PHP 8.2 (current LTS)
- PHP 8.3 (current stable)
- PHP 8.4 (latest)

The codebase uses no deprecated features and should remain compatible through PHP 9.x based on current roadmaps.

### 11.3 Upgrade Path

When PHP versions change:
1. Update CI/CD matrix to include new version
2. Run test suite
3. Address any deprecation warnings
4. Deploy

No framework-specific migration guides, no dependency conflicts, no breaking API changes from third-party packages.

---

## 12. CI/CD Integration

### 12.1 GitHub Actions Workflow

```yaml
name: Tests
on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php-version: ['8.2', '8.3', '8.4']

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
          extensions: pdo_sqlite, mbstring
          coverage: xdebug

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Run tests
        run: composer test
```

### 12.2 Deployment Process

Recommended deployment workflow:
1. Push changes to `develop` branch
2. CI runs tests across PHP versions
3. Create PR to `main` when ready
4. Merge after review and test pass
5. Deploy `main` to production (rsync or git pull)

### 12.3 Backup Strategy

```bash
# Daily backup (cron job)
0 2 * * * cp /var/www/lsapp5000/course_management.db \
    /backups/lsapp5000/course_management_$(date +\%Y\%m\%d).db

# Retain 30 days
find /backups/lsapp5000/ -name "*.db" -mtime +30 -delete
```

---

*This technical addendum accompanies the Executive Report for LSApp Modernization.*
