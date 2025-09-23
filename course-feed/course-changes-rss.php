<?php
opcache_reset();

// Set UTF-8 encoding for the script
mb_internal_encoding('UTF-8');
define('SLASH', DIRECTORY_SEPARATOR);
$docroot = $_SERVER['DOCUMENT_ROOT'] . '/lsapp//';
define('BASE_DIR', $docroot);
function build_path(...$segments) {
    return implode(SLASH, $segments);
}

function getCourse($cid) {
	$path = build_path(BASE_DIR, 'data', 'courses.csv');
	$f = fopen($path, 'r');
	$course = '';
	while ($row = fgetcsv($f)) {
		if($row[0] == $cid) {
			$course = $row;
		}
	}
	fclose($f);
	return $course;
}

// Directory containing the change request JSON files
$directory = "../course-change/requests";

// Get all JSON files
$files = glob("{$directory}/*.json");

// Initialize an array to store request data
$changeRequests = [];

foreach ($files as $file) {
    $changeData = json_decode(file_get_contents($file), true);
    
    if ($changeData) {
        // Get course details
        $courseDetails = getCourse($changeData['courseid']);
        if ($courseDetails) {
            $changeData['courseName'] = $courseDetails[2]; // Course name
            $changeData['partner'] = $courseDetails[36]; // Learning partner
        } else {
            $changeData['courseName'] = 'Unknown Course';
            $changeData['partner'] = 'Unknown Partner';
        }
        
        // Only include incomplete requests (not 100% progress) or recent requests
        $includeRequest = false;
        
        // Include if progress is not 100%
        if (!isset($changeData['progress']) || $changeData['progress'] != '100') {
            $includeRequest = true;
        }
        
        // Also include completed requests from the last 7 days
        if ($changeData['progress'] == '100') {
            $modifiedDate = $changeData['date_modified'] ?? $changeData['date_created'];
            $daysSinceModified = (time() - $modifiedDate) / 86400;
            if ($daysSinceModified <= 7) {
                $includeRequest = true;
            }
        }
        
        if ($includeRequest) {
            $changeRequests[] = $changeData;
        }
    }
}

// Sort by date_created descending (newest first)
usort($changeRequests, function($a, $b) {
    return ($b['date_created'] ?? 0) - ($a['date_created'] ?? 0);
});

// Start building the RSS feed
$rss = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";
$rss .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
$rss .= '<channel>' . "\n";
$rss .= '<title>BC Gov Learning - Course Change Requests</title>' . "\n";
$rss .= '<link>https://gww.bcpublicservice.gov.bc.ca/lsapp/course-change/</link>' . "\n";
$rss .= '<description>Course change requests from BC Government Learning Partners</description>' . "\n";
$rss .= '<language>en-us</language>' . "\n";
$rss .= '<lastBuildDate>' . date(DATE_RSS) . '</lastBuildDate>' . "\n";
$rss .= '<atom:link href="https://learn.bcpublicservice.gov.bc.ca/learning-hub/course-change-requests.xml" rel="self" type="application/rss+xml" />' . "\n";

// Add change requests to the RSS feed
foreach ($changeRequests as $change) {
    // Build title
    $title = htmlspecialchars($change['courseName'], ENT_XML1, 'UTF-8') . ' - ' . htmlspecialchars($change['category'], ENT_XML1, 'UTF-8');
    if ($change['urgent']) {
        $title = '[URGENT] ' . $title;
    }
    
    // Build description
    $description = htmlspecialchars($change['description'] ?? '', ENT_XML1, 'UTF-8');
    
    // Add metadata to description
    $fullDescription = $description;
    
    if (!empty($change['partner'])) {
        $fullDescription .= "\n\nLearning Partner: " . htmlspecialchars($change['partner'], ENT_XML1, 'UTF-8');
    }
    
    if (!empty($change['category'])) {
        $fullDescription .= "\nCategory: " . htmlspecialchars($change['category'], ENT_XML1, 'UTF-8');
    }
    
    if (!empty($change['scope'])) {
        $fullDescription .= "\nScope: " . htmlspecialchars($change['scope'], ENT_XML1, 'UTF-8');
    }
    
    if (isset($change['progress'])) {
        $fullDescription .= "\nProgress: " . htmlspecialchars($change['progress'], ENT_XML1, 'UTF-8') . "%";
    }
    
    if (!empty($change['assign_to'])) {
        $fullDescription .= "\nAssigned to: " . htmlspecialchars($change['assign_to'], ENT_XML1, 'UTF-8');
    }
    
    if (!empty($change['created_by'])) {
        $fullDescription .= "\nRequested by: " . htmlspecialchars($change['created_by'], ENT_XML1, 'UTF-8');
    }
    
    if (!empty($change['crm_ticket_reference'])) {
        $fullDescription .= "\nCRM Ticket: " . htmlspecialchars($change['crm_ticket_reference'], ENT_XML1, 'UTF-8');
    }
    
    // Format dates
    $pubDate = date(DATE_RSS, $change['date_created']);
    $modifiedDate = isset($change['date_modified']) ? date(DATE_RSS, $change['date_modified']) : $pubDate;
    
    // Build the change request URL
    $changeUrl = 'https://gww.bcpublicservice.gov.bc.ca/lsapp/course-change/view.php?courseid=' . 
                 urlencode($change['courseid']) . '&amp;changeid=' . urlencode($change['changeid']);
    
    // Add the item to the RSS feed
    $rss .= '<item>' . "\n";
    $rss .= '<title>' . $title . '</title>' . "\n";
    $rss .= '<link>' . $changeUrl . '</link>' . "\n";
    $rss .= '<description><![CDATA[' . nl2br($fullDescription) . ']]></description>' . "\n";
    $rss .= '<guid isPermaLink="true">' . $changeUrl . '</guid>' . "\n";
    $rss .= '<pubDate>' . $pubDate . '</pubDate>' . "\n";
    
    // Add category tags
    if (!empty($change['category'])) {
        $rss .= '<category>' . htmlspecialchars($change['category'], ENT_XML1, 'UTF-8') . '</category>' . "\n";
    }
    
    if ($change['urgent']) {
        $rss .= '<category>Urgent</category>' . "\n";
    }
    
    if (isset($change['progress']) && $change['progress'] == '100') {
        $rss .= '<category>Completed</category>' . "\n";
    } else {
        $rss .= '<category>In Progress</category>' . "\n";
    }
    
    $rss .= '</item>' . "\n";
}

$rss .= '</channel>' . "\n";
$rss .= '</rss>';

// Write the RSS feed to a file with UTF-8 encoding
$rssFilename = 'data/course-change-requests.xml';
// Ensure the content is UTF-8 encoded
$rss = mb_convert_encoding($rss, 'UTF-8', 'UTF-8');
file_put_contents($rssFilename, $rss);

// Copy to the web-accessible location
$targetFile = 'E:/WebSites/NonSSOLearning/learning-hub/course-change-requests.xml';
if (!copy($rssFilename, $targetFile)) {
    echo 'Failed to copy ' . $rssFilename . ' to ' . $targetFile;
    exit;
}

// Redirect to the next step
header('Location: partner-requests-rss.php');
