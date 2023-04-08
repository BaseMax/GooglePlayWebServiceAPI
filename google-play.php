<?php
/** Crawl information of a specific application in the Google Play Store
 * @class     GooglePlay
 * @version   1.1.0
 * @author    Max Base & Izzy
 * @copyright MIT https://github.com/BaseMax/GooglePlayWebServiceAPI/blob/master/LICENSE
 * @log       2020-10-19 first release
 * @log       2022-05-28 recent version
 * @brief     releases: 2020-10-19, 2020-10-25, 2020-10-29, 2020-10-30, 2020-12-05, 2020-12-06, 2020-12-07, 2020-12-10, 2022-05-28
 * @webpage   repository https://github.com/BaseMax/GooglePlayWebServiceAPI
 **/
class GooglePlay {
  private $debug = false;   // toggle debug output
  private $input = '';      // content retrieved from remote
  private $lastError = '';
  private $categories = []; // list of Google Play app categories

  /** Turn debug mode on or off
   * @method public setDebug
   * @param bool    debug   turn debug mode on (true) or off (false)
   */
  public function setDebug($debug) {
    $this->debug = (bool) $debug;
  }

  /** Check whether debug mode is enabled
   * @method public getDebug
   * @return bool   debug   whether debug mode is turned on (true) or off (false)
   */
  public function getDebug() {
    return $this->debug;
  }

  /** Parse a given RegEx and return the match marked by '(?<content>)'
   * @method protected getRegVal
   * @param string regEx    regular expression to parse
   * @return string result  match when found, null otherwise
   */
  protected function getRegVal($regEx) {
    preg_match($regEx, $this->input, $res);
    if (isset($res["content"])) return trim($res["content"]);
    else return null;
  }

  /** Fetch app page from Google Play
   * @method protected getApplicationPage
   * @param          string packageName identifier for the app, e.g. 'com.example.app'
   * @param optional string lang        language for translations. Should be ISO 639-1 two-letter code. Default: en
   * @param optional string loc         locale, mainly for currency. Again two-letter, but uppercase
   * @return         bool   success
   */
  protected function getApplicationPage($packageName, $lang='en_US', $loc='US') {
    $link = "https://play.google.com/store/apps/details?id=" . $packageName . "&hl=$lang&gl=$loc";
    if ( ! $this->input = @file_get_contents($link) ) {
      $this->lastError = $http_response_header[0];
      return false;
    } else {
      return true;
    }
  }

  /** Obtain app version details
   * @method public parseVersion
   * @param          string packageName identifier for the app, e.g. 'com.example.app'
   * @return         array              details on the app on success, details on the error otherwise
   */
  public function parseVersion($packageName) {
    $lang='en';
    $version = sprintf("[IoIWBc,'[[null,[%s,7]]]',null,%s]", $packageName, $packageName);
    $value = sprintf("[[%s]]", $version);
    $freq = urlencode($value);

    $opts = ['http' => array(
      'method'  => 'POST',
      'header'  => 'Content-type: application/x-www-form-urlencoded;charset=utf-8'
                  ."\r\n".'Referer: https://play.google.com/',
      'content' => "f.req=$freq",
      'ignore_errors' => TRUE
      )
    ];
    $context  = stream_context_create($opts);
    if ( $proto = @file_get_contents('https://play.google.com/_/PlayStoreUi/data/batchexecute?hl=' . $lang, false, $context) ) { // proto_buf/JSON data
      preg_match("!HTTP/1\.\d\s+(\d{3})\s+(.+)$!i", $http_response_header[0], $match);
      $response_code = $match[1];
      switch ($response_code) {
        case '200' : // HTTP/1.0 200 OK
          break;
        case '400' : // echo "! No XHR for '$pkg'\n";
        case '404' : // app no longer on play
        default:
          return ['packageName'=>$packageName, 'versionName'=>'', 'minimumSDKVersion'=>0, 'size'=>0, 'success'=>0, 'message'=>$http_response_header[0]];
          break;
      }
    } else { // network error (e.g. "failed to open stream: Connection timed out")
      return ['packageName'=>$packageName, 'versionName'=>'', 'minimumSDKVersion'=>0, 'size'=>0, 'success'=>0, 'message'=>'network error'];
    }

    $proto = preg_replace('!^\)]}.*?\n!','',$proto);
    $verInfo = json_decode( json_decode($proto)[0][2] );
    $values = [];
    $message = '';

    if ( gettype($verInfo) == 'NULL' ) { // happens rarely, but happens; on a subsequent call for the same package it might succeed (temp hick-up?)
      return ['packageName'=>$packageName, 'versionName'=>'', 'minimumSDKVersion'=>0, 'size'=>0, 'success'=>0, 'message'=>'Google returned no version info'];
    } else {
      $values['packageName'] = $packageName;
      $values['versionName'] = $verInfo[1];
      $values['minimumSDKVersion'] = $verInfo[2];
      $values['size'] = $verInfo[0];
      $values['success'] = 1;
      $values['message'] = $message;
    }

    return $values;
  }

