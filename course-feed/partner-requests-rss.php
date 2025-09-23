<?php
opcache_reset();

// Set UTF-8 encoding for the script
mb_internal_encoding('UTF-8');

// Read the partners data file
$partnersFile = "../data/partners.json";
$partnersData = json_decode(file_get_contents($partnersFile), true);

// Initialize an array to store partner requests
$partnerRequests = [];

// Filter for partners with "requested" status or any non-standard status
foreach ($partnersData as $partner) {
    $status = $partner['status'] ?? '';
    
    // Include partners that are requested or have a non-standard status
    if ($status === 'requested' || ($status !== 'active' && $status !== 'inactive' && $status !== '')) {
        // Add timestamp if not present (use file modification time as fallback)
        if (!isset($partner['request_date'])) {
            $partner['request_date'] = filemtime($partnersFile);
        }
        
        $partnerRequests[] = $partner;
    }
}

// Also check for partner contact requests
$contactRequestsFile = "../data/partner_contact_requests.json";
if (file_exists($contactRequestsFile)) {
    $contactRequests = json_decode(file_get_contents($contactRequestsFile), true);
    
    if (is_array($contactRequests)) {
        foreach ($contactRequests as $contactRequest) {
            // Transform contact request into a format similar to partner requests
            $transformedRequest = [
                'id' => 'contact_' . ($contactRequest['timestamp'] ?? time()),
                'name' => $contactRequest['partner_name'] . ' - New Contact Request',
                'description' => 'Contact request from ' . $contactRequest['name'] . ' (' . $contactRequest['email'] . ')',
                'link' => $contactRequest['partner_slug'] ?? '',
                'status' => 'contact_request',
                'request_date' => $contactRequest['timestamp'] ?? time(),
                'contact_details' => $contactRequest
            ];
            
            $partnerRequests[] = $transformedRequest;
        }
    }
}

// Sort by request_date descending (newest first)
usort($partnerRequests, function($a, $b) {
    return ($b['request_date'] ?? 0) - ($a['request_date'] ?? 0);
});

// Start building the RSS feed
$rss = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";
$rss .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
$rss .= '<channel>' . "\n";
$rss .= '<title>BC Gov Learning - Partner Requests</title>' . "\n";
$rss .= '<link>https://gww.bcpublicservice.gov.bc.ca/lsapp/partners/</link>' . "\n";
$rss .= '<description>New partner requests and partner contact requests for BC Government Learning</description>' . "\n";
$rss .= '<language>en-us</language>' . "\n";
$rss .= '<lastBuildDate>' . date(DATE_RSS) . '</lastBuildDate>' . "\n";
$rss .= '<atom:link href="https://learn.bcpublicservice.gov.bc.ca/learning-hub/partner-requests.xml" rel="self" type="application/rss+xml" />' . "\n";

// Add partner requests to the RSS feed
foreach ($partnerRequests as $request) {
    // Build title
    if ($request['status'] === 'contact_request') {
        $title = htmlspecialchars($request['name'], ENT_XML1, 'UTF-8');
    } else {
        $title = 'New Partner Request: ' . htmlspecialchars($request['name'], ENT_XML1, 'UTF-8');
    }
    
    // Build description
    $description = '';
    
    if ($request['status'] === 'contact_request') {
        $contact = $request['contact_details'];
        $description = "New contact request for " . htmlspecialchars($contact['partner_name']) . "\n\n";
        $description .= "Name: " . htmlspecialchars($contact['name']) . "\n";
        $description .= "Email: " . htmlspecialchars($contact['email']) . "\n";
        if (!empty($contact['idir'])) {
            $description .= "IDIR: " . htmlspecialchars($contact['idir']) . "\n";
        }
        if (!empty($contact['title'])) {
            $description .= "Title: " . htmlspecialchars($contact['title']) . "\n";
        }
        if (!empty($contact['role'])) {
            $description .= "Role: " . htmlspecialchars($contact['role']) . "\n";
        }
    } else {
        $description = htmlspecialchars($request['description'] ?? 'No description provided');
        
        // Add metadata to description
        if (!empty($request['link'])) {
            $description .= "\n\nWebsite: " . htmlspecialchars($request['link']);
        }
        
        if (!empty($request['employee_facing_contact'])) {
            $description .= "\nEmployee Facing Contact: " . htmlspecialchars($request['employee_facing_contact']);
        }
        
        if (!empty($request['contacts']) && is_array($request['contacts'])) {
            $description .= "\n\nContacts:";
            foreach ($request['contacts'] as $contact) {
                $description .= "\n- " . htmlspecialchars($contact['name'] ?? 'Unknown') . 
                               " (" . htmlspecialchars($contact['email'] ?? 'No email') . ")";
            }
        }
    }
    
    // Format dates
    $pubDate = date(DATE_RSS, $request['request_date']);
    
    // Build the partner request URL
    if ($request['status'] === 'contact_request') {
        $requestUrl = 'https://gww.bcpublicservice.gov.bc.ca/lsapp/partners/dashboard.php#contact-requests';
    } else {
        $requestUrl = 'https://gww.bcpublicservice.gov.bc.ca/lsapp/partners/form.php?partnerid=' . 
                     urlencode($request['id']);
    }
    
    // Add the item to the RSS feed
    $rss .= '<item>' . "\n";
    $rss .= '<title>' . $title . '</title>' . "\n";
    $rss .= '<link>' . $requestUrl . '</link>' . "\n";
    $rss .= '<description><![CDATA[' . nl2br($description) . ']]></description>' . "\n";
    $rss .= '<guid isPermaLink="true">' . $requestUrl . '</guid>' . "\n";
    $rss .= '<pubDate>' . $pubDate . '</pubDate>' . "\n";
    
    // Add category tags
    if ($request['status'] === 'contact_request') {
        $rss .= '<category>Contact Request</category>' . "\n";
    } else {
        $rss .= '<category>Partner Request</category>' . "\n";
    }
    
    $rss .= '</item>' . "\n";
}

$rss .= '</channel>' . "\n";
$rss .= '</rss>';

// Write the RSS feed to a file with UTF-8 encoding
$rssFilename = 'data/partner-requests.xml';
// Ensure the content is UTF-8 encoded
$rss = mb_convert_encoding($rss, 'UTF-8', 'UTF-8');
file_put_contents($rssFilename, $rss);

// Copy to the web-accessible location
$targetFile = 'E:/WebSites/NonSSOLearning/learning-hub/partner-requests.xml';
if (!copy($rssFilename, $targetFile)) {
    echo 'Failed to copy ' . $rssFilename . ' to ' . $targetFile;
    exit;
}

// Redirect to the next step
header('Location: index.php?message=Success');
