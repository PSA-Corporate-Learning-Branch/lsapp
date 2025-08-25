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
$compiled_responses = compileResponses('data/test-data.json');



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
        $html .=    '<div class="row align-items-center">';
        $html .=        '<div class="col-4">';
        $html .=            '<p style="font-size: smaller;">' . $option['name'] . '</p>';
        $html .=        '</div>';
        $html .=        '<div class="col-8">';
        $html .=            '<div class="progress" role="progressbar" aria-label="' . $option['name'] . '" aria-valuenow="' . $option['value'] . '" aria-valuemin="0" aria-valuemax="100">';
        $html .=                '<div class="progress-bar text-bg-success" style="width: ' . $option['value'] . '%">' . $option['value'] . '%</div>';
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
                'rgb(255, 99, 132)',
                'rgb(54, 162, 235)',
                'rgb(255, 205, 86)',
                'rgba(32, 185, 57, 1)',
                'rgba(157, 47, 167, 1)',
                ],
                hoverOffset: 4
            }]
        }
    });";

    return $html;
    
}








// $labels = array();
// $data = array();
// foreach ($compiled_responses['iIntendToApplyTheKnowledgeAndSkillsIGainedFromThisLearningBackOnTheJob'] as $key => $value) {
//     $labels[] = $key;
//     $data[] = $value;
// } 


?>

<?php if(canACcess()): ?>

<?php getHeader() ?>
    <title>Evaluation Report</title>
    <script src="/lsapp/js/list.min.js"></script>

<?php getScripts() ?>
<body>
<?php getNavigation() ?>




<pre><?php print_r($compiled_responses) ?></pre>



<div class="container-lg p-lg-5 p-4 bg-light-subtle">
    <h1>Courses Evaluation Report</h1>
     
    <div class="row justify-content-md-center">
        
        <div class="col-4">
            
            

            <?php
            foreach($compiled_responses as $question => $response) {
                if ($response_map[$question]['inputType'] == 'radio') {
                    echo createChartForRadio($question, $compiled_responses);
                }
            }
            ?>
        </div>

        <div class="col-4">

            <div class="card my-3" > <!-- style="width: 18rem;" -->
                <h2 class="m-3">What was tricky?</h2>
                <details>
                    <summary class="m-3">View details</summary>
                    <!-- <div class="card-body"> -->
                    <ul class="list-group m-3">
                        <li class="list-group-item">An item</li>
                        <li class="list-group-item">A second item</li>
                        <li class="list-group-item">A third item</li>
                        <li class="list-group-item">A fourth item</li>
                        <li class="list-group-item">And a fifth one</li>
                    </ul>
                    <!-- </div> -->
                </details>

            </div>

        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

    <?php echo $chart_scripts; ?>




</script>

<?php endif ?> <!-- //canACcess() -->

<?php require('./templates/javascript.php') ?>
<?php require('./templates/footer.php') ?>
