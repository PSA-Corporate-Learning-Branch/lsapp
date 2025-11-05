<?php
opcache_reset();
$path = '../inc/lsapp.php';
require($path);
require('../inc/ches_client.php');
require('../inc/Parsedown.php');
$Parsedown = new Parsedown();
$Parsedown->setSafeMode(true);

// Get parameters from the URL
$courseid = isset($_GET['courseid']) ? htmlspecialchars($_GET['courseid']) : null;
$changeid = isset($_GET['changeid']) ? htmlspecialchars($_GET['changeid']) : null;

if (!$courseid || !$changeid) {
    echo '<div class="alert alert-danger">Error: Course ID and Change ID are required.</div>';
    exit;
}

// Get course details
$course_deets = getCourse($courseid);
$course_steward = getPerson($course_deets[10]);
$course_developer = getPerson($course_deets[34]);

// Get request details
$filePath = "requests/course-$courseid-change-$changeid.json";
if (!file_exists($filePath)) {
    echo '<div class="alert alert-danger">Error: Change request not found.</div>';
    exit;
}
$formData = json_decode(file_get_contents($filePath), true);

// Get requester details
$requester = getPerson($formData['created_by']);

// Get assigned person details
$assigned_person = null;
if (!empty($formData['assign_to'])) {
    $assigned_person = getPerson($formData['assign_to']);
}


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $recipients = isset($_POST['recipients']) ? $_POST['recipients'] : array();
        $additionalEmails = isset($_POST['additional_emails']) ? $_POST['additional_emails'] : '';

        // Process additional emails
        if (!empty($additionalEmails)) {
            $additionalEmailsArray = array_map('trim', explode(',', $additionalEmails));
            foreach ($additionalEmailsArray as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $recipients[] = $email;
                }
            }
        }

        // Remove duplicates
        $recipients = array_unique($recipients);

        if (empty($recipients)) {
            throw new Exception("At least one recipient is required.");
        }

        // Generate email content (similar to generateMailtoLink function)
        $subject = '';
        if($formData['urgent']) {
            $subject .= '[URGENT] ';
        }
        $subject .= '[Course Change] ' . $course_deets[2] . ' - ' . htmlspecialchars($formData['category'] ?? 'N/A') . ' request';

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
        $body .= "\n\nDescription:\n" . htmlspecialchars($formData['description'] ?? 'N/A') . "\n";

        // Separate primary recipients (associated people) from additional recipients
        $primaryRecipients = [];
        $ccRecipients = [];

        // Get emails of associated people for primary "to" field
        if ($course_steward && !empty($course_steward[3]) && in_array($course_steward[3], $recipients)) {
            $primaryRecipients[] = $course_steward[3];
        }
        if ($course_developer && !empty($course_developer[3]) &&
            (!$course_steward || $course_developer[0] !== $course_steward[0]) &&
            in_array($course_developer[3], $recipients)) {
            $primaryRecipients[] = $course_developer[3];
        }
        if ($assigned_person && !empty($assigned_person[3]) &&
            (!$course_steward || $assigned_person[0] !== $course_steward[0]) &&
            (!$course_developer || $assigned_person[0] !== $course_developer[0]) &&
            in_array($assigned_person[3], $recipients)) {
            $primaryRecipients[] = $assigned_person[3];
        }

        // All other recipients go to CC
        foreach ($recipients as $recipient) {
            if (!in_array($recipient, $primaryRecipients)) {
                $ccRecipients[] = $recipient;
            }
        }

        // If no primary recipients selected, move first CC to primary
        if (empty($primaryRecipients) && !empty($ccRecipients)) {
            $primaryRecipients[] = array_shift($ccRecipients);
        }

        // Send emails using CHES
        $ches = new CHESClient();

        // Send single email with all recipients visible
        try {
            $result = $ches->sendEmail(
                $primaryRecipients,
                $subject,
                $body,
                null, // No HTML body
                "donotreply_lsapp@gov.bc.ca",
                empty($ccRecipients) ? null : $ccRecipients, // CC recipients
                null  // No BCC
            );

            $totalRecipients = count($primaryRecipients) + count($ccRecipients);

            // Log notification in the request timeline
            $recipientList = implode(', ', array_merge($primaryRecipients, $ccRecipients));
            $formData['timeline'][] = [
                'field' => 'notification_sent',
                'new_value' => "Email notification sent to: $recipientList",
                'changed_by' => LOGGED_IN_IDIR,
                'changed_at' => time(),
            ];

            // Save the updated request data
            file_put_contents($filePath, json_encode($formData, JSON_PRETTY_PRINT));

            // Redirect to view.php with success message
            $message = urlencode("Notification sent successfully to $totalRecipients recipient(s)");
            header("Location: view.php?courseid={$courseid}&changeid={$changeid}&message={$message}");
            exit;

        } catch (Exception $e) {
            $error = "Failed to send notification: " . $e->getMessage();
            error_log("Failed to send notification email: " . $e->getMessage());
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// get all of the people
$allPeopleUnfiltered = getPeopleAll();
// filter to only active individuals
$allPeopleFiltered = array_filter($allPeopleUnfiltered, function($person) {
    return strtolower($person[4]) === 'active';
});
// map a subset of values to a new array of associative arrays
//
// both array_filter and array_map preserve the original keys which was causing
// json_encode to convert our people array to an object, so we're using array_values
// to re-index the array so it's properly converted to a javascript array
// that can be filtered
$allPeople = array_values(array_map(function($person) {
    return [
        'idir' => $person[0],
        'name' => $person[2],
        'email' => $person[3],
        'title' => isset($person[6]) ? $person[6] : ''
    ];
}, $allPeopleFiltered));
?>

<?php if(canAccess()): ?>

<?php getHeader() ?>

<title>Send Notifications - <?= $course_deets[2] ?></title>

<?php getScripts() ?>
<style>
.person-item {
    padding: 8px;
    margin-bottom: 4px;

    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.person-item input[type="checkbox"] {
    margin-right: 10px;
}
.person-info {
    flex-grow: 1;
}
.person-search {
    position: relative;
}
.search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    border-top: none;
    max-height: 300px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
}
.search-results.show {
    display: block;
}
.search-result-item {
    padding: 10px;
    cursor: pointer;

}
.search-result-item:hover {

}
.selected-additional {
    margin-top: 10px;
}
.selected-person {
    display: inline-block;
    padding: 5px 10px;
    margin: 5px;

    border-radius: 20px;
}
.remove-person {
    margin-left: 5px;
    cursor: pointer;
    color: #dc3545;
}
</style>
<body>
<?php getNavigation() ?>

<div class="container">
    <div class="row justify-content-md-center">
        <div class="col-md-10">
            <h1><a href="/lsapp/course.php?courseid=<?= $course_deets[0] ?>"><?= $course_deets[2] ?></a></h1>
            <h2>Send Notifications for <?= $formData['category'] ?> Request</h2>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="card mb-3">
                    <div class="card-header">
                        <h4>Select Recipients</h4>
                    </div>
                    <div class="card-body">
                        <p>Select people to notify about this course change request. All selected recipients will be visible to each other.</p>
                        <div class="alert alert-info">
                            <strong>Note:</strong> Primary recipients (course steward, developer, requester, and assigned person) will be in the "To" field. Additional recipients will be CC'd. Everyone will see who else received the notification.
                        </div>

                        <div class="mb-4">
                            <h5>Primary Recipients (To:)</h5>

                            <?php 
                                // Here we want to review the possible primary recipients and put them into an associative array
                                // as we go we can check if that person already exists and add to their existing roles if they do
                                // looking at the length of this array will help us determine whether we want checkboxes below
                                $primary_people = array();
                                
                                // Steward
                                if ($course_steward && !empty($course_steward[3])) {
                                    $primary_people[$course_steward[0]] = ['name' => $course_steward[2], 'email' => $course_steward[3], 'roles' => 'Course Steward'];
                                }
                                // Developer
                                if ($course_developer && !empty($course_developer[3])) {
                                    if (isset($primary_people[$course_developer[0]])) {
                                        $primary_people[$course_developer[0]]['roles'] .= ', Developer';
                                    }
                                    else {
                                        $primary_people[$course_developer[0]] = ['name' => $course_developer[2], 'email' => $course_developer[3], 'roles' => 'Developer'];
                                    }
                                }
                                // Assigned to
                                if ($assigned_person && !empty($assigned_person[3])) {
                                    if (isset($primary_people[$assigned_person[0]])) {
                                        $primary_people[$assigned_person[0]]['roles'] .= ', Assigned To';
                                    }
                                    else {
                                        $primary_people[$assigned_person[0]] = ['name' => $assigned_person[2], 'email' => $assigned_person[3], 'roles' => 'Assigned To'];
                                    }
                                }
                                // Requester
                                if (!empty($requester[3])) {
                                    if (isset($primary_people[$requester[0]])) {
                                        $primary_people[$requester[0]]['roles'] .= ', Requested By';
                                    }
                                    else {
                                        $primary_people[$requester[0]] = ['name' => $requester[2], 'email' => $requester[3], 'roles' => 'Requested By'];
                                    }
                                }
                            ?>
                            
                            <!-- We only want to make our primary recipients checkable if there is more than one -->
                            <?php if (count($primary_people) > 1): ?>
                            <?php foreach($primary_people as $idir => $person): ?>
                                <div class="person-item bg-dark-subtle">
                                    <div class="form-check d-flex align-items-center">
                                        <input class="form-check-input me-2" type="checkbox" name="recipients[]" id="<?= $idir ?>" value="<?= htmlspecialchars($person['email']) ?>" checked>    
                                        <label class="form-check-label" for="<?= $idir ?>">
                                            <div class="person-info">
                                                <strong><?= htmlspecialchars($person['name']) ?></strong> (<?= $person['roles'] ?>)
                                                <br><small><?= htmlspecialchars($person['email']) ?></small>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <!-- If we only have one recipient, they can't be unchecked so make it a radio -->
                            <?php elseif (count($primary_people) === 1): ?>
                                <?php $person = $primary_people[array_key_first($primary_people)]; ?>
                                <div class="person-item bg-dark-subtle">
                                    <div class="form-check d-flex align-items-center">
                                        <input class="form-check-input me-2" type="radio" name="recipients[]" id="<?= array_key_first($primary_people) ?>" title="Must have at least one primary recipient" value="<?= htmlspecialchars($person['email']) ?>" checked>
                                        <label class="form-check-label" for="<?= array_key_first($primary_people) ?>">
                                            <div class="person-info">
                                                <strong><?= htmlspecialchars($person['name']) ?></strong> (<?= $person['roles'] ?>)
                                                <br><small><?= htmlspecialchars($person['email']) ?></small>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- No course steward alert -->
                            <?php if (empty($course_steward)): ?>
                                <div class="alert alert-warning my-1"><strong>Missing Course Steward/Owner</strong>: This course does not currently have an assigned steward/owner.</div>
                            <?php endif; ?>

                            <!-- No course developer alert -->
                            <?php if (empty($course_developer)): ?>
                                <div class="alert alert-warning my-1"><strong>Missing Course Developer</strong>: This course does not currently have an assigned developer.</div>
                            <?php endif; ?>
                        
                        </div>

                        <div class="mb-4">
                            <h5>Additional Recipients (CC:)</h5>
                            <div class="person-search">
                                <input type="text"
                                       id="personSearch"
                                       class="form-control"
                                       placeholder="Search for people by name or email...">
                                <div id="searchResults" class="search-results"></div>
                            </div>
                            <div id="selectedAdditional" class="selected-additional"></div>
                            <input type="hidden" id="additionalEmails" name="additional_emails" value="">
                        </div>

                        <div class="mb-4">
                            <h5>Or Enter Email Addresses</h5>
                            <input type="text"
                                   class="form-control"
                                   id="manualEmails"
                                   placeholder="Enter email addresses separated by commas">
                            <small class="form-text text-muted">You can enter multiple email addresses separated by commas</small>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-between">
                    <a href="view.php?courseid=<?= $courseid ?>&changeid=<?= $changeid ?>"
                       class="btn btn-secondary">Skip Notifications</a>
                    <button type="submit" class="btn btn-primary">Send Notifications</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// People data for search
const allPeople = <?= json_encode($allPeople) ?>;
const selectedAdditionalPeople = new Map();

// Search functionality
const searchInput = document.getElementById('personSearch');
const searchResults = document.getElementById('searchResults');
const selectedAdditionalDiv = document.getElementById('selectedAdditional');
const additionalEmailsInput = document.getElementById('additionalEmails');
const manualEmailsInput = document.getElementById('manualEmails');

searchInput.addEventListener('input', function() {
    const query = this.value.toLowerCase().trim();

    if (query.length < 2) {
        searchResults.classList.remove('show');
        return;
    }

    const matches = allPeople.filter(person =>
        person.name.toLowerCase().includes(query) ||
        person.email.toLowerCase().includes(query) ||
        (person.title && person.title.toLowerCase().includes(query))
    );

    searchResults.innerHTML = '';

    if (matches.length > 0) {
        matches.forEach(person => {
            if (!selectedAdditionalPeople.has(person.email)) {
                const div = document.createElement('div');
                div.className = 'search-result-item bg-dark-subtle';
                div.innerHTML = `
                    <strong>${person.name}</strong><br>
                    <small>${person.email}</small>
                    ${person.title ? `<br><small class="text-muted">${person.title}</small>` : ''}
                `;
                div.addEventListener('click', function() {
                    addPerson(person);
                    searchInput.value = '';
                    searchResults.classList.remove('show');
                });
                searchResults.appendChild(div);
            }
        });
        searchResults.classList.add('show');
    } else {
        searchResults.innerHTML = '<div class="search-result-item bg-dark-subtle">No matches found</div>';
        searchResults.classList.add('show');
    }
});

// Close search results when clicking outside
document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
        searchResults.classList.remove('show');
    }
});

