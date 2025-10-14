# ELM Course Sync Script Explanation

## Overview
`elm-course-sync.php` is a synchronization script that keeps the LSApp course catalog in sync with the ELM (Enterprise Learning Management) system's course feed. It runs periodically to update existing courses, add new ones, and manage course visibility in the Learning Hub.

## File Location
`/var/www/html/lsapp/course-feed/elm-course-sync.php`

## Core Functionality

### 1. Data Sources
- **LSApp Courses**: `/data/courses.csv` - The main course database (indexed by ItemCode at position 4)
- **ELM Hub Feed**: `/course-feed/data/courses.csv` - External feed from ELM (indexed by ItemCode at position 0)

### 2. Main Operations

#### A. Course Updates (`updateCourse` function)
When a course exists in both systems, the script syncs these fields from ELM to LSApp:

| LSApp Index | ELM Index | Field Name |
|-------------|-----------|------------|
| 2 | 1 | CourseName |
| 16 | 2 | CourseDescription |
| 50 | 11 | ELMCourseID |
| 19 | 12 | Keywords |
| 21 | 3 | Method |
| 36 | 10 | LearningHubPartner (converted from name to ID) |
| 38 | 15 | Topics |
| 39 | 14 | Audience |
| 40 | 13 | Levels (Group) |

