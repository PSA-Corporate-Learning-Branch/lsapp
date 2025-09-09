<?php
date_default_timezone_set('America/Vancouver');
$partnersFile = "../data/partners.json";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Load existing data
    $existingData = file_exists($partnersFile) ? json_decode(file_get_contents($partnersFile), true) : [];

    // DELETE a Partner
    if ((isset($_POST["delete_id"]) && $_POST["delete_id"] !== '') || (isset($_POST["delete_partner_id"]) && $_POST["delete_partner_id"] !== '')) {
        $deleteId = isset($_POST["delete_id"]) && $_POST["delete_id"] !== '' ? intval($_POST["delete_id"]) : intval($_POST["delete_partner_id"]);
        
        // Only proceed with deletion if we have a valid ID greater than 0
        if ($deleteId > 0) {
            // Create backup of partners.json before deletion
            $backupDir = "../data/backups";
            if (!file_exists($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            $backupFile = $backupDir . "/partners_backup_" . date("Y-m-d_H-i-s") . ".json";
            copy($partnersFile, $backupFile);
            
            $existingData = array_filter($existingData, function ($partner) use ($deleteId) {
                return $partner["id"] !== $deleteId;
            });

            file_put_contents($partnersFile, json_encode(array_values($existingData), JSON_PRETTY_PRINT));
            header('Location: /lsapp/partners/');
            exit;
        }
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

    // Get list of contacts to permanently delete (admin only)
    $contactsToDelete = [];
    if (isset($_POST["delete_contact"]) && is_array($_POST["delete_contact"])) {
        $contactsToDelete = array_map('intval', $_POST["delete_contact"]);
    }

    // Process new contacts
    $newContacts = [];
    if (isset($_POST["contacts"]) && is_array($_POST["contacts"])) {
        foreach ($_POST["contacts"] as $index => $contact) {
            // Skip contacts marked for deletion
            if (in_array($index, $contactsToDelete)) {
                continue;
            }
            
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

    // Detect removed contacts and move them to history (but not deleted ones)
    foreach ($existingContacts as $index => $oldContact) {
        // Skip contacts that were permanently deleted
        if (in_array($index, $contactsToDelete)) {
            continue;
        }
        
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

    // Generate slug from name - always regenerate if name has changed
    $nameChanged = false;
    if ($partnerIndex !== -1 && isset($existingData[$partnerIndex]["name"]) && $existingData[$partnerIndex]["name"] !== $_POST["name"]) {
        $nameChanged = true;
    }
    
    // Always regenerate slug from name if name changed, or if it's a new partner
    if ($partnerIndex === -1 || $nameChanged) {
        $slug = strtolower(str_replace(' ', '-', preg_replace('/[^a-zA-Z0-9\s-]/', '', $_POST["name"])));
    } else {
        // Keep existing slug if name hasn't changed
        $slug = $existingData[$partnerIndex]["slug"] ?? strtolower(str_replace(' ', '-', preg_replace('/[^a-zA-Z0-9\s-]/', '', $_POST["name"])));
    }

    // Handle employee-facing contact based on type selection (required field)
    $employeeFacingContact = "";
    if (!isset($_POST["employee_contact_type"]) || empty($_POST["employee_contact_type"])) {
        die("Error: Employee-facing contact type is required.");
    }
    
    if ($_POST["employee_contact_type"] === "email") {
        if (empty($_POST["employee_facing_contact"])) {
            die("Error: Email address is required when email contact type is selected.");
        }
        if (!filter_var($_POST["employee_facing_contact"], FILTER_VALIDATE_EMAIL)) {
            die("Error: Please provide a valid email address.");
        }
        $employeeFacingContact = $_POST["employee_facing_contact"];
    } elseif ($_POST["employee_contact_type"] === "crm") {
        $employeeFacingContact = "CRM";
    } else {
        die("Error: Invalid employee contact type selected.");
    }

    // Construct the updated partner data
    $status = isset($_POST["status"]) ? $_POST["status"] : "inactive";
    $newPartner = [
        "id" => ($partnerIndex !== -1) ? $existingData[$partnerIndex]["id"] : (count($existingData) ? max(array_column($existingData, 'id')) + 1 : 1),
        "name" => $_POST["name"],
        "slug" => $slug,
        "description" => $_POST["description"],
        "link" => $_POST["link"],
        "employee_facing_contact" => $employeeFacingContact,
        "contacts" => $newContacts,
        "contact_history" => $contactHistory, // Preserve the history
        "status" => $status
    ];

    if ($partnerIndex !== -1) {
        $existingData[$partnerIndex] = $newPartner;
    } else {
        $existingData[] = $newPartner;
    }

    file_put_contents($partnersFile, json_encode(array_values($existingData), JSON_PRETTY_PRINT));
    header('Location: /lsapp/partners/view.php?slug=' . $slug);
}
