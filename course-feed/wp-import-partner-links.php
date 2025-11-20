<?php
/**
 * Import partner URLs from WordPress XML export
 *
 * Parses learninghub.WordPress.YYYY-MM-DD.xml for learning_partner post types,
 * extracts the partner-url from postmeta, and updates the link value in partners.json
 */

date_default_timezone_set('America/Vancouver');

$xmlFile = '../data/learninghub.WordPress.2025-11-20.xml';
$partnersFile = '../data/partners.json';
$logFile = '../data/partner-links-update-log-' . date('Ymd_His') . '.txt';

// Load the XML file
if (!file_exists($xmlFile)) {
    die("WXR XML file not found: $xmlFile\n");
}

$xml = simplexml_load_file($xmlFile, 'SimpleXMLElement', LIBXML_NOCDATA);
$xml->registerXPathNamespace('wp', 'http://wordpress.org/export/1.2/');
$xml->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');
$xml->registerXPathNamespace('content', 'http://purl.org/rss/1.0/modules/content/');

// Find all wp:term elements
$terms = $xml->xpath('//wp:term');

// Load existing partners.json
if (!file_exists($partnersFile)) {
    die("Partners JSON file not found: $partnersFile\n");
}

$partnersJson = file_get_contents($partnersFile);
$partners = json_decode($partnersJson, true);

if ($partners === null) {
    die("Failed to parse partners.json\n");
}

// Build a lookup by slug for matching
$partnersBySlug = [];
foreach ($partners as $index => $partner) {
    if (!empty($partner['slug'])) {
        $partnersBySlug[$partner['slug']] = $index;
    }
}

// Extract partner URLs from XML
$partnerUrls = [];
$log = [];

foreach ($terms as $term) {
    $termTaxonomy = (string)$term->children('wp', true)->term_taxonomy;
    if ($termTaxonomy !== 'learning_partner') continue;

    $termName = trim((string)$term->children('wp', true)->term_name);
    $termSlug = (string)$term->children('wp', true)->term_slug;

    // Extract partner-url from termmeta
    $partnerUrl = '';
    foreach ($term->children('wp', true)->termmeta as $meta) {
        $metaKey = (string)$meta->children('wp', true)->meta_key;
        if ($metaKey === 'partner-url' || $metaKey === 'partner_url') {
            $partnerUrl = (string)$meta->children('wp', true)->meta_value;
            break;
        }
    }

    if (!empty($partnerUrl)) {
        $partnerUrls[$termSlug] = [
            'title' => $termName,
            'url' => $partnerUrl
        ];
    }
}

// Update partners with URLs from XML
$updated = 0;
$notFound = [];

foreach ($partnerUrls as $slug => $data) {
    if (isset($partnersBySlug[$slug])) {
        $index = $partnersBySlug[$slug];
        $oldLink = $partners[$index]['link'] ?? '';
        $partners[$index]['link'] = $data['url'];

        if ($oldLink !== $data['url']) {
            $log[] = "Updated: {$data['title']} (slug: $slug)";
            $log[] = "  Old: $oldLink";
            $log[] = "  New: {$data['url']}";
            $log[] = "";
            $updated++;
        }
    } else {
        $notFound[] = "Not found in partners.json: {$data['title']} (slug: $slug, url: {$data['url']})";
    }
}

// Save updated partners.json
if ($updated > 0) {
    $updatedJson = json_encode($partners, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    file_put_contents($partnersFile, $updatedJson);
}

// Write log file
$logContent = "Partner Links Import Log - " . date('Y-m-d H:i:s') . "\n";
$logContent .= "========================================\n\n";
$logContent .= "XML File: $xmlFile\n";
$logContent .= "Partners found in XML: " . count($partnerUrls) . "\n";
$logContent .= "Partners updated: $updated\n\n";

if (!empty($log)) {
    $logContent .= "Updates:\n";
    $logContent .= "--------\n";
    $logContent .= implode("\n", $log) . "\n\n";
}

if (!empty($notFound)) {
    $logContent .= "Not Found (could not match slug):\n";
    $logContent .= "---------------------------------\n";
    $logContent .= implode("\n", $notFound) . "\n";
}

file_put_contents($logFile, $logContent);

echo "Partner links import complete.\n";
echo "Partners found in XML: " . count($partnerUrls) . "\n";
echo "Partners updated: $updated\n";
echo "Log written to: $logFile\n";

if (!empty($notFound)) {
    echo "\nWarning: " . count($notFound) . " partner(s) not found in partners.json\n";
}