  /** Obtain details on a given app
   * @method public parseApplication
   * @param          string packageName identifier for the app, e.g. 'com.example.app'
   * @param optional string lang        language for translations. Should be ISO 639-1 two-letter code. Default: en
   * @param optional string loc         locale, mainly for currency. Again two-letter, but uppercase
   * @return         array              details on the app on success, details on the error otherwise
   * @verbatim
   *  On error, the array contains 2 keys: success=0 and message=(tring with reason)
   *  Success is signaled by success=1, and details are given via the keys
   *  packageName, name, developer, category, type (game, app, family), description,
   *  icon, images (array of screenshot URLs), updated, version, require (min Android version),
   *  install (number of installs), age, rating (float), votes, price, size,
   *  ads (has ads: 0|1), iap (in-app-payment used: 0|1)
   *  if not explicitly specified otherwise, values are strings
   */
  public function parseApplication($packageName, $lang='en_US', $loc='US') {
    if ( ! $this->getApplicationPage($packageName, $lang, $loc) ) {
      return ['success'=>0,'message'=>$this->lastError];
    }
    $values = [];
    $message = '';
    $verInfo = $this->parseVersion($packageName);
    if ( $verInfo['success'] != 1 ) $verInfo = ['size'=>0, 'minimumSDKVersion'=>0, 'versionName'=>''];
    $values["packageName"] = $packageName;

    $values["name"] = strip_tags($this->getRegVal('/itemprop="name">(?<content>.*?)<\/h1>/'));
    if ($values["name"]==null) {
      return ['success'=>0, 'message'=>'No app data found'];
    }

    $values["developer"] = strip_tags($this->getRegVal('/href="\/store\/apps\/dev(eloper)*\?id=(?<id>[^\"]+)"([^\>]*|)>(\<span[^\>]*>)*(?<content>[^\<]+)(<\/span>|)<\/a>/i'));
    $values["summary"] = strip_tags($this->getRegVal('/property="og:description" content="(?<content>[^\"]+)/i'));
    $values["description"] = $this->getRegVal('/itemprop="description"[^\>]*><div class="bARER"[^\>]*>(?<content>.*?)<\/div><div class=/i');
    if ( strtolower(substr($lang,0,2)) != 'en' ) { // Google sometimes keeps the EN description additionally, so we need to filter it out **TODO:** check if this still applies (2022-05-27)
      if ($this->debug) echo "Original Description:\n" . $values["description"] . "\n\n";
      $values["description"] = preg_replace('!.*?<div jsname="Igi1ac" style="display:none;">(.+)!ims', '$1', $values["description"]);
    }
    //$values["icon"] = $this->getRegVal('/<div class="JU1wdd"><div class="l8YSdd"><img src="(?<content>[^\"]+)"/i');
    $values["icon"] = preg_replace('!(.*)=w\d+.*!i', '$1', $this->getRegVal('/<meta name="twitter:image" content="(?<content>[^\"]+)"/i'));
    $values["featureGraphic"] = preg_replace('!(.*)=w\d+.*!i', '$1', $this->getRegVal('/<img class="oiEt0d" src="(?<content>[^\"]+)"/i'));

    preg_match('/<div class="aoJE7e qwPPwf"([^\>]+|)>(?<content>.*?)<c-data/i', $this->input, $image);
    if ( isset($image["content"]) ) {
      preg_match_all('/<img data-src="(?<content>[^\"]+)"/i', $image["content"], $images);
      if ( isset($images["content"]) && !empty($images["content"]) ) {
        $values["images"] = $images["content"];
      } else {
        preg_match_all('/<img (class="[^\"]+")*src="[^"]*" srcset="(?<content>[^\s"]+)/i', $image["content"], $images);
        if ( isset($images["content"]) ) {
          $values["images"] = $images["content"];
        } else {
          $values["images"] = null;
        }
      }
    } else {
      $values["images"] = null;
    }

    if ( substr(strtolower($lang),0,2)=='en' ) {
      $values["lastUpdated"] = strip_tags($this->getRegVal('/<div class="lXlx5">Updated on<\/div><div class="xg1aie">(?<content>.*?)<\/div><\/div>/i'));
      $values["versionName"] = $verInfo['versionName'];
      $values["minimumSDKVersion"] = $verInfo['minimumSDKVersion'];
      $values["installs"] = strip_tags($this->getRegVal('/<div class="ClM7O">(?<content>[^\>]*?)<\/div><div class="g1rdde">Downloads<\/div>/i'));
      $values["age"] = strip_tags($this->getRegVal('/<span itemprop="contentRating"><span>(?<content>.*?)<\/span><\/span>/i'));
      $values["size"] = $verInfo['size'];
      $values["video"] = $this->getRegVal('/<button aria-label="Play trailer".*?data-trailer-url="(?<content>[^\"]+?)"/i');
      $values["whatsnew"] = $this->getRegVal('/<div class="SfzRHd"><div itemprop="description">(?<content>.*?)<\/div><\/div><\/section>/i');
      $test = $this->getRegVal('/<span class="UIuSk">(?<content>\s*Contains ads\s*)<\/span>/i'); // <span class="UIuSk">Contains ads</span>
      (empty($test)) ? $values["ads"] = 0 : $values["ads"] = 1;
      $test = $this->getRegVal('/<span class="UIuSk">(?<content>\s*In-app purchases\s*)<\/span>/i'); // <span class="UIuSk">In-app purchases</span>
      (empty($test)) ? $values["iap"] = 0 : $values["iap"] = 1;
    } else {
      $envals = $this->parseApplication($packageName);
      foreach(["lastUpdated","versionName","minimumSDKVersion","installs","age","size"] as $val) $values[$val] = $envals[$val];
    }

    $values["rating"] = $this->getRegVal('/<div itemprop="starRating"><div class="TT9eCd"[^\>]*>(?<content>[^<]+)(<i class="[^\>]*>star<\/i>)*<\/div>/i');
    $values["votes"] = $this->getRegVal('/<div class="g1rdde">(?<content>[^>]+) reviews<\/div>/i');
    $values["price"] = $this->getRegVal('/<meta itemprop="price" content="(?<content>[^"]+)">/i');

    // ld+json data, see https://github.com/BaseMax/GooglePlayWebServiceAPI/issues/22#issuecomment-1168397748
    $d = new DomDocument();
    @$d->loadHTML($this->input);
    $xp = new domxpath($d);
    $jsonScripts = $xp->query( '//script[@type="application/ld+json"]' );
    $json = trim( @$jsonScripts->item(0)->nodeValue ); //
    $data = json_decode($json,true);
    if (isset($data['applicationCategory'])) {
      $values["category"] = $data['applicationCategory'];
      if ( substr($values["category"],0,5)=='GAME_' ) $values["type"] = "game";
      elseif ( substr($values["category"],0,7)=='FAMILY?' ) $values["type"] = "family";
      else $values["type"] = "app";
      $cats = $this->parseCategories();
      if ( $cats["success"] && !empty($cats["data"][$values["category"]]) ) $values["category"] = $cats["data"][$values["category"]]->name;
    } else {
      $values["category"] = null;
      $values["type"] = null;
    }
    if ( empty($values["summary"]) && !empty($data["description"]) ) $values["summary"] = $data["description"];
    if (isset($data["contentRating"])) $values["contentRating"] = $data["contentRating"];
    else $values["contentRating"] = "";
    if ( isset($data["aggregateRating"]) ) {
      if ( !empty($data["aggregateRating"]["ratingValue"]) ) $values["rating"] = $data["aggregateRating"]["ratingValue"];
      if ( !empty($data["aggregateRating"]["ratingCount"]) ) $values["votes"] = $data["aggregateRating"]["ratingCount"];
    }

    $limit = 5; $proto = '';
    while ( empty($proto) && $limit > 0 ) { // sometimes protobuf is missing, but present again on subsequent call
      $proto  = json_decode($this->getRegVal("/key: 'ds:4'. hash: '7'. data:(?<content>\[\[\[.+?). sideChannel: .*?\);<\/script/ims")); // DataSource:4 = featureGraphic, video, summary
      if ( empty($proto) || empty($proto[1]) ) {
        $this->getApplicationPage($packageName, $lang, $loc);
        --$limit;
      } else {
        if ( empty($values["featureGraphic"]) ) $values["featureGraphic"] = $proto[1][2][96][0][3][2];
        if ( empty($values["video"]) && !empty($proto[1][2][100]) ) $values["video"] = $proto[1][2][100][0][0][3][2];
        if ( empty($values["summary"]) && !empty($proto[1][2][73]) ) $values["summary"] = $proto[1][2][73][0][1]; // 1, 2, 73, 0, 1
        // category: $proto[1][2][79][0][0][0]; catId: $proto[1][2][79][0][0][2]
        // screenshots: 1,2,78,0,0-n; 1=format,2=[wid,hei],3.2=url
        // more details see: https://github.com/JoMingyu/google-play-scraper/blob/2caddd098b63736318a7725ff105907f397b9a48/google_play_scraper/constants/element.py
        break;
      }
    }

    // reviews
    $values["reviews"] = [];
    if ( $proto = json_decode($this->getRegVal("/key: 'ds:7'. hash: '\d+'. data:(?<content>\[\[\[.+?). sideChannel: .*?\);<\/script/ims")) ) { // DataSource:7 = reviews
      foreach($proto[0] as $rev) {
        $r["review_id"] = $rev[0];
        $r["reviewed_version"] = $rev[10];
        $r["review_date"] = $rev[5][0];
        $r["review_text"]  = $rev[4];
        $r["stars"] = $rev[2];
        $r["like_count"] = $rev[6];
        $r["reviewer"] = [
          "reviewer_id"=>$rev[9][0],
          "name"=>$rev[9][1],
          "avatar"=>$rev[9][3][0][3][2],
          "bg_image"=>$rev[9][4][3][2]
        ];
        if ( empty($rev[7]) ) {
          $r["reply"] = [];
        } else {
          $r["response"] = [
            "responder_name"=>$rev[7][0],
            "response_text"=>$rev[7][1],
            "response_date"=>$rev[7][2][0]
          ];
        }
        $values["reviews"][] = $r;
      }
      $values["review_token"] = $proto[1][1]; // needed if we want to fetch more reviews later
    } else {
      $values["review_token"] = '';
    }

    if ($this->debug) {
      print_r($values);
    }
    $values['success'] = 1;
    $values['message'] = $message;
    return $values;
  }

