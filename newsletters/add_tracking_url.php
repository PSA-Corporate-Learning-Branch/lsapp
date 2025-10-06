<?php
/**
 * Migration: Add tracking_url field to newsletters table
 * This allows each newsletter to have its own tracking pixel URL
 */

echo "Newsletter Tracking URL Migration\n";
echo str_repeat('=', 80) . "\n\n";

try {
    // Connect to database
    $db = new PDO("sqlite:../data/subscriptions.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Step 1: Checking if tracking_url column exists...\n";

    // Check if column already exists
    $result = $db->query("PRAGMA table_info(newsletters)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    $hasTrackingUrl = false;

    foreach ($columns as $column) {
        if ($column['name'] === 'tracking_url') {
            $hasTrackingUrl = true;
            break;
        }
    }

    if ($hasTrackingUrl) {
        echo "  ℹ Column tracking_url already exists. Nothing to do.\n";
        exit(0);
    }

    echo "  ✓ Column doesn't exist yet. Proceeding with migration.\n\n";

    echo "Step 2: Adding tracking_url column...\n";

    // Add the column
    $db->exec("ALTER TABLE newsletters ADD COLUMN tracking_url TEXT");

    echo "  ✓ Column added successfully\n\n";

    echo "Step 3: Setting default tracking URL for existing newsletters...\n";

    // Set a sensible default (can be updated later via UI)
    $defaultUrl = "https://learn.bcpublicservice.gov.bc.ca/newsletter-tracker/track.php";
    $db->exec("UPDATE newsletters SET tracking_url = '$defaultUrl' WHERE tracking_url IS NULL");

    echo "  ✓ Default tracking URL set: $defaultUrl\n\n";

    echo "✅ Migration completed successfully!\n\n";
    echo "Next steps:\n";
    echo "1. Update newsletter configurations with your actual tracking URL\n";
    echo "2. Tracking pixels will be automatically added to all sent emails\n";

} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
