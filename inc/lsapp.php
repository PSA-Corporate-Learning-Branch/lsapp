<?php
opcache_reset();
//
//
// Learning Support Administration Application (LSAPP)
//

date_default_timezone_set('America/Los_Angeles');

define('SLASH', DIRECTORY_SEPARATOR);
$docroot = $_SERVER['DOCUMENT_ROOT'] . '/lsapp//';
define('BASE_DIR', $docroot);

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function build_path(...$segments) {
    return implode(SLASH, $segments);
}

// 
// getHeader, getScripts, and other page layout/template functions
//
require('layout.php');

//
// $_SERVER["REMOTE_USER"] comes out as IDIR/AHAGGETT, but I'm not overly interested in the 
// IDIR bit, so this just strips it off and makes it lowercase for nicer display
//
function stripIDIR($idir) {
	
	$justuser = explode('\\', $idir);
	return strtolower($justuser[1]);
	
}
define('LOGGED_IN_IDIR', stripIDIR($_SERVER["REMOTE_USER"]));
// define('LOGGED_IN_IDIR', 'ahaggett');

// Last synchronization message for everywhere
$today = date('Y-m-d');
$lastsyncmessage = '';

$path = build_path(BASE_DIR, 'data', 'elm.csv');

$lastsync = date ("Y-m-d", filemtime($path));
if($lastsync != $today) {
	$lastsyncmessage = '<span class="badge bg-dark text-white d-inline-block me-2"';
	$lastsyncmessage .=	'title="It has been more than 24 hours since LSApp was ';
	$lastsyncmessage .=	'last synchronized with ELM. Please ask an admin to sync!">';
	$lastsyncmessage .=	'OUT OF SYNC!';
	$lastsyncmessage .=	'</span>';
	$lastsyncmessage .=	'<a href="/lsapp/elm-sync-upload.php">';
	$lastsyncmessage .=	'Sync now';
	$lastsyncmessage .=	'</a>';
}

$people = getPeopleAll();
$lsapppeople = count($people); 


// Helper function to sanitize text
function sanitizeText($text) {
    // Step 1: Remove any invalid UTF-8 sequences
    $cleanText = iconv('UTF-8', 'UTF-8//IGNORE', $text);

    // Step 2: Replace common Word characters with standard equivalents
    $wordCharacters = [
        "\xE2\x80\x9C" => '"', // Left double quote
        "\xE2\x80\x9D" => '"', // Right double quote
        "\xE2\x80\x98" => "'", // Left single quote
        "\xE2\x80\x99" => "'", // Right single quote
        "\xE2\x80\x93" => "-", // En dash
        "\xE2\x80\x94" => "-", // Em dash
        "\xC2\xA0" => " ",     // Non-breaking space
        "\xE2\x80\xA6" => "...", // Ellipsis
        "\xE2\x80\xB9" => "<", // Single left-pointing angle quotation
        "\xE2\x80\xBA" => ">", // Single right-pointing angle quotation
        "\xC2\xAD" => "",      // Soft hyphen (remove)
    ];
    $cleanText = strtr($cleanText, $wordCharacters);

    // Step 3: Remove other control characters except newlines
    $cleanText = preg_replace('/[^\P{C}\n]+/u', '', $cleanText);

    // Step 4: Normalize whitespace (remove excessive spaces)
    $cleanText = preg_replace('/\s+/', ' ', $cleanText); // Replace multiple spaces with a single space
    $cleanText = trim($cleanText); // Trim leading and trailing whitespace

    return $cleanText;
}

// TODO this file really ought to be broken up into separate files?
// 2023-03-23 - There are currently 87 functions in this file

// Get the details of a single class based on it classID 
// classID are simple timestamps YMDHis 
// (e.g. 201908161302 - August 16th 2019 at 1:02pm). 
// At this scale, I'll take my chances that two classes get entered at the exact 
// same second in time and this can easily be tweaked to add a salt to it if 
//
function getClass($cid) {
	
	$path = build_path(BASE_DIR, 'data', 'classes.csv');
	$f = fopen($path, 'r');
	$class = '';
	while ($row = fgetcsv($f)) {
		if($row[0] == $cid) {
			$class = $row;
		}
	}
	fclose($f);
	return $class;
}

//
// Get the details of a single class based on its ITEM CODE 
//
function getClassByItemCode($itemcode) {
	$path = build_path(BASE_DIR, 'data', 'classes.csv');
	$f = fopen($path, 'r');
	$class = '';
	while ($row = fgetcsv($f)) {
		if($row[7] == $itemcode) {
			$class = $row;
		}
	}
	fclose($f);
	return $class;
}


//
// Get all classes based on an ITEM CODE 
//
function getClassesByItemCode($itemcode) {
	$path = build_path(BASE_DIR, 'data', 'classes.csv');
	$f = fopen($path, 'r');
	$classes = array();
	while ($row = fgetcsv($f)) {
		if($row[7] != '' && $row[7] == $itemcode) {
			array_push($classes,$row);
		}
	}
	fclose($f);
	return $classes;
}



//
// Return all classes in a simple array
// We then manipulate the class data in context so that we're not creating
// a million functions in here.
//
function getClasses() {
	$path = build_path(BASE_DIR, 'data', 'classes.csv');
	$f = fopen($path, 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		array_push($list,$row);
	}
	fclose($f);
	return $list;
}

//
// Return all classes in a simple array
// We then manipulate the class data in context so that we're not creating
// a million functions in here.
//
function getELMClasses() {
	$path = build_path(BASE_DIR, 'data', 'elm.csv');
	$f = fopen($path, 'r');
	fgetcsv($f); // pop the headers off
	$list = array();
	while ($row = fgetcsv($f)) {
		array_push($list,$row);
	}
	fclose($f);
	return $list;
}

//
// Return all classes for a given courseID
//
function getCourseClasses($courseid) {
	$path = build_path(BASE_DIR, 'data', 'classes.csv');
	$f = fopen($path, 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		if($row[5] == $courseid) {
			if($row[1] != 'Deleted') {
				array_push($list,$row);
			}
		}
	}
	fclose($f);
	// Create a temp array to hold course names for sorting
	$tmp = array();
	// Loop through the whole classes and add start dates to the temp array
	foreach($list as $line) {
		$tmp[] = $line[8];
	}
	// Use the temp array to sort all the classes by start date
	array_multisort($tmp, SORT_ASC, $list);
	return $list;
}

//
// Return all classes for a given courseID
//
function getCourseAudits($courseid) {

	$path = build_path(BASE_DIR, 'data', 'reviews', 'audits.csv');
	$f = fopen($path, 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		if($row[3] == $courseid) {
			array_push($list,$row);
		}
	}
	fclose($f);

	return $list;
}


//
// Return just the next class for a given courseID
//
function getCourseNextClass($courseid) {
	$path = build_path(BASE_DIR, 'data', 'classes.csv');
	$f = fopen($path, 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		if($row[5] == $courseid) {
			if($row[1] == 'Active') {
				array_push($list,$row);
			}
		}
	}
	fclose($f);
	// Create a temp array to hold course names for sorting
	$tmp = array();
	// Loop through the whole classes and add start dates to the temp array
	foreach($list as $line) {
		$tmp[] = $line[8];
	}
	// Use the temp array to sort all the classes by start date
	array_multisort($tmp, SORT_DESC, $list);

	$classdate = array_pop($list);

	return $classdate;
}