  /** Obtain permissions for a given app
   * @method parsePerms
   * @param          string packageName identifier for the app, e.g. 'com.example.app'
   * @param optional string lang        language for translations. Should be ISO 639-1 two-letter code. Default: en
   * @return         array              permission on success, details on the error otherwise
   * @verbatim
   *  On error, the array contains 2 keys: success=0 and message=(tring with reason)
   *  Success is signaled by success=1, and details are given via the keys
   *  * perms   : array[0..n] of permissions as displayed on play.google.com (i.e. the permission descriptions); unique, no grouping.
   *  * grouped : array of permission groups as displayed on play.google.com. Keys are the group ids as defined there.
   *              keys in each group array are group_name (translated name of the permission group) and perms (array[0..n])
   *              These perms have numeric keys (0 and 1). 0 seems always to be empty, 1 holds the permission description.
   */
  public function parsePerms($packageName, $lang='en') {
    $opts = ['http' => array(
      'method'  => 'POST',
      'header'  => 'Content-type: application/x-www-form-urlencoded;charset=utf-8'
                  ."\r\n".'Referer: https://play.google.com/',
      'content' => 'f.req=%5B%5B%5B%22xdSrCf%22%2C%22%5B%5Bnull%2C%5B%5C%22' . $packageName . '%5C%22%2C7%5D%2C%5B%5D%5D%5D%22%2Cnull%2C%221%22%5D%5D%5D',
      'ignore_errors' => TRUE
      )
    ];
    $context  = stream_context_create($opts);
    if ( $proto = @file_get_contents('https://play.google.com/_/PlayStoreUi/data/batchexecute?rpcids=xdSrCf&bl=boq_playuiserver_20201201.06_p0&hl=' . $lang . '&authuser&soc-app=121&soc-platform=1&soc-device=1&rt=c&f.sid=-8792622157958052111&_reqid=257685', false, $context) ) { // raw proto_buf data
      preg_match("!HTTP/1\.\d\s+(\d{3})\s+(.+)$!i", $http_response_header[0], $match);
      $response_code = $match[1];
      switch ($response_code) {
        case "200" : // HTTP/1.0 200 OK
          break;
        case "400" : // echo "! No XHR for '$pkg'\n";
        case "404" : // app no longer on play
        default:
          return ['success'=>0, 'grouped'=>[], 'perms'=>[], 'message'=>$http_response_header[0]];
          break;
      }
    } else { // network error (e.g. "failed to open stream: Connection timed out")
      return ['success'=>0, 'grouped'=>[], 'perms'=>[], 'message'=>'network error'];
    }

    $perms = $perms_unique = [];
    $json = preg_replace('!.*?(\[.+?\])\s*\d.*!ims', '$1', $proto);
    $arr = json_decode(json_decode($json)[0][2]);
    if (!empty($arr[0])) foreach ($arr[0] as $group) { // 0: group name, 1: group icon, 2: perms, 3: group_id
      if (empty($group)) continue;
      $perms[$group[3][0]] = ['group_name'=>$group[0], 'perms'=>$group[2]];
      foreach($group[2] as $perm) $perms_unique[] = $perm[1];
    }
    if (!empty($arr[1])) {
      $perms['misc'] = ['group_name'=>$arr[1][0][0], 'perms'=>$arr[1][0][2]];
      foreach($arr[1][0][2] as $perm) $perms_unique[] = $perm[1];
    }
    if (!empty($arr[2])) {
      if (array_key_exists('misc',$perms)) $perms['misc']['perms'] = array_merge($perms['misc']['perms'],$arr[2]);
      elseif ( is_array ($arr[1]) && is_array($arr[1][0]) && !empty($arr[1][0][0]) ) $perms['misc'] = ['group_name'=>$arr[1][0][0], 'perms'=>$arr[2]];
      else $perms['misc'] = ['group_name'=>'unknown', 'perms'=>$arr[2]]; // ProtoBuf broken for this app, e.g. com.achunt.weboslauncher (perms display broken on Play website itself)
      foreach($arr[2] as $perm) $perms_unique[] = $perm[1];
    }

    return ['success'=>1, 'message'=>'', 'grouped'=>$perms, 'perms'=>array_unique($perms_unique)];
  }

