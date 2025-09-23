<?php
// This file should be placed in the web-accessible calendar directory
// It reads the published JSON file and serves it as an iCalendar

// Get the calendar slug from the URL
$slug = $_GET['calendar'] ?? '';

if (empty($slug)) {
    http_response_code(404);
    die('Calendar not found');
}

// Sanitize the slug to prevent directory traversal
$slug = preg_replace('/[^a-z0-9-]/', '', strtolower($slug));

// Read the calendar JSON from the published location
$jsonPath = __DIR__ . '/' . $slug . '/calendar.json';

if (!file_exists($jsonPath)) {
    http_response_code(404);
    die('Calendar not found');
}

$calendarJson = file_get_contents($jsonPath);
$targetCalendar = json_decode($calendarJson, true);

if (!$targetCalendar) {
    http_response_code(500);
    die('Invalid calendar data');
}

// Set proper headers for iCal file
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="' . $slug . '.ics"');

// Cache control headers to help with Outlook updates
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('ETag: "' . md5(json_encode($targetCalendar)) . '"');

// Generate iCal content
$ical = "BEGIN:VCALENDAR\r\n";
$ical .= "VERSION:2.0\r\n";
$ical .= "PRODID:-//BC Public Service//LSApp Calendar//EN\r\n";
$ical .= "CALSCALE:GREGORIAN\r\n";
$ical .= "METHOD:PUBLISH\r\n";
$ical .= "X-WR-CALNAME:" . $targetCalendar['name'] . "\r\n";
$ical .= "X-WR-CALDESC:" . str_replace("\n", "\\n", $targetCalendar['description']) . "\r\n";
$ical .= "X-WR-TIMEZONE:America/Los_Angeles\r\n";
// Add refresh interval hint for Outlook (15 minutes)
$ical .= "X-PUBLISHED-TTL:PT15M\r\n";
$ical .= "REFRESH-INTERVAL;VALUE=DURATION:PT15M\r\n";

// Add timezone definition
$ical .= "BEGIN:VTIMEZONE\r\n";
$ical .= "TZID:America/Los_Angeles\r\n";
$ical .= "BEGIN:DAYLIGHT\r\n";
$ical .= "TZOFFSETFROM:-0800\r\n";
$ical .= "TZOFFSETTO:-0700\r\n";
$ical .= "TZNAME:PDT\r\n";
$ical .= "DTSTART:19700308T020000\r\n";
$ical .= "RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=2SU\r\n";
$ical .= "END:DAYLIGHT\r\n";
$ical .= "BEGIN:STANDARD\r\n";
$ical .= "TZOFFSETFROM:-0700\r\n";
$ical .= "TZOFFSETTO:-0800\r\n";
$ical .= "TZNAME:PST\r\n";
$ical .= "DTSTART:19701101T020000\r\n";
$ical .= "RRULE:FREQ=YEARLY;BYMONTH=11;BYDAY=1SU\r\n";
$ical .= "END:STANDARD\r\n";
$ical .= "END:VTIMEZONE\r\n";

// Add events
if (!empty($targetCalendar['events'])) {
    foreach ($targetCalendar['events'] as $event) {
        $ical .= "BEGIN:VEVENT\r\n";
        $ical .= "UID:" . $event['id'] . "@lsapp.gov.bc.ca\r\n";
        
        // Add DTSTAMP (current timestamp when calendar is generated)
        $ical .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        
        // Convert datetime format properly
        // Input format: 2025-10-13T09:15
        $startDT = DateTime::createFromFormat('Y-m-d\TH:i', $event['start_date']);
        if ($startDT) {
            $ical .= "DTSTART;TZID=America/Los_Angeles:" . $startDT->format('Ymd\THis') . "\r\n";
        } else {
            // Fallback for date-only format
            $startDT = DateTime::createFromFormat('Y-m-d', $event['start_date']);
            if ($startDT) {
                $ical .= "DTSTART;VALUE=DATE:" . $startDT->format('Ymd') . "\r\n";
            }
        }
        
        if (!empty($event['end_date'])) {
            $endDT = DateTime::createFromFormat('Y-m-d\TH:i', $event['end_date']);
            if ($endDT) {
                $ical .= "DTEND;TZID=America/Los_Angeles:" . $endDT->format('Ymd\THis') . "\r\n";
            } else {
                // Fallback for date-only format
                $endDT = DateTime::createFromFormat('Y-m-d', $event['end_date']);
                if ($endDT) {
                    $ical .= "DTEND;VALUE=DATE:" . $endDT->format('Ymd') . "\r\n";
                }
            }
        } else {
            // If no end date, make it 1 hour after start
            if ($startDT) {
                $endDT = clone $startDT;
                $endDT->add(new DateInterval('PT1H'));
                $ical .= "DTEND;TZID=America/Los_Angeles:" . $endDT->format('Ymd\THis') . "\r\n";
            }
        }
        
        // Escape special characters in text fields
        $summary = str_replace([',', ';', '\\', "\n"], ['\,', '\;', '\\\\', '\\n'], $event['title']);
        $ical .= "SUMMARY:" . $summary . "\r\n";
        
        if (!empty($event['description'])) {
            $description = str_replace([',', ';', '\\', "\n"], ['\,', '\;', '\\\\', '\\n'], $event['description']);
            $ical .= "DESCRIPTION:" . $description . "\r\n";
        }
        
        if (!empty($event['location'])) {
            $location = str_replace([',', ';', '\\', "\n"], ['\,', '\;', '\\\\', '\\n'], $event['location']);
            $ical .= "LOCATION:" . $location . "\r\n";
        }
        
        $ical .= "STATUS:CONFIRMED\r\n";
        $ical .= "TRANSP:OPAQUE\r\n";
        
        // Add creation/modification timestamps
        if (!empty($event['created_at'])) {
            $createdAt = gmdate('Ymd\THis\Z', strtotime($event['created_at']));
            $ical .= "CREATED:" . $createdAt . "\r\n";
        }
        
        // Use current time as LAST-MODIFIED to help with updates
        $ical .= "LAST-MODIFIED:" . gmdate('Ymd\THis\Z') . "\r\n";
        
        $ical .= "END:VEVENT\r\n";
    }
}

$ical .= "END:VCALENDAR\r\n";

// Output the calendar
echo $ical;