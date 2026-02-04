<?php
opcache_reset();
$path = '../inc/lsapp.php';
require($path); 



/**
 * Take the form id, find the matching config
 * and return the corresponding questions map array
 * 
 * @param $form_id the CHEFS form id
 * @return null
 */
function getQuestionsConfig($form_id) {
    global $alert, $title, $data_path;

    if (!$form_id) {
        $alert = 'Form ID not provided';
        return;
    }

    // open config
    $config_file = $data_path . 'config.json';
    $config_content = file_get_contents($config_file);
    $config_array = json_decode($config_content, true);

    // find config with matching form id
    foreach ($config_array as $config) {
        if ($config['formId'] == $form_id) {
            if (array_key_exists('name', $config)) {
                $title = $config['name'];
            }
            if (array_key_exists('questions', $config)) {
                return $config['questions'];
            } else {
                // if the questions key doesn't exist, we likely need to sync the form
                $alert = 'Questions not found. Form may need sync.';
            }
            
        } 
    }

    // if no forms match the provided id
    $alert = 'Form not found.';
    return;
}

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
function compileResponses($response_data) {
    global $response_map;

    if (!$response_map) {
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
    return $responses;
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

// class codes included in our responses
$classes = array();

// to hold our chart javascript
$chart_scripts = '';

// get our response map from the config using form id
$response_map = getQuestionsConfig($form_id);

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
        $alert_warning .= "<p>No responses found for this class (\"" . $class_code . "\").</p>";
    }

    // otherwise, pass along all the responses
    else {
        $response_data_processed = $response_data;
    }

    // pass through date date inputs and filter if provided
    $responses_filtered_by_date = filterResponsesByDate($response_data_processed, $start_date, $end_date);
    if (count($responses_filtered_by_date) == 0) {
        $alert_warning .= "<p>No responses found for the selected dates.</p>";
    }

    // take our filtered raw responses and summarize
    $compiled_responses = compileResponses($responses_filtered_by_date);

} else {
    // if we don't have a responses file, we'll use this variable to 
    // prevent loading more content after the alerts are added
    $compiled_responses = array();
    $alert_warning .= "<p>No responses found for this survey.</p>";
}


// Filtered classes info
if (!empty($class_code) && count($classes) > 0) {
    $test = !empty($class_code);
    $alert_info .= "<p>Showing results for {$class_code}.</p>";
}

/** Filtered dates info */
// If we have both dates
if (!empty($start_date) && !empty($end_date)) {
    $alert_info .= "<p>Showing results between {$start_date} and {$end_date}.</p>";
} 
// if we have a start date but no end date
else if (!empty($start_date) && empty($end_date)) {
    $alert_info .= "<p>Showing results after {$start_date}.</p>";
}
// if we have an end date but no start date
else if (empty($start_date) && !empty($end_date)) {
    $alert_info .= "<p>Showing results before {$end_date}.</p>";
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

    <div class="col-12">
        <h1 class="mb-5 text-center"><?= $title . ' Report' ?></h1>
    </div>



<div class="col-lg-2" name="side-nav">
    <div class="card sticky-top m-auto z-0 overflow-hidden" style="top: 65px; max-width: 310px;">
        <form action="evaluation-report.php" method="get">
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

    <!-- <pre> -->
        <!-- Testing -->
        <?php //print_r($responses_filtered_by_date); ?>
    <!-- </pre> -->

    <?php if (strlen($alert_warning) > 0): ?>
        <div class="alert alert-warning" role="alert">
            <?= $alert_warning ?>
        </div>
    <?php endif; ?>

    <?php if (strlen($alert_info) > 0): ?>
        <!-- Info alerts -->
        <div class="alert alert-info" role="alert">
            <?= $alert_info ?>
        </div>
    <?php endif; ?>
    

</div>

<!-- if we don't have any responses, don't show the chart area -->
<?php if (count($compiled_responses) > 0): ?>

<div class="container-lg p-lg-5 p-4 bg-secondary-subtle rounded">
     
   
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
    

</div> <!-- /container -->
<?php endif; // count if we have responses ?> 
</div> <!-- /col -->


</div> <!-- /row -->
</div> <!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

    <?php echo $chart_scripts; ?>

    // const compiledResponses = <?php //echo json_encode($test_compiled_responses) ?>

    // console.log(compiledResponses);

    // function updateCharts(classCodes) {
    //     let newData = {};
    //     // if none provided, show all the data

    //     // if one or more selection provided compile and update the data
    //     classCodes.forEach(classCode => {
    //         if (classCode in compiledResponses) {
    //             for (const [key, value] of Object.entries(compiledResponses[classCode])) {

    //             }
    //             compiledResponses[classCode].forEach((response) => {
    //                 if (response in newData) {
    //                     newData[response] = response; // todo
    //                 } else {

    //                 }
    //             })
    //         }
    //     })
    // }

    // Listen for dropdown changes
    // document.getElementById('dataSelector').addEventListener('change', function () {
    //   updateCharts(this.value);
    // });

</script>

<?php endif ?> <!-- //canACcess() -->

<?php require('../templates/javascript.php') ?>
<?php require('../templates/footer.php') ?>