**Additional automatic updates:**
- Sets `Platform` to "PSA Learning System"
- Sets `HUBInclude` to "Yes" (since it's in the ELM feed)
- Reactivates `Status` to "Active" if it was "Inactive"
- Updates `Modified` timestamp when changes occur
- Restores persistent courses' `HubIncludePersistState` to "active" if they return to the feed

#### B. New Course Creation
When a course exists in ELM but not in LSApp (by ItemCode), the script first checks for potential duplicates by course name.

**Duplicate Detection:**
- Before creating a new course, checks if a course with the same name (case-insensitive) already exists in LSApp
- If a name match is found:
  - Logs the potential duplicate
  - Adds to `potentialDuplicates` array for email notification
  - **Skips** creating the new course to prevent duplicates
  - Sends high-priority email to allan.haggett@gov.bc.ca with:
    - Detailed table showing both ELM and LSApp course information
    - Clear action steps to resolve (update ItemCode in LSApp)
    - Timestamp and transaction ID

**If no duplicate found**, creates a new course record with:
- Auto-generated `CourseID` using timestamp + counter
- Status: "Active"
- RequestedBy: "SYNCBOT"
- Platform: "PSA Learning System"
- HUBInclude: "Yes"
- Default color: #F1F1F1
- Featured: 1
- `HubIncludeSync`: "yes" (participates in sync)
- `HubIncludePersist`: "no" (will be hidden when removed from feed)
- `HubIncludePersistState`: "active"

#### C. Course Removal/Hiding Logic
For courses in LSApp but missing from the ELM feed:

**Regular Courses** (when `Platform` = "PSA Learning System"):
- Sets `HUBInclude` to "No" (hides from Learning Hub)
- **Unless** `HubIncludeSync` = "no" (exempt from sync)
- **Unless** `HubIncludePersist` = "yes" (should persist)

**Persistent Courses** (`HubIncludePersist` = "yes"):
- Sets `HubIncludePersistState` to "inactive" instead of hiding
- Keeps `HUBInclude` = "Yes" so course remains visible with custom message

**Design Rationale:**
The script does NOT automatically set Active courses to Inactive when missing from ELM because:
- LSApp may be the first point of creation for new course requests
- Courses need to be "Active" before they're available for registration
- This prevents losing the ability to manage courses that haven't yet been entered in ELM

#### D. Expiration Handling
Checks all courses for expired `HubExpirationDate`:
- If current date > expiration date
- Sets `HUBInclude` to "No"
- Logs the expiration

### 3. Helper Functions

#### `getCoursesFromCSV($filepath, $isActiveFilter, $itemCodeIndex)`
- Loads CSV file into array indexed by ItemCode
- Skips header row
- Converts ItemCode to uppercase
- Sanitizes CourseAbstract field (index 17)
- Optional status filtering (Active/Inactive)

### 4. Logging System

**Timestamped Logs:**
- Path: `/data/course-sync-logs/course-sync-log-{timestamp}.txt`
- Created only when updates occur
- Contains detailed change log

**Persistent Log:**
- Path: `/data/course-sync-logs/elm_sync_log.txt`
- Always updated with ISO 8601 timestamp
- Prepends latest sync time to existing log

**Log Entry Types:**
- Course updates with specific field changes
- New course additions
- HUBInclude status changes
- Expiration-based removals
- Skipped operations (e.g., when HubIncludeSync = "no")
- Persistent course state changes
- Duplicate detection warnings
- Email notification status (success with transaction ID or error)

### 5. File Operations

**Safe Update Process:**
1. Creates temporary file: `/data/temp_courses.csv`
2. Writes header row
3. Reads original courses.csv line by line
4. For each row, checks for updates using **both ItemCode AND CourseID** as keys
   - First checks `$updatedCourses[$itemCode]`
   - If not found, checks `$updatedCourses[$courseId]`
   - This dual-key approach prevents data loss when ItemCode is empty
5. Writes updated version (if exists) or original row
6. Appends any brand new courses at the end
7. Renames temp file to replace original (atomic operation)

**Empty ItemCode Protection:**
- Courses not in ELM feed use **CourseID** (not ItemCode) as array key when storing updates
- This prevents collisions when multiple courses have empty ItemCodes
- Prevents data loss bug where courses with empty ItemCodes would overwrite each other

### 6. Special Fields Explained

| Field | Purpose |
|-------|---------|
| `HubIncludeSync` (index 58) | Controls whether course participates in sync. "no" = exempt from auto-hiding |
| `HubIncludePersist` (index 59) | "yes" = course persists in hub even when removed from ELM feed |
| `HubPersistMessage` (index 60) | Message shown for persistent courses when inactive |
| `HubIncludePersistState` (index 61) | "active"/"inactive" - state of persistent courses |

### 7. Workflow

```
1. Reset OpCache (ensures fresh data)
2. Load LSApp courses and ELM hub courses
3. Build lookup index of LSApp courses by normalized course name
4. For each course in ELM feed:
   - If exists in LSApp (by ItemCode): Update fields, set HUBInclude=Yes, activate if needed
   - If new (no ItemCode match):
     - Check if course name matches existing LSApp course
     - If name match found: Log duplicate, add to notification list, skip creation
     - If no match: Create full course record
5. For each course in LSApp:
   - If Platform="PSA Learning System" AND missing from ELM feed:
     - Regular courses: Set HUBInclude=No (unless exempt)
     - Persistent courses: Set state to "inactive"
6. Check all courses for expiration dates
7. Send email notification if duplicates detected (via CHES)
8. Generate logs
9. Write updated courses.csv
10. Redirect to feed-create.php
```

### 8. Access Control
- Requires `canAccess()` permission check
- Shows no-access template if unauthorized

### 9. Post-Sync Action
Automatically redirects to `feed-create.php` to regenerate the Learning Hub feed with updated course data.

## Key Design Decisions

1. **ItemCode as Primary Key**: Uses ItemCode (course code) to match courses between systems
2. **Duplicate Prevention**: Uses course name matching to prevent duplicate creation when ItemCode is missing in LSApp
3. **Email Notifications**: Sends high-priority alerts to allan.haggett@gov.bc.ca when duplicates are detected (via CHES email service)
4. **Partner Name → ID Conversion**: Converts LearningHubPartner from display name to internal ID
5. **Sanitization**: Applies `sanitizeText()` to CourseAbstract and other text fields
6. **One-Way Activation**: Reactivates Inactive courses found in ELM, but won't deactivate Active courses missing from ELM
7. **Timestamp Tracking**: Updates `Modified` field only when actual changes occur
8. **Persistence Feature**: Allows courses to remain visible even when removed from ELM feed (with custom messaging)
9. **Empty ItemCode Safety**: Uses CourseID (guaranteed unique) as array key for courses being hidden/expired to prevent data loss when ItemCodes are empty

## Dependencies

- **CHES Email Service**: `/inc/ches_client.php` - Used for sending duplicate detection notifications
- **BASE_DIR constant**: Required for file path construction
- **canAccess()**: Permission check function
- **sanitizeText()**: Text sanitization function
- **getPartnerIdByName()**: Partner name to ID conversion function
- **createSlug()**: Course name to URL slug conversion function
