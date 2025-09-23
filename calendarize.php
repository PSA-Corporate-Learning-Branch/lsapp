<?php 
opcache_reset();
require('inc/lsapp.php');

// Load calendars data
$calendarsJson = file_get_contents('data/calendars.json');
$calendars = json_decode($calendarsJson, true);
if (!is_array($calendars)) {
    $calendars = [];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_calendar':
            $newCalendar = [
                'id' => uniqid('cal_'),
                'name' => sanitizeText($_POST['calendar_name'] ?? ''),
                'description' => sanitizeText($_POST['calendar_description'] ?? ''),
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => LOGGED_IN_IDIR,
                'events' => []
            ];
            $calendars[] = $newCalendar;
            file_put_contents('data/calendars.json', json_encode($calendars, JSON_PRETTY_PRINT));
            header('Location: calendarize.php?success=Calendar created');
            exit;
            break;
            
        case 'update_calendar':
            $calendarId = $_POST['calendar_id'] ?? '';
            foreach ($calendars as &$calendar) {
                if ($calendar['id'] === $calendarId) {
                    $calendar['name'] = sanitizeText($_POST['calendar_name'] ?? '');
                    $calendar['description'] = sanitizeText($_POST['calendar_description'] ?? '');
                    $calendar['updated_at'] = date('Y-m-d H:i:s');
                    $calendar['updated_by'] = LOGGED_IN_IDIR;
                    break;
                }
            }
            file_put_contents('data/calendars.json', json_encode($calendars, JSON_PRETTY_PRINT));
            header('Location: calendarize.php?success=Calendar updated');
            exit;
            break;
            
        case 'delete_calendar':
            $calendarId = $_POST['calendar_id'] ?? '';
            $calendars = array_filter($calendars, function($cal) use ($calendarId) {
                return $cal['id'] !== $calendarId;
            });
            $calendars = array_values($calendars); // Re-index array
            file_put_contents('data/calendars.json', json_encode($calendars, JSON_PRETTY_PRINT));
            header('Location: calendarize.php?success=Calendar deleted');
            exit;
            break;
            
        case 'create_event':
            $calendarId = $_POST['calendar_id'] ?? '';
            $newEvent = [
                'id' => uniqid('evt_'),
                'title' => sanitizeText($_POST['event_title'] ?? ''),
                'description' => sanitizeText($_POST['event_description'] ?? ''),
                'start_date' => $_POST['event_start_date'] ?? '',
                'end_date' => $_POST['event_end_date'] ?? '',
                'location' => sanitizeText($_POST['event_location'] ?? ''),
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => LOGGED_IN_IDIR
            ];
            
            foreach ($calendars as &$calendar) {
                if ($calendar['id'] === $calendarId) {
                    if (!isset($calendar['events'])) {
                        $calendar['events'] = [];
                    }
                    $calendar['events'][] = $newEvent;
                    break;
                }
            }
            file_put_contents('data/calendars.json', json_encode($calendars, JSON_PRETTY_PRINT));
            header('Location: calendarize.php?success=Event created');
            exit;
            break;
            
        case 'update_event':
            $calendarId = $_POST['calendar_id'] ?? '';
            $eventId = $_POST['event_id'] ?? '';
            
            foreach ($calendars as &$calendar) {
                if ($calendar['id'] === $calendarId) {
                    foreach ($calendar['events'] as &$event) {
                        if ($event['id'] === $eventId) {
                            $event['title'] = sanitizeText($_POST['event_title'] ?? '');
                            $event['description'] = sanitizeText($_POST['event_description'] ?? '');
                            $event['start_date'] = $_POST['event_start_date'] ?? '';
                            $event['end_date'] = $_POST['event_end_date'] ?? '';
                            $event['location'] = sanitizeText($_POST['event_location'] ?? '');
                            $event['updated_at'] = date('Y-m-d H:i:s');
                            $event['updated_by'] = LOGGED_IN_IDIR;
                            break 2;
                        }
                    }
                }
            }
            file_put_contents('data/calendars.json', json_encode($calendars, JSON_PRETTY_PRINT));
            header('Location: calendarize.php?success=Event updated');
            exit;
            break;
            
        case 'delete_event':
            $calendarId = $_POST['calendar_id'] ?? '';
            $eventId = $_POST['event_id'] ?? '';
            
            foreach ($calendars as &$calendar) {
                if ($calendar['id'] === $calendarId) {
                    $calendar['events'] = array_filter($calendar['events'], function($evt) use ($eventId) {
                        return $evt['id'] !== $eventId;
                    });
                    $calendar['events'] = array_values($calendar['events']); // Re-index array
                    break;
                }
            }
            file_put_contents('data/calendars.json', json_encode($calendars, JSON_PRETTY_PRINT));
            header('Location: calendarize.php?success=Event deleted');
            exit;
            break;
            
        case 'publish_calendar':
            $calendarId = $_POST['calendar_id'] ?? '';
            $targetCalendar = null;
            
            foreach ($calendars as $calendar) {
                if ($calendar['id'] === $calendarId) {
                    $targetCalendar = $calendar;
                    break;
                }
            }
            
            if ($targetCalendar) {
                // Create slugified name
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $targetCalendar['name'])));
                $slug = preg_replace('/-+/', '-', $slug); // Remove multiple dashes
                $slug = trim($slug, '-'); // Remove leading/trailing dashes
                
                // Create directory path
                $calendarDir = 'E:\\WebSites\\NonSSOLearning\\calendars\\' . $slug;
                $calendarRootDir = 'E:\\WebSites\\NonSSOLearning\\calendars';
                // $calendarDir = 'calendars/' . $slug;
                
                // Create directory if it doesn't exist
                if (!file_exists($calendarDir)) {
                    mkdir($calendarDir, 0777, true);
                }
                
                // Copy webcal.php to the calendars root if it doesn't exist
                $webcalTarget = $calendarRootDir . '\\webcal.php';
                if (!file_exists($webcalTarget)) {
                    copy('webcal-publish.php', $webcalTarget);
                }
                
                // Generate iCal content
                $ical = "BEGIN:VCALENDAR\r\n";
                $ical .= "VERSION:2.0\r\n";
                $ical .= "PRODID:-//BC Public Service//LSApp Calendar//EN\r\n";
                $ical .= "CALSCALE:GREGORIAN\r\n";
                $ical .= "METHOD:PUBLISH\r\n";
                $ical .= "X-WR-CALNAME:" . $targetCalendar['name'] . "\r\n";
                $ical .= "X-WR-CALDESC:" . str_replace("\n", "\\n", $targetCalendar['description']) . "\r\n";
                $ical .= "X-WR-TIMEZONE:America/Los_Angeles\r\n";
                // Add refresh interval hint for Outlook (in minutes)
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
                        
                        // Add DTSTAMP (current timestamp when event is created/modified)
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
                            $ical .= "LAST-MODIFIED:" . $createdAt . "\r\n";
                        }
                        
                        $ical .= "END:VEVENT\r\n";
                    }
                }
                
                $ical .= "END:VCALENDAR\r\n";
                
                // Write to file
                $filename = $calendarDir . '\\calendar.ics';
                file_put_contents($filename, $ical);
                
                // Also write a JSON version of just this calendar
                $jsonFilename = $calendarDir . '\\calendar.json';
                file_put_contents($jsonFilename, json_encode($targetCalendar, JSON_PRETTY_PRINT));
                
                // Store publish info in calendar data
                foreach ($calendars as &$calendar) {
                    if ($calendar['id'] === $calendarId) {
                        $calendar['published'] = [
                            'slug' => $slug,
                            'path' => $filename,
                            'url' => 'https://learn.bcpublicservice.gov.bc.ca/calendars/' . $slug . '/calendar.ics',
                            'published_at' => date('Y-m-d H:i:s'),
                            'published_by' => LOGGED_IN_IDIR
                        ];
                        break;
                    }
                }
                file_put_contents('data/calendars.json', json_encode($calendars, JSON_PRETTY_PRINT));
                
                header('Location: calendarize.php?success=Calendar published successfully');
                exit;
            }
            break;
    }
}

