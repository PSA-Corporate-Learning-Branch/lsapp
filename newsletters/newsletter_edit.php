<?php
/**
 * Newsletter Configuration Editor
 * Add or edit newsletter configurations
 */
require('../inc/lsapp.php');
require_once('../inc/encryption_helper.php');

// Check if user is admin
if (!isAdmin()) {
    header('Location: index.php');
    exit();
}

// Database connection
try {
    $db = new PDO("sqlite:../data/subscriptions.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$newsletter = null;
$newsletterId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Load existing newsletter if editing
if ($newsletterId) {
    $stmt = $db->prepare("SELECT * FROM newsletters WHERE id = ?");
    $stmt->execute([$newsletterId]);
    $newsletter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$newsletter) {
        header('Location: index.php');
        exit();
    }
    
    // Decrypt the API password for display
    if (!empty($newsletter['api_password'])) {
        try {
            $newsletter['api_password_decrypted'] = EncryptionHelper::decrypt($newsletter['api_password']);
        } catch (Exception $e) {
            // If decryption fails, it might be plaintext (backward compatibility)
            $newsletter['api_password_decrypted'] = $newsletter['api_password'];
        }
    }
}

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $formId = !empty($_POST['form_id']) ? trim($_POST['form_id']) : null;
        $apiUsername = !empty($_POST['api_username']) ? trim($_POST['api_username']) : null;
        $apiPassword = !empty($_POST['api_password']) ? trim($_POST['api_password']) : null;

        // Encrypt the password before storing (only if provided)
        $encryptedPassword = null;
        if ($apiPassword) {
            $encryptedPassword = EncryptionHelper::encrypt($apiPassword);
        }
        $apiUrl = !empty($_POST['api_url']) ? trim($_POST['api_url']) : 'https://submit.digital.gov.bc.ca/app/api/v1/forms';

        // Validation
        if (empty($name)) {
            throw new Exception("Newsletter name is required");
        }

        // Validate Form ID format (UUID) only if provided
        if ($formId && !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $formId)) {
            throw new Exception("Form ID must be a valid UUID format");
        }

        // If any API field is provided, all must be provided
        $hasAnyApiField = $formId || $apiUsername || $apiPassword;
        if ($hasAnyApiField && (!$formId || !$apiUsername || !$apiPassword)) {
            throw new Exception("If using form API sync, all API fields (Form ID, Username, Password) are required");
        }
        
        $now = date('Y-m-d H:i:s');
        
        if ($newsletterId) {
            // Update existing
            $stmt = $db->prepare("
                UPDATE newsletters
                SET name = ?, description = ?, form_id = ?, api_username = ?,
                    api_password = ?, api_url = ?, updated_at = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $name, $description, $formId, $apiUsername,
                $encryptedPassword, $apiUrl, $now, $newsletterId
            ]);
            $message = "Newsletter updated successfully";
        } else {
            // Check if form_id already exists (only if provided)
            if ($formId) {
                $checkStmt = $db->prepare("SELECT id FROM newsletters WHERE form_id = ?");
                $checkStmt->execute([$formId]);
                if ($checkStmt->fetch()) {
                    throw new Exception("A newsletter with this Form ID already exists");
                }
            }

            // Insert new
            $stmt = $db->prepare("
                INSERT INTO newsletters (name, description, form_id, api_username, api_password, api_url, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name, $description, $formId, $apiUsername,
                $encryptedPassword, $apiUrl, $now, $now
            ]);
            $newsletterId = $db->lastInsertId();
            $message = "Newsletter created successfully";
        }
        
        $messageType = 'success';
        
        // Optionally test the connection (only if API credentials are provided)
        if (isset($_POST['test_connection']) && $formId && $apiUsername && $apiPassword) {
            $testUrl = $apiUrl . '/' . $formId . '/export?format=json&type=submissions';

            $context = stream_context_create([
                'http' => [
                    'header' => "Authorization: Basic " . base64_encode("$apiUsername:$apiPassword"),
                    'timeout' => 10
                ]
            ]);

            $response = @file_get_contents($testUrl, false, $context);

            if ($response === false) {
                $message .= " (Warning: Could not connect to API with provided credentials)";
                $messageType = 'warning';
            } else {
                $message .= " (API connection test successful!)";
            }
        }
        
        // Redirect after successful save (unless testing)
        if ($messageType === 'success' && !isset($_POST['test_connection'])) {
            header('Location: index.php?saved=1');
            exit();
        }
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