  /** Parse page specified by URL for playstore links and extract package names
   * @method public parse
   * @param optional string link    link to parse; if empty or not specified, defaults to 'https://play.google.com/apps'
   * @param optional bool   is_url  whether the link passed is an url to fetch-and-parse (true, default) or a string just to parse (false)
   * @return         array          array of package names
   * @brief this mainly is a helper for all methods parsing for app links, like parseTopApps, parseSimilar etc.
   */
  public function parse($link=null, $is_url=true) {
    if ( $is_url ) {
      if ($link == "" || $link == null) {
        $link = "https://play.google.com/apps";
      }
      if ( ! $input = @file_get_contents($link) ) {
        $this->lastError = $http_response_header[0];
        return [];
      } else {
        $this->lastError = ''; // reset
      }
    } else {
      $input = $link;
    }
    preg_match_all('/href="\/store\/apps\/details\?id=(?<ids>[^\"]+)"/i', $input, $ids);
    if ( isset($ids["ids"]) ) {
      $ids = $ids["ids"];
      $ids = array_values(array_unique($ids));
      $values = $ids;
    } else {
      $values = [];
    }
    if ($this->debug) {
      print_r($values);
    }
    return $values;
  }

  /** Obtain list of top apps
   * @method public parseTopApps
   * @param string category     name of the category to parse
   * @return array              array of package names
   */
  public function parseTopApps() {
    $link = "https://play.google.com/store/apps/top";
    $data = $this->parse($link);
    if ( empty($this->lastError) ) return ['success'=>1, 'message'=>'', 'data'=>$data];
    else return ['success'=>0, 'message'=>$this->lastError, 'data'=>$data];
  }

