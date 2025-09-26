<?php
opcache_reset();
$path = '../inc/lsapp.php';
require($path);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $changeid = $_POST['changeid'] ?? null;
    $date_created = time();

    // Collect form data
    $data = [
        'changeid' => $changeid,
        'courseid' => $_POST['courseid'],
        'assign_to' => $_POST['assign_to'],
        'crm_ticket_reference' => $_POST['crm_ticket_reference'] ?? null,
        'category' => urldecode($_POST['category']),
        'description' => $_POST['description'],
        'scope' => $_POST['scope'],
        'approval_status' => $_POST['approval_status'] ?? null,
        'urgent' => isset($_POST['urgent']) ? true : false,
        'progress' => $_POST['progress'] ?? null,
    ];

    $comment = $_POST['new_comment'] ?? null;
    $logged_in_user = LOGGED_IN_IDIR;

    $courseid = $data['courseid'];

    if ($changeid) {
        $filename = "requests/course-{$courseid}-change-{$changeid}.json";
        if (file_exists($filename)) {
            $existingData = json_decode(file_get_contents($filename), true);

            $data['date_created'] = $existingData['date_created'] ?? $date_created;
            $data['created_by'] = $existingData['created_by'] ?? LOGGED_IN_IDIR;
            $data['date_modified'] = time();

            $existingData['timeline'] = $existingData['timeline'] ?? [];

            // Log changes to other fields
            logFieldChanges($data, $existingData, $existingData['timeline'], $logged_in_user);

            // Handle hyperlinks
            $data['links'] = processLinks($_POST, $existingData, $existingData['timeline'], $logged_in_user);

            // Handle file uploads
            $newFiles = processFiles($_FILES, $courseid, $changeid, $existingData, $existingData['timeline'], $logged_in_user);

            // Handle file deletions
            $remainingFiles = deleteFiles($_POST, $existingData, $existingData['timeline'], $logged_in_user);

            // Merge new and remaining files
            $data['files'] = array_merge($remainingFiles, $newFiles);

            $data['timeline'] = $existingData['timeline'];
        }
    } else {
        $changeid = uniqid();
        $filename = "requests/course-{$courseid}-change-$changeid.json";

        $data['changeid'] = $changeid;
        $data['date_created'] = $date_created;
        $data['date_modified'] = $date_created;
        $data['created_by'] = LOGGED_IN_IDIR;
        $data['timeline'] = [];

        $data['links'] = processLinks($_POST, [], $data['timeline'], $logged_in_user);
        $data['files'] = processFiles($_FILES, $courseid, $changeid, [], $data['timeline'], $logged_in_user);

        $data['timeline'][] = [
            'field' => 'creation',
            'previous_value' => null,
            'new_value' => "Change created by {$logged_in_user}",
            'changed_by' => $logged_in_user,
            'changed_at' => $date_created,
        ];

        if ($comment) {
            $data['timeline'][] = [
                'field' => 'comment',
                'new_value' => $comment,
                'changed_by' => $logged_in_user,
                'changed_at' => time(),
            ];
        }
    }

    file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
    // Redirect to notifications page for new requests, or directly to view for updates
    if (isset($_POST['changeid']) && !empty($_POST['changeid'])) {
        // Existing request - go directly to view
        header("Location: view.php?courseid={$courseid}&changeid={$changeid}&message=Success");
    } else {
        // New request - go to notifications page
        header("Location: notifications.php?courseid={$courseid}&changeid={$changeid}");
    }
    exit;
} else {
    echo "Invalid request method!";
}

/**
 * Log changes to fields and update the timeline.
 */
function logFieldChanges($data, $existingData, &$timeline, $loggedInUser) {
    $trackedFields = ['assign_to', 'crm_ticket_reference', 'category', 'description', 'scope', 'approval_status', 'progress', 'urgent'];
    foreach ($trackedFields as $field) {
        if (isset($existingData[$field]) && $existingData[$field] !== $data[$field]) {
            $timeline[] = [
                'field' => $field,
                'previous_value' => $existingData[$field],
                'new_value' => $data[$field],
                'changed_by' => $loggedInUser,
                'changed_at' => time(),
            ];
        }
    }
}

/**
 * Process hyperlinks and update the timeline.
 */