//
// Return all classes for a given courseID
//
function getCoursesClasses($courseids) {
	
	$path = build_path(BASE_DIR, 'data', 'classes.csv');
	$f = fopen($path, 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		if(in_array($row[5],$courseids)) {
			array_push($list,$row);
		}
	}
	fclose($f);
	// Create a temp array to hold course names for sorting
	$tmp = array();
	// Loop through the whole classes and add start dates to the temp array
	foreach($list as $line) {
		$tmp[] = $line[8];
	}
	// Use the temp array to sort all the classes by start date
	array_multisort($tmp, SORT_ASC, $list);
	return $list;
}




//
// Return all classes for a given courseID
//
function getVenueClasses($venueid) {
	$path = build_path(BASE_DIR, 'data', 'classes.csv');
	$f = fopen($path, 'r');
	$list = array();
	$today = date('Y-m-d');
	while ($row = fgetcsv($f)) {
		if($row[23] == $venueid) {
			if($row[8] >= $today) {	
				array_push($list,$row);
			}
		}
	}
	// Create a temp array to hold course names for sorting
	$tmp = array();
	// Loop through the whole classes and add start dates to the temp array
	foreach($list as $line) {
		$tmp[] = $line[8];
	}
	// Use the temp array to sort all the classes by start date
	array_multisort($tmp, SORT_ASC, $list);
	fclose($f);
	return $list;
}



//
// Return all classes for a given courseID
//
function getVenueRooms($venueid) {
	$f = fopen('data/venue-rooms.csv', 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		if($row[1] == $venueid) {
			array_push($list,$row);
		}
	}
	fclose($f);
	return $list;
}


//
// Return all classes for a given user
// 
function getUserReviews($idir) {
	
	
	$path = build_path(BASE_DIR, 'data', 'reviews', 'audits.csv');
	$f = fopen($path, 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		if($row[2] == $idir) {
			array_push($list,$row);
		}
	}
	fclose($f);
	return $list;
}



//
// Return all classes for a given user
// 
function getUserRequested($idir) {
	
	$path = build_path(BASE_DIR, 'data', 'classes.csv');
	$f = fopen($path, 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		if($row[3] == $idir && $row[1] == 'Requested') {
			array_push($list,$row);
		}
	}
	// Create a temp array to hold course names for sorting
	$tmp = array();
	// Loop through the whole classes and add start dates to the temp array
	foreach($list as $line) {
			$tmp[] = $line[8];	
	}
	// Use the temp array to sort all the classes by start date
	array_multisort($tmp, SORT_ASC, $list);
	fclose($f);
	return $list;
}
//
// Return all classes a given admin has been assigned that 
// are still active
// 
function getUserFacilitatingAll($idir) {
	
	$path = build_path(BASE_DIR, 'data', 'classes.csv');
	$f = fopen($path, 'r');
	$list = array();
	$today = date('Y-m-d');
	while ($row = fgetcsv($f)) {
		if($row[1] != 'Deleted') {
			$facilitators = explode(' ', $row[14]);
			$i = str_replace('@','',$facilitators);
			if(in_array($idir,$i) && $row[1] != 'Closed') {
				array_push($list,$row);
			}
		}
	}
	fclose($f);

	// Create a temp array to hold course names for sorting
	$tmp = array();
	// Loop through the whole classes and add start dates to the temp array
	foreach($list as $line) {
			$tmp[] = $line[8];	
	}
	// Use the temp array to sort all the classes by start date
	array_multisort($tmp, SORT_ASC, $list);
	
	return $list;
}
//
// Return all classes a given admin has been assigned that 
// are still active
// 
function getUserFacilitating($idir) {
	
	$path = build_path(BASE_DIR, 'data', 'classes.csv');
	$f = fopen($path, 'r');
	$list = array();
	$today = date('Y-m-d');
	while ($row = fgetcsv($f)) {
		if($row[8] > $today && $row[1] != 'Deleted') {
			$facilitators = explode(' ', $row[14]);
			$i = str_replace('@','',$facilitators);
			if(in_array($idir,$i) && $row[1] != 'Closed') {
				array_push($list,$row);
			}
		}
	}
	fclose($f);

	// Create a temp array to hold course names for sorting
	$tmp = array();
	// Loop through the whole classes and add start dates to the temp array
	foreach($list as $line) {
			$tmp[] = $line[8];	
	}
	// Use the temp array to sort all the classes by start date
	array_multisort($tmp, SORT_ASC, $list);
	
	return $list;
}
//
// Return all changes for a given user
// 
function getUserChanges($idir) {
	//creqID,ClassID,DateRequested,RequestedBy,Status,CompletedBy,CompletedDate,Request
	//creqID,ClassID,CourseName,StartDate,City,DateRequested,RequestedBy,Status,CompletedBy,CompletedDate,Request
	$f = fopen('data/changes-class.csv', 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		if($row[6] == $idir && $row[7] != 'Completed') {
			array_push($list,$row);
		}
	}
	fclose($f);
	return $list;
}







//
// Return all functions for a given user
// 
function getUserFunctions($idir) {

	$f = fopen('data/functional-map-people.csv', 'r');
	$ulist = array();
	while ($row = fgetcsv($f)) {
		if($row[0] == $idir) {
			array_push($ulist,$row[1]);
		}
	}
	fclose($f);

	$funs = fopen('data/functional-map.csv', 'r');
	$funlist = array();
	while ($row = fgetcsv($funs)) {
		array_push($funlist,$row);
	}
	fclose($funs);

	$functions = [];
	foreach($ulist as $ufun) {
		foreach($funlist as $f) {
			if($ufun == $f[0]) {
				array_push($functions,$f);
			}
		}
	}
	asort($functions);
	return $functions;
}






//
// Return all classes requested a given person 
// 
function getUserRequestedAll($idir) {
	
	$today = date('Y-m-d');
	$path = build_path(BASE_DIR, 'data', 'classes.csv');
	$f = fopen($path, 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		
		if($row[3] == $idir) {
			array_push($list,$row);
		}
		
	}
	// Create a temp array to hold course names for sorting
	$tmp = array();
	// Loop through the whole classes and add start dates to the temp array
	foreach($list as $line) {
			$tmp[] = $line[8];	
	}
	// Use the temp array to sort all the classes by start date
	array_multisort($tmp, SORT_ASC, $list);
	fclose($f);
	return $list;
}
//
// Return all classes a given admin has been assigned that 
// are still active
// 
function getAdminAssigned($idir) {
	
	$today = date('Y-m-d');
	$path = build_path(BASE_DIR, 'data', 'classes.csv');
	$f = fopen($path, 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		
		if($row[44] == $idir && $row[1] == 'Requested') {
			array_push($list,$row);
		}
		
	}
	// Create a temp array to hold course names for sorting
	$tmp = array();
	// Loop through the whole classes and add start dates to the temp array
	foreach($list as $line) {
			$tmp[] = $line[8];	
	}
	// Use the temp array to sort all the classes by start date
	array_multisort($tmp, SORT_ASC, $list);
	fclose($f);
	return $list;
}
//
// Return all classes a given admin has been assigned that 
// are still active
// 
function getAdminAssignedCount($idir) {
	
	$today = date('Y-m-d');
	$path = build_path(BASE_DIR, 'data', 'classes.csv');
	$f = fopen($path, 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		
		if($row[44] == $idir) {
			array_push($list,$row);
		}
		
	}
	fclose($f);
	$number = count($list);
	return $number;
}
//
// Return all courses this person as an owner of
//
function getCoursesOwned($idir) {
	
	$f = fopen('data/courses.csv', 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		if($row[10] == $idir) {
			array_push($list,$row);
		}
	}
	fclose($f);
	return $list;
}

