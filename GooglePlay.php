<?php
// Max Base
// github.com/basemax/GooglePlayWebServiceAPI
// 2020-10-19
class GooglePlay {
	private $debug=true;
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
		$input=file_get_contents("https://play.google.com/store/apps/details?id=".$packageName."&hl=en_US&gl=US");
		$values=[];
		$values["packageName"]=$packageName;

		preg_match('/itemprop="name">(?<content>.*?)<\/h1>/', $input, $name);
		if(isset($name["content"])) {
			$values["name"]=trim(strip_tags($name["content"]));
		}
		else {
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
	}
}
$google = new GooglePlay();
$google->parseApplication("com.bezapps.flowdiademo");