$pageTitle = $newsletterId ? 'Edit Newsletter' : 'Add New Newsletter';
?>
<?php getHeader() ?>
<title><?php echo $pageTitle; ?> - Newsletter Management</title>
<?php getScripts() ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const formFeedback = document.getElementById('form-feedback');
    const testButton = document.getElementById('test-button');
    const saveButton = document.getElementById('save-button');
    
    // Form validation and accessibility enhancements
    const formIdInput = document.getElementById('form_id');
    const apiUsernameInput = document.getElementById('api_username');
    const apiPasswordInput = document.getElementById('api_password');
    
    // Real-time validation feedback
    formIdInput.addEventListener('input', function() {
        const value = this.value.trim();
        const pattern = /^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/;
        
        if (value && !pattern.test(value)) {
            this.setAttribute('aria-invalid', 'true');
            formFeedback.textContent = 'Form ID format is invalid. Use the format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx';
        } else {
            this.setAttribute('aria-invalid', 'false');
            formFeedback.textContent = '';
        }
    });
    
    // Form submission handling with announcements
    form.addEventListener('submit', function(e) {
        const isTestConnection = document.activeElement === testButton;
        
        if (isTestConnection) {
            formFeedback.textContent = 'Testing API connection... Please wait.';
            testButton.disabled = true;
            testButton.textContent = 'Testing...';
        } else {
            formFeedback.textContent = 'Saving newsletter configuration... Please wait.';
            saveButton.disabled = true;
            saveButton.textContent = '<?php echo $newsletterId ? "Updating..." : "Creating..."; ?>';
        }
        
        // Re-enable buttons after a timeout in case of issues
        setTimeout(() => {
            testButton.disabled = false;
            saveButton.disabled = false;
            testButton.textContent = 'Test Connection';
            saveButton.textContent = '<?php echo $newsletterId ? "Update Newsletter" : "Create Newsletter"; ?>';
        }, 30000);
    });
    
    // Auto-populate API username if Form ID is provided
    formIdInput.addEventListener('blur', function() {
        const formId = this.value.trim();
        const username = apiUsernameInput.value.trim();
        
        if (formId && !username) {
            if (confirm('Copy Form ID to API Username field? (This is often the same value)')) {
                apiUsernameInput.value = formId;
                formFeedback.textContent = 'API Username populated from Form ID';
                setTimeout(() => formFeedback.textContent = '', 3000);
            }
        }
    });
});
</script>
</head>
<body>
<?php getNavigation() ?>

