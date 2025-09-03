<?php
opcache_reset();
$path = './inc/lsapp.php';
require($path); 

$response_map = [
    // 'classCode' => '',
    // 'courseCode' => 'ITEM-2014',
    'whatWasTricky' => [
        'label' => 'What was tricky?',
        'inputType' => 'text'
    ],
    'whatWorkedWell' => [
        'label' => 'What worked well?',
        'inputType' => 'text'
    ],
    'iWouldRecommendThisCourseToAColleague' => [
        'label' => 'I would recommend this course to a colleague.',
        'values' => [
            'stronglyDisagree' => 'Strongly Disagree',
            'disagree' => 'Disagree',
            'neutral' => 'Neutral',
            'agree' => 'Agree',
            'stronglyAgree' => 'Strongly Agree'
        ],
        'inputType' => 'radio'
    ],
    'iIntendToApplyTheKnowledgeAndSkillsIGainedFromThisLearningBackOnTheJob' => [
        'label' => 'I intend to apply the knowledge and skills I gained from this learning back on the job.',
        'values' => [
            'stronglyDisagree' => 'Strongly Disagree',
            'disagree' => 'Disagree',
            'neutral' => 'Neutral',
            'agree' => 'Agree',
            'stronglyAgree' => 'Strongly Agree'
        ],
        'inputType' => 'radio'
    ],
    'iGainedMorePersonalInsightIntoHowValuesPersonalityAndFactorsMayLeadToConflictInTheWorkplace' => [
        'label' => 'I gained more personal insight into how values, personality, and factors may lead to conflict in the workplace.',
        'values' => [
            'stronglyDisagree' => 'Strongly Disagree',
            'disagree' => 'Disagree',
            'neutral' => 'Neutral',
            'agree' => 'Agree',
            'stronglyAgree' => 'Strongly Agree'
        ],
        'inputType' => 'radio'
    ],
    'iUnderstandOurResponsibilityAsPublicServiceEmployeesToCreateAndMaintainRespectfulAndInclusiveWorkplaces' => [
        'label' => 'I understand our responsibility as public service employees to create and maintain respectful and inclusive workplaces.',
        'values' => [
            'stronglyDisagree' => 'Strongly Disagree',
            'disagree' => 'Disagree',
            'neutral' => 'Neutral',
            'agree' => 'Agree',
            'stronglyAgree' => 'Strongly Agree'
        ],
        'inputType' => 'radio'
    ]
];

$chart_scripts = '';

function compileResponsesByClass($file) {
    global $response_map;
    global $chart_scripts;

    // open and decode file
    $file_contents = file_get_contents($file);
    $response_data = json_decode($file_contents, true);
    
    // output array
    $responses = array();
    
    foreach ($response_data as $response) {
        if (!array_key_exists($response['classCode'], $responses)) {
                $responses[$response['classCode']] = array();
            }
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
                if (!array_key_exists($question, $responses[$response['classCode']])) {
                    $responses[$response['classCode']][$question] = array();
                } else {
                    $responses[$response['classCode']][$question][] = $answer;
                }
            } 
            // for radio type responses add a count of the response option
            elseif ($response_map[$question]['inputType'] == 'radio') {
                if (!array_key_exists($question, $responses[$response['classCode']])) {
                    $responses[$response['classCode']][$question] = array();
                    $responses[$response['classCode']][$question]['total'] = 0;
                } elseif (!array_key_exists($answer, $responses[$response['classCode']][$question])) {
                    $responses[$response['classCode']][$question][$answer] = 1;
                    $responses[$response['classCode']][$question]['total']++;
                } else {
                    $responses[$response['classCode']][$question][$answer]++;
                    $responses[$response['classCode']][$question]['total']++;
                }
            }

        }
    }
    return $responses;
}

function compileResponses($file) {
    global $response_map;
    global $chart_scripts;

    // open and decode file
    $file_contents = file_get_contents($file);
    $response_data = json_decode($file_contents);
    
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
$compiled_responses = compileResponses('data/surveys/test-data.json');



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



?>

<?php if(canACcess()): ?>

<?php getHeader() ?>
    <title>Evaluation Report</title>
    <script src="/lsapp/js/list.min.js"></script>

<?php getScripts() ?>
<body>
<?php getNavigation() ?>

<?php
$test_responses = compileResponsesByClass('data/surveys/test-data.json')

?>


<pre>
    <?php print_r($test_responses) ?>
</pre>



<div class="container-lg p-lg-5 p-4 bg-light-subtle rounded">
    <h1 class="mb-5">Courses Evaluation Report</h1>
     
    <div class="row justify-content-md-center">
        <div class="col-8 my-3 py-3 bg-secondary-subtle text-secondary-emphasis rounded shadow-sm">
            <p>Select class code to view responses</p>
            <select class="form-select" aria-label="select data" multiple>
                <option selected>All</option>
                <option value="1">One</option>
                <option value="2">Two</option>
                <option value="3">Three</option>
                <option value="4">Four</option>
                <option value="5">Five</option>
            </select>


        </div>
    </div>

    <?php
    foreach($compiled_responses as $question => $response) {
        if ($response_map[$question]['inputType'] == 'radio') {
            echo '<div class="row justify-content-md-center">';
                echo '<div class="col-4">';
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
    

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

    <?php echo $chart_scripts; ?>




</script>

<?php endif ?> <!-- //canACcess() -->

<?php require('./templates/javascript.php') ?>
<?php require('./templates/footer.php') ?>
