<?php
date_default_timezone_set('America/Los_Angeles');

// back up courses file before we begin processing
$coursesbackup = '../data/courses.csv';
$newcoursefile = '../data/backups/courses'.date('Ymd\THis').'.csv';
if (!copy($coursesbackup, $newcoursefile)) {
    echo "Failed to backup $newcoursefile...\n";
    exit;
}

echo "Backup created: $newcoursefile\n\n";

// Load backup data to restore Requested/RequestedBy fields
$backupFile = '../data/courses20251201T070015.csv';
$backupData = [];

if (file_exists($backupFile)) {
    echo "Loading backup file: $backupFile\n";
    $backupHandle = fopen($backupFile, 'r');
    fgetcsv($backupHandle); // Skip header

    while (($row = fgetcsv($backupHandle)) !== false) {
        $courseId = $row[0];
        // Store Requested (index 13) and RequestedBy (index 14) from backup
        $backupData[$courseId] = [
            'requested' => $row[13] ?? '',
            'requestedby' => $row[14] ?? ''
        ];
    }
    fclose($backupHandle);
    echo "Loaded " . count($backupData) . " courses from backup\n\n";
} else {
    echo "Warning: Backup file not found at $backupFile\n";
    echo "Proceeding without restoring Requested/RequestedBy fields\n\n";
}

// build our filepath
$path = '../data/courses.csv';

// open our courses file
$coursesfile = fopen($path, 'r');

// grab the headers
$headers = fgetcsv($coursesfile);
$expectedColumnCount = count($headers);

echo "Expected column count: $expectedColumnCount\n";
echo "Last 3 headers: " . implode(', ', array_slice($headers, -3)) . "\n\n";

// open our output file
$outputcourses = fopen('../data/courses-temp.csv','w');
// write headers
fputcsv($outputcourses, $headers);

$lineNum = 1;
$fixedCount = 0;
$restoredCount = 0;
$errorCount = 0;

while ($row = fgetcsv($coursesfile)) {
    $lineNum++;
    $colCount = count($row);
    $courseId = $row[0];

    // Restore Requested/RequestedBy from backup if available
    if (isset($backupData[$courseId])) {
        $oldRequested = $row[13] ?? '';
        $oldRequestedBy = $row[14] ?? '';

        $row[13] = $backupData[$courseId]['requested'];
        $row[14] = $backupData[$courseId]['requestedby'];

        if ($oldRequested !== $row[13] || $oldRequestedBy !== $row[14]) {
            echo "Line $lineNum (CourseID: $courseId): Restored Requested/RequestedBy\n";
            echo "  Requested: '$oldRequested' -> '{$row[13]}'\n";
            echo "  RequestedBy: '$oldRequestedBy' -> '{$row[14]}'\n";
            $restoredCount++;
        }
    }

    // Fix column count issues
    if ($colCount === $expectedColumnCount) {
        // Row is correct, write it
        fputcsv($outputcourses, $row);
    } elseif ($colCount === 62) {
        // Missing modifiedby column - add 'moallen' as the modifier
        $row[] = 'moallen'; // Add modifiedby
        fputcsv($outputcourses, $row);
        $fixedCount++;
        echo "Line $lineNum (CourseID: {$row[0]}): Fixed - added missing modifiedby column (set to 'moallen')\n";
    } elseif ($colCount === 65) {
        // Has 2 extra columns - remove duplicates from end
        // Keep only first 63 columns, but set modifiedby to 'moallen'
        $row = array_slice($row, 0, 63);
        $row[62] = 'moallen'; // Set modifiedby to moallen
        fputcsv($outputcourses, $row);
        $fixedCount++;
        echo "Line $lineNum (CourseID: {$row[0]}): Fixed - removed 2 duplicate columns, set modifiedby to 'moallen'\n";
    } else {
        // Unexpected column count
        echo "ERROR Line $lineNum (CourseID: {$row[0]}): Unexpected column count $colCount (expected $expectedColumnCount)\n";
        // Write as-is to preserve data
        fputcsv($outputcourses, $row);
        $errorCount++;
    }
}

fclose($coursesfile);
fclose($outputcourses);

// overwrite courses.csv with our updated temp file
rename('../data/courses-temp.csv', '../data/courses.csv');

echo "\n=== Migration Summary ===\n";
echo "Total processed: $lineNum rows\n";
echo "Column alignment fixed: $fixedCount rows\n";
echo "Requested/RequestedBy restored: $restoredCount rows\n";
echo "Errors: $errorCount rows\n";
echo "\nMigration completed successfully.\n";
