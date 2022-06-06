# Google Play Web-Service API

Tiny script to crawl information of a specific application in the Google play/store base on PHP.

## PHP GooglePlay Methods
- `parse`: mostly used internally – but can be used to parse any URL or text for valid Play Store app links and return their packageNames
- `parseSearch`: search for apps by given terms
- `parseSimilar`: search for what Google Play considers apps similar to the one specified
- `parseOthers`: other apps by the same developer
- `parseTopApps`: list top-chart apps
- `parseNewApps`: list latest additions
- `parseCategory`: list apps from a specified category
- `parseCategories`: list available categories
- `parseApplication`: get details for a specific app
- `parsePerms`: retrieve permissions requested by a specific app
- `parsePrivacy`: obtain an app's privacy details (data collected/shared etc)
- `setDebug`: turn debug mode on or off
- `getDebug`: check whether debug mode is turned on or off

## Using PHP GooglePlay
```php
<?php
require "google-play.php";
$google = new GooglePlay();

$app=$google->parseApplication("com.bezapps.flowdiademo");
print_r($app);

$app=$google->parseSimilar("com.bezapps.flowdiademo");
print_r($app);

$apps=$google->parseSearch("telegram");
print_r($apps);

$apps=$google->parseCategory("TOOLS");
print_r($apps);
?>
```

## Detailed instructions
Examples of how to use the code and what results to expect can be found in the [wiki](https://github.com/BaseMax/GooglePlayWebServiceAPI/wiki):

- the [Search](https://github.com/BaseMax/GooglePlayWebServiceAPI/wiki/Search) page shows how to
    - list available categories as well as obtain a list of apps from a given category
    - search for "similar apps"
    - search for apps by keywords
    - action as a crawler and find more applications?
- the [AppDetails](https://github.com/BaseMax/GooglePlayWebServiceAPI/wiki/AppDetails) page shows how to
    - obtain details about a given app
    - interprete the result set (aka the "application structure")
    - obtain content in specific languages
- the [Permissions](https://github.com/BaseMax/GooglePlayWebServiceAPI/wiki/Permissions) page details
    - how to get the permissions an app requests
    - what the result set looks like and how to interprete it

## TODO

- Unit test

## Authors

- Izzy
- Max Base

---------

# Max Base

My nickname is Max, Programming language developer, Full-stack programmer. I love computer scientists, researchers, and compilers. ([Max Base](https://maxbase.org/))

## Asrez Team

A team includes some programmer, developer, designer, researcher(s) especially Max Base.

[Asrez Team](https://www.asrez.com/)