  /** Obtain list of newest apps
   * @method public parseNewApps
   * @param string category     name of the category to parse
   * @return array              array of package names
   */
  public function parseNewApps() {
    $link = "https://play.google.com/store/apps/new";
    $data = $this->parse($link);
    if ( empty($this->lastError) ) return ['success'=>1, 'message'=>'', 'data'=>$data];
    else return ['success'=>0, 'message'=>$this->lastError, 'data'=>$data];
  }

  /** Parse Play Store page for a given category and return package names
   *  use this::parseCategories to obtain a list of available categories
   * @method public parseCategory
   * @param string category     id of the category to parse
   * @return array              array of package names
   */
  public function parseCategory($category) {
    $link = "https://play.google.com/store/apps/category/" . $category;
    $data = $this->parse($link);
    if ( empty($this->lastError) ) return ['success'=>1, 'message'=>'', 'data'=>$data];
    else return ['success'=>0, 'message'=>$this->lastError, 'data'=>$data];
  }

  /** Obtain list of available categories.
   *  Definitions of all available categories are stored in categories.jsonl using
   *  [JSONL](https://en.wikipedia.org/wiki/JSONL) format. This method returns them
   *  as array of objects with the ID as key and the properties id, name, type.
   * @method public parseCategories
   * @return array  array of categories to be used with e.g. this::parseCategory
   * @see           https://developers.apptweak.com/reference/google-play-store-categories
   */
  public function parseCategories($force=false) {
    if ( ! empty($this->categories) && ! $force ) ['success'=>1, 'message'=>'', 'data'=>$this->categories];
    $catfile = __DIR__ . '/categories.jsonl';
    if ( ! file_exists($catfile) ) return ['success'=>0, 'message'=>"Category definition file '$catfile' does not exist.", 'data'=>[]];
    $this->categories = [];
    foreach ( file($catfile) as $line ) {
      $cat = json_decode($line);
      $this->categories[$cat->id] = $cat;
    }
    return ['success'=>1, 'message'=>'', 'data'=>$this->categories];
  }

