<?php
opcache_reset();
$path = '../inc/lsapp.php';
require($path); 
require('functions.php');


/**
 * Take the form id, find the responses json file
 * and return all responses as an associative array
 * 
 * @param string $form_id the CHEFS form id
 * @return array an associative array of responses
 */
function getResponses($form_id) {
    global $alert_warning, $data_path;

    if (!$form_id) {
        $alert_warning .= ' Form ID not provided. ';
        return;
    }
    
    $response_file = $data_path . $form_id . '.json';

    // get the file contents or false on failure
    $response_contents = file_get_contents($response_file);
    
    if (!$response_contents) {
        $alert_warning = ' Error opening file. ';
        return;
    }

    // decode our json data into an associative array
    $response_data = json_decode($response_contents, true);

    return $response_data;

}


/**
 * Find all of the unique class codes in our responses
 * 
 * @param array $response_data an associative array of responses
 * @return array an array of class codes if provided or an empty array
 */
function getResponseClasses($response_data) {
    global $alert;
    $classes = array();

    foreach ($response_data as $response) {
        if (array_key_exists('classCode', $response) && !in_array($response['classCode'], $classes)) {
            array_push($classes, $response['classCode']);
        }
    }

    return $classes;

}


/**
 * Returns a filtered array of responses that contain the provided class code
 * 
 * @param array $response_data an associative array of responses
 * @param string $class_code class code if provided else 0
 * 
 * @return array $response_data a filtered associative array of responses
 */
function filterResponsesByClass($response_data, $class_code = 0) {
    $response_data_by_class = array();

    // if we've been provided a class code in the url, get those responses
    if ($class_code !== 0) {
        foreach ($response_data as $response) {
            if ($response['classCode'] == $class_code) {
                array_push($response_data_by_class, $response);
            }
        }
    }

    return $response_data_by_class;

}



/**
 * Filter response data by greater than start date,
 * less than end date, or both if provided
 * 
 * @param array $response_data an associative array of responses
 * @param string $start_date a date string if provided or 0
 * @param string $end_date a date string if provided or 0
 * 
 * @return array $response_data a filtered associative array of responses
 */
function filterResponsesByDate($response_data, $start_date = 0, $end_date = 0) {
    
    // if we aren't provided either date, immediately return
    if (empty($start_date) && empty($end_date)) {
        return $response_data;
    }

    $responses_by_date = array();
    $timezone = new DateTimeZone('UTC');

    // If we have a start date, build and set to start of day
    if (!empty($start_date)) {
        $start_date_beginning = DateTimeImmutable::createFromFormat('Y-m-d|', $start_date, $timezone);
    }

    // If we have an end date, build and set to the end of the day
    if (!empty($end_date)) {
        $end_date_end = DateTimeImmutable::createFromFormat('Y-m-d|', $end_date, $timezone);
        $end_date_end = $end_date_end->setTime(23, 59, 59, 999999);
    }

    // if we have both fields
    if (isset($start_date_beginning) && isset($end_date_end)) {
        foreach ($response_data as $response) {
            $submitted_at = new DateTimeImmutable($response['form']['submittedAt']);
            
            if ($submitted_at >= $start_date_beginning && $submitted_at <= $end_date_end) {
                array_push($responses_by_date, $response);
            }
        }
        return $responses_by_date;
    }
    // if we have a start date but no end date
    elseif (isset($start_date_beginning) && !isset($end_date_end)) {
        foreach ($response_data as $response) {
            $submitted_at = new DateTimeImmutable($response['form']['submittedAt']);
            
            // only check if we're greater than start date
            if ($submitted_at >= $start_date_beginning) {
                array_push($responses_by_date, $response);
            }
        }
        return $responses_by_date;
    }
    // if we have an end date but no start date
    elseif (!isset($start_date_beginning) && isset($end_date_end)) {
        foreach ($response_data as $response) {
            $submitted_at = new DateTimeImmutable($response['form']['submittedAt']);
            
            // only check if we're less than end date
            if ($submitted_at <= $end_date_end) {
                array_push($responses_by_date, $response);
            }
        }
        return $responses_by_date;
    }
    
    return $responses_by_date;

}

/**
 * Take our filtered raw responses array, and compile into 
 * a summary array for display/export
 * 
 * @param array $response_data an associative array of responses
 * 
 * @return array $responses a compiled summary of the responses
 */
