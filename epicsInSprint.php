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




function getSprintId(){

	global $username;
	global $pass;
	global $baseURL;

	$sprintUrl = $baseURL."/rest/greenhopper/latest/sprintquery/44";

	$sprints =  Request::get($sprintUrl)->authenticateWithBasic($username, $pass)->send()->body->sprints;

	$currentSprintId = null;
	foreach ($sprints as $sprint){
		if($sprint->state == "ACTIVE" && strpos($sprint->name,'Sprint') !== false){
			$currentSprintId = $sprint->id;
		}
	}
	return $currentSprintId;
}

$currentSprintId = getSprintId();

if(!$currentSprintId){
	echo "Could not determine SprintID";
	exit;
}



$jqlStr = urlencode('project = e-Replatform AND type = "Story" AND Sprint = '.$currentSprintId);


$uri = "$baseURL/rest/api/2/search?maxResults=1000&jql=".$jqlStr;
$response = Request::get($uri)->authenticateWithBasic($username, $pass)->send();

$epics = array();
$issueCounter=0;
while($response->body->issues[$issueCounter]){

	$story = $response->body->issues[$issueCounter];

//	if epic is set add it to the array
	if(isset($story->fields->customfield_10008)){
		$epicKey = $story->fields->customfield_10008;
		if(!in_array($epicKey, $epics)){
			$epics[] = $epicKey;
		}
	}
	$issueCounter++;
}




$jqlStr = urlencode('project = e-Replatform AND key in ('.join(', ', $epics).')');
$uri = "$baseURL/rest/api/2/search?maxResults=1000&jql=".$jqlStr;
$response = Request::get($uri)->authenticateWithBasic($username, $pass)->send();

//print_r($response->body);

$issueCounter=0;
while($response->body->issues[$issueCounter]){
	$epic = $response->body->issues[$issueCounter];
	echo $epic->key.' - '.$epic->fields->summary."\n";
	$issueCounter++;
}