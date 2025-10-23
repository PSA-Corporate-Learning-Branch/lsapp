# Empty ItemCode Collision Bug Fix

## Problem Identified

The ELM course sync had a critical data integrity bug where courses with empty ItemCodes could collide in the `$updatedCourses` array, causing one course to overwrite another and resulting in **permanent data loss**.

### Root Cause

The `$updatedCourses` array used **ItemCode** as the key in multiple places:

1. **Lines 128-129** (updating existing ELM courses):
   ```php
   $itemCode = $updatedCourse[4];
   $updatedCourses[$itemCode] = $updatedCourse;
   ```

2. **Lines 200-201** (creating new ELM courses):
   ```php
   $itemCode = $newCourse[4];
   $updatedCourses[$itemCode] = $newCourse;
   ```

3. **Line 220** (courses not in ELM - setting HUBInclude to No):
   ```php
   $updatedCourses[$lsappCode] = $lsappCourse;  // $lsappCode IS the ItemCode
   ```

4. **Line 228** (persistent courses - setting to inactive):
   ```php
   $updatedCourses[$lsappCode] = $lsappCourse;  // $lsappCode IS the ItemCode
   ```

5. **Line 247** (expired courses):
   ```php
   $updatedCourses[$lsappCode] = $lsappCourse;  // $lsappCode IS the ItemCode
   ```

**The Problem:** When multiple courses have **empty ItemCodes**, they all use `""` (empty string) as the array key. The last course processed with an empty ItemCode would **overwrite** all previous ones in the `$updatedCourses` array.

### Real-World Impact

In the September 17, 2025 sync incident:
- **MBCPS course** (CourseID: `20210112130029`) had an empty ItemCode
- Was stored in `$updatedCourses[""]`
- Another course with empty ItemCode overwrote it
- **MBCPS was completely lost** from the CSV file
- A Neurodiversity course appeared in its place at line 1009

## Solution

Changed the three affected loops to use **CourseID** instead of ItemCode as the array key. CourseID is guaranteed to be unique, preventing collisions.

### Changes Made

#### 1. Courses Not in ELM Loop (Lines 218-234)

**Before:**
```php
$updatedCourses[$lsappCode] = $lsappCourse;
```

**After:**
```php
// Use CourseID as key to avoid collisions when ItemCode is empty
$courseId = $lsappCourse[0];
$updatedCourses[$courseId] = $lsappCourse;
```

Applied in 3 places:
- Line 222: Setting HUBInclude to No
- Line 232: Setting HubIncludePersistState to inactive

#### 2. Expired Courses Loop (Lines 243-252)

**Before:**
```php
$updatedCourses[$lsappCode] = $lsappCourse;
```

**After:**
```php
// Use CourseID as key to avoid collisions when ItemCode is empty
$courseId = $lsappCourse[0];
$updatedCourses[$courseId] = $lsappCourse;
```

#### 3. CSV Writing Logic (Lines 360-379)

Updated to check **both** ItemCode and CourseID when looking for updates:

**Before:**
```php
if (isset($updatedCourses[$itemCode])) {
    // write updated course
}
```

**After:**
```php
$itemCode = $row[4]; // ItemCode at index 4
$courseId = $row[0]; // CourseID at index 0

// Check both ItemCode and CourseID for updates (to handle both keying strategies)
if (isset($updatedCourses[$itemCode])) {
    // write updated course from ItemCode lookup
} elseif (isset($updatedCourses[$courseId])) {
    // write updated course from CourseID lookup
}
```

This handles the mixed keying strategy where:
- ELM-sourced courses use ItemCode as key (lines 129, 201)
- Non-ELM courses use CourseID as key (lines 222, 232, 249)

## Benefits

✅ **Prevents data loss** - No more course collisions when ItemCode is empty
✅ **Uses unique identifier** - CourseID is guaranteed unique
✅ **Backward compatible** - Still handles ItemCode-keyed updates
✅ **Safe for all scenarios** - Works whether ItemCode is present or not

## Testing Recommendations

1. **Create test scenario with empty ItemCodes:**
   - Add multiple courses in LSApp without ItemCodes
   - Ensure they're not in ELM feed
   - Run sync
   - Verify ALL courses are retained (not overwritten)

2. **Test mixed scenarios:**
   - Some courses with ItemCodes, some without
   - Run sync with various conditions (expired, not in ELM, etc.)
   - Verify all courses handled correctly

3. **Verify no regressions:**
   - Test normal ELM sync operations
   - Verify duplicate detection still works
   - Check that ItemCode matching still functions

## Files Modified

- `/var/www/html/lsapp/course-feed/elm-course-sync.php`
  - Lines 220-234: Fixed courses not in ELM loop
  - Lines 247-250: Fixed expired courses loop
  - Lines 360-379: Updated CSV writing logic

## Related Issues

This fix complements the duplicate detection implementation which prevents creating duplicate courses when names match but ItemCodes don't. Together, these changes provide comprehensive protection against data corruption during the sync process.