function compileResponses($response_data, $response_map) {
    if (empty($response_map)) {
        return;
    }
    
    // output array
    $responses = array();
    
    foreach ($response_data as $response) {
        foreach ($response as $question => $answer) {
            // ignore fields we don't need
            if ($question == 'form' || $question == 'lateEntry' || $question == 'classCode' || $question == 'courseCode') {
                continue;
            } 
            // don't capture the information from empty question responses
            elseif (empty($answer) || !isset($response_map[$question])) {
                continue;
            } 
            // for text type responses store as an array of answers
            elseif ($response_map[$question]['inputType'] == 'text') {
                if (!array_key_exists($question, $responses)) {
                    $responses[$question] = array();
                } else {
                    $responses[$question][] = $answer;
                }
            } 
            // for radio type responses add a count of the response option
            elseif ($response_map[$question]['inputType'] == 'radio') {
                if (!array_key_exists($question, $responses)) {
                    $responses[$question] = array();
                    $responses[$question]['total'] = 0;
                } elseif (!array_key_exists($answer, $responses[$question])) {
                    $responses[$question][$answer] = 1;
                    $responses[$question]['total']++;
                } else {
                    $responses[$question][$answer]++;
                    $responses[$question]['total']++;
                }
            }
            // for select type responses add a count of the response option
            elseif ($response_map[$question]['inputType'] == 'select') {
                if (!array_key_exists($question, $responses)) {
                    $responses[$question] = array();
                    $responses[$question]['total'] = 0;
                } elseif (!array_key_exists($answer, $responses[$question])) {
                    $responses[$question][$answer] = 1;
                    $responses[$question]['total']++;
                } else {
                    $responses[$question][$answer]++;
                    $responses[$question]['total']++;
                }
            }
        }
    }

    // Sort by the order provided in the map
    $map_order = array_keys($response_map);
    $sorted_responses = array();
    foreach ($map_order as $key) {
        if (array_key_exists($key, $responses)) {
            $sorted_responses[$key] = $responses[$key];
        }
    }

    return $sorted_responses;
}

/**
 * Takes the question, and the compiled responses, and creates the
 * html for the div containing the chart, totals, and percent bars
 * 
 * also populates the required javascript into $chart_scripts
 * 
 * @param string $question the question id/key
 * @param array $responses the compiled responses
 * 
 * @return string $html the html for a response div and content within
 */
