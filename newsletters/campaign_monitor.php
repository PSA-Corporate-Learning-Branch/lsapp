<?php
/**
 * Campaign Monitor - Browser-based Progressive Email Sending
 * Real-time monitoring and control of newsletter campaigns
 */
require('../inc/lsapp.php');

$campaignId = isset($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : 0;
$autoStart = isset($_GET['start']) && $_GET['start'] == '1';

if (!$campaignId) {
    header('Location: index.php');
    exit();
}

// Database connection
try {
    $db = new PDO("sqlite:../data/subscriptions.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get campaign details
    $stmt = $db->prepare("
        SELECT 
            c.*,
            n.name as newsletter_name,
            (SELECT COUNT(*) FROM email_queue WHERE campaign_id = c.id) as total_emails,
            (SELECT COUNT(*) FROM email_queue WHERE campaign_id = c.id AND status = 'sent') as sent_count,
            (SELECT COUNT(*) FROM email_queue WHERE campaign_id = c.id AND status = 'pending') as pending_count,
            (SELECT COUNT(*) FROM email_queue WHERE campaign_id = c.id AND status = 'failed') as failed_count
        FROM email_campaigns c
        LEFT JOIN newsletters n ON c.newsletter_id = n.id
        WHERE c.id = ?
    ");
    $stmt->execute([$campaignId]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$campaign) {
        header('Location: index.php');
        exit();
    }
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Calculate initial progress
$progress = 0;
if ($campaign['total_emails'] > 0) {
    $progress = round(($campaign['sent_count'] + $campaign['failed_count']) / $campaign['total_emails'] * 100, 1);
}
?>
<?php getHeader() ?>
<title>Campaign Monitor - <?php echo htmlspecialchars($campaign['subject']); ?></title>
<style>
    .progress-container {
        position: relative;
        margin: 20px 0;
    }
    
    .progress {
        height: 30px;
        font-size: 14px;
        border-radius: 5px;
        overflow: visible;
    }
    
    .progress-bar {
        transition: width 0.3s ease;
        position: relative;
    }
    
    .progress-text {
        position: absolute;
        width: 100%;
        text-align: center;
        line-height: 30px;
        color: #333;
        font-weight: bold;
        z-index: 1;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin: 20px 0;
    }
    
    .stat-number {
        font-size: 2em;
        font-weight: bold;
        margin: 10px 0;
    }
    
    .stat-label {
        font-size: 0.9em;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .control-buttons {
        display: flex;
        gap: 10px;
        margin: 20px 0;
        flex-wrap: wrap;
    }
    
    .status-badge {
        display: inline-block;
        padding: 5px 15px;
        border-radius: 20px;
        font-weight: bold;
        text-transform: uppercase;
        font-size: 0.85em;
    }
    
    .status-processing { background: #ffc107; color: #000; }
    .status-paused { background: #ff9800; color: #fff; }
    .status-completed { background: #4caf50; color: #fff; }
    .status-failed { background: #f44336; color: #fff; }
    .status-pending { background: #2196f3; color: #fff; }
    .status-cancelled { background: #9e9e9e; color: #fff; }
    
    .log-container {
        max-height: 200px;
        overflow-y: auto;
        padding: 10px;
        border-radius: 5px;
        font-family: monospace;
        font-size: 0.85em;
    }
    
    .log-entry {
        padding: 2px 0;
    }
    
    .pulse {
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }
    
    .estimated-time {
        padding: 10px;
        border-radius: 5px;
        margin: 10px 0;
    }
    
    /* Ensure skip links work in all scenarios */
    .visually-hidden-focusable:focus-within {
        display: block !important;
    }
</style>
<?php getScripts() ?>
</head>
<body>
<?php getNavigation() ?>

<!-- Skip Links for Accessibility -->
<div class="visually-hidden-focusable">
    <a href="#main-content" class="btn btn-primary btn-sm">Skip to main content</a>
    <a href="#campaign-controls" class="btn btn-secondary btn-sm">Skip to campaign controls</a>
    <a href="#activity-log" class="btn btn-outline-secondary btn-sm">Skip to activity log</a>
</div>

<div class="container">
    <main id="main-content">
    <div class="row">
        <div class="col-md-12">
            <h1>📧 Campaign Monitor</h1>
            <p class="text-secondary">
                <strong>Newsletter:</strong> <?php echo htmlspecialchars($campaign['newsletter_name'] ?? 'Unknown'); ?> | 
                <strong>Subject:</strong> <?php echo htmlspecialchars($campaign['subject']); ?>
            </p>
        </div>
    </div>
    
    <!-- Campaign Status -->
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex align-items-center gap-3 mb-3">
                <h3 class="mb-0">Status:</h3>
                <span id="campaign-status" class="status-badge status-<?php echo $campaign['processing_status']; ?>">
                    <?php echo $campaign['processing_status']; ?>
                </span>
                <span id="processing-indicator" class="pulse" style="display: none;">
                    ⏳ Processing batch...
                </span>
            </div>
        </div>
    </div>
    
    <!-- Progress Bar -->
    <div class="row">
        <div class="col-md-12">
            <div class="progress-container">
                <label id="progress-label" class="form-label">Campaign Progress</label>
                <div class="progress" role="progressbar" 
                     aria-labelledby="progress-label" 
                     aria-describedby="progress-description"
                     aria-valuenow="<?php echo $progress; ?>" 
                     aria-valuemin="0" 
                     aria-valuemax="100"
                     aria-valuetext="<?php echo $progress; ?>% complete">
                    <div id="progress-bar" class="progress-bar progress-bar-striped" 
                         style="width: <?php echo $progress; ?>%">
                    </div>
                </div>
                <div id="progress-description" class="mt-2">
                    <span id="progress-text"><?php echo $progress; ?>% Complete</span>
                    (<span id="sent-count-display"><?php echo $campaign['sent_count']; ?></span> sent, 
                     <span id="remaining-count-display"><?php echo $campaign['pending_count']; ?></span> remaining)
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics Grid -->
    <div class="stats-grid">
        <div class="card text-center">
            <div class="card-body">
                <div class="stat-label text-body-secondary">Total Emails</div>
                <div class="stat-number" id="stat-total"><?php echo $campaign['total_emails']; ?></div>
            </div>
        </div>
        <div class="card text-center">
            <div class="card-body">
                <div class="stat-label text-body-secondary">✅ Sent</div>
                <div class="stat-number text-success" id="stat-sent"><?php echo $campaign['sent_count']; ?></div>
            </div>
        </div>
        <div class="card text-center">
            <div class="card-body">
                <div class="stat-label text-body-secondary">⏳ Pending</div>
                <div class="stat-number text-warning" id="stat-pending"><?php echo $campaign['pending_count']; ?></div>
            </div>
        </div>
        <div class="card text-center">
            <div class="card-body">
                <div class="stat-label text-body-secondary">❌ Failed</div>
                <div class="stat-number text-danger" id="stat-failed"><?php echo $campaign['failed_count']; ?></div>
            </div>
        </div>
    </div>
    
    <!-- Estimated Time -->
    <div class="alert alert-info estimated-time" id="estimated-time-container">
        <strong>⏱️ Estimated Time Remaining:</strong> 
        <span id="estimated-time">Calculating...</span>
    </div>
    
    <!-- Control Buttons -->
    <div class="control-buttons" role="group" aria-labelledby="control-buttons-label" id="campaign-controls">
        <h4 id="control-buttons-label" class="visually-hidden">Campaign Controls</h4>
        <button id="btn-start" class="btn btn-success btn-lg" 
                aria-describedby="campaign-status"
                style="display: none;">
            <span class="visually-hidden">Start sending campaign: </span>
            <span aria-hidden="true">▶️</span> Start Sending
        </button>
        <button id="btn-pause" class="btn btn-warning btn-lg" 
                aria-describedby="campaign-status"
                style="display: none;">
            <span class="visually-hidden">Pause campaign sending: </span>
            <span aria-hidden="true">⏸️</span> Pause
        </button>
        <button id="btn-resume" class="btn btn-success btn-lg" 
                aria-describedby="campaign-status"
                style="display: none;">
            <span class="visually-hidden">Resume campaign sending: </span>
            <span aria-hidden="true">▶️</span> Resume
        </button>
        <button id="btn-cancel" class="btn btn-danger btn-lg" 
                aria-describedby="campaign-status"
                style="display: none;">
            <span class="visually-hidden">Cancel campaign permanently: </span>
            <span aria-hidden="true">❌</span> Cancel Campaign
        </button>
        <button id="btn-refresh" class="btn btn-secondary"
                aria-label="Refresh campaign status manually">
            <span aria-hidden="true">🔄</span> Refresh Status
        </button>
        <a href="send_newsletter.php?newsletter_id=<?php echo $campaign['newsletter_id']; ?>" 
           class="btn btn-outline-primary"
           aria-label="Create new campaign for this newsletter">
            <span aria-hidden="true">📝</span> New Campaign
        </a>
    </div>
    
    <!-- Live Regions for Screen Reader Announcements -->
    <div id="status-announcer" aria-live="polite" class="visually-hidden"></div>
    <div id="progress-announcer" aria-live="polite" class="visually-hidden"></div>
    <div id="error-announcer" aria-live="assertive" class="visually-hidden"></div>

    <!-- Activity Log -->
    <div class="row mt-4">
        <div class="col-md-12">
            <h4><span aria-hidden="true">📋</span> Activity Log</h4>
            <div class="card">
                <div class="card-body">
                    <div class="log-container bg-body-tertiary border rounded" 
                         id="activity-log" 
                         role="log" 
                         aria-live="polite" 
                         aria-label="Campaign activity log">
                        <div class="log-entry border-bottom border-subtle">Campaign initialized. Ready to send to <?php echo $campaign['total_emails']; ?> recipients.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Back to Dashboard -->
    <div class="row mt-4">
        <div class="col-md-12">
            <a href="index.php" class="btn btn-outline-secondary">← Back to Newsletters</a>
        </div>
    </div>
    </main>
</div>

<script>
// Campaign Monitor JavaScript
(function() {
    const campaignId = <?php echo $campaignId; ?>;
    const autoStart = <?php echo $autoStart ? 'true' : 'false'; ?>;
    
    // State management
    let state = {
        status: '<?php echo $campaign['processing_status']; ?>',
        isProcessing: false,
        batchInterval: null,
        statusInterval: null,
        lastProcessedCount: 0,
        errors: [],
        lastAnnouncedStatus: '',
        lastAnnouncedProgress: 0,
        focusManagement: {
            lastActiveElement: null,
            shouldMaintainFocus: false
        }
    };
    
    // Save state to localStorage for recovery
    function saveState() {
        localStorage.setItem(`campaign_${campaignId}_state`, JSON.stringify({
            campaignId: campaignId,
            status: state.status,
            timestamp: Date.now()
        }));
    }
    
    // Load state from localStorage
    function loadState() {
        const saved = localStorage.getItem(`campaign_${campaignId}_state`);
        if (saved) {
            const data = JSON.parse(saved);
            // Check if state is recent (within last hour)
            if (Date.now() - data.timestamp < 3600000) {
                return data;
            }
        }
        return null;
    }
    
    // Add log entry with screen reader support
    function addLog(message, type = 'info', announceToScreenReader = false) {
        const log = document.getElementById('activity-log');
        const entry = document.createElement('div');
        entry.className = 'log-entry border-bottom border-subtle';
        
        // Apply Bootstrap text colors for different message types
        if (type === 'error') entry.className += ' text-danger';
        if (type === 'success') entry.className += ' text-success';
        if (type === 'info') entry.className += ' text-body';
        
        const timestamp = new Date().toLocaleTimeString();
        const fullMessage = `[${timestamp}] ${message}`;
        entry.textContent = fullMessage;
        
        log.insertBefore(entry, log.firstChild);
        
        // Announce important messages to screen readers
        if (announceToScreenReader) {
            const announcer = type === 'error' ? 
                document.getElementById('error-announcer') : 
                document.getElementById('status-announcer');
            announcer.textContent = message;
            
            // Clear after announcement
            setTimeout(() => {
                announcer.textContent = '';
            }, 1000);
        }
        
        // Keep only last 50 entries
        while (log.children.length > 50) {
            log.removeChild(log.lastChild);
        }
    }
    
    // Announce status changes to screen readers
    function announceStatusChange(newStatus, data = {}) {
        const statusAnnouncer = document.getElementById('status-announcer');
        let message = '';
        
        switch(newStatus) {
            case 'processing':
                message = 'Campaign sending started';
                break;
            case 'paused':
                message = 'Campaign sending paused';
                break;
            case 'completed':
                message = `Campaign completed successfully. ${data.sent || 0} emails sent.`;
                break;
            case 'failed':
                message = 'Campaign failed. Check activity log for details.';
                break;
        }
        
        if (message && newStatus !== state.lastAnnouncedStatus) {
            statusAnnouncer.textContent = message;
            state.lastAnnouncedStatus = newStatus;
            
            setTimeout(() => {
                statusAnnouncer.textContent = '';
            }, 3000);
        }
    }
    
    // Announce progress milestones
    function announceProgressMilestone(data) {
        const progress = data.progress || 0;
        const milestones = [25, 50, 75, 100];
        
        const currentMilestone = milestones.find(m => 
            progress >= m && state.lastAnnouncedProgress < m
        );
        
        if (currentMilestone) {
            const progressAnnouncer = document.getElementById('progress-announcer');
            progressAnnouncer.textContent = 
                `Campaign ${currentMilestone}% complete. ${data.sent} emails sent, ${data.pending} remaining.`;
            
            state.lastAnnouncedProgress = currentMilestone;
            
            setTimeout(() => {
                progressAnnouncer.textContent = '';
            }, 3000);
        }
    }
    
    // Focus management for dynamic button changes
    function manageFocusForButtons(newStatus, previousStatus) {
        const buttons = {
            'pending': 'btn-start',
            'processing': 'btn-pause',
            'paused': 'btn-resume'
        };
        
        const newButton = buttons[newStatus];
        const previousButton = buttons[previousStatus];
        
        // If focus was on the previous primary action button, move it to new one
        if (previousButton && newButton && document.activeElement && 
            document.activeElement.id === previousButton) {
            setTimeout(() => {
                const targetBtn = document.getElementById(newButton);
                if (targetBtn && targetBtn.style.display !== 'none') {
                    targetBtn.focus();
                }
            }, 100);
        }
    }
    
    // Update UI based on status with accessibility enhancements
    function updateUI(data) {
        const previousStatus = state.status;
        
        // Update statistics
        document.getElementById('stat-total').textContent = data.total || 0;
        document.getElementById('stat-sent').textContent = data.sent || 0;
        document.getElementById('stat-pending').textContent = data.pending || 0;
        document.getElementById('stat-failed').textContent = data.failed || 0;
        
        // Update display counters
        document.getElementById('sent-count-display').textContent = data.sent || 0;
        document.getElementById('remaining-count-display').textContent = data.pending || 0;
        
        // Update progress bar with full accessibility
        const progress = data.progress || 0;
        const progressBar = document.querySelector('.progress');
        const progressBarInner = document.getElementById('progress-bar');
        
        progressBarInner.style.width = progress + '%';
        progressBar.setAttribute('aria-valuenow', progress);
        progressBar.setAttribute('aria-valuetext', `${progress.toFixed(1)}% complete, ${data.sent} sent, ${data.pending} remaining`);
        
        document.getElementById('progress-text').textContent = progress.toFixed(1) + '% Complete';
        
        // Update status badge
        const statusBadge = document.getElementById('campaign-status');
        statusBadge.textContent = data.status || 'unknown';
        statusBadge.className = 'status-badge status-' + (data.status || 'pending');
        
        // Update estimated time
        if (data.estimated_minutes > 0) {
            const hours = Math.floor(data.estimated_minutes / 60);
            const minutes = data.estimated_minutes % 60;
            let timeText = '';
            if (hours > 0) {
                timeText = `${hours} hour${hours > 1 ? 's' : ''} `;
            }
            timeText += `${minutes} minute${minutes > 1 ? 's' : ''}`;
            document.getElementById('estimated-time').textContent = timeText;
        } else {
            document.getElementById('estimated-time').textContent = 'Complete';
        }
        
        // Update button visibility with focus management
        const btnStart = document.getElementById('btn-start');
        const btnPause = document.getElementById('btn-pause');
        const btnResume = document.getElementById('btn-resume');
        const btnCancel = document.getElementById('btn-cancel');
        
        btnStart.style.display = 'none';
        btnPause.style.display = 'none';
        btnResume.style.display = 'none';
        btnCancel.style.display = 'none';
        
        switch(data.status) {
            case 'pending':
                btnStart.style.display = 'inline-block';
                btnCancel.style.display = 'inline-block';
                break;
            case 'processing':
                btnPause.style.display = 'inline-block';
                btnCancel.style.display = 'inline-block';
                break;
            case 'paused':
                btnResume.style.display = 'inline-block';
                btnCancel.style.display = 'inline-block';
                break;
            case 'completed':
                if (data.failed > 0) {
                    addLog(`Campaign completed with ${data.failed} failed emails`, 'error', true);
                }
                break;
        }
        
        // Announce status and progress changes
        if (data.status !== previousStatus) {
            announceStatusChange(data.status, data);
            manageFocusForButtons(data.status, previousStatus);
        }
        
        announceProgressMilestone(data);
        
        // Save current state
        state.status = data.status;
        saveState();
    }
    
    // Fetch campaign status
    async function fetchStatus() {
        try {
            const response = await fetch(`api/campaign_controller.php?action=get_status&campaign_id=${campaignId}`);
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            updateUI(data);
            
            // Check if campaign is complete
            if (data.status === 'completed' || data.status === 'cancelled') {
                stopProcessing();
                if (data.status === 'completed') {
                    addLog('✅ Campaign completed successfully!', 'success');
                }
            }
            
            return data;
            
        } catch (error) {
            addLog('Error fetching status: ' + error.message, 'error');
            console.error('Status fetch error:', error);
        }
    }
    
    // Send batch of emails
    async function sendBatch() {
        if (state.isProcessing) {
            return; // Already processing
        }
        
        state.isProcessing = true;
        document.getElementById('processing-indicator').style.display = 'inline-block';
        
        try {
            const response = await fetch(`api/campaign_controller.php?action=send_batch&campaign_id=${campaignId}`);
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Log batch results
            if (data.processed > 0) {
                addLog(`Sent batch: ${data.processed} emails sent successfully`, 'success');
            }
            
            if (data.failed > 0) {
                addLog(`Batch errors: ${data.failed} emails failed`, 'error');
                if (data.errors && data.errors.length > 0) {
                    data.errors.forEach(error => addLog(error, 'error'));
                }
            }
            
            // Update UI with latest status
            await fetchStatus();
            
            // Check if we should continue
            if (data.status === 'completed') {
                stopProcessing();
                addLog('🎉 All emails sent successfully!', 'success');
            } else if (data.status === 'paused') {
                stopProcessing();
                addLog('Campaign paused', 'info');
            }
            
        } catch (error) {
            addLog('Batch sending error: ' + error.message, 'error');
            console.error('Batch error:', error);
            
            // Stop processing on error
            stopProcessing();
            
        } finally {
            state.isProcessing = false;
            document.getElementById('processing-indicator').style.display = 'none';
        }
    }
    
    // Start processing campaign
    function startProcessing() {
        addLog('Starting campaign processing...', 'info');
        
        // Send first batch immediately
        sendBatch();
        
        // Set up interval for subsequent batches (every 60 seconds for rate limiting)
        state.batchInterval = setInterval(sendBatch, 60000);
        
        // Update status more frequently
        state.statusInterval = setInterval(fetchStatus, 5000);
        
        // Update button visibility
        document.getElementById('btn-start').style.display = 'none';
        document.getElementById('btn-pause').style.display = 'inline-block';
    }
    
    // Stop processing
    function stopProcessing() {
        if (state.batchInterval) {
            clearInterval(state.batchInterval);
            state.batchInterval = null;
        }
        
        if (state.statusInterval) {
            clearInterval(state.statusInterval);
            state.statusInterval = null;
        }
        
        document.getElementById('processing-indicator').style.display = 'none';
    }
    
    // Control button handlers with accessibility improvements
    document.getElementById('btn-start').addEventListener('click', async () => {
        try {
            addLog('Starting campaign...', 'info', true);
            await fetch(`api/campaign_controller.php?action=resume&campaign_id=${campaignId}`);
            startProcessing();
        } catch (error) {
            addLog('Error starting campaign: ' + error.message, 'error', true);
        }
    });
    
    document.getElementById('btn-pause').addEventListener('click', async () => {
        try {
            addLog('Pausing campaign...', 'info', true);
            const response = await fetch(`api/campaign_controller.php?action=pause&campaign_id=${campaignId}`);
            const data = await response.json();
            
            if (data.success) {
                stopProcessing();
                addLog('Campaign paused', 'info', true);
                fetchStatus();
            } else {
                addLog('Failed to pause campaign', 'error', true);
            }
        } catch (error) {
            addLog('Error pausing campaign: ' + error.message, 'error', true);
        }
    });
    
    document.getElementById('btn-resume').addEventListener('click', async () => {
        try {
            addLog('Resuming campaign...', 'info', true);
            const response = await fetch(`api/campaign_controller.php?action=resume&campaign_id=${campaignId}`);
            const data = await response.json();
            
            if (data.success) {
                addLog('Campaign resumed', 'info', true);
                startProcessing();
            } else {
                addLog('Failed to resume campaign', 'error', true);
            }
        } catch (error) {
            addLog('Error resuming campaign: ' + error.message, 'error', true);
        }
    });
    
    document.getElementById('btn-cancel').addEventListener('click', async () => {
        if (!confirm('Are you sure you want to cancel this campaign? This cannot be undone.')) {
            return;
        }
        
        try {
            addLog('Cancelling campaign...', 'info', true);
            const response = await fetch(`api/campaign_controller.php?action=cancel&campaign_id=${campaignId}`);
            const data = await response.json();
            
            if (data.success) {
                stopProcessing();
                addLog('Campaign cancelled permanently', 'info', true);
                fetchStatus();
            } else {
                addLog('Failed to cancel campaign', 'error', true);
            }
        } catch (error) {
            addLog('Error cancelling campaign: ' + error.message, 'error', true);
        }
    });
    
    document.getElementById('btn-refresh').addEventListener('click', () => {
        addLog('Refreshing status...', 'info');
        fetchStatus();
    });
    
    // Check for existing state on page load
    async function initialize() {
        // Load saved state
        const savedState = loadState();
        if (savedState) {
            addLog('Restored campaign state from previous session', 'info');
        }
        
        // Get current status
        const currentStatus = await fetchStatus();
        
        if (!currentStatus) {
            return;
        }
        
        // Check if we should auto-start
        if (autoStart && currentStatus.status === 'pending') {
            addLog('Auto-starting campaign...', 'info');
            startProcessing();
        } else if (currentStatus.status === 'processing') {
            // Resume if already processing
            addLog('Campaign already in progress, resuming monitoring...', 'info');
            startProcessing();
        } else if (currentStatus.status === 'paused') {
            addLog('Campaign is paused. Click Resume to continue.', 'info');
        }
        
        // Set up periodic status updates (every 10 seconds when not actively processing)
        setInterval(() => {
            if (!state.batchInterval) {
                fetchStatus();
            }
        }, 10000);
    }
    
    // Handle page visibility changes (pause when hidden, resume when visible)
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            // Page is hidden, pause updates but keep processing
            if (state.statusInterval) {
                clearInterval(state.statusInterval);
                state.statusInterval = null;
            }
        } else {
            // Page is visible again, resume updates
            if (state.batchInterval && !state.statusInterval) {
                state.statusInterval = setInterval(fetchStatus, 5000);
            }
            // Always fetch fresh status when page becomes visible
            fetchStatus();
        }
    });
    
    // Handle beforeunload event
    window.addEventListener('beforeunload', (e) => {
        if (state.status === 'processing' && state.batchInterval) {
            e.preventDefault();
            e.returnValue = 'Campaign is still sending. Are you sure you want to leave?';
        }
    });
    
    // Keyboard shortcuts for accessibility
    document.addEventListener('keydown', (e) => {
        // Only trigger if not in form fields
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
            return;
        }
        
        // Alt + key shortcuts
        if (e.altKey) {
            switch(e.key.toLowerCase()) {
                case 's':
                    // Alt+S: Start campaign
                    const startBtn = document.getElementById('btn-start');
                    if (startBtn && startBtn.style.display !== 'none') {
                        e.preventDefault();
                        startBtn.click();
                        addLog('Campaign started via keyboard shortcut', 'info');
                    }
                    break;
                    
                case 'p':
                    // Alt+P: Pause campaign
                    const pauseBtn = document.getElementById('btn-pause');
                    if (pauseBtn && pauseBtn.style.display !== 'none') {
                        e.preventDefault();
                        pauseBtn.click();
                        addLog('Campaign paused via keyboard shortcut', 'info');
                    }
                    break;
                    
                case 'r':
                    // Alt+R: Resume campaign or refresh status
                    const resumeBtn = document.getElementById('btn-resume');
                    const refreshBtn = document.getElementById('btn-refresh');
                    
                    if (resumeBtn && resumeBtn.style.display !== 'none') {
                        e.preventDefault();
                        resumeBtn.click();
                        addLog('Campaign resumed via keyboard shortcut', 'info');
                    } else if (refreshBtn) {
                        e.preventDefault();
                        refreshBtn.click();
                        addLog('Status refreshed via keyboard shortcut', 'info');
                    }
                    break;
            }
        }
    });
    
    // Announce keyboard shortcuts on focus
    document.addEventListener('DOMContentLoaded', () => {
        const shortcuts = document.createElement('div');
        shortcuts.className = 'alert alert-info mt-3';
        shortcuts.innerHTML = `
            <h5>Keyboard Shortcuts</h5>
            <ul class="mb-0 small">
                <li><strong>Alt+S</strong>: Start campaign</li>
                <li><strong>Alt+P</strong>: Pause campaign</li>
                <li><strong>Alt+R</strong>: Resume campaign or refresh status</li>
            </ul>
        `;
        
        // Add after control buttons
        const controlButtons = document.querySelector('.control-buttons');
        if (controlButtons) {
            controlButtons.insertAdjacentElement('afterend', shortcuts);
        }
    });
    
    // Initialize on page load
    initialize();
    
})();
</script>

<?php include('../templates/footer.php') ?>