  /** Obtain list of similar apps
   * @method parseSimilar
   * @param  string packageName package name of the app to find similars for, e.g. 'com.example.app'
   * @return array              array of package names
   */
  public function parseSimilar($packageName) {
    if ( ! $this->getApplicationPage($packageName) )
      return ['success'=>0, 'message'=>$this->lastError, 'data'=>[]];
    $input = $this->getRegVal('!<h2 class="sv0AUd bs3Xnd">Similar</h2></a>(?<content>.+?)(<c-wiz jsrenderer="rx5H8d"|</aside>)!ims');
    if ( empty($input) )
      return ['success'=>1, 'message'=>'no data found', 'data'=>[]];
    return ['success'=>1, 'message'=>'', 'data'=>$this->parse($input, false)];
  }

  /** Obtain list of other apps by same author
   * @method parseOthers
   * @param  string packageName package name of the app to find similars for, e.g. 'com.example.app'
   * @return array              array of package names
   */
  public function parseOthers($packageName) {
    if ( ! $this->getApplicationPage($packageName) )
      return ['success'=>0, 'message'=>$this->lastError, 'data'=>[]];
    $input = $this->getRegVal('!<h2 class="sv0AUd bs3Xnd">More by [^<]*</h2></a></div><div class="W9yFB">(?<content>.+?)</c-data></c-wiz></div></div></div><script!ims');
    if ( empty($input) )
      return ['success'=>1, 'message'=>'no data found', 'data'=>[]];
    return ['success'=>1, 'message'=>'', 'data'=>$this->parse($input, false)];
  }

