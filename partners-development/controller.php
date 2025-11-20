<?php
require('../inc/lsapp.php');
require('../inc/ches_client.php');

// Verify CSRF token
if (!validateCsrfToken()) {
    header('Location: index.php?error=Invalid security token. Please try again.');
    exit;
}

$action = $_POST['action'] ?? '';
$partnersFile = '../data/development-partners.csv';
$tempFile = '../data/development-partners-temp.csv';

switch ($action) {
    case 'create':
        createPartner();
        break;
    case 'update':
        updatePartner();
        break;
    case 'delete':
        deletePartner();
        break;
    default:
        header('Location: index.php?error=Invalid action');
        exit;
}

function createPartner() {
    global $partnersFile;

    // Validate required fields
    $name = trim($_POST['name'] ?? '');
    if (empty($name)) {
        header('Location: create.php?error=Partner name is required');
        exit;
    }

    // Generate new ID
    $maxId = 0;
    if (file_exists($partnersFile)) {
        $data = array_map('str_getcsv', file($partnersFile));
        array_shift($data); // Remove header
        foreach ($data as $row) {
            if (!empty($row[0]) && is_numeric($row[0])) {
                $maxId = max($maxId, (int)$row[0]);
            }
        }
    }
    $newId = $maxId + 1;

    // Build the new row
    $newPartner = [
        $newId,
        trim($_POST['status'] ?? 'active'),
        trim($_POST['type'] ?? 'development'),
        $name,
        trim($_POST['description'] ?? ''),
        trim($_POST['url'] ?? ''),
        trim($_POST['contact_name'] ?? ''),
        trim($_POST['contact_email'] ?? '')
    ];

    // Append to CSV file
    $fp = fopen($partnersFile, 'a');
    if ($fp === false) {
        header('Location: index.php?error=Failed to open partners file for writing');
        exit;
    }

    fputcsv($fp, $newPartner);
    fclose($fp);

    // Send email notification for new development partner
    try {
        $ches = new CHESClient();

        $subject = "New Development Partner Added: " . $name;

        $bodyHtml = "<h2>New Development Partner Created</h2>";
        $bodyHtml .= "<p>A new development partner has been added to the system:</p>";
        $bodyHtml .= "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; font-family: Arial, sans-serif;'>";
        $bodyHtml .= "<tr><td><strong>ID:</strong></td><td>" . htmlspecialchars($newId) . "</td></tr>";
        $bodyHtml .= "<tr><td><strong>Name:</strong></td><td>" . htmlspecialchars($name) . "</td></tr>";
        $bodyHtml .= "<tr><td><strong>Status:</strong></td><td>" . htmlspecialchars(trim($_POST['status'] ?? 'active')) . "</td></tr>";
        $bodyHtml .= "<tr><td><strong>Type:</strong></td><td>" . htmlspecialchars(trim($_POST['type'] ?? 'development')) . "</td></tr>";
        if (!empty($_POST['url'])) {
            $bodyHtml .= "<tr><td><strong>URL:</strong></td><td><a href='" . htmlspecialchars(trim($_POST['url'])) . "'>" . htmlspecialchars(trim($_POST['url'])) . "</a></td></tr>";
        }
        if (!empty($_POST['description'])) {
            $bodyHtml .= "<tr><td><strong>Description:</strong></td><td>" . htmlspecialchars(trim($_POST['description'])) . "</td></tr>";
        }
        if (!empty($_POST['contact_name'])) {
            $bodyHtml .= "<tr><td><strong>Contact Name:</strong></td><td>" . htmlspecialchars(trim($_POST['contact_name'])) . "</td></tr>";
        }
        if (!empty($_POST['contact_email'])) {
            $bodyHtml .= "<tr><td><strong>Contact Email:</strong></td><td><a href='mailto:" . htmlspecialchars(trim($_POST['contact_email'])) . "'>" . htmlspecialchars(trim($_POST['contact_email'])) . "</a></td></tr>";
        }
        $bodyHtml .= "<tr><td><strong>Created By:</strong></td><td>" . htmlspecialchars(LOGGED_IN_IDIR) . "</td></tr>";
        $bodyHtml .= "<tr><td><strong>Date Created:</strong></td><td>" . date('Y-m-d H:i:s') . "</td></tr>";
        $bodyHtml .= "</table>";
        $bodyHtml .= "<p><a href='https://gww.bcpublicservice.gov.bc.ca/lsapp/partners-development/view.php?id=" . $newId . "'>View Development Partner</a></p>";

        $bodyText = "New Development Partner Created\n\n";
        $bodyText .= "ID: " . $newId . "\n";
        $bodyText .= "Name: " . $name . "\n";
        $bodyText .= "Status: " . trim($_POST['status'] ?? 'active') . "\n";
        $bodyText .= "Type: " . trim($_POST['type'] ?? 'development') . "\n";
        if (!empty($_POST['url'])) {
            $bodyText .= "URL: " . trim($_POST['url']) . "\n";
        }
        if (!empty($_POST['description'])) {
            $bodyText .= "Description: " . trim($_POST['description']) . "\n";
        }
        if (!empty($_POST['contact_name'])) {
            $bodyText .= "Contact Name: " . trim($_POST['contact_name']) . "\n";
        }
        if (!empty($_POST['contact_email'])) {
            $bodyText .= "Contact Email: " . trim($_POST['contact_email']) . "\n";
        }
        $bodyText .= "Created By: " . LOGGED_IN_IDIR . "\n";
        $bodyText .= "Date Created: " . date('Y-m-d H:i:s') . "\n\n";
        $bodyText .= "View Development Partner: https://gww.bcpublicservice.gov.bc.ca/lsapp/partners-development/view.php?id=" . $newId . "\n";

        $ches->sendEmail(
            ['allan.haggett@gov.bc.ca', 'clip@gov.bc.ca'],
            $subject,
            $bodyText,
            $bodyHtml,
            'learninghub_noreply@gov.bc.ca'
        );

        error_log("Sent new development partner notification for: " . $name);

    } catch (Exception $e) {
        error_log("ERROR: Failed to send development partner notification email: " . $e->getMessage());
        // Don't fail the whole operation if email fails
    }

    header('Location: index.php?message=Partner "' . urlencode($name) . '" created successfully');
    exit;
}

