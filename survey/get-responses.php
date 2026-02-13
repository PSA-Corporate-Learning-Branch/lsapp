<?php
opcache_reset();
$path = '../inc/lsapp.php';
require($path); 
require('eval-functions.php');
$data_path = '../data/surveys/';

// Include encryption helper for decrypting API passwords
require_once(dirname(__DIR__) . '/inc/encryption_helper.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $form_id = $_POST['FormId'];
    $survey_config = getConfigSurvey($form_id);

    // check lastResponsesUpdated

    print_r($survey_config);

    // decrypt form secret
    $form_secret = EncryptionHelper::decrypt($config['formSecret']);

    $responses_config = $config; // replaced by $survey_config

    $params = [
        'format' => 'json',
        'type' => 'submissions'
    ];
    // preference={"minDate":"2020-12-17T08:00:00Z"}
    // $date = new DateTime("@$timestamp");
    // $date->format('Y-m-d\TH:i:sp'); 
    // TODO: use the preference object to only request new responses
    // via the lastResponsesUpdated field in the config (unix timestamp)

    $query_string = http_build_query($params);

    $submissions_export_endpoint = 'https://submit.digital.gov.bc.ca/app/api/v1/forms/' . $form_id . '/export' . '?' . $query_string;

    $credentials = base64_encode($form_id . ':' . $form_secret);

    $options = [
    'http' => [
        'method' => 'GET', 
        'header' => 'Authorization: Basic ' . $credentials
        ]
    ];

    $context = stream_context_create($options);

    $new_responses = file_get_contents($submissions_export_endpoint, false, $context);

    // if we're only getting recent responses, check if there were any

    $response_file = $data_path . $form_id . '-test.json';
    // check if file already exists
    if (file_exists($response_file)) {
        $file_contents = file_get_contents($response_file);
        $current_responses = json_decode($file_contents, true);

        // extract new responses and add to current responses
    }

    file_put_contents($response_file, $response);

    // $response_data = json_decode($response, true);

    $survey_config['lastResponsesUpdated'] = time();

    // update the config file for this form
    // updateConfigByFormId($form_id, $survey_config);
    // return $responses_config;





}