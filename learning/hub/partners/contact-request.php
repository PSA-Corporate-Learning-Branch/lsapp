<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

$requiredFields = ['partner_slug', 'partner_name', 'name', 'email', 'idir', 'title', 'role'];
foreach ($requiredFields as $field) {
    if (empty($_POST[$field])) {
        http_response_code(400);
        echo "Missing required field: $field";
        exit;
    }
}

$request = [
    'partner_slug' => htmlspecialchars(trim($_POST['partner_slug'])),
    'partner_name' => htmlspecialchars(trim($_POST['partner_name'])),
    'name' => htmlspecialchars(trim($_POST['name'])),
    'email' => filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL),
    'idir' => trim($_POST['idir']),
    'title' => htmlspecialchars(trim($_POST['title'])),
    'role' => htmlspecialchars(trim($_POST['role'])),
    'timestamp' => date('c'),
];

// Load existing requests
$file = '../../../lsapp/data/partner_contact_requests.json';
$requests = [];

if (file_exists($file)) {
    $json = file_get_contents($file);
    $requests = json_decode($json, true) ?? [];
}

// Append new request and save
$requests[] = $request;
file_put_contents($file, json_encode($requests, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Send email notification
require_once('../../../lsapp/inc/ches_client.php');

try {
    $ches = new CHESClient();

    // Build email content
    $subject = "Partner Access Request: " . $request['partner_name'];

    $bodyHtml = "<h2>New Partner Access Request</h2>";
    $bodyHtml .= "<p>A user has requested access to manage courses for a partner:</p>";
    $bodyHtml .= "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; font-family: Arial, sans-serif;'>";
    $bodyHtml .= "<tr><td><strong>Partner Name:</strong></td><td>" . htmlspecialchars($request['partner_name']) . "</td></tr>";
    $bodyHtml .= "<tr><td><strong>Requester Name:</strong></td><td>" . htmlspecialchars($request['name']) . "</td></tr>";
    $bodyHtml .= "<tr><td><strong>Email:</strong></td><td>" . htmlspecialchars($request['email']) . "</td></tr>";
    $bodyHtml .= "<tr><td><strong>IDIR:</strong></td><td>" . htmlspecialchars($request['idir']) . "</td></tr>";
    $bodyHtml .= "<tr><td><strong>Title:</strong></td><td>" . htmlspecialchars($request['title']) . "</td></tr>";
    $bodyHtml .= "<tr><td><strong>Role:</strong></td><td>" . htmlspecialchars($request['role']) . "</td></tr>";
    $bodyHtml .= "<tr><td><strong>Date Requested:</strong></td><td>" . htmlspecialchars($request['timestamp']) . "</td></tr>";
    $bodyHtml .= "</table>";
    $bodyHtml .= "<p><a href='https://gww.bcpublicservice.gov.bc.ca/lsapp/partners/dashboard.php'>View Partner Dashboard</a></p>";

    $bodyText = "New Partner Access Request\n\n";
    $bodyText .= "Partner Name: " . $request['partner_name'] . "\n";
    $bodyText .= "Requester Name: " . $request['name'] . "\n";
    $bodyText .= "Email: " . $request['email'] . "\n";
    $bodyText .= "IDIR: " . $request['idir'] . "\n";
    $bodyText .= "Title: " . $request['title'] . "\n";
    $bodyText .= "Role: " . $request['role'] . "\n";
    $bodyText .= "Date Requested: " . $request['timestamp'] . "\n\n";
    $bodyText .= "View Partner Dashboard: https://gww.bcpublicservice.gov.bc.ca/lsapp/partners/dashboard.php\n";

    // Send email
    $result = $ches->sendEmail(
        ['allan.haggett@gov.bc.ca','clip@gov.bc.ca'],
        $subject,
        $bodyText,
        $bodyHtml,
        'learninghub_noreply@gov.bc.ca'
    );

    error_log("Sent partner access request notification email (Transaction ID: {$result['txId']})");

} catch (Exception $e) {
    error_log("ERROR: Failed to send partner access request notification email: " . $e->getMessage());
}

header("Location: {$_SERVER['HTTP_REFERER']}");
exit;
