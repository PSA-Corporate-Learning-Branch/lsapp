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
    header('Location: /learning/hub/partners/new-partner-form.php');
}