function updatePartner() {
    global $partnersFile, $tempFile;

    $id = $_POST['id'] ?? '';
    if (empty($id)) {
        header('Location: index.php?error=Partner ID is required');
        exit;
    }

    // Validate required fields
    $name = trim($_POST['name'] ?? '');
    if (empty($name)) {
        header('Location: update.php?id=' . urlencode($id) . '&error=Partner name is required');
        exit;
    }

    if (!file_exists($partnersFile)) {
        header('Location: index.php?error=Partners file not found');
        exit;
    }

    // Read existing file and write to temp file with updates
    $input = fopen($partnersFile, 'r');
    $output = fopen($tempFile, 'w');

    if ($input === false || $output === false) {
        header('Location: index.php?error=Failed to open files for updating');
        exit;
    }

    // Copy header
    $headers = fgetcsv($input);
    fputcsv($output, $headers);

    $found = false;
    while (($row = fgetcsv($input)) !== false) {
        if (!empty($row[0]) && $row[0] == $id) {
            // Update this row
            $updatedPartner = [
                $id,
                trim($_POST['status'] ?? 'active'),
                trim($_POST['type'] ?? 'development'),
                $name,
                trim($_POST['description'] ?? ''),
                trim($_POST['url'] ?? ''),
                trim($_POST['contact_name'] ?? ''),
                trim($_POST['contact_email'] ?? '')
            ];
            fputcsv($output, $updatedPartner);
            $found = true;
        } else {
            fputcsv($output, $row);
        }
    }

    fclose($input);
    fclose($output);

    if (!$found) {
        unlink($tempFile);
        header('Location: index.php?error=Partner not found');
        exit;
    }

    // Replace original file with updated temp file
    if (!rename($tempFile, $partnersFile)) {
        header('Location: index.php?error=Failed to save updates');
        exit;
    }

    header('Location: index.php?message=Partner "' . urlencode($name) . '" updated successfully');
    exit;
}

function deletePartner() {
    global $partnersFile, $tempFile;

    $id = $_POST['id'] ?? '';
    if (empty($id)) {
        header('Location: index.php?error=Partner ID is required');
        exit;
    }

    if (!file_exists($partnersFile)) {
        header('Location: index.php?error=Partners file not found');
        exit;
    }

    // Read existing file and write to temp file, skipping the deleted row
    $input = fopen($partnersFile, 'r');
    $output = fopen($tempFile, 'w');

    if ($input === false || $output === false) {
        header('Location: index.php?error=Failed to open files for deleting');
        exit;
    }

    // Copy header
    $headers = fgetcsv($input);
    fputcsv($output, $headers);

    $found = false;
    $deletedName = '';
    while (($row = fgetcsv($input)) !== false) {
        if (!empty($row[0]) && $row[0] == $id) {
            // Skip this row (delete it)
            $deletedName = $row[3] ?? 'Unknown';
            $found = true;
        } else {
            fputcsv($output, $row);
        }
    }

    fclose($input);
    fclose($output);

    if (!$found) {
        unlink($tempFile);
        header('Location: index.php?error=Partner not found');
        exit;
    }

    // Replace original file with updated temp file
    if (!rename($tempFile, $partnersFile)) {
        header('Location: index.php?error=Failed to delete partner');
        exit;
    }

    header('Location: index.php?message=Partner "' . urlencode($deletedName) . '" deleted successfully');
    exit;
}
