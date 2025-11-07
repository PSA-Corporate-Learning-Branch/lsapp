<?php
opcache_reset();
require('../inc/lsapp.php'); 
require('../inc/Parsedown.php');
$Parsedown = new Parsedown();
$Parsedown->setSafeMode(true);

$courseid = isset($_GET['courseid']) ? htmlspecialchars($_GET['courseid']) : null;
$changeid = isset($_GET['changeid']) ? htmlspecialchars($_GET['changeid']) : null;

if (!$courseid) {
    echo '<div class="alert alert-danger">Error: Course ID is required.</div>';
    exit;
}

$course_deets = getCourse($courseid);
$course_steward = getPerson($course_deets[10]);
$course_developer = getPerson($course_deets[34]);

$formData = [
    'assign_to' => '',
    'crm_ticket_reference' => '',
    'category' => '',
    'description' => '',
    'scope' => '',
    'approval_status' => '',
    'urgent' => false,
    'comments' => '',
    'progress' => ''
];

if ($changeid) {
    $filePath = "requests/course-$courseid-change-$changeid.json";
    if (file_exists($filePath)) {
        $formData = json_decode(file_get_contents($filePath), true);
    } else {
        echo '<div class="alert alert-warning">Warning: Change ID not found.</div>';
    }
}
function getGuidanceByCategory($cat, $categoriesFile) {
    // Check if the JSON file exists
    if (!file_exists($categoriesFile)) {
        return "Error: Categories file not found.";
    }

    // Decode the JSON file into an associative array
    $categories = json_decode(file_get_contents($categoriesFile), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return "Error decoding JSON: " . json_last_error_msg();
    }

    // Search for the category and return the guidance
    foreach ($categories as $category) {
        if (isset($category['category']) && $category['category'] === $cat) {
            return $category['guidance'] ?? "Guidance not found.";
        }
    }

    // Return a message if the category was not found
    return "Category not found.";
}


$cat = urldecode($formData['category']) ?? '';
$categoriesFile = '../data/course-change-guidance.json';
$guidance = getGuidanceByCategory($cat, $categoriesFile);

$assignedtoemail = getPerson($formData['assign_to']);

$email_addresses = [
                    'steward' => $course_steward[3] ?? '',
                    'developer' => $course_developer[3] ?? '', 
                    'assigned' => $assignedtoemail[3] ?? ''
                    ];


