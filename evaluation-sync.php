<?php
opcache_reset();
$path = './inc/lsapp.php';
require($path); 

$formid = '7b6cd68b-85b9-41c3-b725-97339b06cc6e';
$formpass = '3aa4980f-26ca-49e5-9136-2456de8c2ad9';
$formVersionId = '';

$credentials = base64_encode($formid . ':' . $formpass);

// enpoints
// overall form information.  can be used to see current versions
$endpoint_form_details = 'https://submit.digital.gov.bc.ca/app/api/v1/forms/' . $formid;

// get a specific form version.  used to determine questions and types to create data map
$endpoint_form_version = 'https://submit.digital.gov.bc.ca/app/api/v1/forms/{formId}/versions/{formVersionId}';

// get responses.  requires additional parameters: format, type, and version
$endpoint_submissions_export = 'https://submit.digital.gov.bc.ca/app/api/v1/forms/{formId}/export';


$options = [
    'http' => [
        'method' => 'GET', 
        'header' => 'Authorization: Basic ' . $credentials
        ]
    ];

$context = stream_context_create($options);

$params = [
    'format' => 'json',
    'type' => 'submissions'
];

// $response = file_get_contents($endpoint_form_details, false, $context);
// $formData = json_decode($response, true);

// testing form
$form_contents = file_get_contents('data/surveys/test-form.json');
$formData = json_decode($form_contents, true);


// open and decode config file
$config_file = 'data/surveys/config.json';
$file_contents = file_get_contents($config_file);
$config = json_decode($file_contents, true);



// assume we don't need to sync the version endpoint unless we determine
// the version has changed
$sync_version = false;

$output_config = [];

// check if form exists in config table, and if not add relevant details
foreach ($config as $form_config) {
    // if form exists, check for any changes to form or published version
    if ($form_config['formId'] == $formData['id']) {
        
        // currently looking at each value, but should the number of things we're looking at
        // increase significantly we could restructure to only do additional processing if
        // updatedAt changes
        if ($form_config['lastUpdated'] !== $formData['updatedAt']) {
            $form_config['lastUpdated'] = $formData['updatedAt'];
        }

        if ($form_config['name'] !== $formData['name']) {
            $form_config['name'] = $formData['name'];
        }

        if ($form_config['description'] !== $formData['description']) {
            $form_config['description'] = $formData['description'];
        }
        
        if ($form_config['responses'] !== $formData['snake']) {
            $form_config['responses'] = $formData['snake'];
        }

        // versions change? 
        foreach ($formData['versions'] as $version) {
            // check if it's the published version
            if ($version['published'] == true) {
                // check if it matches our published version, and if not,
                // update our values and opt in to syncing the version
                if ($form_config['publishedVersion'] !== $version['version']) {
                    $form_config['publishedVersion'] = $version['version'];
                    $form_config['publishedVersionId'] = $version['id'];
                    $sync_version = true;
                }
            }
        }
    }
    $output_config[] = $form_config;
}

function processVersionQuestions(array $array, array &$return_array = []) {
    
    foreach ($array as $key => $value) {
        // check if the value is an array
        if (is_array($value)) {
            // if it is an array, check if it has the inputType key that lets us know it's 
            // a question and not another type of component
            if (array_key_exists('inputType', $value)) {
                // once we've determined it's a question, determine the type and process accordingly
                if ($value['inputType'] == 'radio') {
                    $value_options = [];
                    foreach ($value['values'] as $option) {
                        $value_options[$option['value']] = $option['label'];
                    }
                    $return_array[$value['key']] = [
                        'label' => $value['label'],
                        'values' => $value_options,
                        'inputType' => $value['inputType']
                    ];
                    continue;
                }
                else if ($value['inputType'] == 'text') {
                    $return_array[$value['key']] = [
                        'label' => $value['label'],
                        'inputType' => $value['inputType']
                    ];
                    continue;
                }
                // TODO: add checkbox type, possibly others
            }
            // if we determine it's not a question, process the array looking for
            // questions within as they can be nested in a layout component
            $return_array = processVersionQuestions($value, $return_array);
        }
    }
    return $return_array;
}


// testing version
$version_contents = file_get_contents('data/surveys/test-version.json');
$versionData = json_decode($version_contents, true);

echo '<pre>';
print_r($versionData);
echo '</pre>';

// $test_result = processVersionQuestions($versionData);

echo '<pre>';
// print_r($test_result);
echo '</pre>';



