<?php 
opcache_reset();

$finalCols = ['Course Code', 'Course Name', 'Course Description', 'Delivery Method', 'Category', 'Learner Group', 
              'Duration', 'Available Classes', 'Link to ELM Search', 'Course Last Modified', 
              'Course Owner Org', 'Course ID', 'Keywords', 'Group', 'Audience', 'Topic'];

// Process keywords CSV
$keywordsFile = "data/GBC_ATWORK_CATALOG_KEYWORDS.csv";
$keywords = processCsvFile($keywordsFile);
$keywordsByCode = mergeKeywords($keywords);

// Process catalog CSV
$catalogFile = "data/GBC_LEARNINGHUB_SYNC2.csv";
$courses = processCsvFile($catalogFile);
$newCourses = mergeCourses($courses, $keywordsByCode);

// Write final course data to CSV
writeCsv('data/courses.csv', $finalCols, $newCourses);

// Redirect or output success message
// echo 'Success!';
header('Location: elm-course-sync.php');

// Functions
function processCsvFile($filename) {
    if (($handle = fopen($filename, "r")) === false) return [];

    $csvData = [];
    $columnNames = fgetcsv($handle);
    while (($row = fgetcsv($handle)) !== false) {
        $csvData[] = array_combine($columnNames, $row);
    }
    fclose($handle);
    return $csvData;
}

function mergeKeywords($keywords) {
    $keywordsByCode = [];
    foreach ($keywords as $keyword) {
        $code = $keyword['Course Code'];
        if (!isset($keywordsByCode[$code])) {
            $keywordsByCode[$code] = ['keywords' => [], 'partnerKey' => ''];
        }
        $key = $keyword['Keyword'];
        if ($keyword['Keyword Type ID'] == 1039) {
            $keywordsByCode[$code]['partnerKey'] = $key;
        }
        $keywordsByCode[$code]['keywords'][] = $key;
    }
    return $keywordsByCode;
}

function mergeCourses($courses, $keywordsByCode) {
    $coursesByCode = [];

    // Group courses by Course Code
    foreach ($courses as $course) {
        if (!in_array($course['Learner Group'], ['All Government of British Columbia Learners', 'Excluded Managers', 'Ministries - All'])) {
            continue;
        }
        $code = $course['Course Code'];
        if (!isset($coursesByCode[$code])) {
            $coursesByCode[$code] = [];
        }
        $coursesByCode[$code][] = $course;
    }

    $newCourses = [];

    // Process each course code separately
    foreach ($coursesByCode as $code => $courseEntries) {
        // Initialize variables for each course
        $categories = '';
        $kwords = '';
        $learningPartner = '';
        $audience = '';
        $group = '';
        $topic = '';

        // Get the first course entry (assuming details are the same across entries)
        $course = $courseEntries[0];

        // Get the keywords and learning partner
        if (isset($keywordsByCode[$code])) {
            $kwordsArray = $keywordsByCode[$code]['keywords'];
            $partnerKey = $keywordsByCode[$code]['partnerKey'];
            $learningPartner = mapPartnerCode($partnerKey);
            $kwords = implode(', ', $kwordsArray);
        } else {
            // No keywords or partnerKey found, skip this course
            continue;
        }

        // If no partnerKey (learningPartner), skip this course
        if (empty($learningPartner)) {
            continue;
        }

        // Collect categories, audience, group, topic
        foreach ($courseEntries as $entry) {
            $category = $entry['Category'];
            $shortName = $entry['Short Name'];

            if (strlen($category) > 0) {
                switch ($shortName) {
                    case 'Audience':
                        $audience = $category; // Store only the last value
                        break;
                    case 'Group':
                        $group = $category; // Store only the last value
                        break;
                    case 'Topic':
                        $topic = $category; // Store only the last value
                        break;
                    default:
                        $categories .= $category . ', ';
                        break;
                }
            }
        }

        // Trim trailing commas and spaces
        $categories = rtrim($categories, ', ');
        $audience = trim($audience);
        $group = trim($group);
        $topic = trim($topic);

        // Build the course array
        $newCourses[] = buildCourseArray($course, $categories, $learningPartner, $kwords, $group, $audience, $topic);
    }

    return $newCourses;
}

function mapPartnerCode($code) {
    $partners = [
        'EMCR' => 'Emergency Management and Climate Readiness',
        'Priorities & Innovation' => 'Leadership, Engagement and Priority Initiatives',
        'CIRMO' => 'Corporate Information and Records Management Office',
        'DWCS' => 'Digital Workplace and Collaboration Services Branch',
        'Procurement Strategy Gov' => 'Procurement Strategy and Governance Branch',
        'Service BC Web Services Branch' => 'Service BC - Web Services Branch',
        // Add other mappings as needed
    ];
    return $partners[$code] ?? $code;
}

function buildCourseArray($course, $categories, $learningPartner, $keywords, $group, $audience, $topic) {
    return [
        $course['Course Code'],
        htmlspecialchars($course['Course Name'], ENT_QUOTES, 'UTF-8'),
        trim_all($course['Course Description']),
        convertDeliveryMethod($course['Delivery Method']),
        $categories,
        $course['Learner Group'],
        'Not Listed', // Duration placeholder
        $course['Available Classes'],
        $course['Link to ELM Search'],
        $course['Course Last Modified'],
        $learningPartner,
        $course['Course ID'],
        $keywords,
        $group,
        $audience,
        $topic
    ];
}

function writeCsv($filename, $headers, $data) {
    $fp = fopen($filename, 'w');
    fputcsv($fp, $headers);
    foreach ($data as $fields) {
        fputcsv($fp, $fields);
    }
    fclose($fp);
}

function trim_all($str, $what = null, $with = ' ') {
    $what = $what ?? "\\x00-\\x20";
    return trim(preg_replace("/[" . $what . "]+/", $with, $str), $what);
}

function convertDeliveryMethod($method) {
    $method = str_replace(
        ['Moodle', 'Virtual', 'Self-Directed', 'Scheduled Learning Activities', 'Self-Paced Learning Activities'],
        'eLearning',
        $method
    );
    // Handle 'Webinar' specifically
    if (stripos($method, 'Webinar') !== false) {
        $method = 'Webinar';
    }
    return $method;
}