function buildMailtoLink($email_addresses, $subject, $body) {
    // Collect the 'to' email addresses (steward and assigned)
    $toEmails = array_unique([
        $email_addresses['steward'] ?? null,
        $email_addresses['assigned'] ?? null
    ]);

    // Filter out empty or null values
    $toEmails = array_filter($toEmails);

    // Check if the developer's email is unique and not already in the 'to' emails
    $ccEmails = [];
    if (!empty($email_addresses['developer']) && !in_array($email_addresses['developer'], $toEmails)) {
        $ccEmails[] = $email_addresses['developer'];
    }

    // Build the mailto link
    $mailto = 'mailto:' . implode(';', $toEmails);
    $mailto .= "?subject=" . rawurlencode($subject);
    $mailto .= "&body=" . rawurlencode($body);

    // Add the CC field if there are any CC emails
    if (!empty($ccEmails)) {
        $mailto .= "&cc=" . rawurlencode(implode(';', $ccEmails));
    }

    return $mailto;
}
function generateMailtoLink($formData, $courseid, $changeid, $course_deets, $email_addresses) {
    $subject = '';
    if($formData['urgent']) {
        $subject .= '[URGENT] ';    
    }
    $subject .= $course_deets[2] . ' - ' . htmlspecialchars($formData['category'] ?? 'N/A') . ' request';

    $body = "This is just a notification. Please reply via the request page on LSApp.\n";
    // Add a link back to the request
    $requestLink = "https://gww.bcpublicservice.gov.bc.ca/lsapp/course-change/view.php?courseid=$courseid&changeid=$changeid";
    $body .= "\nView the full request here: $requestLink\n\n";

    // Build the body of the email
    $body .= "Course: $course_deets[2]\n";
    $body .= "Category: " . htmlspecialchars($formData['category'] ?? 'N/A') . "\n";
    $body .= "Scope: " . htmlspecialchars($formData['scope'] ?? 'N/A') . "\n";
    $body .= "Assigned To: " . htmlspecialchars($formData['assign_to'] ?? 'N/A') . "\n";
    $body .= "Approval Status: " . htmlspecialchars($formData['approval_status'] ?? 'N/A') . "\n";
    $body .= "Progress: " . htmlspecialchars($formData['progress'] ?? 'N/A') . "\n";
    if (!empty($formData['crm_ticket_reference'])) {
        $body .= "CRM Ticket Reference: " . htmlspecialchars($formData['crm_ticket_reference']) . "\n";
    }
    $body .= "Description: \n" . htmlspecialchars(strip_tags($formData['description'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') . "\n";

    // Add links section
    if (!empty($formData['links'])) {
        $body .= "\nLinks:\n";
        foreach ($formData['links'] as $link) {
            $url = htmlspecialchars($link['url'] ?? 'N/A');
            $description = htmlspecialchars($link['description'] ?? $url);
            $body .= "- $description: $url\n";
        }
    }

    // Add files section
    if (!empty($formData['files'])) {
        $body .= "\nUploaded Files:\n";
        foreach ($formData['files'] as $file) {
            $shortFileName = preg_replace("/^course-[a-zA-Z0-9\-]+-change-[a-z0-9]+-/", '', $file);
            $fileUrl = "https://gww.bcpublicservice.gov.bc.ca/lsapp/requests/files/" . urlencode($file);
            $body .= "- $shortFileName: $fileUrl\n";
        }
    }

    // Add comments section
    if (!empty($formData['timeline'])) {
        $body .= "\nComments:\n";
        foreach ($formData['timeline'] as $event) {
            if ($event['field'] === 'comment') {
                $comment = htmlspecialchars_decode($event['new_value'] ?? ''); // Decode special characters
                $body .= "- " . htmlspecialchars($event['changed_by'] ?? 'Unknown') . " at " . date('Y-m-d H:i:s', $event['changed_at'] ?? 0) . ":\n";
                $body .= "  $comment\n";
            }
        }
    }

    $mailto = buildMailtoLink($email_addresses, $subject, $body);
    return $mailto;
}



?>

<?php if (canACcess()): ?>

<?php getHeader(); ?>

<title><?= $course_deets[2] ?> - <?= htmlspecialchars($formData['category']) ?> Request</title>

<?php getScripts(); ?>

<body>

<?php getNavigation(); ?>

<div class="container mt-4">
    <div class="row justify-content-md-center">
        <div class="col">
            <a href="edit.php?courseid=<?= $courseid ?>&changeid=<?= $changeid ?>" class="btn btn-primary mb-4 float-end">Edit request</a>
            <h1 class="mb-3">
                <a href="/lsapp/course.php?courseid=<?= $course_deets[0] ?>" class="text-decoration-none"><?= $course_deets[2] ?></a>
            </h1>
            <div class="">
            <?php if ($formData['urgent']): ?>
            <span class="badge bg-danger">
                <strong>Urgent</strong>
            </span>
            <?php endif; ?>
            <span class="badge bg-primary"><?= htmlspecialchars($formData['approval_status'] ?? 'Unknown') ?></span>
            </div>
            <h2 class="my-2"><?= htmlspecialchars($formData['category']) ?> Request <small class="text-muted"><?= $formData['changeid'] ?? '' ?></small></h2>
            
        </div>
    </div>
    <div class="row">
    <div class="col-md-6">
    

        <div class="mb-2 d-flex align-items-center gap-2">
            <strong>Scope:</strong> <span class="badge bg-primary"><?= htmlspecialchars($formData['scope']) ?></span> 
            <a aria-label="More information about scope" class="scopeinfo" role="button" id="toggle-scopeguide" title="Click to view guidance">
                <span class="icon-svg baseline-svg">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free 6.5.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.-->
                        <path fill="#999" d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM216 336h24V272H216c-13.3 0-24-10.7-24-24s10.7-24 24-24h48c13.3 0 24 10.7 24 24v88h8c13.3 0 24 10.7 24 24s-10.7 24-24 24H216c-13.3 0-24-10.7-24-24s10.7-24 24-24zm40-208a32 32 0 1 1 0 64 32 32 0 1 1 0-64z"></path>
                    </svg>
                </span>
            </a>
        </div>

        <div class="row">
        <div class="col">
            <strong>Assigned To:</strong> <a href="../person.php?idir=<?= htmlspecialchars($formData['assign_to']) ?>" class="badge bg-primary"><?= htmlspecialchars($formData['assign_to']) ?></a>
            <?php if($formData['assign_to'] != LOGGED_IN_IDIR): ?>
            <button class="btn btn-sm btn-success" id="claim-button" data-changeid="<?= $changeid ?>" data-courseid="<?= $courseid ?>">Claim</button>
            <?php endif ?>
        </div>
        <div class="col">
            <strong>Progress:</strong>
            <span id="progress-badge" class="badge bg-primary">
                <?= htmlspecialchars($formData['progress'] ?? 'Draft') ?>
            </span>
            <button class="btn btn-sm btn-success" id="progress-button" data-changeid="<?= $changeid ?>" data-courseid="<?= $courseid ?>" data-progress="<?= htmlspecialchars($formData['progress'] ?? 'Draft') ?>">
                Mark as In Progress
            </button>
        </div>
        </div>
        <div class="my-1 p-3 bg-dark-subtle rounded-3">
            <?= $Parsedown->text(htmlspecialchars($formData['description'] ?? 'N/A', ENT_QUOTES, 'UTF-8')) ?>
        </div>
        <?php if ($formData['crm_ticket_reference']): ?>
        <div class="mb-2">
            <strong><a href="https://rightnow.gov.bc.ca" target="_blank" rel="noopener">CRM Ticket</a> #:</strong> 
            <?= htmlspecialchars($formData['crm_ticket_reference'] ?? 'N/A') ?>
        </div>
        <?php endif; ?>

        <?php $mailtoLink = generateMailtoLink($formData, $courseid, $changeid, $course_deets, $email_addresses); ?>
        <div class="mb-3"><a href="<?= $mailtoLink ?>" class="mb-1 btn btn-sm btn-success">Email this request</a></div>

        <?php
        // Assuming $data['links'] contains the hyperlinks and descriptions
        if (!empty($formData['links'])): ?>
        <h4>Links</h4>
        <ul class="list-group mb-3">
            <?php foreach ($formData['links'] as $link): ?>
                <?php 
                    $url = htmlspecialchars($link['url']);
                    $description = !empty($link['description']) ? htmlspecialchars($link['description']) : $url;
                ?>
                <li class="list-group-item">
                    <a href="<?= $url ?>" target="_blank" rel="noopener noreferrer">
                        <?= $description ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <!-- Files Section -->
        <?php if (!empty($formData['files'])): ?>
        <h4>Uploaded Files</h4>
        <ul class="list-group">
            <?php foreach ($formData['files'] as $file): ?>
                <?php $shortFileName = preg_replace("/^course-[a-zA-Z0-9\-]+-change-[a-z0-9]+-/", '', $file); ?>
                <li class="list-group-item">
                    <a href="requests/files/<?= htmlspecialchars($file) ?>" target="_blank"><?= htmlspecialchars($shortFileName) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    
        </div>
        <div class="col-md-6">
            <?php require('../templates/guidance.php') ?>
        


            <h4 class="fs-5 mt-5">Comments</h4>
            <details>
                <summary class="mb-2">Add a comment</summary>
                <!-- Comments Section -->
                <form action="comment-add.php" method="post" class="mb-4">
                <!-- Hidden Fields -->
                <input type="hidden" name="courseid" value="<?= $courseid ?>">
                <input type="hidden" name="changeid" value="<?= $changeid ?>">

                <!-- Comment Field -->
                <div class="mb-2">
                    <label for="new_comment" class="form-label visually-hidden">Add Comment</label>
                    <textarea id="new_comment" name="new_comment" class="form-control bg-dark-subtle" rows="4" placeholder="Enter your comment here..." required></textarea>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary">Submit Comment</button>
                </form>
                </details>
                <?php if (!empty($formData['timeline'])): ?>
                <ul class="list-group">
                    <?php foreach ($formData['timeline'] as $event): ?>
                        <?php if ($event['field'] === 'comment'): ?>
                            <li class="list-group-item bg-dark-subtle">
                                <strong><?= htmlspecialchars($event['changed_by']) ?>:</strong> 
                                <?= $Parsedown->text(htmlspecialchars($event['new_value'])) ?>
                                <br><small class="text-muted">At: <?= date('Y-m-d H:i:s', $event['changed_at']) ?></small>
                                
                                <!-- Delete Comment Form -->
                                <form action="delete-comment.php" method="post" style="display: inline;">
                                    <input type="hidden" name="courseid" value="<?= htmlspecialchars($formData['courseid']) ?>">
                                    <input type="hidden" name="changeid" value="<?= htmlspecialchars($formData['changeid']) ?>">
                                    <input type="hidden" name="comment_id" value="<?= htmlspecialchars($event['comment_id']) ?>">
                                    <button type="submit" class="btn btn-dark-subtle btn-sm">Delete</button>
                                </form>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

        </div>
    </div>

    <div class="mt-3">
        <strong>Created:</strong> <?= date('Y-m-d H:i:s', $formData['date_created']) ?> 
        by <?= htmlspecialchars($formData['created_by'] ?? '') ?>
    </div>
    <div class="mb-3">
        <strong>Last modified:</strong> <?= date('Y-m-d H:i:s', $formData['date_modified']) ?> 
        by <?= htmlspecialchars($formData['created_by'] ?? '') ?>
    </div>

    <!-- Timeline Section -->
    <?php if (!empty($formData['timeline'])): ?>
        
    <details>
        <summary>Timeline</summary>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Field</th>
                    <th>Previous Value</th>
                    <th>New Value</th>
                    <th>Changed By</th>
                    <th>Changed At</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($formData['timeline'] as $event): ?>
                <?php if (!empty($event['field']) && $event['field'] !== 'comment'): ?>
                    <tr>
                        <td><?= htmlspecialchars($event['field'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php 
                            if ($event['field'] === 'link_updated') {
                                echo (!empty($event['previous_value']['description']) 
                                    ? htmlspecialchars($event['previous_value']['description'], ENT_QUOTES, 'UTF-8') 
                                    : 'N/A') . ' - ' . 
                                    (!empty($event['previous_value']['url']) 
                                    ? htmlspecialchars($event['previous_value']['url'], ENT_QUOTES, 'UTF-8') 
                                    : 'N/A');
                            } else {
                                echo !empty($event['previous_value'])
                                    ? htmlspecialchars($event['previous_value'], ENT_QUOTES, 'UTF-8')
                                    : 'N/A';
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            if (in_array($event['field'], ['link_added', 'link_updated'])) {
                                echo (!empty($event['new_value']['description']) 
                                    ? htmlspecialchars($event['new_value']['description'], ENT_QUOTES, 'UTF-8') 
                                    : 'N/A') . ' - ' . 
                                    (!empty($event['new_value']['url']) 
                                    ? htmlspecialchars($event['new_value']['url'], ENT_QUOTES, 'UTF-8') 
                                    : 'N/A');
                            } else {
                                echo !empty($event['new_value'])
                                    ? htmlspecialchars($event['new_value'], ENT_QUOTES, 'UTF-8')
                                    : 'N/A';
                            }
                            ?>
                        </td>
                        <td>
                            <?= !empty($event['changed_by']) 
                                ? htmlspecialchars($event['changed_by'], ENT_QUOTES, 'UTF-8') 
                                : 'N/A' ?>
                        </td>
                        <td>
                            <?= !empty($event['changed_at']) 
                                ? date('Y-m-d H:i:s', $event['changed_at']) 
                                : 'N/A' ?>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
        </table>
    </details>

    <?php endif; ?>
</div>
<script src="/lsapp/js/confetti.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    document.getElementById('toggle-scopeguide').addEventListener('click', function (event) {
        event.preventDefault(); // Prevent default behavior of the <a> tag
        const detailsElement = document.getElementById('scopeguide');
        if (detailsElement) {
            detailsElement.open = !detailsElement.open; // Toggle the `open` attribute
        }
    });

    // Progress change stuff
    const progressButton = document.getElementById('progress-button');
    const progressBadge = document.getElementById('progress-badge');

    if (!progressButton || !progressBadge) return;

    // Define the progress progression
    const progressMap = {
        'Not Started': 'In Progress',
        'In Progress': 'In Review',
        'In Review': 'Ready to Publish',
        'Ready to Publish': 'Closed',
        'Closed': 'Closed'
    };

    function updateUI(newProgress, triggerConfetti = false) {
        // Update the button text
        if (newProgress in progressMap) {
            if (newProgress === 'Closed') {
                progressButton.remove(); // Remove the button from the DOM
                // Only trigger confetti if this was a user action and prefers-reduced-motion is not set
                if (triggerConfetti && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    launchConfetti();
                }
            } else {
                progressButton.textContent = progressMap[newProgress];
            }
        }

        // Update the badge
        progressBadge.textContent = newProgress;
        progressBadge.className = 'badge ' + getProgressBadgeClass(newProgress);
    }

    function getProgressBadgeClass(progress) {
        switch (progress) {
            case 'Not Started': return 'bg-secondary';
            case 'In Progress': return 'bg-warning';
            case 'In Review': return 'bg-warning';
            case 'Ready to Publish': return 'bg-warning';
            case 'Closed': return 'bg-success';
            default: return 'bg-primary';
        }
    }

    function launchConfetti() {
        var defaults = {
            spread: 360,
            ticks: 50,
            gravity: 0,
            decay: 0.94,
            startVelocity: 30,
            colors: ['FFE400', 'FFBD00', 'E89400', 'FFCA6C', 'FDFFB8']
        };

        function shoot() {
            confetti({
                ...defaults,
                particleCount: 40,
                scalar: 1.2,
                shapes: ['star']
            });

            confetti({
                ...defaults,
                particleCount: 10,
                scalar: 0.75,
                shapes: ['circle']
            });
        }

        setTimeout(shoot, 0);
        setTimeout(shoot, 100);
        setTimeout(shoot, 200);
        setTimeout(shoot, 500);
        setTimeout(shoot, 1000);
    }

    // Get initial progress from data attribute
    let currentProgress = progressButton.getAttribute('data-progress');

    // Update UI **without triggering confetti on page load**
    updateUI(currentProgress, false);

    progressButton.addEventListener('click', function() {
        const changeid = this.getAttribute('data-changeid');
        const courseid = this.getAttribute('data-courseid');

        if (!changeid || !courseid) {
            alert('Invalid request data.');
            return;
        }

        fetch('update-progress.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `changeid=${changeid}&courseid=${courseid}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.progress === 'success') {
                const wasClosed = currentProgress === "Closed"; // Check if it was already Closed
                currentProgress = data.new_progress;
                updateUI(currentProgress, !wasClosed); // Only trigger confetti if it wasn't already Closed
            } else {
                alert(`Error: ${data.message}`);
            }
        })
        .catch(error => alert('Failed to update progress. Please try again.'));
    });

    const claimButton = document.getElementById('claim-button');

    if (claimButton) {
        claimButton.addEventListener('click', function() {
            const changeid = this.getAttribute('data-changeid');
            const courseid = this.getAttribute('data-courseid');

            if (!changeid || !courseid) {
                alert('Invalid request data.');
                return;
            }

            fetch('update-claim.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `changeid=${changeid}&courseid=${courseid}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // alert('Request claimed successfully!');
                    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                        launchConfetti();
                    }
                    location.reload(); // Refresh page to reflect change
                } else {
                    alert(`Error: ${data.message}`);
                }
            })
            .catch(error => alert('Failed to claim request. Please try again.'));
        });
    }
});
</script>
<?php endif; ?>

<?php require('../templates/javascript.php'); ?>
<?php require('../templates/footer.php'); ?>