<?php
///////////////////////////////////////////
// bootstrap-lib.php
//===================================================
////////////////////////////////////
if (!defined('ABSPATH')) { die('ABSPATH must be defined before loading bootstrap-lib.php'); }
if (!defined('WH_LIB'))  { define('WH_LIB', '/home/customer/www/_lib/'); }
//======================================================================
require_once WH_LIB . 'PathInspector.php';
require_once WH_LIB . 'Autoloader.php';
//======================================================================
Autoloader::setPath(WH_LIB);
spl_autoload_register(['Autoloader', 'loader']);
//======================================================================
///////////////////////////////////////