function processLinks($postData, $existingData, &$timeline, $loggedInUser) {
    $links = [];
    $removedLinks = [];

    if (isset($postData['removed_links'])) {
        $removedLinks = is_array($postData['removed_links']) ? $postData['removed_links'] : explode(',', $postData['removed_links']);
    }

    $removedLinks = array_filter($removedLinks);

    if (!empty($postData['hyperlinks'])) {
        foreach ($postData['hyperlinks'] as $index => $link) {
            $link = filter_var(trim($link), FILTER_SANITIZE_URL);
            $description = $postData['descriptions'][$index] ?? null;
            $description = filter_var(trim($description), FILTER_SANITIZE_SPECIAL_CHARS);

            if (!empty($link)) {
                if (isset($postData['link_ids'][$index]) && isset($existingData['links'][$postData['link_ids'][$index]])) {
                    $linkId = $postData['link_ids'][$index];
                    if (!in_array($linkId, $removedLinks)) {
                        $existingLink = $existingData['links'][$linkId];
                        if ($existingLink['url'] !== $link || $existingLink['description'] !== $description) {
                            $timeline[] = [
                                'field' => 'link_updated',
                                'previous_value' => $existingLink,
                                'new_value' => ['url' => $link, 'description' => $description],
                                'changed_by' => $loggedInUser,
                                'changed_at' => time(),
                            ];
                        }
                        $links[$linkId] = ['url' => $link, 'description' => $description];
                    }
                } else {
                    $links[] = ['url' => $link, 'description' => $description];
                    $timeline[] = [
                        'field' => 'link_added',
                        'new_value' => ['url' => $link, 'description' => $description],
                        'changed_by' => $loggedInUser,
                        'changed_at' => time(),
                    ];
                }
            }
        }
    }

    foreach ($removedLinks as $linkId) {
        if (isset($existingData['links'][$linkId])) {
            $timeline[] = [
                'field' => 'link_removed',
                'previous_value' => $existingData['links'][$linkId],
                'new_value' => null,
                'changed_by' => $loggedInUser,
                'changed_at' => time(),
            ];
        }
    }

    return $links;
}

/**
 * Process file uploads and update the timeline.
 */
function processFiles($files, $courseid, $changeid, $existingData, &$timeline, $loggedInUser) {
    $uploadedFiles = [];
    $uploadDir = "requests/files/";

    if (!empty($files['uploaded_files']['name'][0])) {
        foreach ($files['uploaded_files']['name'] as $key => $name) {
            $tmpName = $files['uploaded_files']['tmp_name'][$key];
            $error = $files['uploaded_files']['error'][$key];
            $size = $files['uploaded_files']['size'][$key];

            if ($error === UPLOAD_ERR_OK && $size <= 20 * 1024 * 1024) {
                $normalizedFilename = strtolower(str_replace(' ', '-', $name));
                $uniqueName = "course-{$courseid}-change-{$changeid}-{$normalizedFilename}";
                $destination = $uploadDir . $uniqueName;

                if (move_uploaded_file($tmpName, $destination)) {
                    $uploadedFiles[] = $uniqueName;
                    $timeline[] = [
                        'field' => 'file_added',
                        'new_value' => $uniqueName,
                        'changed_by' => $loggedInUser,
                        'changed_at' => time(),
                    ];
                }
            }
        }
    }

    return $uploadedFiles;
}

/**
 * Delete files and update the timeline.
 */
function deleteFiles($postData, $existingData, &$timeline, $loggedInUser) {
    $files = $existingData['files'] ?? [];
    $removedFiles = isset($postData['removed_files']) ? (is_array($postData['removed_files']) ? $postData['removed_files'] : explode(',', $postData['removed_files'])) : [];

    foreach ($removedFiles as $fileId) {
        if (isset($files[$fileId])) {
            $filePath = "requests/files/" . $files[$fileId];
            if (file_exists($filePath)) {
                unlink($filePath);
                $timeline[] = [
                    'field' => 'file_removed',
                    'previous_value' => $files[$fileId],
                    'new_value' => null,
                    'changed_by' => $loggedInUser,
                    'changed_at' => time(),
                ];
            }
            unset($files[$fileId]);
        }
    }

    return array_values($files);
}
