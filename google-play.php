<?php
/** Crawl information of a specific application in the Google Play Store
 * @class     GooglePlay
 * @version   0.3
 * @author    Max & Izzy
 * @copyright MIT https://github.com/BaseMax/GooglePlayWebServiceAPI/blob/master/LICENSE
 * @log       2020-10-19 first release
 * @log       2020-12-10 recent version
 * @brief     releases: 2020-10-19, 2020-10-25, 2020-10-29, 2020-10-30, 2020-12-05, 2020-12-06, 2020-12-07, 2020-12-10
 * @webpage   repository https://github.com/BaseMax/GooglePlayWebServiceAPI
 **/
class GooglePlay {
  private $debug = false;   // toggle debug output
  private $input = '';      // content retrieved from remote
  private $lastError = '';

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
    $values["packageName"] = $packageName;

    $values["name"] = strip_tags($this->getRegVal('/itemprop="name">(?<content>.*?)<\/h1>/'));
    if ($values["name"]==null) {
      return ['success'=>0, 'message'=>'No app data found'];
    }

    $values["developer"] = strip_tags($this->getRegVal('/href="\/store\/apps\/developer\?id=(?<id>[^\"]+)"([^\>]+|)>(?<content>[^\<]+)<\/a>/i'));

    preg_match('/itemprop="genre" href="\/store\/apps\/category\/(?<id>[^\"]+)"([^\>]+|)>(?<content>[^\<]+)<\/a><\/span>/i', $this->input, $category);
    if (isset($category["id"], $category["content"])) {
      $values["category"] = trim(strip_tags($category["content"]));
      $catId = trim(strip_tags($category["id"]));
      if ($catId=='GAME' || substr($catId,0,5)=='GAME_') $values["type"] = "game";
      elseif ($catId=='FAMILY' || substr($catId,0,7)=='FAMILY?') $values["type"] = "family";
      else $values["type"] = "app";
    } else {
      $values["category"] = null;
      $values["type"] = null;
    }

    $values["summary"] = '';
    $values["description"] = $this->getRegVal('/itemprop="description"><span jsslot><div jsname="sngebd">(?<content>.*?)<\/div><\/span><div/i');
    if ( strtolower(substr($lang,0,2)) != 'en' ) { // Google sometimes keeps the EN description additionally, so we need to filter it out
      if ($this->debug) echo "Original Description:\n" . $values["description"] . "\n\n";
      $values["description"] = preg_replace('!.*?<div jsname="Igi1ac" style="display:none;">(.+)!ims', '$1', $values["description"]);
    }
    $values["icon"] = $this->getRegVal('/<div class="hkhL9e"><div class="xSyT2c"><img src="(?<content>[^\"]+)"/i');
    $values["featureGraphic"] = preg_replace('!(.*)=w\d+.*!i', '$1', $this->getRegVal('/<meta name="twitter:image" content="(?<content>[^\"]+)"/i'));

