<?php

if (!defined('WP_UNINSTALL_PLUGIN'))
    exit();

global $easyling_instance;

if ($easyling_instance == null) {
    require_once 'easyling.php';
}

$ptm = $easyling_instance->getPtm();
$ptm->uninstall();

delete_option('easyling_available_locales');
delete_option('easyling_project_languages');
delete_option('easyling_linked_project');
delete_option('easyling_multidomain');
delete_option('easyling_consent');
delete_option('easyling_access_tokens');
// added 0.9.10
delete_option('easyling_source_langs');
// option to store oauth consumer key and secret
// remove our sessions
if (!session_id())
    session_start();
unset($_SESSION['oauth']);
unset($_SESSION['oauth_internal_redirect']);

delete_option('easyling');
delete_option('easyling_id');