<?php
/**
*
* @Name : GooglePlayWebServiceAPI/google-play.php
* @Version : 0.3
* @Programmer : Max & Izzy
* @Date : 2020-10-19, 2020-10-25, 2020-10-29, 2020-10-30, 2020-12-05, 2020-12-06
* @Released under : https://github.com/BaseMax/GooglePlayWebServiceAPI/blob/master/LICENSE
* @Repository : https://github.com/BaseMax/GooglePlayWebServiceAPI
*
**/
class GooglePlay {
  private $debug=false;

  protected function getRegVal($regEx) {
    preg_match($regEx, $this->input, $res);
    if(isset($res["content"])) return trim($res["content"]);
    else return null;
  }

  public function parseApplication($packageName,$lang='en_US',$loc='US') {
    $link="https://play.google.com/store/apps/details?id=".$packageName."&hl=$lang&gl=$loc";
    if ( ! $this->input = @file_get_contents($link) ) {
      return ['success'=>0,'message'=>'Google returned: '.$http_response_header[0]];
    }
    $values=[];
    $values["packageName"]=$packageName;

    $values["name"] = strip_tags($this->getRegVal('/itemprop="name">(?<content>.*?)<\/h1>/'));
    if ($values["name"]==null) {
      return ['success'=>0,'message'=>'No app data found'];
    }

    $values["developer"] = strip_tags($this->getRegVal('/href="\/store\/apps\/developer\?id=(?<id>[^\"]+)"([^\>]+|)>(?<content>[^\<]+)<\/a>/i'));

    preg_match('/itemprop="genre" href="\/store\/apps\/category\/(?<id>[^\"]+)"([^\>]+|)>(?<content>[^\<]+)<\/a><\/span>/i', $this->input, $category);
    if(isset($category["id"], $category["content"])) {
      $values["category"]=trim(strip_tags($category["content"]));
      $catId=trim(strip_tags($category["id"]));
      if($catId=='GAME' || substr($catId,0,5)=='GAME_') $values["type"]="game";
      elseif($catId=='FAMILY' || substr($catId,0,7)=='FAMILY?') $values["type"]="family";
      else $values["type"]="app";
    } else {
      $values["category"]=null;
      $values["type"]=null;
    }

    $proto = json_decode($this->getRegVal('/data:(?<content>\[\[\[.+?). sideChannel: .*?\);<\/script/ims'));
    $values["summary"] = $proto[0][10][1][1];
    $values["description"] = $this->getRegVal('/itemprop="description"><span jsslot><div jsname="sngebd">(?<content>.*?)<\/div><\/span><div/i');
    $values["icon"] = $this->getRegVal('/<div class="hkhL9e"><div class="xSyT2c"><img src="(?<content>[^\"]+)"/i');
    $values["featureGraphic"] = preg_replace('!(.*)=w\d+.*!i','$1',$this->getRegVal('/<meta name="twitter:image" content="(?<content>[^\"]+)"/i'));

    preg_match('/<div class="Rx5dXb"([^\>]+|)>(?<content>.*?)<c-data/i', $this->input, $image);
    if(isset($image["content"])) {
      preg_match_all('/<img data-src="(?<content>[^\"]+)"/i', $image["content"], $images);
      if(isset($images["content"]) && !empty($images["content"])) {
        $values["images"]=$images["content"];
      } else {
        preg_match_all('/<img src="[^"]*" srcset="(?<content>[^\s"]+)/i', $image["content"], $images);
        if(isset($images["content"])) {
          $values["images"]=$images["content"];
        } else {
          $values["images"]=null;
        }
      }
    } else {
      $values["images"]=null;
    }

    if (substr(strtolower($lang),0,2)=='en') {
      $values["lastUpdated"] = strip_tags($this->getRegVal('/<div class="BgcNfc">Updated<\/div><span class="htlgb"><div class="IQ1z0d"><span class="htlgb">(?<content>.*?)<\/span><\/div><\/span><\/div>/i'));
      $values["versionName"] = strip_tags($this->getRegVal('/<div class="BgcNfc">Current Version<\/div><span class="htlgb"><div class="IQ1z0d"><span class="htlgb">(?<content>.*?)<\/span><\/div><\/span><\/div>/i'));
      $values["minimumSDKVersion"] = strip_tags($this->getRegVal('/<div class="hAyfc"><div class="BgcNfc">Requires Android<\/div><span class="htlgb"><div class="IQ1z0d"><span class="htlgb">(?<content>.*?)<\/span><\/div><\/span><\/div>/i'));
      $values["installs"] = strip_tags($this->getRegVal('/<div class="hAyfc"><div class="BgcNfc">Installs<\/div><span class="htlgb"><div class="IQ1z0d"><span class="htlgb">(?<content>.*?)<\/span><\/div><\/span><\/div>/i'));
      $values["age"] = strip_tags($this->getRegVal('/<div class="hAyfc"><div class="BgcNfc">Content Rating<\/div><span class="htlgb"><div class="IQ1z0d"><span class="htlgb"><div>(?<content>.*?)<\/div>/i'));
      $values["size"] = $this->getRegVal('/<div class="BgcNfc">Size<\/div><span class="htlgb"><div class="IQ1z0d"><span class="htlgb">(?<content>[^<]+)<\/span>/i');
    } else {
      $envals = $this->parseApplication($packageName);
      foreach(["lastUpdated","versionName","minimumSDKVersion","installs","age","size"] as $val) $values[$val]=$envals[$val];
    }

    $values["rating"] = $this->getRegVal('/<div class="BHMmbe"[^>]*>(?<content>[^<]+)<\/div>/i');
    $values["votes"] = $this->getRegVal('/<span class="AYi5wd TBRnV"><span[^>]*>(?<content>[^>]+)<\/span>/i');
    $values["price"] = $this->getRegVal('/<meta itemprop="price" content="(?<content>[^"]+)">/i');

    if($this->debug) {
      print_r($values);
    }
    $values['success'] = 1;
    return $values;
  }

