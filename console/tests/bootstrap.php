<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

define('ROOT', realpath(__DIR__ . '/..'));
define('TESTS', __DIR__ . '/');
define('TMP', sys_get_temp_dir() . '/');
