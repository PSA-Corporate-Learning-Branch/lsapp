<?php
require('../inc/lsapp.php');

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
