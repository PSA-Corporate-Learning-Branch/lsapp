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
 * For initial implementation of adding survey id
 * using getNextConfigID() to get initial value
 * and then passing the return value to updateConfig()
 * 
 * @param array config file array to be updated
 * @param int current max id + 1 as starting point
 * 
 * @return array updated config file array
 */
// function addConfigID($config_file, $start_id) {
//     $id = $start_id;
//     $new_config = [];
//     foreach ($config_file as $config) {
//         if (!isset($config['id'])) {
//             $config['id'] = $id;
//             $id++;
//         }
//         $new_config[] = $config;
//     }
//     return $new_config;
// }


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

// random_bytes() 