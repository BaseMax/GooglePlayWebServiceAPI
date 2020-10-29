<?php
/**
*
* @Name : GooglePlayWebServiceAPI/google-play.php
* @Version : 0.2
* @Programmer : Max
* @Date : 2020-10-19, 2020-10-25, 2020-10-29
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
	public function parseApplication($packageName) {
	    $link="https://play.google.com/store/apps/details?id=".$packageName."&hl=en_US&gl=US";
		$input=file_get_contents($link);
// 		file_put_contents("t.html", $input);
		$values=[];
		$values["packageName"]=$packageName;
        // print $link."\n";
		preg_match('/itemprop="name">(?<content>.*?)<\/h1>/', $input, $name);
// 		print_r($name);
		if(isset($name["content"])) {
			$values["name"]=trim(strip_tags($name["content"]));
		}
		else {
		    return [];
			return $values;
			$values["name"]=null;
		}

		preg_match('/href="\/store\/apps\/developer\?id=(?<id>[^\"]+)"([^\>]+|)>(?<content>[^\<]+)<\/a>/i', $input, $developer);
		if(isset($developer["id"], $developer["content"])) {
			$values["developer"]=trim(strip_tags($developer["content"]));
		}
		else {
			$values["developer"]=null;
		}

		preg_match('/itemprop="genre" href="\/store\/apps\/category\/(?<id>[^\"]+)"([^\>]+|)>(?<content>[^\<]+)<\/a><\/span>/i', $input, $category);
		if(isset($category["id"], $category["content"])) {
			$values["category"]=trim(strip_tags($category["content"]));
			$isGame=false;
			foreach($this->categories["game"] as $game) {
				// if($values["category"] == $game) {
				if(strtolower($values["category"]) == strtolower($game)) {
					$isGame=true;
					break;
				}
			}
			$values["type"]=$isGame ? "game" : "app";
		}
		else {
			$values["category"]=null;
			$values["type"]=null;
		}

		preg_match('/itemprop="description"><span jsslot><div jsname="sngebd">(?<content>.*?)<\/div><\/span><div/i', $input, $description);
		if(isset($description["content"])) {
			$values["description"]=trim($description["content"]);
		}
		else {
			$values["description"]=null;
		}

		preg_match('/<div class="hkhL9e"><div class="xSyT2c"><img src="(?<content>[^\"]+)"/i', $input, $icon);
		if(isset($icon["content"])) {
			$values["icon"]=trim($icon["content"]);
		}
		else {
			$values["icon"]=null;
		}

		// preg_match_all('/item-index="([0-9]+)"><img (data-|)src="(?<content>[^\"]+)" srcset/i', $input, $images);
		// preg_match_all('/data-ils="3" jsaction="rcuQ6b:trigger.M8vzZb;" data-srcset="(?<content>[^\"]+)"/i', $input, $images);
		preg_match('/<div class="Rx5dXb"([^\>]+|)>(?<content>.*?)<c-data/i', $input, $image);
		// print_r($image);
		if(isset($image["content"])) {
			preg_match_all('/<img data-src="(?<content>[^\"]+)"/i', $image["content"], $images);
			if(isset($images["content"])) {
				$values["images"]=$images["content"];
			}
			else {
				$values["images"]=null;
			}
		}
		else {
			$values["images"]=null;
		}
		
		preg_match('/<div class="BgcNfc">Updated<\/div><span class="htlgb"><div class="IQ1z0d"><span class="htlgb">(?<content>.*?)<\/span><\/div><\/span><\/div>/i', $input, $updated);
		if(isset($updated["content"])) {
			$values["lastUpdated"]=trim(strip_tags($updated["content"]));
		}
		else {
			$values["lastUpdated"]=null;
		}

		preg_match('/<div class="BgcNfc">Current Version<\/div><span class="htlgb"><div class="IQ1z0d"><span class="htlgb">(?<content>.*?)<\/span><\/div><\/span><\/div>/i', $input, $version);
		if(isset($version["content"])) {
			$values["versionName"]=trim(strip_tags($version["content"]));
		}
		else {
			$values["versionName"]=null;
		}

		preg_match('/<div class="hAyfc"><div class="BgcNfc">Requires Android<\/div><span class="htlgb"><div class="IQ1z0d"><span class="htlgb">(?<content>.*?)<\/span><\/div><\/span><\/div>/i', $input, $require);
		if(isset($require["content"])) {
			$values["minimumSDKVersion"]=trim(strip_tags($require["content"]));
		}
		else {
			$values["minimumSDKVersion"]=null;
		}

		preg_match('/<div class="hAyfc"><div class="BgcNfc">Installs<\/div><span class="htlgb"><div class="IQ1z0d"><span class="htlgb">(?<content>.*?)<\/span><\/div><\/span><\/div>/i', $input, $install);
		if(isset($install["content"])) {
			$values["installs"]=trim(strip_tags($install["content"]));
		}
		else {
			$values["installs"]=null;
		}

		preg_match('/<div class="hAyfc"><div class="BgcNfc">Content Rating<\/div><span class="htlgb"><div class="IQ1z0d"><span class="htlgb"><div>(?<content>.*?)<\/div>/i', $input, $age);
		if(isset($age["content"])) {
			$values["age"]=trim(strip_tags($age["content"]));
		}
		else {
			$values["age"]=null;
		}

		if($this->debug) {
			print_r($values);
		}
		return $values;
	}

	public function parse($link=null) {
		if($link == "" || $link ==  null) {
			$link="https://play.google.com/apps";
		}
		$input=file_get_contents($link);
		preg_match_all('/href="\/store\/apps\/details\?id=(?<ids>[^\"]+)"/i', $input, $ids);
		if(isset($ids["ids"])) {
			$ids=$ids["ids"];
			$ids=array_values(array_unique($ids));
			$values=$ids;
		}
		else {
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

	public function parseSearch($query) {
		$link="https://play.google.com/store/search?q=".$query."&c=apps";
		return $this->parse($link);
	}
}
