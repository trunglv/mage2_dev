<?php
// Constants Magento defines at bootstrap (app/autoload.php), so PHPStan can resolve them.
if (!defined('BP')) {
    define('BP', dirname(__DIR__, 2));
}