<div class="container">
    <div class="row">
        <div class="col-md-12">
            <h1><?php echo $pageTitle; ?></h1>
            <p class="text-secondary">Configure newsletter API connection and settings</p>
            <div class="mb-3">
                <a href="index.php" class="btn btn-sm btn-outline-primary">← Back to Newsletters</a>
            </div>
        </div>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="alert <?php 
            if ($messageType === 'success') echo 'alert-success';
            elseif ($messageType === 'warning') echo 'alert-warning';
            else echo 'alert-danger';
        ?>" role="alert">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="name" class="form-label">Newsletter Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? $newsletter['name'] ?? ''); ?>" 
                                   required>
                            <div class="form-text">A friendly name for this newsletter</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="2"><?php echo htmlspecialchars($_POST['description'] ?? $newsletter['description'] ?? ''); ?></textarea>
                            <div class="form-text">Optional description of this newsletter</div>
                        </div>
                        
                        <hr class="my-4">
                        <h5>API Configuration <span class="text-secondary small">(Optional - for automated sync)</span></h5>
                        <p class="text-muted small">Leave these fields empty if you plan to import subscribers manually via CSV instead of using form API sync.</p>

                        <div class="mb-3">
                            <label for="form_id" class="form-label">Form ID</label>
                            <input type="text" class="form-control font-monospace" id="form_id" name="form_id"
                                   value="<?php echo htmlspecialchars($_POST['form_id'] ?? $newsletter['form_id'] ?? ''); ?>"
                                   placeholder="e.g., fd03b54b-84aa-4a05-b5ff-c5536b733f57"
                                   pattern="[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}"
                                   aria-describedby="form-id-help form-id-format">
                            <div id="form-id-help" class="form-text">The UUID of the form in BC Gov Digital Forms</div>
                            <div id="form-id-format" class="form-text">Format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx (lowercase letters and numbers only)</div>
                        </div>

                        <div class="mb-3">
                            <label for="api_username" class="form-label">API Username</label>
                            <input type="text" class="form-control font-monospace" id="api_username" name="api_username"
                                   value="<?php echo htmlspecialchars($_POST['api_username'] ?? $newsletter['api_username'] ?? ''); ?>"
                                   aria-describedby="api-username-help"
                                   autocomplete="username">
                            <div id="api-username-help" class="form-text">Username for Basic Authentication (often same as Form ID)</div>
                        </div>

                        <div class="mb-3">
                            <label for="api_password" class="form-label">API Password</label>
                            <input type="password" class="form-control font-monospace" id="api_password" name="api_password"
                                   value="<?php echo htmlspecialchars($_POST['api_password'] ?? $newsletter['api_password_decrypted'] ?? ''); ?>"
                                   aria-describedby="api-password-help api-password-security"
                                   autocomplete="current-password">
                            <div id="api-password-help" class="form-text">Password/API Key for Basic Authentication</div>
                            <div id="api-password-security" class="form-text text-info">
                                <small><span aria-hidden="true">🔒</span> This password is encrypted before storage for security</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="api_url" class="form-label">API Base URL</label>
                            <input type="url" class="form-control font-monospace" id="api_url" name="api_url" 
                                   value="<?php echo htmlspecialchars($_POST['api_url'] ?? $newsletter['api_url'] ?? 'https://submit.digital.gov.bc.ca/app/api/v1/forms'); ?>">
                            <div class="form-text">Leave default unless using a different API endpoint</div>
                        </div>
                        
                        <!-- Live region for form feedback -->
                        <div id="form-feedback" aria-live="polite" class="visually-hidden"></div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary" id="save-button"
                                    aria-describedby="save-button-help">
                                <?php echo $newsletterId ? 'Update Newsletter' : 'Create Newsletter'; ?>
                            </button>
                            <div id="save-button-help" class="visually-hidden">Save newsletter configuration and validate settings</div>
                            
                            <button type="submit" name="test_connection" value="1" class="btn btn-outline-secondary" id="test-button"
                                    aria-describedby="test-button-help">
                                Test Connection
                            </button>
                            <div id="test-button-help" class="visually-hidden">Test API connection before saving</div>
                            
                            <a href="index.php" class="btn btn-link">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card bg-info-subtle">
                <div class="card-body">
                    <h5 class="card-title">📋 Configuration Help</h5>
                    <p class="card-text small">
                        <strong>Two ways to manage subscribers:</strong>
                    </p>
                    <ol class="small">
                        <li><strong>Form API Sync (Automated):</strong> Provide Form ID and API credentials to automatically sync subscriptions from BC Gov Digital Forms</li>
                        <li><strong>CSV Import (Manual):</strong> Skip the API fields and import subscribers from a CSV file instead</li>
                    </ol>
                    <p class="card-text small">
                        <strong>API Fields (all required if using sync):</strong>
                    </p>
                    <ul class="small">
                        <li><strong>Form ID:</strong> The unique identifier of your form</li>
                        <li><strong>API Credentials:</strong> Username and password for API access</li>
                        <li><strong>API URL:</strong> The base URL (usually the default)</li>
                    </ul>
                </div>
            </div>
            
            <?php if ($newsletterId): ?>
            <div class="card bg-warning-subtle mt-3">
                <div class="card-body">
                    <h5 class="card-title">⚠️ Important</h5>
                    <p class="card-text small">
                        Changing the Form ID or API credentials will affect the ability to sync subscriptions. 
                        Make sure the new credentials are correct before saving.
                    </p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include('../templates/footer.php') ?>