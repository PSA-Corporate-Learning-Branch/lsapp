<?php
opcache_reset();
date_default_timezone_set('America/Vancouver');
$path = '../../../lsapp/inc/lsapp.php';
require($path); 
$partnersFile = "../../../lsapp/data/partners.json";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Load existing data
    $existingData = file_exists($partnersFile) ? json_decode(file_get_contents($partnersFile), true) : [];

    // DELETE a Partner
    if (isset($_POST["delete_id"])) {
        $deleteId = intval($_POST["delete_id"]);
        $existingData = array_filter($existingData, function ($partner) use ($deleteId) {
            return $partner["id"] !== $deleteId;
        });

        file_put_contents($partnersFile, json_encode(array_values($existingData), JSON_PRETTY_PRINT));
        echo "Partner deleted successfully!";
        exit;
    }

    // ADD or EDIT a Partner
    $partnerIndex = -1;
    
    // First try to find by ID (for editing existing partners)
    if (!empty($_POST["id"])) {
        foreach ($existingData as $index => $partner) {
            if ($partner["id"] == $_POST["id"]) {
                $partnerIndex = $index;
                break;
            }
        }
    }
    
    // Fallback to finding by slug (for legacy compatibility)
    if ($partnerIndex === -1 && !empty($_POST["slug"])) {
        foreach ($existingData as $index => $partner) {
            if ($partner["slug"] === $_POST["slug"]) {
                $partnerIndex = $index;
                break;
            }
        }
    }

    // Load existing partner data if editing
    $existingContacts = [];
    $contactHistory = [];

    if ($partnerIndex !== -1) {
        $existingContacts = $existingData[$partnerIndex]["contacts"];
        $contactHistory = $existingData[$partnerIndex]["contact_history"] ?? [];
    }

    // Process new contacts
    $newContacts = [];
    if (isset($_POST["contacts"]) && is_array($_POST["contacts"])) {
        foreach ($_POST["contacts"] as $contact) {
            // Check if contact is new (not in existing contacts)
            $isNewContact = true;
            foreach ($existingContacts as $existingContact) {
                if ($existingContact["email"] === $contact["email"]) {
                    $isNewContact = false;
                    break;
                }
            }

            // Ensure 'added_at' remains unchanged once set
            if (!isset($contact["added_at"]) && $isNewContact) {
                $contact["added_at"] = date("Y-m-d H:i:s");
            }

            $newContacts[] = [
                "idir" => $contact["idir"],
                "email" => $contact["email"],
                "name" => $contact["name"],
                "title" => $contact["title"],
                "role" => $contact["role"],
                "added_at" => $contact["added_at"] // Preserve added_at if already set
            ];
        }
    }

    // Detect removed contacts and move them to history
    foreach ($existingContacts as $oldContact) {
        $existsInNewContacts = false;
        foreach ($newContacts as $newContact) {
            if ($oldContact["email"] === $newContact["email"]) {
                $existsInNewContacts = true;
                break;
            }
        }

        if (!$existsInNewContacts) {
            // Mark the old contact as removed and add to history
            $oldContact["removed_at"] = date("Y-m-d H:i:s");
            $contactHistory[] = $oldContact;
        }
    }

    // Always regenerate slug from name for all updates
    $slug = createSlug($_POST["name"]);

    // Construct the updated partner data
    $status = $_POST["status"] ?? "requested";
    $newPartner = [
        "id" => ($partnerIndex !== -1) ? $existingData[$partnerIndex]["id"] : (count($existingData) ? max(array_column($existingData, 'id')) + 1 : 1),
        "name" => $_POST["name"],
        "slug" => $slug,
        "description" => $_POST["description"],
        "link" => $_POST["link"],
        "employee_facing_contact" => $_POST["employee_facing_contact"] ?? "",
        "contacts" => $newContacts,
        "contact_history" => $contactHistory, // Preserve the history
        "status" => $status
    ];
    
    // Add date_requested and requested_idir for new requests
    if ($partnerIndex === -1 && $status === "requested") {
        $newPartner["date_requested"] = date("Y-m-d H:i:s");
        if (defined('LOGGED_IN_IDIR') && LOGGED_IN_IDIR) {
            $newPartner["requested_idir"] = LOGGED_IN_IDIR;
        }
    } elseif ($partnerIndex !== -1) {
        // Preserve existing date_requested and requested_idir if they exist
        if (isset($existingData[$partnerIndex]["date_requested"])) {
            $newPartner["date_requested"] = $existingData[$partnerIndex]["date_requested"];
        }
        if (isset($existingData[$partnerIndex]["requested_idir"])) {
            $newPartner["requested_idir"] = $existingData[$partnerIndex]["requested_idir"];
        }
    }

    if ($partnerIndex !== -1) {
        $existingData[$partnerIndex] = $newPartner;
    } else {
        $existingData[] = $newPartner;
    }

    file_put_contents($partnersFile, json_encode(array_values($existingData), JSON_PRETTY_PRINT));

    // Send email notification for new partner requests
    if ($partnerIndex === -1 && $status === "requested") {
        require_once('../../../lsapp/inc/ches_client.php');

        try {
            $ches = new CHESClient();

            // Build email content
            $subject = "New Learning Partner Request: " . htmlspecialchars($newPartner["name"]);

            $bodyHtml = "<h2>New Learning Partner Request</h2>";
            $bodyHtml .= "<p>A new learning partner request has been submitted:</p>";
            $bodyHtml .= "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; font-family: Arial, sans-serif;'>";
            $bodyHtml .= "<tr><td><strong>Partner Name:</strong></td><td>" . htmlspecialchars($newPartner["name"]) . "</td></tr>";
            $bodyHtml .= "<tr><td><strong>Description:</strong></td><td>" . htmlspecialchars($newPartner["description"]) . "</td></tr>";
            $bodyHtml .= "<tr><td><strong>Link:</strong></td><td><a href='" . htmlspecialchars($newPartner["link"]) . "'>" . htmlspecialchars($newPartner["link"]) . "</a></td></tr>";
            $bodyHtml .= "<tr><td><strong>Employee-facing Contact:</strong></td><td>" . htmlspecialchars($newPartner["employee_facing_contact"]) . "</td></tr>";
            $bodyHtml .= "<tr><td><strong>Date Requested:</strong></td><td>" . htmlspecialchars($newPartner["date_requested"]) . "</td></tr>";

            if (isset($newPartner["requested_idir"]) && $newPartner["requested_idir"]) {
                $bodyHtml .= "<tr><td><strong>Requested By:</strong></td><td>" . htmlspecialchars($newPartner["requested_idir"]) . "</td></tr>";
            }

            $bodyHtml .= "</table>";

            if (!empty($newPartner["contacts"])) {
                $bodyHtml .= "<h3>Contacts:</h3>";
                $bodyHtml .= "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; font-family: Arial, sans-serif;'>";
                $bodyHtml .= "<tr style='background-color: #f2f2f2;'>";
                $bodyHtml .= "<th>Name</th><th>Email</th><th>IDIR</th><th>Title</th><th>Role</th>";
                $bodyHtml .= "</tr>";

                foreach ($newPartner["contacts"] as $contact) {
                    $bodyHtml .= "<tr>";
                    $bodyHtml .= "<td>" . htmlspecialchars($contact["name"]) . "</td>";
                    $bodyHtml .= "<td>" . htmlspecialchars($contact["email"]) . "</td>";
                    $bodyHtml .= "<td>" . htmlspecialchars($contact["idir"]) . "</td>";
                    $bodyHtml .= "<td>" . htmlspecialchars($contact["title"]) . "</td>";
                    $bodyHtml .= "<td>" . htmlspecialchars($contact["role"]) . "</td>";
                    $bodyHtml .= "</tr>";
                }

                $bodyHtml .= "</table>";
            }

            $bodyHtml .= "<p><a href='https://gww.bcpublicservice.gov.bc.ca/lsapp/partners/dashboard.php'>View Partner Dashboard</a></p>";

            $bodyText = "New Learning Partner Request\n\n";
            $bodyText .= "Partner Name: " . $newPartner["name"] . "\n";
            $bodyText .= "Description: " . $newPartner["description"] . "\n";
            $bodyText .= "Link: " . $newPartner["link"] . "\n";
            $bodyText .= "Employee-facing Contact: " . $newPartner["employee_facing_contact"] . "\n";
            $bodyText .= "Date Requested: " . $newPartner["date_requested"] . "\n";

            if (isset($newPartner["requested_idir"]) && $newPartner["requested_idir"]) {
                $bodyText .= "Requested By: " . $newPartner["requested_idir"] . "\n";
            }

            if (!empty($newPartner["contacts"])) {
                $bodyText .= "\nContacts:\n";
                foreach ($newPartner["contacts"] as $contact) {
                    $bodyText .= "- " . $contact["name"] . " (" . $contact["email"] . ")\n";
                    $bodyText .= "  IDIR: " . $contact["idir"] . "\n";
                    $bodyText .= "  Title: " . $contact["title"] . "\n";
                    $bodyText .= "  Role: " . $contact["role"] . "\n";
                }
            }

            $bodyText .= "\nView Partner Dashboard: https://gww.bcpublicservice.gov.bc.ca/lsapp/partners/dashboard.php\n";

            // Send email
            $result = $ches->sendEmail(
                ['allan.haggett@gov.bc.ca','clip@gov.bc.ca'],
                $subject,
                $bodyText,
                $bodyHtml,
                'learninghub_noreply@gov.bc.ca'
            );

            error_log("Sent new partner request notification email (Transaction ID: {$result['txId']})");

            // Send confirmation emails to each administrative contact
            if (!empty($newPartner["contacts"])) {
                foreach ($newPartner["contacts"] as $contact) {
                    if (!empty($contact["email"])) {
                        try {
                            $confirmSubject = "Learning Partner Request Received - " . htmlspecialchars($newPartner["name"]);

                            $confirmBodyHtml = "<h2>Thank you for your Learning Partner request</h2>";
                            $confirmBodyHtml .= "<p>Hello " . htmlspecialchars($contact["name"]) . ",</p>";
                            $confirmBodyHtml .= "<p>We have received your request to become a Corporate Learning Partner for <strong>" . htmlspecialchars($newPartner["name"]) . "</strong>.</p>";
                            $confirmBodyHtml .= "<h3>What happens next?</h3>";
                            $confirmBodyHtml .= "<p>Our team will review your request and get back to you soon with next steps. Please stay tuned for more information.</p>";
                            $confirmBodyHtml .= "<h3>Request Summary:</h3>";
                            $confirmBodyHtml .= "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; font-family: Arial, sans-serif;'>";
                            $confirmBodyHtml .= "<tr><td><strong>Partner Name:</strong></td><td>" . htmlspecialchars($newPartner["name"]) . "</td></tr>";
                            $confirmBodyHtml .= "<tr><td><strong>Description:</strong></td><td>" . htmlspecialchars($newPartner["description"]) . "</td></tr>";
                            $confirmBodyHtml .= "<tr><td><strong>Date Submitted:</strong></td><td>" . htmlspecialchars($newPartner["date_requested"]) . "</td></tr>";
                            $confirmBodyHtml .= "</table>";
                            $confirmBodyHtml .= "<p>If you have any questions in the meantime, please don't hesitate to reach out.</p>";
                            $confirmBodyHtml .= "<p>Thank you,<br>LearningHUB Team</p>";

                            $confirmBodyText = "Thank you for your Learning Partner request\n\n";
                            $confirmBodyText .= "Hello " . $contact["name"] . ",\n\n";
                            $confirmBodyText .= "We have received your request to become a Corporate Learning Partner for " . $newPartner["name"] . ".\n\n";
                            $confirmBodyText .= "What happens next?\n";
                            $confirmBodyText .= "Our team will review your request and get back to you soon with next steps. Please stay tuned for more information.\n\n";
                            $confirmBodyText .= "Request Summary:\n";
                            $confirmBodyText .= "Partner Name: " . $newPartner["name"] . "\n";
                            $confirmBodyText .= "Description: " . $newPartner["description"] . "\n";
                            $confirmBodyText .= "Date Submitted: " . $newPartner["date_requested"] . "\n\n";
                            $confirmBodyText .= "If you have any questions in the meantime, please don't hesitate to reach out.\n\n";
                            $confirmBodyText .= "Thank you,\nLearningHUB Team\n";

                            $confirmResult = $ches->sendEmail(
                                [$contact["email"]],
                                $confirmSubject,
                                $confirmBodyText,
                                $confirmBodyHtml,
                                'learninghub_noreply@gov.bc.ca'
                            );

                            error_log("Sent confirmation email to {$contact['email']} (Transaction ID: {$confirmResult['txId']})");

                        } catch (Exception $confirmException) {
                            error_log("ERROR: Failed to send confirmation email to {$contact['email']}: " . $confirmException->getMessage());
                        }
                    }
                }
            }

        } catch (Exception $e) {
            error_log("ERROR: Failed to send partner request notification email: " . $e->getMessage());
        }
    }

    header('Location: /learning/hub/partners/new-partner-form.php');
}
