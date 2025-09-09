<?php
require('../../../lsapp/inc/lsapp.php');

if (!canAccess()) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

$partnerId = $_POST['partner_id'] ?? '';
$contactEmail = $_POST['contact_email'] ?? '';

if (empty($partnerId) || empty($contactEmail)) {
    http_response_code(400);
    echo 'Missing required parameters';
    exit;
}

$partnersFile = '../../../lsapp/data/partners.json';

if (!file_exists($partnersFile)) {
    http_response_code(404);
    echo 'Partners file not found';
    exit;
}

$partners = json_decode(file_get_contents($partnersFile), true);
if (!$partners) {
    http_response_code(500);
    echo 'Error reading partners file';
    exit;
}

// Find the partner by ID
$partnerIndex = -1;
foreach ($partners as $index => $partner) {
    if ($partner['id'] == $partnerId) {
        $partnerIndex = $index;
        break;
    }
}

if ($partnerIndex === -1) {
    http_response_code(404);
    echo 'Partner not found';
    exit;
}

// Find and retire the contact
$contactFound = false;
$contactHistory = $partners[$partnerIndex]['contact_history'] ?? [];

foreach ($partners[$partnerIndex]['contacts'] as $key => $contact) {
    if ($contact['email'] === $contactEmail) {
        // Move contact to history with retirement timestamp
        $contact['removed_at'] = date('Y-m-d H:i:s');
        $contact['removed_by'] = LOGGED_IN_IDIR;
        $contactHistory[] = $contact;
        
        // Remove from active contacts
        unset($partners[$partnerIndex]['contacts'][$key]);
        $partners[$partnerIndex]['contacts'] = array_values($partners[$partnerIndex]['contacts']);
        $partners[$partnerIndex]['contact_history'] = $contactHistory;
        
        $contactFound = true;
        break;
    }
}

if (!$contactFound) {
    http_response_code(404);
    echo 'Contact not found';
    exit;
}

// Save updated partners data
if (file_put_contents($partnersFile, json_encode($partners, JSON_PRETTY_PRINT))) {
    // Redirect back to dashboard
    header('Location: dashboard.php?partnerid=' . $partnerId . '&message=ContactRetired');
} else {
    http_response_code(500);
    echo 'Error saving changes';
}