$message = $_GET['success'] ?? '';
?>

<?php if(canAccess()): ?>
<?php getHeader() ?>
<title>Calendarize!</title>
<?php getScripts() ?>
<?php getNavigation() ?>
<link rel="stylesheet" href="css/summernote-bs4.css">

<div class="container">
    <div class="row justify-content-md-center mb-3">
        <div class="col-md-10">
            <h1>Calendar Management</h1>
            
            <?php if($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <div class="mb-4">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCalendarModal">
                    Create New Calendar
                </button>
            </div>
            
            <?php if(empty($calendars)): ?>
                <p>No calendars created yet.</p>
            <?php else: ?>
                <?php foreach($calendars as $calendar): ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="mb-0"><?= htmlspecialchars($calendar['name']) ?></h3>
                        <div>
                            <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#editCalendarModal<?= $calendar['id'] ?>">
                                Edit
                            </button>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createEventModal<?= $calendar['id'] ?>">
                                Add Event
                            </button>
                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#publishCalendarModal<?= $calendar['id'] ?>">
                                Publish
                            </button>
                            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteCalendarModal<?= $calendar['id'] ?>">
                                Delete
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if($calendar['description']): ?>
                        <p class="text-muted"><?= htmlspecialchars($calendar['description']) ?></p>
                        <?php endif; ?>
                        
                        <?php if(isset($calendar['published'])): ?>
                        <div class="alert alert-info">
                            <strong>Published:</strong> This calendar is available at:<br>
                            <strong>Static URL:</strong> <code><?= htmlspecialchars($calendar['published']['url']) ?></code>
                            <button class="btn btn-sm btn-outline-secondary ms-2" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($calendar['published']['url']) ?>')">Copy</button>
                            <br>
                            <strong>Dynamic URL (recommended for Outlook):</strong> <code>https://learn.bcpublicservice.gov.bc.ca/calendars/webcal.php?calendar=<?= htmlspecialchars($calendar['published']['slug']) ?></code>
                            <button class="btn btn-sm btn-outline-secondary ms-2" onclick="navigator.clipboard.writeText('https://learn.bcpublicservice.gov.bc.ca/calendars/webcal.php?calendar=<?= htmlspecialchars($calendar['published']['slug']) ?>')">Copy</button>
                            <br>
                            <small class="text-muted">Last published: <?= $calendar['published']['published_at'] ?> by <?= $calendar['published']['published_by'] ?></small>
                        </div>
                        <?php endif; ?>
                        
                        <?php if(empty($calendar['events'])): ?>
                            <p class="text-muted">No events in this calendar yet.</p>
                        <?php else: ?>
                            <h4>Events:</h4>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Location</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($calendar['events'] as $event): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($event['title']) ?></td>
                                            <td><?= htmlspecialchars($event['start_date']) ?></td>
                                            <td><?= htmlspecialchars($event['end_date']) ?></td>
                                            <td><?= htmlspecialchars($event['location']) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#editEventModal<?= $event['id'] ?>">
                                                    Edit
                                                </button>
                                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteEventModal<?= $event['id'] ?>">
                                                    Delete
                                                </button>
                                            </td>
                                        </tr>
                                        
                                        <!-- Edit Event Modal -->
                                        <div class="modal fade" id="editEventModal<?= $event['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST">
                                                        <input type="hidden" name="action" value="update_event">
                                                        <input type="hidden" name="calendar_id" value="<?= $calendar['id'] ?>">
                                                        <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Edit Event</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">Event Title</label>
                                                                <input type="text" class="form-control" name="event_title" value="<?= htmlspecialchars($event['title']) ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Description</label>
                                                                <textarea class="form-control" name="event_description" rows="3"><?= htmlspecialchars($event['description']) ?></textarea>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Start Date</label>
                                                                <input type="datetime-local" class="form-control" name="event_start_date" value="<?= str_replace(' ', 'T', $event['start_date']) ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">End Date</label>
                                                                <input type="datetime-local" class="form-control" name="event_end_date" value="<?= str_replace(' ', 'T', $event['end_date']) ?>">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Location</label>
                                                                <input type="text" class="form-control" name="event_location" value="<?= htmlspecialchars($event['location']) ?>">
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Update Event</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Delete Event Modal -->
                                        <div class="modal fade" id="deleteEventModal<?= $event['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST">
                                                        <input type="hidden" name="action" value="delete_event">
                                                        <input type="hidden" name="calendar_id" value="<?= $calendar['id'] ?>">
                                                        <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Delete Event</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to delete the event "<?= htmlspecialchars($event['title']) ?>"?</p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger">Delete Event</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Edit Calendar Modal -->
                <div class="modal fade" id="editCalendarModal<?= $calendar['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_calendar">
                                <input type="hidden" name="calendar_id" value="<?= $calendar['id'] ?>">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Calendar</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Calendar Name</label>
                                        <input type="text" class="form-control" name="calendar_name" value="<?= htmlspecialchars($calendar['name']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="calendar_description" rows="3"><?= htmlspecialchars($calendar['description']) ?></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Update Calendar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Delete Calendar Modal -->
                <div class="modal fade" id="deleteCalendarModal<?= $calendar['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST">
                                <input type="hidden" name="action" value="delete_calendar">
                                <input type="hidden" name="calendar_id" value="<?= $calendar['id'] ?>">
                                <div class="modal-header">
                                    <h5 class="modal-title">Delete Calendar</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Are you sure you want to delete the calendar "<?= htmlspecialchars($calendar['name']) ?>" and all its events?</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger">Delete Calendar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Create Event Modal -->
                <div class="modal fade" id="createEventModal<?= $calendar['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST">
                                <input type="hidden" name="action" value="create_event">
                                <input type="hidden" name="calendar_id" value="<?= $calendar['id'] ?>">
                                <div class="modal-header">
                                    <h5 class="modal-title">Create Event in <?= htmlspecialchars($calendar['name']) ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Event Title</label>
                                        <input type="text" class="form-control" name="event_title" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="event_description" rows="3"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Start Date</label>
                                        <input type="datetime-local" class="form-control" name="event_start_date" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">End Date</label>
                                        <input type="datetime-local" class="form-control" name="event_end_date">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Location</label>
                                        <input type="text" class="form-control" name="event_location">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Create Event</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Publish Calendar Modal -->
                <div class="modal fade" id="publishCalendarModal<?= $calendar['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST">
                                <input type="hidden" name="action" value="publish_calendar">
                                <input type="hidden" name="calendar_id" value="<?= $calendar['id'] ?>">
                                <div class="modal-header">
                                    <h5 class="modal-title">Publish Calendar</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Publishing this calendar will create a static .ics file that can be subscribed to in Outlook or other calendar applications.</p>
                                    
                                    <?php if(isset($calendar['published'])): ?>
                                    <div class="alert alert-warning">
                                        <strong>Note:</strong> This calendar was last published on <?= $calendar['published']['published_at'] ?>. Publishing again will update the file with the latest events.
                                    </div>
                                    <?php endif; ?>
                                    
                                    <p>The calendar will be available at:</p>
                                    <?php 
                                    $previewSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $calendar['name'])));
                                    $previewSlug = preg_replace('/-+/', '-', $previewSlug);
                                    $previewSlug = trim($previewSlug, '-');
                                    ?>
                                    <p><strong>Static file:</strong><br>
                                    <code>https://learn.bcpublicservice.gov.bc.ca/calendars/<?= $previewSlug ?>/calendar.ics</code></p>
                                    
                                    <p><strong>Dynamic URL (recommended for Outlook):</strong><br>
                                    <code>https://learn.bcpublicservice.gov.bc.ca/calendars/webcal.php?calendar=<?= $previewSlug ?></code></p>
                                    
                                    <div class="alert alert-warning">
                                        <strong>Note for Outlook users:</strong> Use the dynamic URL for better refresh behavior. Outlook may cache the static file and not update it regularly.
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-success">Publish Calendar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create Calendar Modal -->
<div class="modal fade" id="createCalendarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create_calendar">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Calendar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Calendar Name</label>
                        <input type="text" class="form-control" name="calendar_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="calendar_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Calendar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require('templates/javascript.php') ?>
<?php require('templates/footer.php') ?>

<?php else: ?>
<?php require('templates/noaccess.php') ?>
<?php endif ?>