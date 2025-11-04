<?php 
opcache_reset();
$path = '../inc/lsapp.php';
require($path); 
require('../inc/Parsedown.php');
$Parsedown = new Parsedown();
 // Get parameters from the URL
 $courseid = isset($_GET['courseid']) ? htmlspecialchars($_GET['courseid']) : null;
 $changeid = isset($_GET['changeid']) ? htmlspecialchars($_GET['changeid']) : null;

 if (!$courseid) {
     echo '<div class="alert alert-danger">Error: Course ID is required.</div>';
     exit;
 }
 $deets = getCourse($courseid);
    // Prefill data if updating an existing change
    $formData = [
    'assign_to' => '',
    'crm_ticket_reference' => '',
    'category' => '',
    'description' => '',
    'scope' => '',
    'approval_status' => '',
    'urgent' => false,
    'comments' => '',
    'status' => ''
];

if ($changeid) {
    $filePath = "requests/course-$courseid-change-$changeid.json";
    if (file_exists($filePath)) {
        $formData = json_decode(file_get_contents($filePath), true);
    } else {
        echo '<div class="alert alert-warning">Warning: Change ID not found. Starting a new form.</div>';
        echo $filePath;
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

// Example usage
$cat = isset($_GET['cat']) ? urldecode($_GET['cat']) : '';
$categoriesFile = '../data/course-change-guidance.json';
$guidance = getGuidanceByCategory($cat, $categoriesFile);
$peopleActive = getPeopleAll($filteractive = true);

?>

<?php if(canACcess()): ?>

<?php getHeader() ?>

<title><?= $deets[2] ?> Change Request</title>

<?php getScripts() ?>
<style>
.scopeinfo {
    all: unset; /* Removes default button styles */
    cursor: pointer; /* Ensures it behaves like a link */
    display: inline-flex; /* Keeps icon alignment */
    align-items: center;
}
/* Preserve default focus outline */
.scopeinfo:focus {
    outline: revert;
}
</style>
<body>
<?php getNavigation() ?>

<div class="container">
    <div class="row justify-content-md-center">
        <div class="col-md-12">
            <h1 class=""><a href="/lsapp/course.php?courseid=<?= $deets[0] ?>"><?= $deets[2] ?></a></h1>
            <h2><?= $cat ?> Request <small><?= $formData['changeid'] ?? '' ?></small></h2>
            <?php if(!empty($formData['date_created'])): ?>
            <div>
                Created <?php echo date('Y-m-d H:i:s', $formData['date_created']); ?> 
                by <?= $formData['created_by'] ?? '' ?>
            </div>
            <?php endif ?>
        </div>
    </div>
    
    <div class="row justify-content-md-center">
    <div class="col-md-6">
        
        <?php if (empty($cat)): ?>
            <?php
            $categories = [];
            if (file_exists($categoriesFile)) {
                $json = json_decode(file_get_contents($categoriesFile), true);
                if (is_array($json)) {
                    $categories = array_column($json, 'category');
                }
            }
            ?>
            <form method="get" class="mb-3">
                <input type="hidden" name="courseid" value="<?= htmlspecialchars($courseid) ?>">
                <div class="alert alert-warning"><strong>Please select a category before proceeding.</strong></div>
                <label for="category-selector" class="form-label">Category</label>
                <select id="category-selector" name="cat" class="form-select" required>
                    <option value="" selected>-- Choose a category --</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary mt-2" id="category-submit" disabled>Continue</button>
            </form>
        <?php endif; ?>

        <div id="form-wrapper" style="opacity: <?= empty($cat) ? '0.5' : '1' ?>">
        <form action="controller.php" method="post" enctype="multipart/form-data" class="needs-validation mt-3" novalidate>

            <!-- Hidden Fields -->
            <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
            <input type="hidden" name="changeid" value="<?php echo $changeid; ?>">
            <input type="hidden" name="category" value="<?php echo urlencode($cat); ?>">
   
            <div class="row">
            
            <div class="col">
                <!-- Scope -->
                <label for="scope" class="form-label visually-hidden">Scope</label>
                <div class="d-flex align-items-center">
                    <select id="scope" name="scope" class="form-select me-2" required>
                        <option value="" disabled>Choose a scope</option>
                        <option value="Minor">Minor Change (1-2 hours)</option>
                        <option value="Moderate">Moderate Change (2-24 hours)</option>
                        <option value="Major">Major Change (&gt;24 hours)</option>
                        <option value="Unknown">Unknown (To be decided)</option>
                    </select>
                    <button aria-label="More information about scope" class="scopeinfo" role="button" id="toggle-scopeguide"><span class="icon-svg baseline-svg"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free 6.5.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.--><path fill="#999" d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM216 336h24V272H216c-13.3 0-24-10.7-24-24s10.7-24 24-24h48c13.3 0 24 10.7 24 24v88h8c13.3 0 24 10.7 24 24s-10.7 24-24 24H216c-13.3 0-24-10.7-24-24s10.7-24 24-24zm40-208a32 32 0 1 1 0 64 32 32 0 1 1 0-64z"></path></svg></span></button>
                </div>
                <div class="invalid-feedback">Please select the scope of the request.</div>
                <script>
                document.getElementById('toggle-scopeguide').addEventListener('click', function (event) {
                    event.preventDefault(); // Prevent default behavior of the <a> tag
                    const detailsElement = document.getElementById('scopeguide');
                    if (detailsElement) {
                        detailsElement.open = !detailsElement.open; // Toggle the `open` attribute
                    }
                });
                </script>
            </div>
            <div class="col align-self-center">
            <div class="form-check">
                <input type="checkbox" id="urgent" name="urgent" class="form-check-input" value="yes" <?php echo $formData['urgent'] ? 'checked' : ''; ?>>
                <label for="urgent" class="form-check-label">Urgent?</label>
            </div>
            </div>


            <!-- Description -->
            <div class="my-3">

                <div id="toolbar" class="btn-group">
                    <!-- <button class="btn btn-sm bg-light-subtle" type="button" onclick="applyMarkdown('###', '')">Header</button> -->
                    <button class="btn btn-sm bg-light-subtle" type="button" onclick="applyMarkdown('**', '**')">Bold</button>
                    <button class="btn btn-sm bg-light-subtle" type="button" onclick="applyLink()">Link</button>
                    <button class="btn btn-sm bg-light-subtle" type="button" onclick="applyList('unordered')">Unordered List</button>
                    <button class="btn btn-sm bg-light-subtle" type="button" onclick="applyList('ordered')">Ordered List</button>
                </div>
                <textarea id="description" name="description" class="form-control" rows="4" required placeholder="Detailed description" <?= empty($cat) ? 'disabled' : '' ?>><?php echo $formData['description']; ?></textarea>
                <div class="invalid-feedback">Please provide a description of the request.</div>
                <script>
                    function applyMarkdown(before, after) {
                    const textarea = document.getElementById("description");
                    const start = textarea.selectionStart;
                    const end = textarea.selectionEnd;
                    const selectedText = textarea.value.slice(start, end);

                    // Apply Markdown symbols
                    const newText = before + selectedText + after;
                    textarea.setRangeText(newText);

                    // Re-select the newly formatted text
                    textarea.setSelectionRange(start + before.length, end + before.length);
                    textarea.focus();
                    }

                    function applyLink() {
                    const textarea = document.getElementById("description");
                    const start = textarea.selectionStart;
                    const end = textarea.selectionEnd;
                    const selectedText = textarea.value.slice(start, end);

                    // Default link format
                    const newText = `[${selectedText || 'text'}](http://example.com)`;
                    textarea.setRangeText(newText);

                    // Re-select the text to allow easy editing of the link
                    if (!selectedText) {
                        textarea.setSelectionRange(start + 1, start + 5); // Select 'text'
                    } else {
                        textarea.setSelectionRange(start + newText.length - 19, start + newText.length - 1); // Select 'http://example.com'
                    }
                    textarea.focus();
                    }

                    function applyList(type) {
                    const textarea = document.getElementById("description");
                    const start = textarea.selectionStart;
                    const end = textarea.selectionEnd;
                    const selectedText = textarea.value.slice(start, end);

                    // Split selected text by lines
                    const lines = selectedText.split('\n');
                    
                    // Prefix each line with list markdown symbols
                    const newLines = lines.map((line, index) => {
                        if (type === 'unordered') {
                        return `- ${line}`;
                        } else if (type === 'ordered') {
                        return `${index + 1}. ${line}`;
                        }
                        return line;
                    });
                    
                    const newText = newLines.join('\n');
                    textarea.setRangeText(newText);

                    // Re-select the newly formatted text
                    textarea.setSelectionRange(start, start + newText.length);
                    textarea.focus();
                    }
                    </script>
            </div>
            <!-- Approval Status -->
            <div class="col-md-6">
                <label for="approval_status" class="form-label">Approval Status</label>
                <div class="d-flex align-items-center gap-2">
                    <select id="approval_status" name="approval_status" class="form-select" required>
                        <option value="" selected disabled>Please select&hellip;</option>
                        <option value="Pending">Pending Approval</option>
                        <option value="Denied">Denied</option>
                        <option value="On Hold">On Hold</option>
                        <option value="Approved">Approved</option>
                    </select>
                    <a aria-label="More information about approval status" class="approvalinfo" role="button" id="toggle-approvalguide">
                        <span class="icon-svg baseline-svg">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free 6.5.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.-->
                                <path fill="#999" d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM216 336h24V272H216c-13.3 0-24-10.7-24-24s10.7-24 24-24h48c13.3 0 24 10.7 24 24v88h8c13.3 0 24 10.7 24 24s-10.7 24-24 24H216c-13.3 0-24-10.7-24-24s10.7-24 24-24zm40-208a32 32 0 1 1 0 64 32 32 0 1 1 0-64z"></path>
                            </svg>
                        </span>
                    </a>
                </div>
                <script>
                document.getElementById('toggle-approvalguide').addEventListener('click', function (event) {
                    event.preventDefault(); // Prevent default behavior of the <a> tag
                    const detailsElement = document.getElementById('approvalguide');
                    if (detailsElement) {
                        detailsElement.open = !detailsElement.open; // Toggle the `open` attribute
                    }
                });
                </script>
            </div>
            <!-- Status -->
            <div class="col">
                <label for="progress" class="form-label">Progress</label>
                <div class="d-flex align-items-center gap-2">
                    <select id="progress" name="progress" class="form-select" required>
                        <option value="" selected disabled>Please select&hellip;</option>
                        <option value="Not Started">Not Started</option>
                        <option value="In Progress">In Progress</option>
                        <option value="In Review">In Review</option>
                        <option value="Ready to Publish">Ready to Publish</option>
                        <option value="Closed">Closed</option>
                    </select>
                    <a aria-label="More information about scope" class="progressinfo" role="button" id="toggle-progressguide">
                        <span class="icon-svg baseline-svg">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free 6.5.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.-->
                                <path fill="#999" d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM216 336h24V272H216c-13.3 0-24-10.7-24-24s10.7-24 24-24h48c13.3 0 24 10.7 24 24v88h8c13.3 0 24 10.7 24 24s-10.7 24-24 24H216c-13.3 0-24-10.7-24-24s10.7-24 24-24zm40-208a32 32 0 1 1 0 64 32 32 0 1 1 0-64z"></path>
                            </svg>
                        </span>
                    </a>
                </div>
                <script>
                document.getElementById('toggle-progressguide').addEventListener('click', function (event) {
                    event.preventDefault(); // Prevent default behavior of the <a> tag
                    const detailsElement = document.getElementById('progressguide');
                    if (detailsElement) {
                        detailsElement.open = !detailsElement.open; // Toggle the `open` attribute
                    }
                });
                </script>
            </div>

            
            </div>

            <div class="row my-3">
            <div class="col">
                <label for="assign_to" class="form-label">Assigned To</label>
                <!-- <input type="text" id="assign_to" name="assign_to" class="form-control" value="<?php echo $formData['assign_to']; ?>" required> -->
                <div class="invalid-feedback">Please provide the assignee.</div>
                <input list="people" 
                        name="assign_to" 
                        id="assign_to" 
                        class="form-control" 
                        placeholder="Select a person"
                        value="<?php echo htmlspecialchars($formData['assign_to'] ?? ''); ?>"
                >
                <datalist id="people">
                    <?php
                    // Generate the datalist options
                    foreach ($peopleActive as $person) {
                        // Ensure the array has at least 3 elements for IDIR and Name
                        if (!empty($person[0]) && !empty($person[2])) {
                            $value = htmlspecialchars($person[0]); // Use IDIR (index 0) as the value
                            $label = htmlspecialchars($person[2]); // Use Name (index 2) as the displayed label
                            echo "<option value=\"{$value}\" label=\"{$label}\"></option>";
                        }
                    }
                    ?>
                </datalist>
                <?php if(!empty($formData['last_assigned_at'])): ?>
                Assigned on <?php echo date('Y-m-d H:i:s', $formData['last_assigned_at']); ?>
                <?php endif ?>
            </div>
            <!-- CRM Ticket Reference -->
            <div class="col">
                <label for="crm_ticket_reference" class="form-label">CRM Ticket #</label>
                <input type="text" id="crm_ticket_reference" name="crm_ticket_reference" class="form-control" value="<?php echo $formData['crm_ticket_reference']; ?>">
            </div>
            </div>


            
            <!-- Add New Files -->
            <div class="mb-3">
                <label for="uploaded_files" class="form-label">Upload Files</label>
                <input type="file" id="uploaded_files" name="uploaded_files[]" class="form-control" multiple>
                <small class="text-muted">You can upload multiple files. Max size: 20MB each.</small>
            </div>

            <!-- Add Hyperlinks with Descriptions -->
            <div class="mb-3" id="hyperlinks-section">
                <label for="hyperlink_1" class="form-label">Hyperlinks</label>
                <div class="input-group mb-2" id="hyperlink-group-1">
                    <input type="url" id="hyperlink_1" name="hyperlinks[]" class="form-control" placeholder="Enter hyperlink (e.g., https://example.com)">
                    <input type="text" id="description_1" name="descriptions[]" class="form-control" placeholder="Enter title (optional)">
                    <button type="button" class="btn btn-success" onclick="addHyperlinkField()" title="Add another hyperlink">+</button>
                </div>
                <small class="text-muted">Add one or more hyperlinks with optional descriptions. Click "+" to add additional fields.</small>
            </div>

            <script>
                let hyperlinkCount = 1; // Counter for hyperlink fields

                function addHyperlinkField() {
                    hyperlinkCount++;
                    const section = document.getElementById("hyperlinks-section");

                    // Create a new input group
                    const newInputGroup = document.createElement("div");
                    newInputGroup.className = "input-group mb-2";
                    newInputGroup.id = `hyperlink-group-${hyperlinkCount}`;

                    // Create the hyperlink input field
                    const newHyperlinkInput = document.createElement("input");
                    newHyperlinkInput.type = "url";
                    newHyperlinkInput.id = `hyperlink_${hyperlinkCount}`;
                    newHyperlinkInput.name = "hyperlinks[]";
                    newHyperlinkInput.className = "form-control";
                    newHyperlinkInput.placeholder = "Enter hyperlink (e.g., https://example.com)";

                    // Create the description input field
                    const newDescriptionInput = document.createElement("input");
                    newDescriptionInput.type = "text";
                    newDescriptionInput.id = `description_${hyperlinkCount}`;
                    newDescriptionInput.name = "descriptions[]";
                    newDescriptionInput.className = "form-control";
                    newDescriptionInput.placeholder = "Enter description (optional)";

                    // Create the remove button
                    const removeButton = document.createElement("button");
                    removeButton.type = "button";
                    removeButton.className = "btn btn-danger";
                    removeButton.title = "Remove this hyperlink";
                    removeButton.innerHTML = "−";
                    removeButton.onclick = function () {
                        section.removeChild(newInputGroup);
                    };

                    // Append the input fields and button to the input group
                    newInputGroup.appendChild(newHyperlinkInput);
                    newInputGroup.appendChild(newDescriptionInput);
                    newInputGroup.appendChild(removeButton);

                    // Append the input group to the hyperlinks section
                    section.appendChild(newInputGroup);
                }
            </script>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary w-100"><?php echo $changeid ? 'Update' : 'Submit'; ?></button>
        
        </form>
        </div>

</div>
<div class="col-md-6">
    
<?php require('../templates/guidance.php') ?>

</div>
</div>

    


    <!-- Form Validation -->
    <script>
        (function () {
            'use strict';
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</div>
</div>
</div>

<?php endif ?>

<?php require('../templates/javascript.php') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selector = document.getElementById('category-selector');
    const submitButton = document.getElementById('category-submit');

    if (selector && submitButton) {
        // Set initial state based on current selection
        submitButton.disabled = !selector.value || selector.value === "";

        selector.addEventListener('change', function () {
            submitButton.disabled = !this.value;
        });
    }
});
</script>
<?php require('../templates/footer.php') ?>