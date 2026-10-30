<?php
/**
 * setup.php — initialize the application.
 * Define database credentials and API keys.
 */

 if(!defined('DB_HOST')){define('DB_HOST','mysql.railway.internal');  }
 if(!defined('DB_NAME')){define('DB_NAME','railway'); }
 if(!defined('DB_USER')){define('DB_USER','root'); }
 if(!defined('DB_PASS')){define('DB_PASS',''); }