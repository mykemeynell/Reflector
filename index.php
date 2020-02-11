<?php

require __DIR__ . '/vendor/autoload.php';

container()->bind('anon', function ($app) {
//    return $app;
    return new Exception("Hello");
});

echo "<pre>";
var_export(
    container()->make('anon')
);
die;
