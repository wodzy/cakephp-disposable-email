<?php
declare(strict_types=1);

use Cake\Cache\Cache;
use Cake\Core\Configure;

require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
define('PLUGIN_ROOT', dirname(__DIR__));
define('ROOT', PLUGIN_ROOT . DS . 'tests' . DS . 'test_app');
define('APP_DIR', 'src');
define('APP', ROOT . DS . 'src' . DS);
define('CONFIG', ROOT . DS . 'config' . DS);
define('WWW_ROOT', ROOT . DS . 'webroot' . DS);
define('TESTS', PLUGIN_ROOT . DS . 'tests' . DS);
define('TMP', sys_get_temp_dir() . DS . 'cakephp-disposable-email' . DS);
define('LOGS', TMP . 'logs' . DS);
define('CACHE', TMP . 'cache' . DS);
define('CAKE_CORE_INCLUDE_PATH', PLUGIN_ROOT . DS . 'vendor' . DS . 'cakephp' . DS . 'cakephp');
define('CORE_PATH', CAKE_CORE_INCLUDE_PATH . DS);
define('CAKE', CORE_PATH . 'src' . DS);

if (!is_dir(TMP)) {
    mkdir(TMP, 0777, true);
}

require CAKE . 'functions.php';

Configure::write('debug', true);
Configure::write('App', [
    'namespace' => 'TestApp',
    'encoding' => 'UTF-8',
    'defaultLocale' => 'en_US',
    'paths' => [
        'plugins' => [ROOT . DS . 'plugins' . DS],
        'templates' => [ROOT . DS . 'templates' . DS],
        'locales' => [ROOT . DS . 'resources' . DS . 'locales' . DS],
    ],
]);

Cache::setConfig([
    '_cake_core_' => ['className' => 'Array'],
    '_cake_translations_' => ['className' => 'Array'],
    '_cake_model_' => ['className' => 'Array'],
    'default' => ['className' => 'Array'],
]);