//
// Return all courses this person has claimed as part of taxonomy project.
//
function getCoursesClaimed($idir) {
	
	$f = fopen('data/courses.csv', 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		// We're repurposing 
		if($row[48] != $idir && $row[49] == $idir) {
			array_push($list,$row);
		}
	}
	fclose($f);
	return $list;
}


// Get the details of a single course based on it course ID
// courseID's are the courses' item code from ELM
//
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

// Get the details of a single course based on it course ID
// and return it as an associate array with the column names
// as the keys
//
function getCourseDeets($cid) {
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

// Get the details of a single course based on it course ID
// courseID's are the courses' item code from ELM
//
function getCourseByAbbreviation($cid) {
	
	$path = build_path(BASE_DIR, 'data', 'courses.csv');
	$f = fopen($path, 'r');
	$course = '';
	while ($row = fgetcsv($f)) {
		if(strtolower($row[3]) == strtolower($cid)) {
			$course = $row;
		}
	}
	fclose($f);
	return $course;
}


//
// Return _all_ (active or not) classes in a simple array
// We then manipulate the class data in context so that we're not creating
// a million functions in here.
function getCourses() {
	
	$path = build_path(BASE_DIR, 'data', 'courses.csv');
	$f = fopen($path, 'r');
	
	$list = array();
	while ($row = fgetcsv($f)) {
		
		array_push($list,$row);
		
	}
	fclose($f);
	return $list;
}




//
// Return only upcoming classes for a given courseID (today or later)
//
function getCoursesClassesUpcoming($courseid) {
    $path = build_path(BASE_DIR, 'data', 'classes.csv');
    $f = fopen($path, 'r');
    $list = array();
    $today = date('Y-m-d'); // Get today's date in YYYY-MM-DD format

    while ($row = fgetcsv($f)) {
        $startDate = $row[8]; // StartDate is in YYYY-MM-DD format

        // Include only future classes (today or later)
        if ($row[5] == $courseid && $startDate >= $today) {
            $list[] = $row;
        }
    }
    fclose($f);

    // Sort classes by start date (earliest first)
    usort($list, function($a, $b) {
        return strcmp($a[8], $b[8]);
    });

    return $list;
}











//
// Return _all_ (active or not) classes in a simple array
// We then manipulate the class data in context so that we're not creating
// a million functions in here.
function getPartners() {
	
	$path = build_path(BASE_DIR, 'data', 'learning_partners.json');
	$p = file_get_contents($path);
	$list = json_decode($p);
	return $list;
}
//
// Return _all_ (active or not) classes in a simple array
// We then manipulate the class data in context so that we're not creating
// a million functions in here.
function getPartnerDetails($partnername) {
	
	return '#todo Details unimplemted.';
	
}

//
// Get partner information from partners.json by ID
// Returns partner array or null if not found
//
function getPartnerById($partnerId) {
	if(empty($partnerId)) {
		return null;
	}
	
	$path = build_path(BASE_DIR, 'data', 'partners.json');
	if(!file_exists($path)) {
		return null;
	}
	
	$json = file_get_contents($path);
	$partners = json_decode($json, true);
	
	if(!$partners) {
		return null;
	}
	
	foreach($partners as $partner) {
		if($partner['id'] == $partnerId) {
			return $partner;
		}
	}
	
	return null;
}

//
// Get partner name by ID
// Returns partner name string or the ID if partner not found
//
function getPartnerNameById($partnerId) {
	if(empty($partnerId)) {
		return '';
	}
	
	$partner = getPartnerById($partnerId);
	if($partner) {
		return $partner['name'];
	}
	
	// Return the ID as fallback if partner not found
	return $partnerId;
}

//
// Get all partners from partners.json
// Returns array of partner objects
//
function getAllPartners() {
	$path = build_path(BASE_DIR, 'data', 'partners.json');
	if(!file_exists($path)) {
		return [];
	}
	
	$json = file_get_contents($path);
	$partners = json_decode($json, true);
	
	return $partners ? $partners : [];
}

//
// Get partner ID by name
// Returns partner ID or 372 (Unknown partner) if not found
//
function getPartnerIdByName($partnerName) {
	if(empty($partnerName)) {
		return 372; // Return Unknown partner ID for empty names
	}
	
	$partnerName = trim($partnerName);
	
	// Handle known variations
	if($partnerName === 'Learning Centre') {
		return 59; // Maps to PSA Corporate Learning Branch
	}
	if($partnerName === 'CIRMO') {
		return 78; // Maps to Corporate Information and Records Management Office
	}
	if($partnerName === 'Procurement Strategy and Governance Branch') {
		return 372; // Maps to Unknown partner
	}
	
	$partners = getAllPartners();
	foreach($partners as $partner) {
		if($partner['name'] === $partnerName) {
			return $partner['id'];
		}
	}
	
	// Return Unknown partner ID if not found
	return 372;
}

//
// Return all courses that have a given Learning Hub Partner by ID
//
function getCoursesByPartnerId($partnerId) {
	
	$path = build_path(BASE_DIR, 'data', 'courses.csv');
	$f = fopen($path, 'r');
	
	$list = array();
	while ($row = fgetcsv($f)) {
		if($row[36] == $partnerId) {
			array_push($list,$row);
		}
	}
	fclose($f);
	return $list;
}

//
// Return all courses that have a given Learning Hub Partner
// Now accepts either partner name (for backwards compatibility) or partner ID
//
function getCoursesByPartnerName($partnerIdentifier) {
	
	$path = build_path(BASE_DIR, 'data', 'courses.csv');
	$f = fopen($path, 'r');
	
	$list = array();
	while ($row = fgetcsv($f)) {
		// Check if identifier matches directly (for IDs) or by name lookup
		if($row[36] == $partnerIdentifier) {
			array_push($list,$row);
		} else if(is_numeric($partnerIdentifier)) {
			// If numeric, already checked above
			continue;
		} else {
			// If not numeric, it might be a name - check if this row's ID matches the name
			$partnerInfo = getPartnerById($row[36]);
			if($partnerInfo && $partnerInfo['name'] == $partnerIdentifier) {
				array_push($list,$row);
			}
		}
	}
	fclose($f);
	return $list;
}

















//
// Return all classes THAT ARE ACTIVE in a simple array
// We then manipulate the class data in context so that we're not creating
// a million functions in here.
function getCoursesActive() {
	
	$path = build_path(BASE_DIR, 'data', 'courses.csv');
	$f = fopen($path, 'r');
	
	$list = array();
	while ($row = fgetcsv($f)) {
		if($row[1] == 'Active') {
			array_push($list,$row);
		}
	}
	fclose($f);
	return $list;
}

// Outputs an HTML list of courses with a data-cid attribute
// I need both the course text and it's numerical ID, but if 
// we use a value='' here, then standard form operations replace
// the name with the value in the $_POST array; if no value='', it 
// uses the text in between the option <></>
// By using data-cid I can still put the numeric value in context
// with the name, and access it via javascript
function getCourseList() {
	
	$f = fopen('data/courses.csv', 'r');
	$list = '';
	while ($row = fgetcsv($f)) {
		$list .= '<option data-cid="' . $row[0] . '">' . $row[1] . '</option>';
	}
	fclose($f);
	print $list;
}

//
// Return all courses of a particualr category in a simple array
//
function getCoursesByCategory($category) {
	
	$path = build_path(BASE_DIR, 'data', 'courses.csv');
	$f = fopen($path, 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		$cats = explode(',',$row[20]);
		if(in_array($category,$cats)) {
			array_push($list,$row);
		}
	}
	fclose($f);
	return $list;
}



//
// Return all courses of a particualr topic in a simple array
//
function getCoursesByTopic($topic) {
	
	$path = build_path(BASE_DIR, 'data', 'courses.csv');
	$f = fopen($path, 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		if($row[1] != 'Active') continue;
		if($topic == $row[38]) {
			array_push($list,$row);
		}
	}
	fclose($f);
	return $list;
}
//
// Return all courses of a particualr audience in a simple array
//
function getCoursesByAudience($audience) {
	
	$path = build_path(BASE_DIR, 'data', 'courses.csv');
	$f = fopen($path, 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		if($row[1] != 'Active') continue;
		if($audience == $row[39]) {
			array_push($list,$row);
		}
	}
	fclose($f);
	return $list;
}

//
// Return all courses of a particualr level in a simple array
//
function getCoursesByLevels($levels) {
	
	$path = build_path(BASE_DIR, 'data', 'courses.csv');
	$f = fopen($path, 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		if($row[1] != 'Active') continue;
		if($levels == $row[40]) {
			array_push($list,$row);
		}
	}
	fclose($f);
	return $list;
}

//
// Return all courses of a particualr reporting mechanism in a simple array
//
function getCoursesByReporting($level) {
	
	$path = build_path(BASE_DIR, 'data', 'courses.csv');
	$f = fopen($path, 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		if($row[1] != 'Active') continue;
		if($level == $row[41]) {
			array_push($list,$row);
		}
	}
	fclose($f);
	return $list;
}




//
// Return all course categories in a simple array
//
function getCategories() {

	$path = build_path(BASE_DIR, 'data', 'categories.csv');
	$f = fopen($path, 'r');
	fgetcsv($f);
	$list = array();
	while ($row = fgetcsv($f)) {
		array_push($list,$row);
	}
	fclose($f);
	return $list;
}
//
// Return all course categories in a simple array
//
function getCategory($category_name) {

	$path = build_path(BASE_DIR, 'data', 'categories.csv');
	$f = fopen($path, 'r');
	fgetcsv($f);
	
	while ($row = fgetcsv($f)) {
		if($row[1] == $category_name) {
			$list = $row;
		}
	}
	fclose($f);
	return $list;
}
//
// Get the details of a single venue based on it VenueID 
//
function getVenue($vid) {
	$f = fopen('data/venues.csv', 'r');
	$class = '';
	while ($row = fgetcsv($f)) {
		if($row[0] == $vid) {
			$venue = $row;
		}
	}
	fclose($f);
	return $venue;
}
//
// Return all venues in a simple array
//
function getVenues() {
	$f = fopen('data/venues.csv', 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		array_push($list,$row);
	}
	fclose($f);
	return $list;
}

//
// Get a list of venues based on the city
// 
function getVenuesByCity($cityid) {
	$f = fopen('data/venues.csv', 'r');
	$venues = array();
	while ($row = fgetcsv($f)) {
		if($row[5] == $cityid) {
			array_push($venues,$row);
		}
	}
	fclose($f);
	return $venues;
}


function getCities() {
	$f = fopen('data/venues.csv', 'r');
	fgetcsv($f); // pop headers off
	$cities = array();
	while ($row = fgetcsv($f)) {
		
			array_push($cities,$row[5]);
		
	}
	fclose($f);
	$citylist = array_unique($cities);
	return $citylist;
}



//
// Return all checklist items for a given course
// 
function getChecklist($courseid) {
	
	$f = fopen('data/checklists.csv', 'r');
	$list = '';
	while ($row = fgetcsv($f)) {
		if($row[3] == $courseid) {
			$list = $row;
		}
	}
	fclose($f);
	return $list;
}


//
// Get a list of materials
// MaterialID,CourseName,CourseID,MaterialName,PerCourse,InStock,Partial,Restock,Notes,FileName
function getMaterialsAll() {
	$f = fopen('data/materials.csv', 'r');
	$materials = array();
	while ($row = fgetcsv($f)) {
		array_push($materials,$row);
	}
	fclose($f);
	return $materials;
}


//
// Get a list of materials based on the course
// MaterialID,CourseName,CourseID,MaterialName,PerCourse,InStock,Partial,Restock,Notes,FileName
function getMaterials($courseid) {
	$f = fopen('data/materials.csv', 'r');
	$materials = array();
	while ($row = fgetcsv($f)) {
		if($row[2] == $courseid) {
			array_push($materials,$row);
		}
	}
	fclose($f);
	return $materials;
}


//
// Get a material based on its ID
// MaterialID,CourseName,CourseID,MaterialName,PerCourse,InStock,Partial,Restock,Notes,FileName
//
function getMaterial($matid) {
	$f = fopen('data/materials.csv', 'r');
	$material = '';
	while ($row = fgetcsv($f)) {
		if($row[0] == $matid) {
			$material = $row;
		}
	}
	fclose($f);
	return $material;
}






//
// Get the details of a single person based on their IDIR 
//
function getPerson($idir) {
	$path = build_path(BASE_DIR, 'data', 'people.csv');
	$f = fopen($path, 'r');
	// $f = fopen('data/people.csv', 'r');
	$user = '';
	while ($row = fgetcsv($f)) {
		if($row[0] == $idir) {
			$user = $row;
		}
	}
	fclose($f);
	return $user;
}

//
// Get the details of a single person based on their IDIR 
//
function getTips() {
	$f = fopen('data/insights-tips.csv', 'r');
	$tips = array();
	fgetcsv($f);
	while ($row = fgetcsv($f)) {
			array_push($tips,$row);
	}
	return $tips;
}

/**
 * Get a list of colleagues.
 * 
 * @param bool $filteractive return active people if true else all.
 * 
 * @return array returns an array of people.
 */
function getPeopleAll($filteractive = null) {

	$path = build_path(BASE_DIR, 'data', 'people.csv');
	$f = fopen($path, 'r');
	fgetcsv($f); // Pop off the header
	$list = array();
	// If we want to filter for active only
	if ($filteractive) {
		while ($row = fgetcsv($f)) {
			if ($row[4] === 'Active') {
				array_push($list,$row);
			}
		}
	}
	// Otherwise get everyone
	else {
		while ($row = fgetcsv($f)) {
			array_push($list,$row);
		}
	}
	// Create a temp array to hold names for sorting
	$tmp = array();
	// Loop through the whole people and add names to the temp array
	foreach($list as $line) {
		$tmp[] = $line[2];
	}
	// Use the temp array to sort all the people by name
	array_multisort($tmp, SORT_ASC, $list);
	
	fclose($f);

	return $list;
}



//
// Get a list of colleagues who are Directors
//
function getDirectors() {
	
	$f = fopen('data/people.csv', 'r');
	fgetcsv($f); // Pop off the header
	$list = array();
	while ($row = fgetcsv($f)) {
		if($row[4] === 'Active' && $row[8]) {
			array_push($list,$row);
		}
	}
	
	fclose($f);

	return $list;
}

//
// Get a list of colleagues who have access to Kepler
//
function getKeplerPeople() {
	
	$f = fopen('data/people.csv', 'r');
	fgetcsv($f); // Pop off the header
	$list = array();
	while ($row = fgetcsv($f)) {
		if($row[12] == 1) {
			array_push($list,$row);
		}
	}
	
	fclose($f);

	return $list;
}
//
// Get a list of colleagues who are designated iStore submitters
//
function getiStoreDesignees() {
	
	$f = fopen('data/people.csv', 'r');
	fgetcsv($f); // Pop off the header
	$list = array();
	while ($row = fgetcsv($f)) {
		if($row[11] == 1) {
			array_push($list,$row);
		}
	}
	
	fclose($f);

	return $list;
}

//
// Get a list of colleagues
//
function getPeopleByRole($role) {
	
	$f = fopen('data/people.csv', 'r');
	fgetcsv($f); // Pop off the header
	$list = array();
	while ($row = fgetcsv($f)) {
		if($row[1] == $role && $row[4] == 'Active') {
			array_push($list,$row);
		}
	}
	// Create a temp array to hold course names for sorting
	$tmp = array();
	// Loop through the whole classes and add start dates to the temp array
	foreach($list as $line) {
		$tmp[] = $line[2];
	}
	// Use the temp array to sort all the classes by start date
	array_multisort($tmp, SORT_ASC, $list);
	
	fclose($f);

	return $list;
}


//
// Get a list of colleagues
// Primarily for the "facilitating" field
// Pass it an idir and it'll select that one (good for edit forms)
//
function getPeople($idir = null) {
	
	$f = fopen('data/people.csv', 'r');
	fgetcsv($f); // Pop off the header
	$list = array();
	while ($row = fgetcsv($f)) {
		array_push($list,$row);
	}
	fclose($f);
	// Create a temp array to hold course names for sorting
	$tmp = array();
	// Loop through the whole 
	foreach($list as $line) {
		$tmp[] = $line[2];
	}
	// Use the temp array to sort all the classes by start date
	array_multisort($tmp, SORT_ASC, $list);
	$options = '';
	foreach($list as $row) {
		
		if($row[4] == 'Active') {
			if($row[0] == $idir) {
				$options .= '<option value="' . $row[0] . '" selected>' . $row[2] . '</option>';
			} else {
				$options .= '<option value="' . $row[0] . '">' . $row[2] . '</option>';
			}
		}
	}
	print $options;
}

// 
// return an array of key values pairs, with the values as arrays of team details
//
function getTeams() {
	
	return [
		'ExecutiveDirector' => ['name' => 'Executive Director', 'isBranch' => 1],
		'Operations' => ['name' => 'Operations &amp; Technology', 'isBranch' => 1],
		'Employees' => ['name' => 'Corp Learning All Employees', 'isBranch' => 1],
		'Leaders' => ['name' => 'Corp Learning People Leaders', 'isBranch' => 1],
		'Governance' => ['name' => 'Planning, Evaluation &amp; Governance', 'isBranch' => 1],
		'Coaching' => ['name' => 'Coaching Services', 'isBranch' => 1],
		'LeadershipDev' => ['name' => 'Leadership Development', 'isBranch' => 1],
		'Internal' => ['name' => 'Internal', 'isBranch' => 0],
		'External' => ['name' => 'External', 'isBranch' => 0]
	];
}















// Return all classes a given admin has been assigned that 
// are still active
// 
function getMentions($idir) {
	//creqID,ClassID,CourseName,StartDate,City,DateRequested,RequestedBy,Status,CompletedBy,CompletedDate,Request
	$f = fopen('data/changes-class.csv', 'r');
	$list = array();
	$today = date('Y-m-d');
	fgetcsv($f);
	while ($row = fgetcsv($f)) {
		if($row[3] > $today) {
		if($row[7] == 'Pending') {
			if(strpos($row[10],$idir)) {
				array_push($list,$row);
			}
		}
		}
	}
	fclose($f);
	return $list;
}

function getNotes($cid) {
	$f = fopen('data/notes.csv', 'r');
	$notes = array();
	while ($row = fgetcsv($f)) {
		if($row[1] == $cid) {
			array_push($notes,$row);
		}
	}
	fclose($f);
	return $notes;
}


function getBookingNotes($cid) {
	$f = fopen('data/notes-booking.csv', 'r');
	$notes = array();
	while ($row = fgetcsv($f)) {
		if($row[1] == $cid) {
			array_push($notes,$row);
		}
	}
	fclose($f);
	return $notes;
}



function getVenueNotes($vid) {
	$f = fopen('data/notes-venue.csv', 'r');
	fgetcsv($f);
	$notes = array();
	while ($row = fgetcsv($f)) {
		if($row[1] == $vid) {
			array_push($notes,$row);
		}
	}
	fclose($f);
	return $notes;
}



// Get changes for a class date
function getClassChanges($cid) {
	$f = fopen('data/changes-class.csv', 'r');
	$changes = array();
	while ($row = fgetcsv($f)) {
		if($row[1] == $cid) {
			array_push($changes,$row);
		}
	}
	fclose($f);
	return $changes;
}
// Get changes for a course
function getCourseChanges($cid) {
	$f = fopen('data/changes-course.csv', 'r');
	$changes = array();
	while ($row = fgetcsv($f)) {
		if($row[1] == $cid) {
			array_push($changes,$row);
		}
	}
	fclose($f);
	// Create a temp array to hold request dates for sorting
	// creqID,CourseID,CourseName,DateRequested,RequestedBy,Status,CompletedBy,CompletedDate,Request
	$tmp = array();
	// Loop through the whole classes and add start dates to the temp array
	foreach($changes as $line) {
		$tmp[] = $line[3];
	}
	// Use the temp array to sort all the classes by start date
	array_multisort($tmp, SORT_DESC, $changes);
	return $changes;
}

// Get changes for a course
function getCourseChangesAll() {
	$f = fopen('data/changes-course.csv', 'r');
	$changes = array();
	fgetcsv($f);
	while ($row = fgetcsv($f)) {
		if($row[5] != 'Completed') {
			array_push($changes,$row);
		}
	}
	fclose($f);
	// Create a temp array to hold request dates for sorting
	// creqID,CourseID,CourseName,DateRequested,RequestedBy,Status,CompletedBy,CompletedDate,Request
	$tmp = array();
	// Loop through the whole classes and add start dates to the temp array
	foreach($changes as $line) {
		$tmp[] = $line[3];
	}
	// Use the temp array to sort all the classes by start date
	array_multisort($tmp, SORT_DESC, $changes);
	return $changes;
}


// Get changes for a course
// creqID,CourseID,CourseName,DateRequested,RequestedBy,Status,CompletedBy,CompletedDate,Request,RequestType,AssignedTo,Urgency
function getCourseChange($changeid) {
	$f = fopen('data/changes-course.csv', 'r');
	$changes = array();
	while ($row = fgetcsv($f)) {
		if($row[0] == $changeid) {
			array_push($changes,$row);
		}
	}
	fclose($f);
	return $changes;
}


// Get people history for a course
function getCoursePeople($courseid) {

	$filepath = 'data/course-people.csv';
	$f = fopen($filepath, 'r');
	fgetcsv($f);
	$people = array();
	while ($row = fgetcsv($f)) {
		if($row[0] == $courseid) {
			array_push($people,$row);
		}
	}
	fclose($f);
	$tmpdate = [];
	foreach($people as $p) {
		// sort by data 
		$tmpdate[] = $p[3];
	}
	array_multisort($tmpdate, SORT_DESC, $people);
	$stews = [];
	$devs = [];
	foreach($people as $p) {
		if($p[1] == 'steward') {
			$stews[] = $p;
		}
		if($p[1] == 'dev') {
			$devs[] = $p;
		}
	}

	$stewsdevs = ['stewards' => $stews, 'developers' => $devs];

	return $stewsdevs;
}



// Get comments for a particular change request for a course
function getCourseChangeComments($cid) {
	$f = fopen('data/changes-course-comments.csv', 'r');
	$comments = array();
	while ($row = fgetcsv($f)) {
		if($row[1] == $cid) {
			array_push($comments,$row);
		}
	}
	fclose($f);
	// Create a temp array to hold request dates for sorting
	// commentID,creqID,CourseID,CourseName,created,Comment,Commenter
	$tmp = array();
	// Loop through the whole classes and add start dates to the temp array
	foreach($comments as $line) {
		$tmp[] = $line[4];
	}
	// Use the temp array to sort all the classes by start date
	array_multisort($tmp, SORT_ASC, $comments);
	return $comments;
}


// Get all pending changes for class dates
function getPendingClassChanges() {
	$f = fopen('data/changes-class.csv', 'r');
	$changes = array();
	while ($row = fgetcsv($f)) {
		if($row[7] == 'Pending') {
			array_push($changes,$row);
		}
	}
	fclose($f);
	// Create a temp array to hold course names for sorting
	$tmp = array();
	// Loop through the whole classes and add start dates to the temp array
	foreach($changes as $line) {
		$tmp[] = $line[5];
	}
	// Use the temp array to sort all the classes by start date
	array_multisort($tmp, SORT_DESC, $changes);

	return $changes;
}

// Get all pending changes for courses
function getPendingCourseChanges() {
	$f = fopen('data/changes-course.csv', 'r');
	$changes = array();
	while ($row = fgetcsv($f)) {
		if($row[5] == 'Pending') {
		if($row[11] != 'Backlog') {
			array_push($changes,$row);
		}
		}
	}
	fclose($f);
	// Create a temp array to hold course names for sorting
	$tmp = array();
	// Loop through the whole classes and add start dates to the temp array
	foreach($changes as $line) {
		$tmp[] = $line[3];
	}
	// Use the temp array to sort all the classes by start date
	array_multisort($tmp, SORT_DESC, $changes);
	return $changes;
}


function getCouriers() {
	$f = fopen('data/couriers.csv', 'r');
	fgetcsv($f);
	$couriers = array();
	while ($row = fgetcsv($f)) {
		array_push($couriers,$row);
	}
	fclose($f);
	return $couriers;
}

function getRegions() {
	$f = fopen('data/regions.csv', 'r');
	fgetcsv($f);
	$regions = array();
	while ($row = fgetcsv($f)) {
		array_push($regions,$row);
	}
	fclose($f);
	return $regions;
}
function getRegion($regionid) {
	// regionid is not the numerical ID, but the RegionShort column e.g. NBC
	$f = fopen('data/regions.csv', 'r');
	fgetcsv($f);
	$regions = array();
	while ($row = fgetcsv($f)) {
		if($row[1] == $regionid) {
			array_push($regions,$row);
		}
	}
	fclose($f);
	return $regions;
}


function getOrdersAll() {
	
	$f = fopen('data/materials-orders.csv', 'r');
	fgetcsv($f);
	$orders = array();
	while ($row = fgetcsv($f)) {
		array_push($orders,$row);
	}
	fclose($f);
	return $orders;
	
}

function getOrders($courseid) {
	
	$f = fopen('data/materials-orders.csv', 'r');
	fgetcsv($f);
	$orders = array();
	while ($row = fgetcsv($f)) {
		if($row[6] == $courseid) {
			array_push($orders,$row);
		}
	}
	fclose($f);
	return $orders;
	
}

function getOrder($orderid) {
	
	$f = fopen('data/materials-orders.csv', 'r');
	fgetcsv($f);
	$order = '';
	while ($row = fgetcsv($f)) {
		if($row[0] == $orderid) {
			$order = $row;
		}
	}
	fclose($f);
	return $order;
	
}
function getOrderItems($orderid) {
	
	$f = fopen('data/materials-order-items.csv', 'r');
	fgetcsv($f);
	$items = array();
	while ($row = fgetcsv($f)) {
		if($row[0] == $orderid) {
			array_push($items,$row);
		}
	}
	fclose($f);
	return $items;
}




function getAVassigned($classid) {
	
	$f = fopen('data/audio-visual.csv', 'r');
	fgetcsv($f);
	$av = array();
	while ($row = fgetcsv($f)) {
		if($row[1] == $classid) {
			array_push($av,$row);
		}
	}
	fclose($f);
	return $av;
}

function getAVunassigned() {
	
	$f = fopen('data/audio-visual.csv', 'r');
	fgetcsv($f);
	$av = array();
	while ($row = fgetcsv($f)) {
		if(!$row[1]) {
			array_push($av,$row);
		}
	}
	fclose($f);
	return $av;
	
}

function getAV($avid) {
	
	$f = fopen('data/audio-visual.csv', 'r');
	fgetcsv($f);
	$av = '';
	while ($row = fgetcsv($f)) {
		if($row[0] == $avid) {
			$av = $row;
		}
	}
	fclose($f);
	return $av;
	
}




















//
// Get the link list for the Resources drop-down in the header
// 
function getLinks() {

	$path = build_path(BASE_DIR, 'data', 'links.csv');
	$f = fopen($path, 'r');
	$links = array();
	fgetcsv($f);
	while ($row = fgetcsv($f)) {
		array_push($links,$row);
	}
	fclose($f);
	return $links;
}



//
// Get the last blog post
// 
function getPostLast() {
	$f = file('data/blog.csv');
	$last_row = array_pop($f);
	$post = str_getcsv($last_row);
	return $post;
}
//
// Get the last announcement
// 
function getAnnounceLast() {
	$f = file('data/announcements.csv');
	$last_row = array_pop($f);
	$post = str_getcsv($last_row);
	return $post;
}


//
// Return all the people on the external-mailing-list
//
function getExternalMailList() {
	$f = fopen('data/external-mailing-list.csv', 'r');
	fgetcsv($f);
	$list = array();
	while ($row = fgetcsv($f)) {
		array_push($list,$row);
	}
	fclose($f);
	return $list;
}

//
// output our preferred date format 
//
function goodDateLong($start,$end = null) {
	
	$sdate = strtotime($start);
	$startdate = date('D M j',$sdate);
	$startmonthtest = date('F', $sdate);
	if($end && $start != $end) {
		$etime = strtotime($end);
		$endmonthtest = date('F', $etime);
		if($startmonthtest != $endmonthtest) {
			$ended = '-' . date('D M j',$etime);
		} else {
			$ended = ' - ' . date('D M j',$etime);
		}
		$goodness = $startdate . $ended;
	} else {
		$goodness = $startdate;
	}
	$goodness = $goodness . ' ' . date('Y',$sdate);
	
	return $goodness;
}
//
// output our preferred date format 
//
function icalDate($start) {
	
	$sdate = strtotime($start);
	$icaldate = date('Ymd',$sdate);	
	return $icaldate;
}
//
// output our preferred date format, but abbreviated as possible
//
function goodDateShort($start,$end = null) {
	
	$sdate = strtotime($start);
	$thisyear = date('Y');
	$startdate = date('D M j',$sdate);
	$startmonthtest = date('F', $sdate);
	if($end && $start != $end) {
		$etime = strtotime($end);
		$endmonthtest = date('F', $etime);
		if($startmonthtest != $endmonthtest) {
			$ended = '-' . date('M j',$etime);
		} else {
			$ended = '-' . date('j',$etime);
		}
		$goodness = $startdate . $ended;
	} else {
		$goodness = $startdate;
	}
	if(date('Y',$sdate) != $thisyear) {
		$goodness = $goodness . ' ' . date('\'y',$sdate);
	} 
	
	
	return $goodness;
}


//
// output the silly American date format that ELM uses
//
function elmStartDate($start) {
	$sdate = strtotime($start);
	$elmstartdate = date('d/m/Y',$sdate);
	return $elmstartdate;
}

//
// Convert from the silly American date format that ELM uses
// to YYYY-MM-DD
//
function elmMakeSane($start = NULL) {
	
	if($start) {
		// mixing objects into primarily procedural code? lol
		// as if Allan actually knows what he's doing
		// $date = DateTime::createFromFormat('d/m/Y', $start);
		// ELM apparently changed date format after OCI lift-n-shift 2024-07-18
		$date = DateTime::createFromFormat('Y/m/d', $start);
		return $date->format('Y-m-d');
		
	} else {
		return $start;
	}

}

function aWeekBefore($date) {
	
	$weekbefore = date("d/m/Y", strtotime($date . ' - 7 days'));
	return $weekbefore;
	
}
function twoDaysBefore($date) {
	
	$weekbefore = date("Y-m-d", strtotime($date . ' - 2 days'));
	return $weekbefore;
	
}
function daysFromNow($date) {
	
	$startdate = strtotime($date);
	$startyear = date('Y',$startdate);
	$start = date('z',$startdate);
	$year = date('Y');
	$today = date('z');
	if($year == $startyear) {
		$days = $start - $today;
	} else {
		$daysleftthisyear = 365 - $today;
		$days = $start + $daysleftthisyear;
	}
	return $days;	
}
function nextDay($date) {
	
	$nextday = date("D M j", strtotime($date . ' + 1 days'));
	return $nextday;
	
}

function daysAgo($date) {
	
	$startdate = strtotime($date);
	$start = date('z',$startdate);
	$today = date('z');
	$days = $today - $start;
	return $days;
	
}









































//
// Return all courses requested 
// 
function getRequestedCourses() {
	
	$today = date('Y-m-d');
	$f = fopen('data/courses.csv', 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		if($row[1] == 'Requested') {
			array_push($list,$row);
		}
	}
	return $list;
}

//
// Return all classes requested 
// 
function getRequestedClasses() {
	
	$today = date('Y-m-d');
	$path = build_path(BASE_DIR, 'data', 'classes.csv');
	$f = fopen($path, 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		
		if($row[1] == 'Requested') {
			array_push($list,$row);
		}
	}
	return $list;
}


//
// Return all changes
// 
function getChanges() {
	$f = fopen('data/changes-class.csv', 'r');
	$list = array();
	fgetcsv($f);
	while ($row = fgetcsv($f)) {
		if($row[7] != 'Completed') {
			array_push($list,$row);
		}
	}
	fclose($f);
	return $list;
}





//
// Return all proposals
//
function getProposals() {
	$f = fopen('data/backups/proposals.csv', 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		array_push($list,$row);
	}
	fclose($f);
	return $list;
}








//
// Return all evaluations
//
function getAudits() {
	$f = fopen('data/reviews/audits.csv', 'r');
	$list = array();
	while ($row = fgetcsv($f)) {
		array_push($list,$row);
	}
	fclose($f);
	return $list;
}












//
// Is this person someone who should be able to see any of this?
//
function canAccess() {
	

	$path = build_path(BASE_DIR, 'data', 'people.csv');
	$f = fopen($path, 'r');
	$yup = 0;
	while ($row = fgetcsv($f)) {
		if($row[0] == LOGGED_IN_IDIR) $yup = 1;
	}
	fclose($f);
	if($yup) return true;
	
}


//
// Is this person on the admin team?
//
function isAdmin() {
	
	$path = build_path(BASE_DIR, 'data', 'people.csv');
	$f = fopen($path, 'r');
	$yup = 0;
	while ($row = fgetcsv($f)) {
		if($row[0] == LOGGED_IN_IDIR && !empty($row[7])) {
			$yup = 1;
		}
	}
	fclose($f);
	if($yup) return true;
}

//
// Is this user a super user?
//
function isSuper() {

	$path = build_path(BASE_DIR, 'data', 'people.csv');
	$f = fopen($path, 'r');
	$yup = 0;
	while	($row = fgetcsv($f)) {
		if($row[0] == LOGGED_IN_IDIR && !empty($row[7])) {
			$yup = 1;
		}
	}
	fclose($f);
	if($yup) return true;
}


//
// Basic sanitation for user input output
// text $i goes in, text comes out without any of the nasty bits 
// https://stackoverflow.com/questions/110575/do-htmlspecialchars-and-mysql-real-escape-string-keep-my-php-code-safe-from-inje
//
function h($str) {
	//$str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
	//$str = htmlentities($str, ENT_QUOTES, 'UTF-8');
	//return $str;
	$str = trim($str);
	//$str = stripslashes($str);
	//$str = htmlspecialchars($str);
	return $str;
}

/**
 * Secure sanitization function for user input/output
 * Provides proper XSS protection by escaping HTML special characters
 * 
 * @param string $str The string to sanitize
 * @param int $flags Optional flags for htmlspecialchars (default: ENT_QUOTES | ENT_HTML5)
 * @return string The sanitized string
 */
function sanitize($str, $flags = null) {
	// Return empty string for null values
	if ($str === null) {
		return '';
	}
	
	// Convert to string if needed
	$str = (string) $str;
	
	// Trim whitespace
	$str = trim($str);
	
	// Set default flags if not provided
	if ($flags === null) {
		$flags = ENT_QUOTES | ENT_HTML5;
	}
	
	// Convert special characters to HTML entities to prevent XSS
	$str = htmlspecialchars($str, $flags, 'UTF-8', false);
	
	return $str;
}

/**
 * Sanitize array values recursively
 * Useful for sanitizing entire $_POST arrays
 * 
 * @param array $array The array to sanitize
 * @return array The sanitized array
 */
function sanitizeArray($array) {
	$sanitized = array();
	foreach ($array as $key => $value) {
		if (is_array($value)) {
			$sanitized[$key] = sanitizeArray($value);
		} else {
			$sanitized[$key] = sanitize($value);
		}
	}
	return $sanitized;
}

function createSlug($string) {
    // Define a list of common words to remove
    $commonWords = ['and', 'the', 'of', 'in', 'for', 'on', 'with', 'at', 'by', 'to', 'a', 'an'];
    
    // Convert the string to lowercase
    $string = strtolower($string);
    
    // Remove common words
    $words = explode(' ', $string);
    $filteredWords = array_filter($words, function($word) use ($commonWords) {
        return !in_array($word, $commonWords);
    });
    
    // Limit the result to 8 words
    $limitedWords = array_slice($filteredWords, 0, 8);
    
    // Join the limited words into a single string
    $filteredString = implode(' ', $limitedWords);
    
    // Replace any non-alphanumeric characters with hyphens and trim extra hyphens
    $slug = preg_replace('/[^a-z0-9]+/', '-', $filteredString);
    $slug = trim($slug, '-'); // Remove leading/trailing hyphens
    
    return $slug;
}





// TODO most of the below should probably get a CRUD datasource?

function getAuditTypes () {

	return [
		'Corporate catalog course',
		'Course outside corporate catalog',
		'Learn @ Work Week',
		'Webinar Recording',
		'Video',
		'Podcast',
		'Job Aid',
		'Resource website',
		'Curated Learning Pathway'
	];
}

function getAllTopics () {

	return [
		'Being a Public Service Employee',
		'Communication and Facilitation',
		'Equity, Diversity and Inclusion',
		'Ethics and Integrity',
		'Finance and Accounting',
		'Health, Safety and Well-being',
		'Human Resources Management',
		'Indigenous Learning',
		'Information Management',
		'Innovation',
		'IT and Digital',
		'Leadership',
		'Policy and Regulation',
		'Procurement and Contract Management',
		'Project Management',
		'Respectful Workplaces'
	];
}

function getAllPlatforms () {

	$path = build_path(BASE_DIR, 'data', 'platforms.json');
	$jsonData = file_get_contents($path);
	$platforms = json_decode($jsonData, true);
	
	$platformNames = array();
	foreach ($platforms as $platform) {
		array_push($platformNames, $platform['name']);
	}
	
	return $platformNames;

}

function getAllAudiences () {

	return [
		'All Employees', 
		'People Leaders', 
		'Senior Leaders',
		'Executive'
	];
}

function getDeliveryMethods () {

	return [
		'Classroom',
		'eLearning',
		'Blended',
		'Webinar'
	];
}

function getLevels () {

	return [
		'Mandatory',
		// 'Essentials',
		'Core',
		// 'Extension'
		'Complementary'
	];
}

function getReportingList () {

	return [
		'None',
		'Consistent Evaluation - Alchemer',
		'Moodle Form',
		'In-course poll',
		'Paper evaluation',
		'Partner Managed'
	];
}

function removeOutlookSafeLinks($link) {
    $url = $link;
    if (strpos($link, 'can01.safelinks.protection.outlook') != false) {
        $url_parsed = parse_url($link);
        parse_str($url_parsed['query'], $parsed_str);
        $url = $parsed_str['url'];
    }
    return $url;
};

function truncateStringByWords($string, $wordLimit, $ellipsis = true) {
    $words = explode(' ', $string);
    if (count($words) > $wordLimit) {
        $truncated = implode(' ', array_slice($words, 0, $wordLimit));
        return $truncated . ($ellipsis ? '...' : '');
    }
    return $string;
}

/**
 * Get CSRF token for forms
 * @return string The CSRF token
 */
function getCsrfToken() {
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Output CSRF token hidden input field
 * Use this in all forms
 */
function csrfField() {
    $token = htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8');
    echo '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Validate CSRF token from POST request
 * @return bool True if valid, false otherwise
 */
function validateCsrfToken() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/**
 * Require valid CSRF token or die
 * Call this at the start of any POST request handler
 */
function requireCsrfToken() {
    if (!validateCsrfToken()) {
        http_response_code(403);
        die('CSRF token validation failed. Please refresh the page and try again.');
    }
}

/**
 * Handle database connection errors securely
 * Logs detailed error, shows generic message to user
 *
 * @param Exception $e The exception to handle
 */
function handleDatabaseError($e) {
    // Log detailed error for administrators
    error_log("Database error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());

    // Show generic error to user
    http_response_code(500);
    die("A database error occurred. Please try again later or contact support if the problem persists.");
}

/**
 * Get user-friendly error message
 * Logs detailed error, returns generic message
 *
 * @param Exception $e The exception
 * @return string Generic error message for display
 */
function getUserFriendlyError($e) {
    // Log detailed error for administrators
    error_log("Application error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());

    // Return generic message
    return "An error occurred while processing your request. Please try again.";
}

/**
 * Sanitize CSV value to prevent formula injection
 * Prevents CSV injection attacks when opening in Excel/LibreOffice
 *
 * @param mixed $value The value to sanitize
 * @return string Sanitized value safe for CSV export
 */
function sanitizeCSVValue($value) {
    if ($value === null) {
        return '';
    }

    $value = (string)$value;

    // If value starts with dangerous characters, prepend single quote
    // Excel/LibreOffice/Google Sheets treat leading single quote as text indicator
    // Dangerous characters: = + - @ \t \r (formula injection characters)
    if (preg_match('/^[=+\-@\t\r]/', $value)) {
        return "'" . $value;
    }

    return $value;
}

/**
 * Inject tracking pixel into HTML email content
 * Automatically appends tracking pixel to email body for open tracking
 *
 * @param string $htmlBody The HTML email content
 * @param string $trackingUrl Base URL for tracking pixel (e.g., https://server.com/track.php)
 * @param string $recipientEmail Email address of recipient
 * @param int $newsletterId Newsletter ID
 * @param int $campaignId Campaign ID
 * @return string HTML with tracking pixel appended
 */
function injectTrackingPixel($htmlBody, $trackingUrl, $recipientEmail, $newsletterId, $campaignId) {
    // Return original if no tracking URL configured
    if (empty($trackingUrl)) {
        return $htmlBody;
    }

    // Generate unique tracking ID for this recipient/campaign
    $trackingId = md5($recipientEmail . $campaignId . time() . bin2hex(random_bytes(8)));

    // Build tracking pixel URL with parameters
    $pixelUrl = $trackingUrl . '?' . http_build_query([
        'id' => $trackingId,
        'e' => $recipientEmail,
        'n' => $newsletterId,
        'c' => $campaignId
    ]);

    // Tracking pixel HTML (1x1 transparent image at end of email)
    $trackingPixel = sprintf(
        '<img src="%s" width="1" height="1" border="0" alt="" style="display:block;width:1px;height:1px;border:0;">',
        htmlspecialchars($pixelUrl, ENT_QUOTES, 'UTF-8')
    );

    // Try to inject before closing </body> tag if present
    if (stripos($htmlBody, '</body>') !== false) {
        return str_ireplace('</body>', $trackingPixel . '</body>', $htmlBody);
    }

    // Otherwise append to end
    return $htmlBody . $trackingPixel;
}

/**
 * Comprehensive email validation
 * Validates email format with security checks to prevent injection attacks
 *
 * @param string $email Email address to validate
 * @return bool True if valid, false otherwise
 */
function validateEmail($email) {
    // Basic type check
    if (!is_string($email)) {
        return false;
    }

    // Check for null bytes BEFORE trimming (security risk)
    if (strpos($email, "\0") !== false) {
        return false;
    }

    // Check for control characters and newlines BEFORE trimming (prevents email header injection)
    // Blocks ASCII control chars: 0x00-0x1F and DEL 0x7F
    if (preg_match('/[\x00-\x1F\x7F]/', $email)) {
        return false;
    }

    // Now safe to trim
    $email = trim($email);

    // Check if empty after trimming
    if ($email === '') {
        return false;
    }

    // Length check (RFC 5321: local part max 64, domain max 255, total max 254)
    if (strlen($email) > 254) {
        return false;
    }

    // Basic format validation using PHP's built-in filter
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    // Must have exactly one @ symbol
    if (substr_count($email, '@') !== 1) {
        return false;
    }

    // Split into local and domain parts
    list($local, $domain) = explode('@', $email);

    // Validate local part length (before @)
    if (strlen($local) > 64 || strlen($local) < 1) {
        return false;
    }

    // Validate domain part length (after @)
    if (strlen($domain) > 255 || strlen($domain) < 1) {
        return false;
    }

    // Check for consecutive dots (not allowed in email addresses)
    if (strpos($local, '..') !== false || strpos($domain, '..') !== false) {
        return false;
    }

    // Check for leading/trailing dots in local part
    if ($local[0] === '.' || $local[strlen($local) - 1] === '.') {
        return false;
    }

    // Domain must contain at least one dot
    if (strpos($domain, '.') === false) {
        return false;
    }

    // Optional: Check DNS records (verify domain exists)
    // This is disabled by default as it can cause performance issues
    // Uncomment to enable DNS validation:
    /*
    if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A') && !checkdnsrr($domain, 'AAAA')) {
        return false;
    }
    */

    return true;
}
