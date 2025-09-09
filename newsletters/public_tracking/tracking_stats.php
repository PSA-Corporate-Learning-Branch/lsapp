<?php
/**
 * Email Tracking Statistics Viewer
 * Simple dashboard to view email open tracking data
 */

// Database connection
try {
    $dbPath = __DIR__ . '/data/email_tracking.db';
    if (!file_exists($dbPath)) {
        die("No tracking data available yet. Database will be created when first email is opened.");
    }
    
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get filter parameters
$campaignFilter = $_GET['campaign'] ?? '';
$newsletterFilter = $_GET['newsletter'] ?? '';
$dateFilter = $_GET['date'] ?? date('Y-m-d');

// Build query with filters
$query = "SELECT * FROM email_opens WHERE 1=1";
$params = [];

if ($campaignFilter) {
    $query .= " AND campaign_id = :campaign";
    $params[':campaign'] = $campaignFilter;
}

if ($newsletterFilter) {
    $query .= " AND newsletter_id = :newsletter";
    $params[':newsletter'] = $newsletterFilter;
}

if ($dateFilter) {
    $query .= " AND DATE(opened_at) = :date";
    $params[':date'] = $dateFilter;
}

$query .= " ORDER BY opened_at DESC LIMIT 500";

$stmt = $db->prepare($query);
$stmt->execute($params);
$opens = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$statsQuery = "
    SELECT 
        COUNT(DISTINCT tracking_id) as total_opens,
        COUNT(DISTINCT email) as unique_opens,
        COUNT(DISTINCT campaign_id) as campaigns,
        DATE(MIN(opened_at)) as first_open,
        DATE(MAX(opened_at)) as last_open
    FROM email_opens
";
$statsStmt = $db->query($statsQuery);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Get opens by date
$dailyQuery = "
    SELECT 
        DATE(opened_at) as open_date,
        COUNT(*) as open_count,
        COUNT(DISTINCT email) as unique_count
    FROM email_opens
    WHERE DATE(opened_at) >= DATE('now', '-30 days')
    GROUP BY DATE(opened_at)
    ORDER BY open_date DESC
";
$dailyStmt = $db->query($dailyQuery);
$dailyStats = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Tracking Statistics</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #4CAF50;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #4CAF50;
            color: white;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .filters {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .filter-group {
            display: inline-block;
            margin-right: 20px;
        }
        label {
            display: inline-block;
            margin-right: 5px;
            font-weight: bold;
        }
        input, select {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        button {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 5px 15px;
            border-radius: 3px;
            cursor: pointer;
        }
        button:hover {
            background: #45a049;
        }
        .user-agent {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Email Tracking Statistics</h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_opens'] ?? 0); ?></div>
                <div class="stat-label">Total Opens</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['unique_opens'] ?? 0); ?></div>
                <div class="stat-label">Unique Recipients</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['campaigns'] ?? 0); ?></div>
                <div class="stat-label">Campaigns</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['last_open'] ?? 'N/A'; ?></div>
                <div class="stat-label">Last Open</div>
            </div>
        </div>

        <div class="filters">
            <form method="get">
                <div class="filter-group">
                    <label for="campaign">Campaign:</label>
                    <input type="text" id="campaign" name="campaign" value="<?php echo htmlspecialchars($campaignFilter); ?>" placeholder="Campaign ID">
                </div>
                <div class="filter-group">
                    <label for="newsletter">Newsletter:</label>
                    <input type="number" id="newsletter" name="newsletter" value="<?php echo htmlspecialchars($newsletterFilter); ?>" placeholder="Newsletter ID">
                </div>
                <div class="filter-group">
                    <label for="date">Date:</label>
                    <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($dateFilter); ?>">
                </div>
                <button type="submit">Apply Filters</button>
                <a href="?" style="margin-left: 10px;">Clear</a>
            </form>
        </div>

        <h2>Daily Open Statistics (Last 30 Days)</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Total Opens</th>
                    <th>Unique Opens</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dailyStats as $day): ?>
                <tr>
                    <td><?php echo $day['open_date']; ?></td>
                    <td><?php echo $day['open_count']; ?></td>
                    <td><?php echo $day['unique_count']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Recent Opens (Last 500)</h2>
        <table>
            <thead>
                <tr>
                    <th>Opened At</th>
                    <th>Email</th>
                    <th>Campaign</th>
                    <th>Newsletter</th>
                    <th>IP Address</th>
                    <th>User Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($opens as $open): ?>
                <tr>
                    <td><?php echo $open['opened_at']; ?></td>
                    <td><?php echo htmlspecialchars($open['email'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($open['campaign_id'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($open['newsletter_id'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($open['ip_address']); ?></td>
                    <td class="user-agent" title="<?php echo htmlspecialchars($open['user_agent']); ?>">
                        <?php echo htmlspecialchars($open['user_agent']); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>