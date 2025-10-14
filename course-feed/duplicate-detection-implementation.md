# Duplicate Detection Implementation

## Summary
Added duplicate course detection to the ELM course sync process to prevent creating duplicate courses when the ItemCode isn't properly synced between LSApp and ELM.

## Changes Made to `course-feed/elm-course-sync.php`

### 1. Course Name Index (Lines 115-120)
Built a lookup index of LSApp courses by normalized course name:
```php
$lsappCoursesByName = [];
foreach ($lsappCourses as $lsCode => $lsCourse) {
    $normalizedName = strtolower(trim($lsCourse[2])); // CourseName at index 2
    $lsappCoursesByName[$normalizedName] = $lsCode;
}
```

### 2. Duplicate Detection Logic (Lines 130-151)
Before creating a new course, check if a course with the same name already exists:

```php
// Check for duplicate by course name before creating new course
$elmCourseName = strtolower(trim($hc[1])); // ELM CourseName at index 1

if (isset($lsappCoursesByName[$elmCourseName])) {
    // Found a course with the same name - potential duplicate!
    $matchedItemCode = $lsappCoursesByName[$elmCourseName];
    $matchedCourse = $lsappCourses[$matchedItemCode];

    $potentialDuplicates[] = [
        'elm_item_code' => $hcCode,
        'elm_course_name' => $hc[1],
        'lsapp_course_id' => $matchedCourse[0],
        'lsapp_item_code' => $matchedCourse[4],
        'lsapp_course_name' => $matchedCourse[2]
    ];

    $logEntries[] = "DUPLICATE DETECTED: ELM course '$hcCode - {$hc[1]}' matches existing LSApp course '{$matchedCourse[4]} - {$matchedCourse[2]}' by name. Skipped creation.";

    // Skip creating the new course
    continue;
}
```

### 3. Email Notification (Lines 249-318)
If duplicates are detected, send an email notification to allan.haggett@gov.bc.ca:

**Email Features:**
- **Subject**: "Course Sync Alert: X Potential Duplicate(s) Detected"
- **Priority**: High
- **Format**: Both HTML and plain text
- **Content**: Detailed table showing:
  - ELM Item Code
  - ELM Course Name
  - LSApp Course ID
  - LSApp Item Code
  - LSApp Course Name

**Action Steps Included:**
1. Verify if the ELM course and LSApp course are the same
2. If they are the same, update the ItemCode in LSApp to match the ELM Item Code
3. The next sync will then update the existing course instead of trying to create a duplicate

**Error Handling:**
- Wrapped in try/catch block
- Logs success with transaction ID or failure with error message

## How It Works

### Scenario: Course Created in LSApp First

1. **User creates course** "Workplace Safety 101" in LSApp (no ItemCode assigned yet)
2. **Someone enters same course in ELM** with ItemCode "ITEM-1234"
3. **Sync runs** and processes ELM feed:
   - Looks for "ITEM-1234" in LSApp courses (not found - no ItemCode match)
   - **NEW**: Checks if "Workplace Safety 101" exists in LSApp by name
   - **Match found!** (case-insensitive)
   - Adds to `potentialDuplicates` array
   - Logs the detection
   - **Skips** creating new course (using `continue`)
4. **After sync loop completes**:
   - Sends email to allan.haggett@gov.bc.ca with duplicate details
   - Logs email transaction ID
5. **Admin receives email** with clear table showing the match
6. **Admin updates** ItemCode in LSApp to "ITEM-1234"
7. **Next sync** will find the ItemCode match and update the existing course

## Benefits

✅ **Prevents duplicates** - No longer creates duplicate courses with same name
✅ **Email alerts** - Immediate notification when duplicates are detected
✅ **Detailed reporting** - Clear information about both courses for easy verification
✅ **Actionable** - Email includes exact steps to resolve the issue
✅ **Logged** - All duplicate detections are logged for audit trail
✅ **Safe** - Only skips creation, doesn't modify existing data
✅ **Case-insensitive** - Matches courses regardless of capitalization

## Dependencies

- **CHES Email Service**: Uses `/inc/ches_client.php` for sending emails
- **BASE_DIR constant**: Must be defined for file path construction
- **CHESClient class**: Handles OAuth2 authentication and email delivery

## Testing Recommendations

1. **Create test scenario**:
   - Add a course in LSApp without ItemCode
   - Add same course to ELM feed with ItemCode
   - Run sync and verify email is sent

2. **Verify email content**:
   - Check that both courses are listed correctly
   - Confirm action steps are clear

3. **Test resolution**:
   - Update ItemCode in LSApp
   - Re-run sync
   - Verify course is updated (not duplicated)

4. **Edge cases**:
   - Multiple duplicates in one sync
   - Email delivery failure (check logs)
   - Case sensitivity variations

## Files Modified

- `/var/www/html/lsapp/course-feed/elm-course-sync.php`

## Files Referenced

- `/var/www/html/lsapp/inc/ches_client.php` (CHES email client)
