<?php
// https://github.com/BaseMax/GooglePlayWebServiceAPI
require "google-play.php";
$google = new GooglePlay();
$app=$google->parseApplication("com.bezapps.flowdiademo");
print_r($app);
