<?php
opcache_reset();
$path = './inc/lsapp.php';
require($path); 

/**
 * Using the provided config, reach out to form endpoint
 * update basic info field, and determine if version sync is required
 * to update the question mapping
 */
function syncForm($config) {
    // take the provided config, sync with the form endpoint
    // and determine if version needs to sync
    $return_config = $config;
    $form_id = $config['formId'];
    $secret = $config['formSecret'];

    $form_endpoint = 'https://submit.digital.gov.bc.ca/app/api/v1/forms/' . $form_id;
    $credentials = base64_encode($form_id . ':' . $secret);
    $options = [
    'http' => [
        'method' => 'GET', 
        'header' => 'Authorization: Basic ' . $credentials
        ]
    ];
    $context = stream_context_create($options);

    // reach out to form endpoint and get data
    $response = file_get_contents($form_endpoint, false, $context);
    $form_data = json_decode($response, true);

    // update from form
    $return_config['lastUpdated'] = $form_data['updatedAt'];

    $return_config['name'] = $form_data['name'];
    
    $return_config['description'] = $form_data['description'];
    
    if (!isset($return_config['responseFile'])) {
        $return_config['responseFile'] = $form_data['snake'];
    }
    else if ($return_config['responseFile'] !== $form_data['snake']) {
        // if we're using the snake for the responses filename (for now)
        // we'll need do something to the old file if we create a new one
        $return_config['responseFile'] = $form_data['snake'];
    }

    // check for a change to the current version
    foreach ($form_data['versions'] as $version) {
        
        // check if it's the published version
        if ($version['published'] == true) {
            
            // check if it's not set or matches our published version, and if not,
            // update our values and process the version to update the question map
            if (!isset($return_config['publishedVersion']) || $return_config['publishedVersion'] !== $version['version']) {
                
                // capture the new version information
                $return_config['publishedVersion'] = $version['version'];
                $return_config['publishedVersionId'] = $version['id'];
                
                // reach out to version endpoint get the form version json
                $version_data = getVersion($return_config);

                // process the version and extract questions to create our questions map
                $questions_map = processVersionQuestions($version_data);

                // add questions map to config
                $return_config['questions'] = $questions_map;
            }
        }
    }

    return $return_config;

}

/**
 * Return the version json/array so it can be parsed
 * by processVersionQuestions() to pull out the questions for mapping
 */
function getVersion($config) {
    
    $form_id = $config['formId'];
    $secret = $config['formSecret'];
    $version_id = $config['publishedVersionId'];

    $version_endpoint = 'https://submit.digital.gov.bc.ca/app/api/v1/forms/' . $form_id . '/versions/' . $version_id;

    $credentials = base64_encode($form_id . ':' . $secret);
    $options = [
    'http' => [
        'method' => 'GET', 
        'header' => 'Authorization: Basic ' . $credentials
        ]
    ];
    $context = stream_context_create($options);

    // reach out to form endpoint and get data
    $response = file_get_contents($version_endpoint, false, $context);
    $version_data = json_decode($response, true);

    return $version_data;
}


/**
 * Takes the form version json as an array, and returns
 * an array mapping of the questions, types, response options, and labels
 */
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


function getResponses($config) {

    // get responses.  requires additional parameters: format, type, and version
    
    $form_id = $config['formId'];
    $secret = $config['formSecret'];

    $params = [
        'format' => 'json',
        'type' => 'submissions'
    ];
    $query_string = http_build_query($params);

    $submissions_export_endpoint = 'https://submit.digital.gov.bc.ca/app/api/v1/forms/' . $form_id . '/export' . '?' . $query_string;

    $credentials = base64_encode($form_id . ':' . $secret);

    $options = [
    'http' => [
        'method' => 'GET', 
        'header' => 'Authorization: Basic ' . $credentials
        ]
    ];

    $context = stream_context_create($options);

    

    $response = file_get_contents($submissions_export_endpoint, false, $context);
    $response_data = json_decode($response, true);

}

// $response = file_get_contents($endpoint_form_details, false, $context);
// $formData = json_decode($response, true);

// // testing form
// $form_contents = file_get_contents('data/surveys/test-form.json');
// $formData = json_decode($form_contents, true);


// open and decode config file
$config_file = 'data/surveys/config.json';
$file_contents = file_get_contents($config_file);

// create config backup file
file_put_contents('data/surveys/config-backup.json', $file_contents);
 
$config = json_decode($file_contents, true);

// updated config data for file
$updated_config = [];

// sync the forms in the config file
foreach ($config as $form_config) {
    
    // sync the forms
    $synced_config = syncForm($form_config);
    $updated_config[] = $synced_config;
    
    // get new responses
}

$json_config = json_encode($updated_config, JSON_PRETTY_PRINT);
file_put_contents($config_file, $json_config);

// testing version
$version_contents = file_get_contents('data/surveys/test-version.json');
$versionData = json_decode($version_contents, true);

echo '<pre>';
print_r($updated_config);
echo '</pre>';

// $test_result = processVersionQuestions($versionData);

// $test_questions = processVersionQuestions($test_result);



echo '<pre>';
// print_r($test_result);
echo '</pre>';

echo '<pre>';
// print_r($test_questions);
echo '</pre>';



