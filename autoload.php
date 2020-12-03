<?php
/**
 * Created by PhpStorm.
 * User: andreyselikov
 * Date: 19.11.2020
 * Time: 14:13
 */

spl_autoload_register('AutoLoader');

function AutoLoader($className)
{
    $file = str_replace('\\',DIRECTORY_SEPARATOR,$className);
    require_once $file . '.php';
}