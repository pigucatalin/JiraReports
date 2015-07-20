<?php

namespace Httpful;
include_once "./vendor/nategood/httpful/src/Httpful/Request.php";
include_once "./vendor/nategood/httpful/src/Httpful/Http.php";
include_once "./vendor/nategood/httpful/src/Httpful/Bootstrap.php";


//ini_set('display_errors', '0');

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




$jqlStr1 = urlencode('project = e-Replatform AND type = "QA Task" AND Sprint = '.$currentSprintId.' and status not in (Closed,"Demo", Resolved) and summary ~ "create"');
$jqlStr2 = urlencode('project = e-Replatform AND type = "QA Task" AND Sprint = '.$currentSprintId.' and status not in (Closed,"Demo", Resolved) and (summary ~ "execute" or summary ~ "verify")');

$uri = "$baseURL/rest/api/2/search?maxResults=1000&jql=".$jqlStr1;
$response = Request::get($uri)->authenticateWithBasic($username, $pass)->send();


$issueCounter=0;
$assignedTimes = array();
while($response->body->issues[$issueCounter]){
	$issue = $response->body->issues[$issueCounter];
	$assignedTimes[$issue->fields->assignee->name]['create'] += $issue->fields->aggregatetimeestimate;
	$issueCounter++;
}


$uri = "$baseURL/rest/api/2/search?maxResults=1000&jql=".$jqlStr2;
$response = Request::get($uri)->authenticateWithBasic($username, $pass)->send();


$issueCounter=0;
while($response->body->issues[$issueCounter]){
	$issue = $response->body->issues[$issueCounter];
	$assignedTimes[$issue->fields->assignee->name]['execute'] += $issue->fields->aggregatetimeestimate;
	$issueCounter++;
}

foreach($assignedTimes as $key=>$value){

	echo "----------------------\n";
	echo $key."\n";
	echo 'Create tests - '. $value['create']/3600, "h\n";
	echo 'Execute tests - '. $value['execute']/3600, "h\n";

	echo 'Total - '.($value['create']+$value['execute'])/3600, "h\n";

	echo "----------------------\n";
}