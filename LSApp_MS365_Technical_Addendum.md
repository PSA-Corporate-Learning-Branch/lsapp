# LSApp to Microsoft 365
## Technical Addendum - Implementation Details & Platform Limitations

**TECHNICAL DOCUMENT**

*Generated January 9, 2026*

---

## Table of Contents

1. [SharePoint List Schema Design](#1-sharepoint-list-schema-design)
2. [Platform Limitations & Thresholds](#2-platform-limitations--thresholds)
3. [Power Apps Architecture](#3-power-apps-architecture)
4. [Power Automate Workflows](#4-power-automate-workflows)
5. [Search Comparison: FTS5 vs SharePoint](#5-search-comparison-fts5-vs-sharepoint)
6. [Data Migration Strategy](#6-data-migration-strategy)
7. [Security & Permissions Model](#7-security--permissions-model)
8. [ELM Sync Implementation Challenges](#8-elm-sync-implementation-challenges)
9. [Power BI Integration](#9-power-bi-integration)
10. [Platform Churn History](#10-platform-churn-history)
11. [Hybrid Architecture Option](#11-hybrid-architecture-option)
12. [Licensing Deep Dive](#12-licensing-deep-dive)

---

## 1. SharePoint List Schema Design

To replicate lsapp5000's SQLite database in SharePoint, the following Lists would be required:

### Courses List (Primary)

**Purpose:** Store course catalog (~300 items)

| Column | Type |
|--------|------|
| CourseID | Single line text (PK) |
| CourseName | Single line text |
| CourseShort | Single line text |
| CourseDescription | Multiple lines (Rich) |
| CourseAbstract | Multiple lines |
| Status | Choice (Active/Inactive/Deleted) |
| Topic | Lookup → Topics |
| Audience | Lookup → Audiences |
| DeliveryMethod | Lookup → DeliveryMethods |
| Partner | Lookup → Partners |
| Keywords | Multiple lines |
| Prerequisites | Multiple lines |
| MinEnrollment | Number |
| MaxEnrollment | Number |
| Featured | Yes/No |
| HUBInclude | Yes/No |
| ELMCourseID | Single line text |
| Color | Single line text |
| Modified | Date/Time (auto) |
| ModifiedBy | Person (auto) |

**Note:** lsapp5000 has 130+ columns. Many would need to be added as needed, but SharePoint Lists have a practical limit of ~50-100 columns before management becomes difficult.

### Classes List

**Purpose:** Store class instances (~1000+ items)

| Column | Type |
|--------|------|
| ClassID | Single line text (PK) |
| Course | Lookup → Courses |
| Venue | Lookup → Venues |
| StartDate | Date/Time |
| EndDate | Date/Time |
| StartTime | Single line text |
| EndTime | Single line text |
| Status | Choice |
| Enrolled | Number |
| Waitlisted | Number |
| Facilitators | Multiple lines |
| WebinarLink | Hyperlink |

### CourseChanges List (Change Requests)

**Purpose:** Track course modification requests

| Column | Type |
|--------|------|
| ChangeID | Auto-number (PK) |
| Course | Lookup → Courses |
| Category | Choice |
| Description | Multiple lines (Rich) |
| Scope | Choice (Minor/Moderate/Major) |
| Urgent | Yes/No |
| Progress | Choice |
| ApprovalStatus | Choice |
| AssignedTo | Person |
| CreatedBy | Person (auto) |
| Created | Date/Time (auto) |

### Additional Required Lists

- **People:** Supplement to Azure AD for roles/assignments
- **Venues:** Training locations
- **Partners:** Learning Hub partners
- **Topics:** Lookup table (17 items)
- **Audiences:** Lookup table (4 items)
- **DeliveryMethods:** Lookup table (5 items)
- **ChangeTimeline:** Field-level change history
- **ChangeComments:** Discussion thread per change
- **ClassNotes:** Notes on classes
- **CoursesPeople:** Junction for instructor assignments

**Total Lists Required:** 12-15 SharePoint Lists

---

## 2. Platform Limitations & Thresholds

> **Critical:** SharePoint and Power Platform have hard limits that differ significantly from SQLite's capabilities. These must be understood before committing to an MS365 approach.

### Key Limitations

| Limitation | Threshold | Impact | Workaround |
|------------|-----------|--------|------------|
| List View Threshold | **5,000 items** | Views cannot display or filter more than 5,000 items without indexed columns | Index columns used in filters; use pagination; archive old data |
| List Item Limit | 30 million | Maximum items per list | None for this use case |
| Lookup Column Limit | **12 per query** | Views can only include 12 lookup columns maximum | Denormalize data; use multiple views |
| Power Apps Row Limit | **500-2,000 rows** | Non-delegable queries return max 500-2,000 rows (configurable) | Use delegable functions only; server-side filtering |
| Power Automate Runs | Varies by license | Standard: 6,000/month; Premium: 40,000/month per user | ELM sync frequency limited; complex flows consume runs quickly |
| Column Limits | ~400 columns | Practical limit around 50-100 for usability | lsapp5000 has 130+ course fields; would need trimming or splitting |

### 2.1 Delegation in Power Apps

> **Understanding Delegation:** Power Apps has two modes:
> - **Delegable:** Query runs on SharePoint; returns all matching items
> - **Non-delegable:** Power Apps downloads data locally, then filters; limited to 500-2,000 rows

Many common operations are **NOT delegable** with SharePoint:
- `Search()` function - not delegable
- `StartsWith()` on non-indexed columns - not delegable
- Complex `Filter()` with OR conditions - partially delegable
- `Sort()` on calculated columns - not delegable
- `LookUp()` with complex conditions - not delegable

### 2.2 Comparison: SQLite vs SharePoint Queries

**SQLite (lsapp5000):**
```sql
-- Full-text search with ranking
SELECT c.*, bm25(courses_fts) AS rank
FROM courses_fts
JOIN courses c ON courses_fts.CourseID = c.CourseID
WHERE courses_fts MATCH 'leadership training'
ORDER BY rank
LIMIT 50;
```
**Result:** Returns top 50 most relevant courses, ranked by BM25 algorithm. Works on any data volume.

**SharePoint + Power Apps:**
```
// Power Apps formula (NOT delegable)
Search(
    Courses,
    SearchInput.Text,
    "CourseName", "CourseDescription"
)

// Alternative: delegable but limited
Filter(
    Courses,
    StartsWith(CourseName, SearchInput.Text)
)
```
**Result:** `Search()` downloads ALL courses then filters locally. `StartsWith()` works but only matches beginning of text.

---

## 3. Power Apps Architecture

### 3.1 App Structure

A Power Apps solution for LSApp would require multiple apps or a complex multi-screen app:

| App/Screen | Purpose | Complexity |
|------------|---------|------------|
| Course Catalog | Browse, search, filter courses | High (delegation issues) |
| Course Detail | View course info, classes, instructors | Medium |
| Course Edit | Admin form for course modifications | Medium-High (many fields) |
| Class List | Upcoming classes, calendar view | Medium |
| Class Detail | View class info, enrollment, notes | Medium |
| Change Requests | List, create, manage change requests | High (workflow integration) |
| Dashboard | Statistics, metrics | Medium (or use Power BI) |
| Admin Panel | People, venues, partners management | Medium |

### 3.2 Power Apps Limitations for LSApp

- **No server-side code:** All logic must be in formulas or Power Automate
- **Limited Markdown support:** Can't render course descriptions in Markdown like lsapp5000
- **Formula complexity limits:** Very long formulas become unmanageable
- **Performance:** Complex screens with many controls load slowly
- **Version control:** No Git integration; harder to track changes
- **Custom styling:** Limited compared to CSS; Bootstrap-like themes not available

### 3.3 Example: Course Search Screen

```
// Gallery Items property - attempting search with delegation workaround
If(
    IsBlank(SearchInput.Text),
    // No search term - show all active courses (delegable)
    Filter(Courses, Status = "Active"),

    // With search term - NOT fully delegable!
    If(
        Len(SearchInput.Text) >= 3,
        // Search function downloads all data locally
        Search(
            Filter(Courses, Status = "Active"),
            SearchInput.Text,
            "CourseName",
            "CourseDescription",
            "Keywords"
        ),
        // For short terms, use delegable StartsWith
        Filter(
            Courses,
            Status = "Active" &&
            StartsWith(CourseName, SearchInput.Text)
        )
    )
)

// WARNING: Search() will only work correctly if
// total Active courses < 2000 (delegation limit)
```

---

## 4. Power Automate Workflows

### 4.1 Required Flows

| Flow | Trigger | Actions | Complexity |
|------|---------|---------|------------|
| Change Request Notification | When item created in CourseChanges | Send email to assigned person; post to Teams | Easy |
| Change Request Approval | When status changes to "Pending Approval" | Start approval; update status; notify requester | Medium |
| Change Timeline Logger | When item modified in CourseChanges | Compare old/new values; create timeline entry | Complex |
| ELM Sync (Scheduled) | Recurrence (daily/weekly) | Fetch CSV; parse; compare; update courses | Very Complex |
| Upcoming Class Reminder | Recurrence (daily) | Query classes starting in 7 days; send notifications | Medium |

### 4.2 ELM Sync Challenge

> **Major Challenge:** The ELM sync process in lsapp5000 is sophisticated:
> 1. Parse external CSV files
> 2. Merge keywords with course data
> 3. Compare each course field with database
> 4. Log specific field changes with old/new values
> 5. Handle persistence flags for removed courses
> 6. Track sync run statistics

Replicating this in Power Automate would require:
- **Premium HTTP connector** - to fetch external CSV
- **Custom parsing logic** - CSV parsing is limited in Power Automate
- **Hundreds of flow runs** - one per course comparison
- **Complex condition logic** - field-by-field comparison
- **Potential Azure Function** - for heavy processing

**Estimated complexity: Very High.** May require Azure Logic Apps or Azure Functions for practical implementation.

### 4.3 Sample Flow: Change Timeline Logger

```
Trigger: When item modified
    ↓
Get previous version
    ↓
Compare: Description changed? → Create timeline entry
    ↓
Compare: Status changed? → Create timeline entry
    ↓
Compare: AssignedTo changed? → Create timeline entry + notify
```

**Issue:** SharePoint doesn't store previous values on modification. You need to maintain a "shadow" list or use version history API (complex).

---

## 5. Search Comparison: FTS5 vs SharePoint

### 5.1 Feature Comparison

| Capability | SQLite FTS5 | SharePoint Search |
|------------|-------------|-------------------|
| Full-text search | Yes - dedicated FTS5 engine | Yes - Microsoft Search |
| Relevance ranking | BM25 with tunable weights | Proprietary algorithm |
| Phrase matching | Yes - "exact phrase" | Yes |
| Prefix matching | Yes - term* | Yes |
| Boolean operators | AND, OR, NOT | AND, OR, NOT |
| Field-specific search | Yes - CourseName:leadership | Limited - managed properties |
| Customizable ranking | Yes - weights per field | Limited - query rules |
| Instant results | Yes - indexed, immediate | Crawl delay (minutes to hours) |
| Works in Power Apps | N/A (different platform) | No - Search() is different |
| Structured data queries | Excellent - SQL | Limited - KQL |

> **Key Issue:** SharePoint Search and Power Apps `Search()` function are different systems:
> - **SharePoint Search:** Enterprise search across all content; requires crawling; uses KQL
> - **Power Apps Search():** Client-side text search; downloads all data; not delegable
>
> To use SharePoint Search in Power Apps, you'd need a custom connector or Flow - adding complexity.

### 5.2 Search Quality Example

**lsapp5000: Search "leadership communication"**

FTS5 finds documents containing both terms, ranks by:
- Term frequency in document
- Term rarity across corpus
- Document length normalization

**Result:** "Leadership Communication Skills" ranks #1; "Public Speaking for Leaders" ranks lower.

**Power Apps: Search "leadership communication"**

Search() function performs simple text matching:
- Finds substring in specified fields
- No relevance ranking
- Returns in list order

**Result:** All matches returned in arbitrary order; user must scroll to find best match.

---

## 6. Data Migration Strategy

### 6.1 Migration Approach

1. **Export from SQLite:**
   ```bash
   sqlite3 course_management.db ".mode csv" ".output courses.csv" "SELECT * FROM courses;"
   ```

2. **Transform Data:** Map SQLite columns to SharePoint column types; handle data type conversions

3. **Import to SharePoint:**
   - Use Power Automate for small datasets
   - Use SharePoint Migration Tool for bulk
   - Consider PnP PowerShell for complex scenarios

4. **Establish Relationships:** Re-create lookup relationships; verify referential integrity

5. **Validate:** Compare record counts; spot-check data accuracy

### 6.2 Data Type Mapping

| SQLite Type | SharePoint Type | Notes |
|-------------|-----------------|-------|
| TEXT (short) | Single line of text | 255 char limit |
| TEXT (long) | Multiple lines of text | Rich text if HTML needed |
| INTEGER | Number | Decimal places configurable |
| REAL | Number | Set appropriate decimals |
| TEXT (date) | Date and Time | May need format conversion |
| INTEGER (boolean) | Yes/No | 0/1 → No/Yes |
| Foreign Key (ID) | Lookup column | Requires list to exist first |

### 6.3 Migration Risks

- **Data Loss:** Rich formatting may not transfer; validate thoroughly
- **Relationship Integrity:** Lookup columns require exact title matches
- **Historical Data:** Change timeline entries need careful mapping
- **File Attachments:** Need separate migration to document library

---

## 7. Security & Permissions Model

### 7.1 SharePoint Permissions

| lsapp5000 Role | SharePoint Equivalent | Configuration |
|----------------|----------------------|---------------|
| Authenticated User (basic) | Site Members (Read/Contribute) | Can view courses, submit change requests |
| Super User (admin) | Site Owners or Custom Permission Level | Can edit courses, manage all data |

### 7.2 Considerations

- **IDIR Integration:** Azure AD already federated with IDIR; users authenticate seamlessly
- **List-Level Permissions:** Can restrict edit access per list
- **Item-Level Permissions:** Possible but adds overhead; avoid if possible
- **Power Apps Security:** App-level sharing; respects SharePoint permissions for data

> **Advantage:** MS365 authentication is already in place. No additional auth setup required. IDIR users can access immediately via existing federation.

---

## 8. ELM Sync Implementation Challenges

### 8.1 Current lsapp5000 Sync Process

```php
// Simplified sync logic from elm-course-sync.php
foreach ($csvCourses as $course) {
    $existing = $db->query("SELECT * FROM courses WHERE ELMCourseID = ?", [$course['id']]);

    if (!$existing) {
        // Insert new course
        insertCourse($course);
        logChange('new', $course);
    } else {
        // Compare each field
        $changes = compareFields($existing, $course);
        if ($changes) {
            updateCourse($course);
            foreach ($changes as $field => $diff) {
                logChange('updated', $course, $field, $diff['old'], $diff['new']);
            }
        }
    }
}

// Handle courses removed from feed
foreach ($existingNotInFeed as $course) {
    if ($course['HubIncludePersist'] === 'Yes') {
        markPersistedInactive($course);
    } else {
        removeFromHub($course);
    }
}
```

### 8.2 Power Automate Challenges

**Significant Barriers:**

1. **CSV Parsing:** Power Automate has limited CSV parsing. Would need:
   - Premium HTTP connector to fetch file
   - Custom parsing via expressions or Azure Function

2. **Looping Limits:** "Apply to each" over 300+ courses hits performance issues

3. **Field Comparison:** Comparing 50+ fields per course is impractical in flow expressions

4. **Flow Run Limits:** Each sync could consume thousands of API calls

5. **Error Handling:** Complex error handling difficult to implement

### 8.3 Realistic MS365 Sync Options

| Option | Description | Feasibility |
|--------|-------------|-------------|
| Power Automate (Basic) | Simple flow with limited comparison | Partial - loses detailed tracking |
| Azure Logic Apps | More powerful workflows with better parsing | Possible - additional cost |
| Azure Function | Custom code (C#/Python) for heavy processing | Best - but requires development |
| Keep lsapp5000 for Sync | Hybrid: lsapp5000 syncs, pushes to SharePoint | Pragmatic workaround |
| Manual Sync | Admin uploads CSV, flow processes | Simple but not automated |

---

## 9. Power BI Integration

### 9.1 Dashboard Capabilities

> **Good Fit:** Power BI excels at creating dashboards and could enhance either platform:
> - Connect directly to SharePoint Lists
> - Or connect to lsapp5000's SQLite database via gateway
> - Rich visualizations (charts, graphs, maps)
> - Scheduled refresh
> - Embed in SharePoint or Teams

### 9.2 Example Metrics

- Course count by status, topic, delivery method
- Classes scheduled by month, venue
- Enrollment trends over time
- Change request volume and resolution time
- Partner course distribution

### 9.3 Licensing Consideration

Power BI Pro ($10/user/month) required for:
- Sharing dashboards with others
- Publishing to workspace
- Scheduled refresh

Free tier only allows personal use.

---

## 10. Platform Churn History

> **Historical Pattern:** Microsoft regularly deprecates, renames, and replaces tools. This creates ongoing maintenance burden.

### 10.1 Notable Changes (2015-2025)

| Year | Deprecated/Changed | Replaced By | Migration Impact |
|------|-------------------|-------------|------------------|
| 2017 | InfoPath | Power Apps | Complete rebuild required |
| 2019 | Microsoft Flow | Power Automate | Rebranding; UI changes |
| 2020 | Classic SharePoint | Modern SharePoint | Page rebuilds; feature gaps |
| 2021 | SharePoint Workflows | Power Automate | Complete rebuild required |
| 2023 | Power Apps portal | Power Pages | Rebranding; licensing changes |
| 2024 | Dataverse for Teams | Consolidated Dataverse | Licensing impact |
| Ongoing | Connector deprecations | New versions | Flow updates required |

### 10.2 Projected Next 8 Years

Based on historical patterns, expect:
- **2-3 major UI overhauls** requiring screen redesigns
- **1-2 product renames/mergers** affecting documentation and training
- **Multiple connector updates** requiring flow modifications
- **Licensing model changes** potentially affecting costs
- **Feature deprecations** requiring workarounds

> **Contrast with PHP/SQLite:** PHP 5.6 code from 2014 still runs on PHP 8.3 with minimal changes. SQLite databases from the 1990s are still readable. The stability profile is dramatically different.

---

## 11. Hybrid Architecture Option

Rather than full migration, a hybrid approach leverages MS365 strengths while keeping lsapp5000's core:

### 11.1 Hybrid Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         Users                                    │
└─────────────────────────────────────────────────────────────────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
        ▼                     ▼                     ▼
┌───────────────┐    ┌───────────────┐    ┌───────────────┐
│   lsapp5000   │    │   Power BI    │    │    Teams      │
│  (PHP/SQLite) │    │  Dashboards   │    │ Notifications │
│               │◄───┤               │    │               │
│ • Course CRUD │    │ • Analytics   │    │ • Webhooks    │
│ • Classes     │    │ • Reports     │    │ • Alerts      │
│ • Changes     │    │ • Trends      │    │ • Bot         │
│ • ELM Sync    │    │               │    │               │
│ • Search      │    │               │    │               │
└───────────────┘    └───────────────┘    └───────────────┘
        │                     ▲                     ▲
        │                     │                     │
        └─────────────────────┴─────────────────────┘
                    SQLite Database
                    (Single source of truth)
```

### 11.2 Hybrid Components

| Component | Platform | Rationale |
|-----------|----------|-----------|
| Core Application | lsapp5000 | Custom workflows, search, data management |
| Analytics Dashboards | Power BI | Rich visualizations; executive reporting |
| Notifications | Teams Webhooks | Instant alerts to team channel |
| Document Storage | SharePoint | Course materials, attachments |
| Feedback Forms | Microsoft Forms | Easy form creation; data flows to lsapp5000 |

### 11.3 Implementation Example: Teams Notifications

```php
// In lsapp5000 PHP code - send to Teams on change request
function notifyTeams($changeRequest) {
    $webhookUrl = getenv('TEAMS_WEBHOOK_URL');

    $payload = [
        '@type' => 'MessageCard',
        'summary' => 'New Change Request',
        'themeColor' => '0078D4',
        'title' => 'Course Change Request #' . $changeRequest['changeid'],
        'sections' => [[
            'facts' => [
                ['name' => 'Course', 'value' => $changeRequest['coursename']],
                ['name' => 'Category', 'value' => $changeRequest['category']],
                ['name' => 'Requested By', 'value' => $changeRequest['created_by']],
                ['name' => 'Urgent', 'value' => $changeRequest['urgent'] ? 'Yes' : 'No']
            ]
        ]],
        'potentialAction' => [[
            '@type' => 'OpenUri',
            'name' => 'View Request',
            'targets' => [['uri' => 'https://lsapp.gov.bc.ca/course-changes/view.php?id=' . $changeRequest['changeid']]]
        ]]
    ];

    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_exec($ch);
    curl_close($ch);
}
```

---

## 12. Licensing Deep Dive

### 12.1 What's Included in Government MS365

Standard G3/G5 licenses typically include:
- SharePoint Online (Lists, Libraries, Sites)
- Power Apps for Office 365 (limited - SharePoint customization only)
- Power Automate for Office 365 (standard connectors only)
- Power BI Free (personal use only)
- Microsoft Forms
- Teams

### 12.2 Additional Licensing Required

| Capability Needed | License Required | Est. Cost/User/Month |
|-------------------|------------------|---------------------|
| Standalone Power Apps (not just SharePoint forms) | Power Apps per-user or per-app | $5 - $20 |
| Premium connectors (HTTP, SQL, etc.) | Power Automate Premium | $15 |
| Share Power BI dashboards | Power BI Pro | $10 |
| Dataverse storage (if using) | Capacity add-on | Variable |
| Azure Functions (for sync) | Azure consumption | Variable (likely minimal) |

### 12.3 Per-App vs Per-User Licensing

> **Power Apps Licensing Models:**
> - **Per-app ($5/user/app/month):** Users can access ONE specific app. Good if you have few apps, many users.
> - **Per-user ($20/user/month):** Users can access UNLIMITED apps. Good if you have many apps, fewer users.
>
> For LSApp (likely 1-2 apps, ~10-20 users): **Per-app is probably cheaper** = ~$50-100/month total.

### 12.4 Hidden Costs

- **Training:** Staff need to learn Power Platform development
- **Consulting:** May need external help for complex features (ELM sync)
- **Ongoing maintenance:** Platform updates require attention
- **Opportunity cost:** Time spent on platform issues vs. feature development

---

*This technical addendum accompanies the Executive Report for LSApp MS365 Migration Analysis.*
