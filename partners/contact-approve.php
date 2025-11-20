<?php
opcache_reset();

require('../inc/lsapp.php');

if (!canAccess()) {
    header('Location: /lsapp/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$requiredFields = ['partner_slug', 'name', 'email', 'idir', 'title', 'role'];
foreach ($requiredFields as $field) {
    if (empty($_POST[$field])) {
        http_response_code(400);
        exit("Missing required field: $field");
    }
}

$slug = $_POST['partner_slug'];
$contact = [
    'name' => $_POST['name'],
    'email' => $_POST['email'],
    'idir' => trim($_POST['idir']),
    'title' => $_POST['title'],
    'role' => $_POST['role'],
    'added_at' => date('Y-m-d H:i:s'),
    'approved_by' => LOGGED_IN_IDIR
];

// Load partner data
$partnerFile = '../data/partners.json';
$partners = file_exists($partnerFile) ? json_decode(file_get_contents($partnerFile), true) : [];

$partnerName = '';
$partnerId = '';

foreach ($partners as &$partner) {
    if ($partner['slug'] === $slug) {
        if (!isset($partner['contacts']) || !is_array($partner['contacts'])) {
            $partner['contacts'] = [];
        }
        // Ensure employee_facing_contact field exists
        if (!isset($partner['employee_facing_contact'])) {
            $partner['employee_facing_contact'] = '';
        }
        $partner['contacts'][] = $contact;
        $partnerName = $partner['name'];
        $partnerId = $partner['id'];
        break;
    }
}
file_put_contents($partnerFile, json_encode($partners, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Remove from contact requests
$requestsFile = '../data/partner_contact_requests.json';
$requests = file_exists($requestsFile) ? json_decode(file_get_contents($requestsFile), true) : [];
$requests = array_filter($requests, function($r) use ($slug, $contact) {
    return !(
        $r['partner_slug'] === $slug &&
        $r['idir'] === $contact['idir']
    );
});
file_put_contents($requestsFile, json_encode(array_values($requests), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Send email notifications
require_once('../inc/ches_client.php');

try {
    $ches = new CHESClient();

    // 1. Send notification to admin (allan.haggett@gov.bc.ca)
    $adminSubject = "Partner Admin Approved: " . htmlspecialchars($contact['name']) . " for " . htmlspecialchars($partnerName);

    $adminBodyHtml = "<h2>Partner Admin Approved</h2>";
    $adminBodyHtml .= "<p>A new partner administrator has been approved:</p>";
    $adminBodyHtml .= "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; font-family: Arial, sans-serif;'>";
    $adminBodyHtml .= "<tr><td><strong>Partner Name:</strong></td><td>" . htmlspecialchars($partnerName) . "</td></tr>";
    $adminBodyHtml .= "<tr><td><strong>Contact Name:</strong></td><td>" . htmlspecialchars($contact['name']) . "</td></tr>";
    $adminBodyHtml .= "<tr><td><strong>Email:</strong></td><td>" . htmlspecialchars($contact['email']) . "</td></tr>";
    $adminBodyHtml .= "<tr><td><strong>IDIR:</strong></td><td>" . htmlspecialchars($contact['idir']) . "</td></tr>";
    $adminBodyHtml .= "<tr><td><strong>Title:</strong></td><td>" . htmlspecialchars($contact['title']) . "</td></tr>";
    $adminBodyHtml .= "<tr><td><strong>Role:</strong></td><td>" . htmlspecialchars($contact['role']) . "</td></tr>";
    $adminBodyHtml .= "<tr><td><strong>Approved By:</strong></td><td>" . htmlspecialchars($contact['approved_by']) . "</td></tr>";
    $adminBodyHtml .= "<tr><td><strong>Date Approved:</strong></td><td>" . htmlspecialchars($contact['added_at']) . "</td></tr>";
    $adminBodyHtml .= "</table>";
    $adminBodyHtml .= "<p><a href='https://gww.bcpublicservice.gov.bc.ca/lsapp/partners/dashboard.php'>View Partner Dashboard</a></p>";

    $adminBodyText = "Partner Admin Approved\n\n";
    $adminBodyText .= "Partner Name: " . $partnerName . "\n";
    $adminBodyText .= "Contact Name: " . $contact['name'] . "\n";
    $adminBodyText .= "Email: " . $contact['email'] . "\n";
    $adminBodyText .= "IDIR: " . $contact['idir'] . "\n";
    $adminBodyText .= "Title: " . $contact['title'] . "\n";
    $adminBodyText .= "Role: " . $contact['role'] . "\n";
    $adminBodyText .= "Approved By: " . $contact['approved_by'] . "\n";
    $adminBodyText .= "Date Approved: " . $contact['added_at'] . "\n\n";
    $adminBodyText .= "View Partner Dashboard: https://gww.bcpublicservice.gov.bc.ca/lsapp/partners/dashboard.php\n";

    $adminResult = $ches->sendEmail(
        ['allan.haggett@gov.bc.ca','clip@gov.bc.ca'],
        $adminSubject,
        $adminBodyText,
        $adminBodyHtml,
        'learninghub_noreply@gov.bc.ca'
    );

    error_log("Sent partner admin approval notification to admin (Transaction ID: {$adminResult['txId']})");

    // 2. Send welcome email to the approved contact
    $welcomeSubject = "Welcome - You've been approved as a Partner Administrator for " . htmlspecialchars($partnerName);

    $partnerDashboardUrl = "https://gww.bcpublicservice.gov.bc.ca/learning/hub/partners/dashboard.php?partnerid=" . urlencode($partnerId);

    $welcomeBodyHtml = "<h2>Welcome to the Learning Partner Program!</h2>";
    $welcomeBodyHtml .= "<p>Hello " . htmlspecialchars($contact['name']) . ",</p>";
    $welcomeBodyHtml .= "<p>Great news! You've been approved as a partner administrator for <strong>" . htmlspecialchars($partnerName) . "</strong>.</p>";
    $welcomeBodyHtml .= "<h3>What's next?</h3>";
    $welcomeBodyHtml .= "<p>You can now access the partner admin panel to manage courses for your organization:</p>";
    $welcomeBodyHtml .= "<p><a href='" . htmlspecialchars($partnerDashboardUrl) . "' style='display: inline-block; padding: 10px 20px; background-color: #036; color: white; text-decoration: none; border-radius: 5px;'>Access Partner Admin Panel</a></p>";
    $welcomeBodyHtml .= "<p>Or copy this link: <a href='" . htmlspecialchars($partnerDashboardUrl) . "'>" . htmlspecialchars($partnerDashboardUrl) . "</a></p>";
    $welcomeBodyHtml .= "<h3>Your Details:</h3>";
    $welcomeBodyHtml .= "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; font-family: Arial, sans-serif;'>";
    $welcomeBodyHtml .= "<tr><td><strong>Partner:</strong></td><td>" . htmlspecialchars($partnerName) . "</td></tr>";
    $welcomeBodyHtml .= "<tr><td><strong>Your Role:</strong></td><td>" . htmlspecialchars($contact['role']) . "</td></tr>";
    $welcomeBodyHtml .= "<tr><td><strong>Date Approved:</strong></td><td>" . htmlspecialchars($contact['added_at']) . "</td></tr>";
    $welcomeBodyHtml .= "</table>";
    $welcomeBodyHtml .= "<p>If you have any questions, please don't hesitate to reach out.</p>";
    $welcomeBodyHtml .= "<p>Thank you,<br>LearningHUB Team</p>";

    $welcomeBodyText = "Welcome to the Learning Partner Program!\n\n";
    $welcomeBodyText .= "Hello " . $contact['name'] . ",\n\n";
    $welcomeBodyText .= "Great news! You've been approved as a partner administrator for " . $partnerName . ".\n\n";
    $welcomeBodyText .= "What's next?\n";
    $welcomeBodyText .= "You can now access the partner admin panel to manage courses for your organization:\n\n";
    $welcomeBodyText .= $partnerDashboardUrl . "\n\n";
    $welcomeBodyText .= "Your Details:\n";
    $welcomeBodyText .= "Partner: " . $partnerName . "\n";
    $welcomeBodyText .= "Your Role: " . $contact['role'] . "\n";
    $welcomeBodyText .= "Date Approved: " . $contact['added_at'] . "\n\n";
    $welcomeBodyText .= "If you have any questions, please don't hesitate to reach out.\n\n";
    $welcomeBodyText .= "Thank you,\nLearningHUB Team\n";

    $welcomeResult = $ches->sendEmail(
        [$contact['email']],
        $welcomeSubject,
        $welcomeBodyText,
        $welcomeBodyHtml,
        'learninghub_noreply@gov.bc.ca'
    );

    error_log("Sent welcome email to approved contact {$contact['email']} (Transaction ID: {$welcomeResult['txId']})");

} catch (Exception $e) {
    error_log("ERROR: Failed to send approval notification emails: " . $e->getMessage());
}

header('Location: dashboard.php');
exit;
