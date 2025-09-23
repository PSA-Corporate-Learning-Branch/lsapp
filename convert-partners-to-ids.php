<?php
/**
 * One-off conversion script to update LearningHubPartner field in courses.csv
 * from partner names to numeric IDs based on partners.json
 * 
 * Usage: php convert-partners-to-ids.php
 */

// Load partners.json to build name-to-id mapping
$partnersJson = file_get_contents('data/partners.json');
if (!$partnersJson) {
    die("Error: Could not read partners.json\n");
}

$partners = json_decode($partnersJson, true);
if (!$partners) {
    die("Error: Could not parse partners.json\n");
}

// Build name-to-id mapping
$partnerNameToId = [];
foreach ($partners as $partner) {
    $partnerNameToId[$partner['name']] = $partner['id'];
}

// Add manual mappings for known variations
$partnerNameToId['Learning Centre'] = 59; // Maps to PSA Corporate Learning Branch
$partnerNameToId['CIRMO'] = 78; // Maps to Corporate Information and Records Management Office
// Map "Procurement Strategy and Governance Branch" to Unknown since it doesn't exist in partners.json
$partnerNameToId['Procurement Strategy and Governance Branch'] = 372; // Maps to Unknown partner

echo "Loaded " . count($partnerNameToId) . " partners from partners.json\n";
echo "Partner name to ID mapping:\n";
foreach ($partnerNameToId as $name => $id) {
    echo "  - \"$name\" => $id\n";
}
echo "\n";

// Process courses.csv
$coursesFile = 'data/courses.csv';
$tempFile = 'data/courses-temp-conversion.csv';

if (!file_exists($coursesFile)) {
    die("Error: courses.csv not found\n");
}

$input = fopen($coursesFile, 'r');
if (!$input) {
    die("Error: Could not open courses.csv for reading\n");
}

$output = fopen($tempFile, 'w');
if (!$output) {
    fclose($input);
    die("Error: Could not create temporary file\n");
}

// Copy header row
$headers = fgetcsv($input);
if (!$headers) {
    fclose($input);
    fclose($output);
    die("Error: Could not read headers from courses.csv\n");
}
fputcsv($output, $headers);

// Track statistics
$totalRows = 0;
$convertedCount = 0;
$notFoundCount = 0;
$emptyCount = 0;
$alreadyNumericCount = 0;
$notFoundPartners = [];

// Process each row
while (($row = fgetcsv($input)) !== FALSE) {
    $totalRows++;
    
    // LearningHubPartner is at index 36
    if (isset($row[36])) {
        $currentValue = trim($row[36]);
        
        if (empty($currentValue)) {
            $emptyCount++;
            echo "Row $totalRows: Empty partner field\n";
        } elseif (is_numeric($currentValue)) {
            $alreadyNumericCount++;
            echo "Row $totalRows: Already numeric ID ($currentValue)\n";
        } elseif (isset($partnerNameToId[$currentValue])) {
            $oldValue = $currentValue;
            $row[36] = $partnerNameToId[$currentValue];
            $convertedCount++;
            echo "Row $totalRows: Converted \"$oldValue\" to ID {$row[36]}\n";
        } else {
            $notFoundCount++;
            if (!in_array($currentValue, $notFoundPartners)) {
                $notFoundPartners[] = $currentValue;
            }
            echo "Row $totalRows: WARNING - Partner \"$currentValue\" not found in partners.json\n";
        }
    }
    
    fputcsv($output, $row);
}

fclose($input);
fclose($output);

// Display summary
echo "\n=== CONVERSION SUMMARY ===\n";
echo "Total rows processed: $totalRows\n";
echo "Converted to IDs: $convertedCount\n";
echo "Already numeric: $alreadyNumericCount\n";
echo "Empty fields: $emptyCount\n";
echo "Partners not found: $notFoundCount\n";

if (count($notFoundPartners) > 0) {
    echo "\nPartners not found in partners.json:\n";
    foreach ($notFoundPartners as $partner) {
        echo "  - \"$partner\"\n";
    }
}

// Ask for confirmation before replacing the file
echo "\nDo you want to replace courses.csv with the converted version? (yes/no): ";
$handle = fopen("php://stdin", "r");
$response = trim(fgets($handle));
fclose($handle);

if (strtolower($response) === 'yes' || strtolower($response) === 'y') {
    // Create backup
    $backupFile = 'data/courses-backup-' . date('Y-m-d-His') . '.csv';
    if (copy($coursesFile, $backupFile)) {
        echo "Created backup: $backupFile\n";
    } else {
        die("Error: Could not create backup file\n");
    }
    
    // Replace original file
    if (rename($tempFile, $coursesFile)) {
        echo "Successfully updated courses.csv\n";
    } else {
        die("Error: Could not replace courses.csv\n");
    }
} else {
    echo "Conversion cancelled. Temporary file saved as: $tempFile\n";
    echo "You can manually review and rename it if needed.\n";
}

echo "\nConversion script complete.\n";