  /** Search for apps by a given string
   * @method public parseSearch
   * @param string query    string to search for
   * @return array          array of package names
   */
  public function parseSearch($query) {
    $link = "https://play.google.com/store/search?q=". urlencode($query) ."&c=apps";
    $data = $this->parse($link);
    if ( empty($this->lastError) ) {
      if ( empty($data) ) return ['success'=>1, 'message'=>'no data found', 'data'=>$data];
      else return ['success'=>1, 'message'=>'', 'data'=>$data];
    }
    else return ['success'=>0, 'message'=>$this->lastError, 'data'=>$data];
  }

  /* Obtain Data Safety details for a given app
   * @method public parsePrivacy
   * @param          string packageName identifier for the app, e.g. 'com.example.app'
   * @param optional string lang        language for translations. Should be ISO 639-1 two-letter code. Default: en
   * @return         array              privacy details on the app on success, details on the error otherwise
   */
  public function parsePrivacy($packageName, $lang='en') {
    $link = sprintf('https://play.google.com/store/apps/datasafety?id=%s&hl=%s', $packageName, $lang);
    if ( $this->input = @file_get_contents($link) ) {
      preg_match("!HTTP/1\.\d\s+(\d{3})\s+(.+)$!i", $http_response_header[0], $match);
      $response_code = $match[1];
      switch ($response_code) {
        case "200" : // HTTP/1.0 200 OK
          break;
        case "400" : // echo "! No XHR for '$pkg'\n";
        case "404" : // app no longer on play
        default:
          $this->lastError = $http_response_header[0];
          return ['success'=>0, 'values'=>[], 'message'=>$http_response_header[0]];
          break;
      }
    } else { // network error (e.g. "failed to open stream: Connection timed out")
      $this->lastError = 'network error';
      return ['success'=>0, 'values'=>[], 'message'=>'network error'];
    }

    $doc = new DOMDocument();
    @$doc->loadHTML($this->input);
    $xp  = new DOMXPath($doc);

    $nlh = $xp->query("//div[@class='Mf2Txd']/h2"); // node list of headers
    if ($this->debug) echo "Privacy sections: ".$nlh->length."\n";

    $sections = [];
    foreach($nlh as $section) {
      $sname = trim ($section->nodeValue);
      $node = $section->nextSibling;
      $desc = ''; $extras = [];
      switch ( $node->getAttribute('class') ) {
        case 'ivTO9c': $desc = $node->firstChild->textContent; break;
        case 'XgPdwe':
          $desc = 'see extras';
          foreach ($node->childNodes as $child) {
            $ex = $child->firstChild->nextSibling->firstChild; // the extra detail's header
            $eh = $ex->nodeValue;
            $ex = $ex->nextSibling; // the extra's details
            $ed = $ex->nodeValue;
            $extras[] = ['name'=>$eh, 'desc'=>$ed];
          }
          break;
        default: if ($this->debug) echo "Got unknown class '". strtolower($node->getAttribute('class'))."' for section description\n"; break;
      }
      $node = $node->nextSibling;
      if ( empty($extras) ) {
        foreach ($node->childNodes as $child) {
          $ex = $child->firstChild->firstChild->firstChild->firstChild->firstChild->nextSibling->firstChild; // the extra detail's header
          $eh = $ex->nodeValue;
          $ex = $ex->nextSibling; // the extra's details
          $ed = $ex->nodeValue;
          $extras[] = ['name'=>$eh, 'desc'=>$ed];
        }
      }
      $sections[] = ['name'=>$sname, 'desc'=>$desc, 'extras' => $extras];
    }
    return ['success'=>1, 'values'=>$sections, 'message'=>''];
  }
}
