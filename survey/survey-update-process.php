<?php
opcache_reset();
$path = '../inc/lsapp.php';
require($path); 
require('eval-functions.php');

// Include encryption helper for decrypting API passwords
require_once(dirname(__DIR__) . '/inc/encryption_helper.php');

// print_r($_POST);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get our submitted values
    $form_id = $_POST['FormId'];
    $new_status = $_POST['Status'];
    $new_secret = $_POST['FormSecret'];

    // check our form id is valid before proceeding
    if (!verifyFormID($form_id)) {
        AlertManager::addAlert('danger', 'Invalid Form ID. Please correct and re-submit.');
        header('Location: ./edit-survey.php?formId=' . $form_id);
        exit;
    }

    // get our current survey config
    $survey_config = getConfigSurvey($form_id);
    if (empty($survey_config)) {
        AlertManager::addAlert('danger', 'Survey not found.');
        header('Location: ./edit-survey.php?formId=' . $form_id);
        exit;
    }

    // create a copy for updates
    $new_config = $survey_config;

    // if we're provided a new/different form secret
    if (!isset($survey_config['formSecret']) || $new_secret !== $survey_config['formSecret']) {
        // nothing provided, go back to edit page
        if (empty($new_secret)) {
            header('Location: ./edit-survey.php?formId=' . $form_id);
            exit;
        }
        // secret provided but not what expected
        else if (strlen($new_secret) < 30) {
            AlertManager::addAlert('danger', 'Invalid Form Secret. Please correct and re-submit.');
            header('Location: ./edit-survey.php?formId=' . $form_id);
            exit;
        }
        // good to update
        else {
            $secret_encrypted = EncryptionHelper::encrypt($new_secret);
            $new_config['formSecret'] = $secret_encrypted;
        }
    }
    
    // if survey is being changed to active but wasn't before
    if ($new_status == 'active' && $survey_config['status'] !== 'active') {
        // if we don't have a secret, or the new one isn't valid
        if (!isset($survey_config['formSecret']) || strlen($new_config['formSecret']) < 30) {
            AlertManager::addAlert('danger', 'Survey must have a valid secret before it can be made active. Please correct and re-submit.');
            header('Location: ./edit-survey.php?formId=' . $form_id);
            exit;
        } 
        $new_config['status'] = $new_status;
    } else {
        $new_config['status'] = $new_status;
    }

    // if something has changed, save config
    if ($new_config !== $survey_config) {
        updateConfigByFormId($form_id, $new_config);
        AlertManager::addAlert('success', 'Survey details successfully updated.');
        header('Location: ./edit-survey.php?formId=' . $form_id);
        exit;
    } else {
        header('Location: ./edit-survey.php?formId=' . $form_id);
        exit;
    }
    
}


