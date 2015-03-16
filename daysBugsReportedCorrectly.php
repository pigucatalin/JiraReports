<?php

namespace Httpful;
include_once "./vendor/nategood/httpful/src/Httpful/Request.php";
include_once "./vendor/nategood/httpful/src/Httpful/Http.php";
include_once "./vendor/nategood/httpful/src/Httpful/Bootstrap.php";


ini_set('display_errors', '0');

$ini_array = parse_ini_file("jira.properties");

$username = $ini_array['user'];
$pass = $ini_array['pass'];
$baseURL=$ini_array['baseURL'];

$yesterday = date('Y-m-d',strtotime("-1 days"));
$jqlStr = urlencode('project = e-Replatform AND type = Bug and status not in (Closed,"Customer Testing", Resolved) AND createdDate >='.$yesterday);
$uri = "$baseURL/rest/api/2/search?jql=".$jqlStr."&maxResults=1000";
$response = Request::get($uri)->authenticateWithBasic($username, $pass)->send();

$issueCounter=0;
while($response->body->issues[$issueCounter]){
	$issueResp = Request::get($response->body->issues[$issueCounter]->self)->authenticateWithBasic($username, $pass)->send();

	echo "----------------------\n";
	echo $issueResp->body->key."\n";

	$allGood = checkPriority($issueResp) & hasStory($issueResp) & hasEpic($issueResp) & hasSeverity($issueResp) & checkEnvironment($issueResp);

	if($allGood){
		echo "ALL GOOOD!!!!\n";
	}

	echo "----------------------\n";

	$issueCounter++;
}



function checkPriority($issueResp){
	$prioName = $issueResp->body->fields->priority->name;
	$acceptedValues = array("Blocker", "Critical", "Medium", "Trivial");
	if(!in_array($prioName, $acceptedValues, false)){
		echo "Priority set to: ".$prioName."\n";
		return false;
	}
	return true;
}

function hasStory($issueResp){
	$count = 0;
	while(isset($issueResp->body->fields->issuelinks[$count])){
		if($issueResp->body->fields->issuelinks[$count]->outwardIssue->fields->issuetype->name == "Story"){
			return true;
		}
		$count++;
	}
	echo "NO STORY LINKED!!\n";
	return false;
}

function hasEpic($issueResp){
	if(isset($issueResp->body->fields->customfield_10008)){
		return true;
	}
	echo "NO EPIC SET!!!\n";
	return false;
}

function hasSeverity($issueResp){
	$setSev = $issueResp->body->fields->customfield_11700->value;
	$severities = array("Sev1", "Sev2", "Sev3");

	if(in_array($setSev, $severities, false)){
		return true;
	}
	echo "Severity set to: ".$setSev."\n";
}

function checkEnvironment($issueResp){
	$setEnv = $issueResp->body->fields->environment;
	$contains = array();
	array_push($contains, preg_match('/Env:/', $setEnv));
	array_push($contains, preg_match('/Build:/', $setEnv));
	array_push($contains, preg_match('/Browser:/', $setEnv));
	array_push($contains, preg_match('/Mobile:/', $setEnv));

	foreach($contains as $var){
		if(!$var){
			echo "Environment set to: ".$setEnv."\n";
			return false;
		}
	}
	return true;
}
