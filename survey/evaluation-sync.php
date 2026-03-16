<?php
opcache_reset();
$path = '../inc/lsapp.php';
require($path); 
require('eval-functions.php');
require_once(dirname(__DIR__) . '/inc/encryption_helper.php');

$data_path = '../data/surveys/';


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
    $secret = EncryptionHelper::decrypt($config['formSecret']);

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
    
    $return_config['slug'] = $form_data['snake'];

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
    $secret = EncryptionHelper::decrypt($config['formSecret']);
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
                // TODO: add select, checkbox types, possibly others
            }
            # select questions don't have an inputType so we need to look for type value
            else if (array_key_exists('type', $value)) {
                if ($value['type'] == 'simpleselectadvanced') {
                    $select_options = [];
                    foreach ($value['data']['values'] as $option) {
                        $select_options[$option['value']] = $option['label'];
                    }
                    $return_array[$value['key']] = [
                        'label' => $value['label'],
                        'values' => $select_options,
                        'inputType' => 'select'
                    ];
                    continue;
                }
            }

            // if we determine it's not a question, process the array looking for
            // questions within as they can be nested in a layout component
            $return_array = processVersionQuestions($value, $return_array);
        }
    }
    return $return_array;
}


function getResponses($config) {
    global $data_path;

    // get responses.  requires additional parameters: format, type, and version
    
    $form_id = $config['formId'];
    $secret = EncryptionHelper::decrypt($config['formSecret']);
    $responses_config = $config;

    $params = [
        'format' => 'json',
        'type' => 'submissions'
    ];
    // preference={"minDate":"2020-12-17T08:00:00Z"}
    // TODO: use the preference object to only request new responses
    // via the lastResponsesUpdated field in the config (unix timestamp)

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

    $response_file = $data_path . $form_id . '.json';

    file_put_contents($response_file, $response);

    // $response_data = json_decode($response, true);

    $responses_config['lastResponsesUpdated'] = time();

    return $responses_config;

}

// $response = file_get_contents($endpoint_form_details, false, $context);
// $formData = json_decode($response, true);

// // testing form
// $form_contents = file_get_contents('data/surveys/test-form.json');
// $formData = json_decode($form_contents, true);


// open and decode config file
$config_file = $data_path . 'config.json';
$file_contents = file_get_contents($config_file);

// create config backup file
file_put_contents($data_path . 'config-backup.json', $file_contents);
 
$config = json_decode($file_contents, true);

// updated config data for file
$updated_config = [];

$form_id = $_GET['formId'] ?? '';

// sync a particular form if id is provided
if (!empty($form_id)) {
    
    foreach ($config as $form_config) {
        
        // if it's the form we want to sync
        if (isset($form_config['formId']) && $form_config['formId'] == $form_id) {

            // only sync if active
            if (isset($form_config['status']) && $form_config['status'] == 'active') {

                // check lastResponsesUpdated
                $time_since_last_sync = time() - $form_config['lastResponsesUpdated'];
                if ($time_since_last_sync < 60) {
                    AlertManager::addAlert('warning', "Sync run $time_since_last_sync seconds ago. Please wait a full minute before next sync.");
                    header('Location: ./edit-survey.php?formId=' . $form_id);
                    exit;
                }
            
                // sync the form and return an updated config
                $synced_form_config = syncForm($form_config);
                
                // pass the updated config and get new responses
                $synced_response_config = getResponses($synced_form_config);

                // take the fully updated config and add to our array
                $updated_config[] = $synced_response_config;

            }
            // if not active return to edit with error
            else {
                AlertManager::addAlert('danger', 'Survey must be Active to sync');
                header('Location: ./edit-survey.php?formId=' . $form_id);
                exit;
            }
        }
        // if it's not the form we want to sync add to the list and continue
        else {
            $updated_config[] = $form_config;
        }
    
    }

}

// otherwise sync the all forms in the config file
else {
    foreach ($config as $form_config) {
        
        // only sync active forms
        if (isset($form_config['status']) && $form_config['status'] == 'active') {

            // sync the form and return an updated config
            $synced_form_config = syncForm($form_config);
            
            // pass the updated config and get new responses
            $synced_response_config = getResponses($synced_form_config);

            // take the fully updated config and add to our array
            $updated_config[] = $synced_response_config;

        }
        # if form isn't active, add to new config as-is
        else {
            $updated_config[] = $form_config;
        }
    
    }

}

// save our updated config to file
$json_config = json_encode($updated_config, JSON_PRETTY_PRINT);
file_put_contents($config_file, $json_config);


// $test_questions = processVersionQuestions($test_result);



echo '<pre>';
// print_r($test_result);
echo '</pre>';

echo '<pre>';
// print_r($test_questions);
echo '</pre>';



