<?php
/**
*
* @Name : GooglePlayWebServiceAPI/google-play.php
* @Version : 0.3
* @Programmer : Max
* @Date : 2020-10-19, 2020-10-25, 2020-10-29, 2020-10-30, 2020-12-05
* @Released under : https://github.com/BaseMax/GooglePlayWebServiceAPI/blob/master/LICENSE
* @Repository : https://github.com/BaseMax/GooglePlayWebServiceAPI
*
**/
class GooglePlay {
	private $debug=false;
	private $categories=[
		"app"=>[
			"Art & Design",
			"Augmented Reality",
			"Auto & Vehicles",
			"Beauty",
			"Books & Reference",
			"Business",
			"Comics",
			"Communication",
			"Dating",
			"Daydream",
			"Education",
			"Entertainment",
			"Events",
			"Finance",
			"Food & Drink",
			"Health & Fitness",
			"House & Home",
			"Libraries & Demo",
			"Lifestyle",
			"Maps & Navigation",
			"Medical",
			"Music & Audio",
			"News & Magazines",
			"Parenting",
			"Personalization",
			"Photography",
			"Productivity",
			"Shopping",
			"Social",
			"Sports",
			"Tools",
			"Travel & Local",
			"Video Players & Editors",
			"Wear OS by Google",
			"Weather",
		],
		"game"=>[
			"Action",
			"Adventure",
			"Arcade",
			"Board",
			"Card",
			"Casino",
			"Casual",
			"Educational",
			"Music",
			"Puzzle",
			"Racing",
			"Role Playing",
			"Simulation",
			"Sports",
			"Strategy",
			"Trivia",
			"Word",
		],
	];
	protected function getRegVal($regEx) {
		preg_match($regEx, $this->input, $res);
		if(isset($res["content"])) return trim($res["content"]);
		else return null;
	}

	public function parseApplication($packageName) {
		$link="https://play.google.com/store/apps/details?id=".$packageName."&hl=en_US&gl=US";
		if ( ! $this->input = @file_get_contents($link) ) {
			return ['success'=>0,'message'=>'Google returned: '.$http_response_header[0]];
		}
		$values=[];
		$values["packageName"]=$packageName;

		$values["name"] = strip_tags($this->getRegVal('/itemprop="name">(?<content>.*?)<\/h1>/'));
		if ($values["name"]===null) {
			return ['success'=>0,'message'=>'No app data found'];
		}

		$values["developer"] = strip_tags($this->getRegVal('/href="\/store\/apps\/developer\?id=(?<id>[^\"]+)"([^\>]+|)>(?<content>[^\<]+)<\/a>/i'));

		preg_match('/itemprop="genre" href="\/store\/apps\/category\/(?<id>[^\"]+)"([^\>]+|)>(?<content>[^\<]+)<\/a><\/span>/i', $this->input, $category);
		if(isset($category["id"], $category["content"])) {
			$values["category"]=trim(strip_tags($category["content"]));
			$isGame=false;
			foreach($this->categories["game"] as $game) {
				if(strtolower($values["category"]) == strtolower($game)) {
					$isGame=true;
					break;
				}
			}
			$values["type"]=$isGame ? "game" : "app";
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

		$values["lastUpdated"] = strip_tags($this->getRegVal('/<div class="BgcNfc">Updated<\/div><span class="htlgb"><div class="IQ1z0d"><span class="htlgb">(?<content>.*?)<\/span><\/div><\/span><\/div>/i'));
		$values["versionName"] = strip_tags($this->getRegVal('/<div class="BgcNfc">Current Version<\/div><span class="htlgb"><div class="IQ1z0d"><span class="htlgb">(?<content>.*?)<\/span><\/div><\/span><\/div>/i'));
		$values["minimumSDKVersion"] = strip_tags($this->getRegVal('/<div class="hAyfc"><div class="BgcNfc">Requires Android<\/div><span class="htlgb"><div class="IQ1z0d"><span class="htlgb">(?<content>.*?)<\/span><\/div><\/span><\/div>/i'));
		$values["installs"] = strip_tags($this->getRegVal('/<div class="hAyfc"><div class="BgcNfc">Installs<\/div><span class="htlgb"><div class="IQ1z0d"><span class="htlgb">(?<content>.*?)<\/span><\/div><\/span><\/div>/i'));
		$values["age"] = strip_tags($this->getRegVal('/<div class="hAyfc"><div class="BgcNfc">Content Rating<\/div><span class="htlgb"><div class="IQ1z0d"><span class="htlgb"><div>(?<content>.*?)<\/div>/i'));
		$values["rating"] = $this->getRegVal('/<div class="BHMmbe"[^>]*>(?<content>[^<]+)<\/div>/i');
		$values["votes"] = $this->getRegVal('/<span class="AYi5wd TBRnV"><span[^>]*>(?<content>[^>]+)<\/span>/i');
		$values["price"] = $this->getRegVal('/<meta itemprop="price" content="(?<content>[^"]+)">/i');
		$values["size"] = $this->getRegVal('/<div class="BgcNfc">Size<\/div><span class="htlgb"><div class="IQ1z0d"><span class="htlgb">(?<content>[^<]+)<\/span>/i');

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