function addPerson(person) {
    if (!selectedAdditionalPeople.has(person.email)) {
        selectedAdditionalPeople.set(person.email, person);
        updateSelectedDisplay();
    }
}

function removePerson(email) {
    selectedAdditionalPeople.delete(email);
    updateSelectedDisplay();
}

function updateSelectedDisplay() {
    selectedAdditionalDiv.innerHTML = '';

    selectedAdditionalPeople.forEach((person, email) => {
        const span = document.createElement('span');
        span.className = 'selected-person bg-dark-subtle';
        span.innerHTML = `
            ${person.name}
            <span class="remove-person" onclick="removePerson('${email}')">×</span>
        `;
        selectedAdditionalDiv.appendChild(span);
    });

    // Update hidden input with emails
    const emails = Array.from(selectedAdditionalPeople.keys());
    additionalEmailsInput.value = emails.join(',');
}

// Handle manual email input on form submission
document.querySelector('form').addEventListener('submit', function(e) {
    const manualEmails = manualEmailsInput.value.trim();
    if (manualEmails) {
        const currentEmails = additionalEmailsInput.value;
        if (currentEmails) {
            additionalEmailsInput.value = currentEmails + ',' + manualEmails;
        } else {
            additionalEmailsInput.value = manualEmails;
        }
    }
});
</script>

<?php endif ?>

<?php require('../templates/javascript.php') ?>
<?php require('../templates/footer.php') ?>