function createChartForRadio($question, $responses) {
    global $response_map;
    global $chart_scripts;

    // initialize chart values
    $chart_labels = array();
    $chart_values = array();

    // get question options
    $answer_options = array();
    foreach ($response_map[$question]['values'] as $option_key => $option_name) {
        // set the value if it exists or 0
        $value = $responses[$question][$option_key] ?? 0;
        
        $answer_options[$option_key] = ['name' => $option_name, 'value' => $value];
        
        $chart_labels[] = $option_name;
        $chart_values[] = $value;
    }
    
    // create html content for chart / card
    $html = '<div class="card my-3" >';
    $html .=    '<h2 class="m-3">' . $response_map[$question]['label'] . '</h2>';
    $html .=    '<div class="mt-3">';
    $html .=        '<canvas id="' . $question . '"></canvas>';
    $html .=    '</div>';

    $html .=    '<div class="card-body">';
                    
    // iterate through answer options
    foreach($answer_options as $option) {
        $percent_total = round(($option['value'] / $responses[$question]['total']) * 100);
        $html .=    '<div class="row align-items-center">';
        $html .=        '<div class="col-4">';
        $html .=            '<p style="font-size: smaller;" class="my-1">' . $option['name'] . '</p>';
        $html .=        '</div>';
        $html .=        '<div class="col-2">';
        $html .=            '<p style="font-size: smaller;" class="my-1">' . $option['value'] . '</p>';
        $html .=        '</div>';
        $html .=        '<div class="col-6">';
        $html .=            '<div class="progress" role="progressbar" aria-label="' . $option['name'] . '" aria-valuenow="' . $percent_total . '" aria-valuemin="0" aria-valuemax="100">';
        $html .=                '<div class="progress-bar text-bg-success" style="width: ' . $percent_total . '%">' . $percent_total . '%</div>';
        $html .=            '</div>';
        $html .=        '</div>';
        $html .=    '</div><hr class="m-0 p-0">';
    }

    // add total row
    $html .=    '<div class="row align-items-center">';
    $html .=        '<div class="col-4">';
    $html .=            '<p style="font-size: smaller;" class="my-1"><strong>Total</strong></p>';
    $html .=        '</div>';
    $html .=        '<div class="col-2">';
    $html .=            '<p style="font-size: smaller;" class="my-1"><strong>' . $responses[$question]['total'] . '</strong></p>';
    $html .=        '</div>';
    $html .=        '<div class="col-6">';       
    $html .=        '</div>';
    $html .=    '</div>';

    $html .=    '</div>'; // /card-body
    $html .= '</div>'; // /card

    // add the chart details
    $chart_scripts .= "const " . $question . "_chart" . " = document.getElementById('" . $question . "');

    new Chart(" . $question . "_chart" . ", {
        type: 'pie',
        data: {
            labels: " . json_encode($chart_labels) . ",   
            datasets: [{
                data: " . json_encode($chart_values) . ",
                backgroundColor: [
                '#971B2F',
                '#5F2167',
                '#e3a82b',
                '#007864',
                '#234075',
                '#917C78',
                '#FB8B24',
                '#A288E3',
                '#DA4167',
                '#6F9CEB'
                ],
                hoverOffset: 4
            }]
        },
        options: {
            layout: {
                padding: {
                top: 0,
                bottom: 0,
                left: 20,
                right: 20
                }
            },
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });";

    return $html;
    
}

/**
 * Takes the question, and the compiled responses, and creates the
 * html for the div containing the list of responses
 * 
 * @param string $question the question id/key
 * @param array $responses the compiled responses
 * 
 * @return string $html the html for a response div and content within
 */
function createTextResponses($question, $responses) {
    global $response_map;

    $html =    '<div class="card my-3" >';
    $html .=        '<h2 class="m-3 mb-0">' . $response_map[$question]['label'] . '</h2>';
    $html .=        '<details>';
    $html .=            '<summary class="m-3">View responses <span class="badge text-bg-secondary">' . count($responses[$question]) . '</span></summary>';
    $html .=            '<ul class="list-group m-3">';
    foreach ($responses[$question] as $response) {
        $html .=            '<li class="list-group-item">' . $response . '</li>';
    }
    $html .=            '</ul>';
    $html .=        '</details>';
    $html .=    '</div>';

    return $html;

}

$form_id = $_GET['formId'] ?? 0;
$class_code = $_GET['classCode'] ?? 0;
$start_date = $_GET['startDate'] ?? 0;
$end_date = $_GET['endDate'] ?? 0;

$alert = '';
$alert_info = '';
$alert_warning = '';

$title = 'Course Survey';
$data_path = '../data/surveys/';
$last_sync = 'Unknown';

// class codes included in our responses
$classes = array();

// to hold our chart javascript
$chart_scripts = '';

// get our form config information
$survey_config = getConfigSurvey($form_id);
$response_map = array();
if (!empty($survey_config)) {
    $response_map = $survey_config['questions'] ?? array();
    $title = $survey_config['name'] ?? 'Course Survey';
} else {
    $alert_warning .= "Form ID not found.<br>";
}

if (isset($survey_config['lastResponsesUpdated'])) {
    $last_sync = date('Y-m-d H:i', $survey_config['lastResponsesUpdated']);
}

// check that we have a responses file for this survey
if (file_exists("../data/surveys/{$form_id}.json")) {
    
    // load our responses json file
    $response_data = getResponses($form_id);

    // get an array of class codes that are included in the response data
    $classes = getResponseClasses($response_data);

    // if a class code is provided, filter to responses from those classes
    if ($class_code != 0 && in_array($class_code, $classes)) {
        $response_data_processed = filterResponsesByClass($response_data, $class_code);
    }

    // if a class code is provided, but we don't have any responses for it
    elseif ($class_code != 0 && !in_array($class_code, $classes)) {
        $response_data_processed = array();
        $alert_warning .= "No responses found for this class (\"" . $class_code . "\").<br>";
    }

    // otherwise, pass along all the responses
    else {
        $response_data_processed = $response_data;
    }

    // pass through date date inputs and filter if provided
    $responses_filtered_by_date = filterResponsesByDate($response_data_processed, $start_date, $end_date);
    if (count($responses_filtered_by_date) == 0) {
        $alert_warning .= "No responses found for the selected dates.<br>";
    }

    // take our filtered raw responses and summarize
    $compiled_responses = compileResponses($responses_filtered_by_date, $response_map);

} else {
    // if we don't have a responses file, we'll use this variable to 
    // prevent loading more content after the alerts are added
    $compiled_responses = array();
    $alert_warning .= "No responses found for this survey.<br>";
}

$have_responses = count($compiled_responses) > 0 ? true : false;

// Filtered classes info
if (!empty($class_code) && count($classes) > 0) {
    $test = !empty($class_code);
    $alert_info .= "Showing results for {$class_code}.<br>";
}

/** Filtered dates info */
// If we have both dates
if (!empty($start_date) && !empty($end_date)) {
    $alert_info .= "Showing results between {$start_date} and {$end_date}.<br>";
} 
// if we have a start date but no end date
else if (!empty($start_date) && empty($end_date)) {
    $alert_info .= "Showing results after {$start_date}.<br>";
}
// if we have an end date but no start date
else if (empty($start_date) && !empty($end_date)) {
    $alert_info .= "Showing results before {$end_date}.<br>";
}

// Download to csv
if (isset($_POST['download_to_csv'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="survey-report-export.csv"'); 

    $output_file = fopen('php://output', 'w');
    // Write UTF-8 BOM so Excel recognizes encoding
    fwrite($output_file, "\xEF\xBB\xBF");

    // Create header row
    fputcsv($output_file, [$title . ' Summary Report']);
    fputcsv($output_file, []);

    foreach($compiled_responses as $question=>$responses) {
        // Question
        fputcsv($output_file, [$response_map[$question]['label']]);

        // Text questions
        if ($response_map[$question]['inputType'] == 'text') {
            // Column Headers
            fputcsv($output_file, ['Response #', 'Value']);
            foreach($responses as $index=>$response) {
                fputcsv($output_file, [$index + 1, $response]);
            }
            fputcsv($output_file, []);
        }
        // Radio questions
        else if ($response_map[$question]['inputType'] == 'radio') {
            // Get the total value
            $total = $compiled_responses[$question]['total'] ?? 0;
            // Column Headers
            fputcsv($output_file, ['Response', 'Value', 'Percent']);
            foreach($response_map[$question]['values'] as $key=>$value) {
                $num_values = $compiled_responses[$question][$key] ?? 0;
                if ($total == 0 || $num_values == 0) {
                    $percent_rounded = 0;
                }
                else if ($num_values > 0) {
                    $percent_total = ($num_values / $total) * 100;
                    $percent_rounded = round($percent_total);
                }
                fputcsv($output_file, [$value, $num_values, $percent_rounded . '%']);
            }
            fputcsv($output_file, ['Total', $compiled_responses[$question]['total']]);
            fputcsv($output_file, []);
        }
        // Select questions
        else if ($response_map[$question]['inputType'] == 'select') {
            // Get the total value
            $total = $compiled_responses[$question]['total'] ?? 0;
            // Column Headers
            fputcsv($output_file, ['Response', 'Value', 'Percent']);
            foreach($response_map[$question]['values'] as $key=>$value) {
                $num_values = $compiled_responses[$question][$key] ?? 0;
                if ($total == 0 || $num_values == 0) {
                    $percent_rounded = 0;
                }
                else if ($num_values > 0) {
                    $percent_total = ($num_values / $total) * 100;
                    $percent_rounded = round($percent_total);
                }
                fputcsv($output_file, [$value, $num_values, $percent_rounded . '%']);
            }
            fputcsv($output_file, ['Total', $compiled_responses[$question]['total']]);
            fputcsv($output_file, []);
        }
    }
    
    fclose($output_file);
    exit;
    

}











?>

<?php if(canACcess()): ?>

<?php getHeader() ?>
    <title><?= $title ?> Evaluation Report</title>
    <script src="/lsapp/js/list.min.js"></script>

<?php getScripts() ?>
<body>
<?php getNavigation() ?>

<?php



?>

<!-- <pre> -->
    <?php // print_r(gettype($responses_filtered_by_date)) ?>
    <?php // print_r($responses_filtered_by_date); ?>
<!-- </pre> -->

<div class="container-fluid">
    <div class="row justify-content-md-center mb-3">
        <div class="col-md-10">
            <h1 class="mb-2 text-center"><?= $title . ' Report' ?></h1>
        </div>
    </div>

    

    <div class="row justify-content-md-center">

        <div class="col-lg-2" name="side-nav">
            <div class="card sticky-top m-auto z-0 overflow-hidden" style="top: 65px; max-width: 310px;">
                <form action="report.php" method="get">
                <h5 class="card-header">Filter Results</h5>
                <div class="card-body">
                    <h6 class="card-title text-body-secondary mb-0">By Offering</h6>
                </div> <!-- /card-body -->
                <ul class="list-group">
                    <li class="list-group-item">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="classCode" id="all-radio" value="0" autocomplete="off" <?= $class_code == 0 ? 'checked=""' : '' ?>>
                            <label class="form-check-label" for="all-radio">All</label>
                        </div>
                    </li>
                    <?php foreach($classes as $class): ?>
                    <li class="list-group-item">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="classCode" id="<?= $class ?>-radio" value="<?= $class ?>" autocomplete="off" <?= $class === $class_code ? 'checked=""' : '' ?>>
                            <label class="form-check-label" for="<?= $class ?>-radio"><?= $class ?></label>
                        </div>
                    </li>
                    <?php endforeach ?>
                    
                </ul>
                <div class="card-body">
                    <h6 class="card-title text-body-secondary mb-2">By Date Range</h6>
                    <div class="input-group input-group-sm mb-2">
                        <label class="input-group-text" for="startDate">Start Date</label>
                        <input type="date" class="form-control" name="startDate" id="startDate" value="<?= $start_date ?? '' ?>"> 
                    </div>
                    <div class="input-group input-group-sm mb-2">
                        <label class="input-group-text" for="endDate">End Date</label>
                        <input type="date" class="form-control" name="endDate" id="endDate" value="<?= $end_date ?? '' ?>"> 
                    </div>
                </div>
                <input type="hidden" id="form-id" name="formId" value="<?= $form_id ?>">
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">Apply</button>	
                </div>
                </form>
            </div>
        </div>

    

<div class="col-9">

    

<div class="container-lg">

    

    <?php $flash_alerts = AlertManager::getAlertsAll(); ?>
    <?php if (count($flash_alerts) > 0): ?>
        <?php foreach ($flash_alerts as $falert): ?>
            <div class="alert alert-<?= $falert['type'] ?>" role="alert">
                <?= $falert['message'] ?><br>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (strlen($alert_warning) > 0): ?>
        <div class="alert alert-warning" role="alert">
            <?= $alert_warning ?>
        </div>
    <?php endif; ?>

    <?php if (strlen($alert_info) > 0 && $have_responses): ?>
        <!-- Info alerts -->
        <div class="alert alert-info" role="alert">
            <?= $alert_info ?>
        </div>
    <?php endif; ?>
    
    

</div>

<div class="container-lg d-flex justify-content-end bg-light-subtle rounded-top border-secondary-subtle border-start border-top border-end">
    <div class="row">
        <div class="col p-1">
                <div class="d-inline-flex align-items-center m-1">
                    
                    <div class="text-body-secondary m-1">Last Sync: <?= $last_sync ?></div>
                    <form method="post" action="get-responses.php">
                        <input type="hidden" name="FormId" value="<?= $form_id ?>">
                        <input type="hidden" name="ClassCode" value="<?= $class_code ?>">
                        <input type="hidden" name="StartDate" value="<?= $start_date ?>">
                        <input type="hidden" name="EndDate" value="<?= $end_date ?>">
                        <button type="submit" name="get_responses" class="btn btn-secondary m-1">Get Responses</button>
                    </form>
                    <?php if ($have_responses): ?>
                    <form method="post" onsubmit="return confirm('Are you sure you want to download the results with the current filters applied?')">
                        <button type="submit" name="download_to_csv" class="btn btn-success m-1">Export to CSV</button>
                    </form>
                    <?php endif; ?>
                </div>
        </div> <!-- /col -->
    </div> <!-- /row -->
</div>

<div class="container-lg p-lg-5 p-4 border border-secondary-subtle bg-secondary-subtle rounded-bottom">
     
    <!-- if we don't have any responses, don't show charts -->
    <?php if (count($compiled_responses) > 0): ?>
   
    <?php
    foreach($compiled_responses as $question => $response) {
        if ($response_map[$question]['inputType'] == 'radio') {
            echo '<div class="row justify-content-md-center">';
                echo '<div class="col-6">';
                    echo createChartForRadio($question, $compiled_responses);
                echo '</div>';
            echo '</div>';
        }
        else if ($response_map[$question]['inputType'] == 'select') {
            echo '<div class="row justify-content-md-center">';
                echo '<div class="col-6">';
                    echo createChartForRadio($question, $compiled_responses);
                echo '</div>';
            echo '</div>';
        }
        else if ($response_map[$question]['inputType'] == 'text') {
            echo '<div class="row justify-content-md-center">';
                echo '<div class="col-8">';
                    echo createTextResponses($question, $compiled_responses);
                echo '</div>';
            echo '</div>';
        }
    }
    ?>
    
    <?php else: ?>
        <div class="alert alert-warning" role="alert">
            No responses available for the currently selected filters. Please either adjust filters, 
            or select <strong>Get Responses</strong> to check for new responses.
        </div>

</div> <!-- /container -->
<?php endif; // count if we have responses ?> 
</div> <!-- /col -->


</div> <!-- /row -->
</div> <!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

    <?php echo $chart_scripts; ?>


</script>

<?php endif ?> <!-- //canACcess() -->

<?php require('../templates/javascript.php') ?>
<?php require('../templates/footer.php') ?>
