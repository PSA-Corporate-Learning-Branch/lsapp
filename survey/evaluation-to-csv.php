<?php
opcache_reset();
$path = '../inc/lsapp.php';
require($path); 

$form_id = (isset($_GET['formid'])) ? $_GET['formid'] : 0;
$class_code = (isset($_GET['classCode'])) ? $_GET['classCode'] : 0;
$alert = '';
$title = 'Course Survey Export';
$data_path = '../data/surveys/';

// testing link - http://localhost:8080/lsapp/survey/evaluation-report.php?formid=7b6cd68b-85b9-41c3-b725-97339b06cc6e
// testing link with class - http://localhost:8080/lsapp/survey/evaluation-report.php?formid=7b6cd68b-85b9-41c3-b725-97339b06cc6e&classCode=ITEM-2014-55

/**
 * Take the form id, find the matching config
 * and return the corresponding questions map array
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

// populate our response map from the url form id
$response_map = getQuestionsConfig($form_id);

/**
 * Take the form id, and return all
 * responses as an associative array
 */
function getResponses($form_id) {
    global $alert, $data_path;

    if (!$form_id) {
        $alert = 'Form ID not provided';
        return;
    }
    
    $response_file = $data_path . $form_id . '.json';

    // get the file contents or false on failure
    $response_contents = file_get_contents($response_file);
    
    if (!$response_contents) {
        $alert = 'Error opening file.';
        return;
    }

    // decode our json data into an associative array
    $response_data = json_decode($response_contents, true);

    return $response_data;

}
$response_data = getResponses($form_id, $class_code);

/**
 * Return an array of unique class codes across responses
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
$classes = getResponseClasses($response_data);

/**
 * Returns a filtered array of responses
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

if ($class_code && in_array($class_code, $classes)) {
    $response_data_processed = filterResponsesByClass($response_data, $class_code);
} else {
    $response_data_processed = $response_data;
}

$chart_scripts = '';


function compileResponses($response_data) {
    global $response_map;
    global $chart_scripts;

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
            elseif (empty($answer)) {
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

        }
    }
    return $responses;
}

$compiled_responses = compileResponses($response_data_processed);

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
        $html .=    '</div>';
    }

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
                ],
                hoverOffset: 4
            }]
        }
    });";

    return $html;
    
}

function createTextResponses($question, $responses) {
    global $response_map;

    $html =    '<div class="card my-3" >';
    $html .=        '<h2 class="m-3">' . $response_map[$question]['label'] . '</h2>';
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

function exportToCsv($form_id) {
    global $response_map, $compiled_responses, $data_path;

    // open config
    $config_file = $data_path . 'config.json';
    $config_content = file_get_contents($config_file);
    $config_array = json_decode($config_content, true);

    foreach ($config_array as $config) {
        if ($config['formId'] == $form_id) {
            if (array_key_exists('slug', $config) && array_key_exists('lastResponsesUpdated', $config)) {
                $as_of_date = date('Y-m-d', $config['lastResponsesUpdated']);
                $output_filename = $data_path . $config['slug'] . '_export_as_of_' . $as_of_date . '.csv';
                $survey_config = $config;
            } 
            else {
                return 'config missing data.  run sync and try again.';
            }
        }
    }
    $output_file = fopen($output_filename, 'w');

    # Create header
    fputcsv($output_file, [$survey_config['name'] . ' Summary Report']);
    fputcsv($output_file, []);

    foreach($compiled_responses as $question=>$responses) {
        # Question
        fputcsv($output_file, [$response_map[$question]['label']]);

        # text questions
        if ($response_map[$question]['inputType'] == 'text') {
            # Column Headers
            fputcsv($output_file, ['Response #', 'Value']);
            foreach($responses as $index=>$response) {
                fputcsv($output_file, [$index + 1, $response]);
            }
            fputcsv($output_file, []);
        }
        # radio questions
        if ($response_map[$question]['inputType'] == 'radio') {
            # Column Headers
            fputcsv($output_file, ['Response', 'Value', 'Percent']);
            foreach($response_map[$question]['values'] as $key=>$value) {
                $num_values = $compiled_responses[$question][$key] ?? 0;
                $total = $compiled_responses[$question]['total'] ?? 0;
                if ($num_values !== 0 && $total !== 0) {
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


}

    



?>

<?php if(canACcess()): ?>

<?php getHeader() ?>
    <title>Evaluation Export</title>
    <script src="/lsapp/js/list.min.js"></script>

<?php getScripts() ?>
<body>
<?php getNavigation() ?>

<?php



?>


<pre>
    <?php //print_r(gettype($response_data)) ?>
    <?php //print_r($classes); ?>
</pre>

<div class="container-fluid">
<div class="row justify-content-md-center mb-3">

    <div class="col-12">
        <h1 class="mb-5 text-center"><?= $title . ' Export' ?></h1>
    </div>




<div class="col-9">
<div class="container-lg p-lg-5 p-4 bg-secondary-subtle rounded">
     
    <!-- Alerts & Errors -->
    <span style="background-color: yellow;"><strong><?= $alert ?></strong></span>
    
    <?php 
    
    exportToCsv($form_id);



    ?>


<pre>
    <?php print_r($compiled_responses) ?>
</pre>

<pre>
    <?php print_r($response_map) ?>
</pre>

</div> <!-- /container -->
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
