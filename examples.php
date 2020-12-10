<?php
// https://github.com/BaseMax/GooglePlayWebServiceAPI
require "google-play.php";
$google = new GooglePlay();

// Listing app details
$app=$google->parseApplication("com.bezapps.flowdiademo");
print_r($app);

// Listing available categories
$cats = $google->parseCategories();

// Listing apps in a given category
$category = 'TRAVEL_AND_LOCAL';
$apps = $google->parseCategory($category);

// Listing Charts and Latest Additions
$charts = $google->parseTopApps();
$news   = $google->parseNewApps();

// Listing what Google considers "similar apps" and "other apps from this developer"
$packageName = "com.bezapps.flowdiademo";
$similar = $google->parseSimilar($packageName);
$other   = $google->parseOthers($packageName);

// Searching for apps by keywords
$query = 'sms backup';
$apps = $google->parseSearch($query);

// Obtaining app permissions
$perms = $google->parsePerms($packageName)
  
// How to action as a crawler and find more applications?
$alphas = range('A', 'Z');
foreach($alphas as $alpha) {
    $apps=$google->parseSearch($alpha);
    print_r($apps);
}
// or
$alphas = range('A', 'Z');
foreach($alphas as $alpha) {
    $apps=$google->parseSearch($alpha);
    print_r($apps);
    foreach($alphas as $alpha2) {
        $apps=$google->parseSearch($alpha.$alpha2);
        print_r($apps);
    }
}
// It's more
$alphas = range('A', 'Z');
foreach($alphas as $alpha) {
  $apps=$google->parseSearch($alpha);
  print_r($apps);
  foreach($alphas as $alpha2) {
    $apps=$google->parseSearch($alpha.$alpha2);
    print_r($apps);
    foreach($alphas as $alpha3) {
      $apps=$google->parseSearch($alpha.$alpha2.$alpha3);
      print_r($apps);
    }
  }
}
