<?php


require_once('./vendor/autoload.php');

use Geekbrains\Application1\Application\Application;
use Geekbrains\Application1\Application\Render;


$memory_start = memory_get_usage();

try{
    $app = new Application();
    echo $app->run();
}
catch(Exception $e){
    echo Render::renderExceptionPage($e);
}

$memory_end = memory_get_usage();
echo "<h4>Потреблено " . ($memory_end - $memory_start) . " байт памяти</h4>";