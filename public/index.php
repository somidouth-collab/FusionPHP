<?php

define("BASE_PATH", dirname(__DIR__));

require_once BASE_PATH . "/fusionphp/core/ErrorHandler.php";
\Fusion\Core\ErrorHandler::register(false);

require BASE_PATH . "/app/config.php";
$dev = config("app.debug");
\Fusion\Core\ErrorHandler::setDebug($dev);

require_once BASE_PATH . "/fusionphp/core/PhpCache.php";
require_once BASE_PATH . "/fusionphp/core/Autoloader.php";
\Fusion\Core\Autoloader::register($dev);

// Debugger — boot early so it catches everything
require_once BASE_PATH . "/fusionphp/core/Debugger.php";
\Fusion\Core\Debugger::boot($dev);

require_once BASE_PATH . "/fusionphp/helpers/view.php";

(new \Fusion\Core\Router($dev))->dispatch();