    preg_match('/<div class="Rx5dXb"([^\>]+|)>(?<content>.*?)<c-data/i', $this->input, $image);
    if ( isset($image["content"]) ) {
      preg_match_all('/<img data-src="(?<content>[^\"]+)"/i', $image["content"], $images);
      if ( isset($images["content"]) && !empty($images["content"]) ) {
        $values["images"] = $images["content"];
      } else {
        preg_match_all('/<img src="[^"]*" srcset="(?<content>[^\s"]+)/i', $image["content"], $images);
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
      $values["lastUpdated"] = strip_tags($this->getRegVal('/<div class="BgcNfc">Updated<\/div><span class="htlgb"><div class="IQ1z0d"><span class="htlgb">(?<content>.*?)<\/span><\/div><\/span><\/div>/i'));
      $values["versionName"] = strip_tags($this->getRegVal('/<div class="BgcNfc">Current Version<\/div><span class="htlgb"><div class="IQ1z0d"><span class="htlgb">(?<content>.*?)<\/span><\/div><\/span><\/div>/i'));
      $values["minimumSDKVersion"] = strip_tags($this->getRegVal('/<div class="hAyfc"><div class="BgcNfc">Requires Android<\/div><span class="htlgb"><div class="IQ1z0d"><span class="htlgb">(?<content>.*?)<\/span><\/div><\/span><\/div>/i'));
      $values["installs"] = strip_tags($this->getRegVal('/<div class="hAyfc"><div class="BgcNfc">Installs<\/div><span class="htlgb"><div class="IQ1z0d"><span class="htlgb">(?<content>.*?)<\/span><\/div><\/span><\/div>/i'));
      $values["age"] = strip_tags($this->getRegVal('/<div class="hAyfc"><div class="BgcNfc">Content Rating<\/div><span class="htlgb"><div class="IQ1z0d"><span class="htlgb"><div>(?<content>.*?)<\/div>/i'));
      $values["size"] = $this->getRegVal('/<div class="BgcNfc">Size<\/div><span class="htlgb"><div class="IQ1z0d"><span class="htlgb">(?<content>[^<]+)<\/span>/i');
    } else {
      $envals = $this->parseApplication($packageName);
      foreach(["lastUpdated","versionName","minimumSDKVersion","installs","age","size"] as $val) $values[$val] = $envals[$val];
    }

    $values["rating"] = $this->getRegVal('/<div class="BHMmbe"[^>]*>(?<content>[^<]+)<\/div>/i');
    $values["votes"] = $this->getRegVal('/<span class="AYi5wd TBRnV"><span[^>]*>(?<content>[^>]+)<\/span>/i');
    $values["price"] = $this->getRegVal('/<meta itemprop="price" content="(?<content>[^"]+)">/i');
    $test = $this->getRegVal('/<div class="bSIuKf">(?<content>[^<]+)<div/i'); // <div class="bSIuKf">Contains Ads<div
    (empty($test)) ? $values["ads"] = 0 : $values["ads"] = 1;
    $test = $this->getRegVal('/<div class="aEKMHc">&middot;<\/div>(?<content>[^<]+)</i'); // <div class="aEKMHc">&middot;</div>Offers in-app purchases</div>
    (empty($test)) ? $values["iap"] = 0 : $values["iap"] = 1;

    $limit = 3;
    while ( empty($values["summary"]) && $limit > 0 ) { // sometimes protobuf is missing, but present again on subsequent call
      $proto = json_decode($this->getRegVal('/data:(?<content>\[\[\[.+?). sideChannel: .*?\);<\/script/ims'));
      if ( empty($proto[0][10]) ) {
        --$limit;
       $this->getApplicationPage($packageName, $lang, $loc);
      } else {
        $values["summary"] = $proto[0][10][1][1];
        break;
      }
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
          return ['success'=>0, 'message'=>$http_response_header[0]];
          break;
      }
    } else { // network error (e.g. "failed to open stream: Connection timed out")
      return ['success'=>0, 'message'=>'network error'];
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
      else $perms['misc'] = ['group_name'=>$arr[1][0][0], 'perms'=>$arr[2]];
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
   * @param string category     name of the category to parse
   * @return array              array of package names
   */
  public function parseCategory($category) {
    $link = "https://play.google.com/store/apps/category/" . $category;
    $data = $this->parse($link);
    if ( empty($this->lastError) ) return ['success'=>1, 'message'=>'', 'data'=>$data];
    else return ['success'=>0, 'message'=>$this->lastError, 'data'=>$data];
  }

  /** Obtain list of available categories
   * @method public parseCategories
   * @return array  array[0..n] of category names to be used with this::parseCategory
   */
  public function parseCategories() {
    if ( ! $this->getApplicationPage('com.google.android.gm','en','US') )
      return ['success'=>0, 'message'=>$this->lastError, 'data'=>[]];
    preg_match_all('!href="/store/apps/category/([^"]+)"[^>]*>([^<]+)!i', $this->input, $cats);
    return ['success'=>1, 'message'=>'', 'data'=>array_unique($cats[1])];
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
}