  public function parse($link=null) {
    if($link == "" || $link == null) {
      $link="https://play.google.com/apps";
    }
    $input=file_get_contents($link);
    preg_match_all('/href="\/store\/apps\/details\?id=(?<ids>[^\"]+)"/i', $input, $ids);
    if(isset($ids["ids"])) {
      $ids=$ids["ids"];
      $ids=array_values(array_unique($ids));
      $values=$ids;
    } else {
      $values=[];
    }
    if($this->debug) {
      print_r($values);
    }
    return $values;
  }

  public function parsePerms($packageName,$lang='en') {
    $opts = ['http' => array(
      'method'  => 'POST',
      'header'  => 'Content-type: application/x-www-form-urlencoded;charset=utf-8'
                  ."\r\n".'Referer: https://play.google.com/',
      'content' => 'f.req=%5B%5B%5B%22xdSrCf%22%2C%22%5B%5Bnull%2C%5B%5C%22'.$packageName.'%5C%22%2C7%5D%2C%5B%5D%5D%5D%22%2Cnull%2C%221%22%5D%5D%5D',
      'ignore_errors' => TRUE
      )
    ];
    $context  = stream_context_create($opts);
    if ( $proto = @file_get_contents('https://play.google.com/_/PlayStoreUi/data/batchexecute?rpcids=xdSrCf&bl=boq_playuiserver_20201201.06_p0&hl='.$lang.'&authuser&soc-app=121&soc-platform=1&soc-device=1&rt=c&f.sid=-8792622157958052111&_reqid=257685', false, $context) ) { // raw proto_buf data
      preg_match("!HTTP/1\.\d\s+(\d{3})\s+(.+)$!i",$http_response_header[0],$match);
      $response_code = $match[1];
      switch ($response_code) {
        case "200" : // HTTP/1.0 200 OK
          break;
        case "400" : // echo "! No XHR for '$pkg'\n";
        case "404" : // app no longer on play
        default:
          return ['success'=>0,'message'=>$http_response_header[0]];
          break;
      }
    } else { // network error (e.g. "failed to open stream: Connection timed out")
      return ['success'=>0,'message'=>'network error'];
    }

    $perms = $perms_unique = [];
    $json = preg_replace('!.*?(\[.+?\])\s*\d.*!ims','$1',$proto);
    $arr = json_decode(json_decode($json)[0][2]);
    if (!empty($arr[0])) foreach ($arr[0] as $group) { // 0: group name, 1: group icon, 2: perms, 3: group_id
      $perms[$group[3][0]] = ['group_name'=>$group[0], 'perms'=>$group[2]];
      foreach($group[2] as $perm) $perms_unique[] = $perm[1];
    }
    if (!empty($arr[1])) {
      $perms['misc'] = ['group_name'=>$arr[1][0][0], 'perms'=>$arr[1][0][2]];
      foreach($arr[1][0][2] as $perm) $perms_unique[] = $perm[1];
    }

    return ['success'=>1,'grouped'=>$perms,'perms'=>array_unique($perms_unique)];
  }

  public function parseCategory($category) {
    $link="https://play.google.com/store/apps/category/".$category;
    return $this->parse($link);
  }

  public function parseCategories() {
    return array_merge($this->categories["game"], $this->categories["app"]);
  }

  public function parseSearch($query) {
    $link="https://play.google.com/store/search?q=".$query."&c=apps";
    return $this->parse($link);
  }
}
