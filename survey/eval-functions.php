<?php

$data_path = '../data/surveys/';
$config_file = $data_path . 'config.json';

function getConfig() {
    global $config_file;
    
    $file_contents = file_get_contents($config_file);

    $config = json_decode($file_contents, true);

    return $config;
}

function updateConfig($new_config) {
    global $config_file, $data_path;

    // back up config file
    $file_contents = file_get_contents($config_file);
    file_put_contents($data_path . 'config-backup.json', $file_contents);

    // save updated config
    $json_config = json_encode($new_config, JSON_PRETTY_PRINT);
    file_put_contents($config_file, $json_config);

}

function verifyFormID($form_id) {
    // verify our CHEFS form id matches the pattern
    $pattern = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/';
    $match = preg_match($pattern, $form_id); // returns 1 on match

    if ($match === 1) {
        return true;
    } else {
        return false;
    }
}