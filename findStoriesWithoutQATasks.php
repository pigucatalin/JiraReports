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




$jqlStr = urlencode('project = e-Replatform AND type = "Story" AND Sprint = '.$currentSprintId);
//$jqlStr = urlencode('key = ER-3512');

$uri = "$baseURL/rest/api/2/search?maxResults=1000&jql=".$jqlStr;

$response = Request::get($uri)->authenticateWithBasic($username, $pass)->send();

//print_r($response->body);
//exit;

$issueCounter=0;
while($response->body->issues[$issueCounter]){

	echo "-------------------------\n";
	$issue = $response->body->issues[$issueCounter];
	echo "Processing issue no $issueCounter - ".$issue->key."\n";



	$foundQATasks = false;
	$subtaskCounter = 0;
	while($issue->fields->subtasks[$subtaskCounter]){
		$subtaskType = $issue->fields->subtasks[$subtaskCounter]->fields->issuetype->name;
		if($subtaskType == "QA Task"){
			$foundQATasks = true;
			$subtaskResponse = Request::get($issue->fields->subtasks[$subtaskCounter]->self)->authenticateWithBasic($username, $pass)->send();

//			print_r($subtaskResponse->body);
//			exit;

			if(!$subtaskResponse->body->fields->assignee){
				echo "QA Task ".$subtaskResponse->body->key." has no assignee!\n";
			}

			if(!$subtaskResponse->body->fields->timeoriginalestimate){
				echo "QA Task ".$subtaskResponse->body->key." has no estimated time!\n";
			}

		}

		$subtaskCounter++;
	}

	if(!$foundQATasks){
		echo "NO QA TASKS!!!!!!!!\n";
	}

	echo "-------------------------\n";
	$issueCounter++;
}

