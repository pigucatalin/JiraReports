<?php

namespace Httpful;
include_once "./vendor/nategood/httpful/src/Httpful/Request.php";
include_once "./vendor/nategood/httpful/src/Httpful/Http.php";
include_once "./vendor/nategood/httpful/src/Httpful/Bootstrap.php";


$bugsInQaNo = 0;
$toTestIssues = array();


$ini_array = parse_ini_file("jira.properties");
$username = $ini_array['user'];
$pass = $ini_array['pass'];
$baseURL=$ini_array['baseURL'];


function isBug($issue){

	global $bugsInQaNo;

	$issueType=  $issue->fields->issuetype->name;
	if($issueType == "Bug" || $issueType == "Bug Subtask"){
		$bugsInQaNo++;
		return true;
	}
	return false;
}

function processIssue($issue){

	global $toTestIssues;
	global $username;
	global $pass;

	$foundQATask = false;
	$subtaskCounter = 0;
	while($issue->fields->subtasks[$subtaskCounter]){
		$subtaskType = $issue->fields->subtasks[$subtaskCounter]->fields->issuetype->name;

		if($subtaskType == "QA Task"){
			$foundQATask = true;
			$qaTask = Request::get($issue->fields->subtasks[$subtaskCounter]->self)->authenticateWithBasic($username, $pass)->send();

			$assignee = $qaTask->body->fields->assignee->displayName;
			$status = $qaTask->body->fields->status->name;

			if($status != "Closed"){

				if(!array_key_exists($assignee, $toTestIssues)){
					$toTestIssues[$assignee] = array();
				}

				if(!in_array($issue->key, $toTestIssues[$assignee])){
					$toTestIssues[$assignee][] = $issue->key;
				}
			}

		}
		$subtaskCounter++;
	}

	if(!$foundQATask){
		if(!array_key_exists('QATaskless', $toTestIssues)){
			$toTestIssues['QATaskless'] = array();
		}
		if(!in_array($issue->key, $toTestIssues['QATaskless'])){
			$toTestIssues['QATaskless'][]  = $issue->key;
		}
	}
}

function pp($arr){
	$retStr = '<ul>';
	if (is_array($arr)){
		foreach ($arr as $key=>$val){
			if (is_array($val)){
				$retStr .= '<li>' . $key . ': ' . pp($val) . '</li>';
			}else{
				$retStr .= "<li><a href=\"https://newpig.atlassian.net/browse/$val\">$val</a></li>";
			}
		}
	}
	$retStr .= '</ul>';
	return $retStr;
}

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

$jqlStr = urlencode("project = ER AND status = QA AND Sprint = $currentSprintId");

$uri = "$baseURL/rest/api/2/search?maxResults=1000&jql=".$jqlStr;

$response = Request::get($uri)->authenticateWithBasic($username, $pass)->send();



$issueCounter=0;
while($response->body->issues[$issueCounter]){

	if(!isBug($response->body->issues[$issueCounter])){
		processIssue($response->body->issues[$issueCounter]);
	}
	$issueCounter++;
}

$bugFilterURL = "$baseURL/issues/?jql=".urlencode('project = ER AND status = QA AND Sprint = '.$currentSprintId.' AND type in (Bug, "Bug Subtask")');


echo "<h3>Things in QA by assignee</h3>";
echo "<p>BUGS IN QA COLUMN: <a href='$bugFilterURL' >$bugsInQaNo</a> </p>";
echo pp($toTestIssues);

