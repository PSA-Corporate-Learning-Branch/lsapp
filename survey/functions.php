<?php

// $data_path = '../data/surveys/';
$data_path = build_path(BASE_DIR, 'data', 'surveys/');
$config_file = build_path($data_path, 'config.json');

function getConfig() {
    global $config_file;
    
    $file_contents = file_get_contents($config_file);

    $config = json_decode($file_contents, true);

    return $config;
}

/**
 * Return the config details for a single survey
 */
function getConfigSurvey($form_id) {
    global $config_file;
    
    $file_contents = file_get_contents($config_file);
    $config = json_decode($file_contents, true);
    $survey_config = array();

    foreach ($config as $survey) {
        if ($survey['formId'] == $form_id) {
            $survey_config = $survey;
        }
    }
    return $survey_config;
}

/**
 * Backup then save updated config to file
 */
function updateConfig($new_config) {
    global $config_file, $data_path;

    // back up config file
    $file_contents = file_get_contents($config_file);
    file_put_contents($data_path . 'config-backup.json', $file_contents);

    // save updated config
    $json_config = json_encode($new_config, JSON_PRETTY_PRINT);
    file_put_contents($config_file, $json_config);

}

/**
 * Update the survey in the config by the provided id
 */
function updateConfigByFormId($form_id, $new_survey_config) {
    global $config_file, $data_path;

    // back up config file
    $file_contents = file_get_contents($config_file);
    file_put_contents($data_path . 'config-backup.json', $file_contents);

    $config = json_decode($file_contents, true);
    $new_config = array();

    // replace the config for the matching form id
    foreach ($config as $survey) {
        if ($survey['formId'] == $form_id) {
            array_push($new_config, $new_survey_config);
        } else {
            array_push($new_config, $survey);
        }
    }
    
    // save updated config
    $json_config = json_encode($new_config, JSON_PRETTY_PRINT);
    file_put_contents($config_file, $json_config);

}


function getNextConfigID($config_file) {
    $id = 0;
    foreach ($config_file as $config) {
        if (isset($config['id']) && is_numeric($config['id'])) {
            $id = max($id, (int)$config['id']);
        }
    }
    return $id+1;
}

/**
 * Check that we have responses for a given form id
 * 
 * @param string $form_id the chefs form id
 * 
 * @return bool whether we have a corresponding response file
 */
function hasResponses($form_id) {
    global $data_path;
    $response_files = glob($data_path . "*-*-*-*.json");
    foreach ($response_files as $file) {
        
    $without_path = str_replace($data_path, '', $file);
        $without_extension = str_replace('.json', '', $without_path);
        // if we find a matching form id, return
        if (trim($without_extension) == trim($form_id)) {
            return true;
        }

    }
    
    // if we don't find a matching form id
    return false;

}

/**
 * Do a pattern match on the form id to ensure it's valid
 */
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

class AlertManager {
    
    public static function addAlert(string $type, string $message): void {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function getAlertsAll(): array {
        $alerts = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $alerts;
    }

}