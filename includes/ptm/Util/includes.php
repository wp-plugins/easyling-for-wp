<?php
/**
 * User: Atesz
 * Date: 2012.12.15.
 * Time: 22:14
 */

if (version_compare(PHP_VERSION, '5.1.0', '<')) {
require_once dirname(__FILE__) . '/PHP51.php';
}
if (version_compare(PHP_VERSION, '5.2.0', '<')) {
	require_once dirname(__FILE__) . '/JSON.php';
	require_once dirname(__FILE__) . '/PHP52.php';
}
require_once dirname(__FILE__) . '/DOMUtil.php';
require_once dirname(__FILE__) . '/SerializeUtil.php';
require_once dirname(__FILE__) . '/HTTPHeader.php';
require_once dirname(__FILE__) . '/Locale.php';