<?php

namespace Httpful;
require __DIR__."/../JiraClient.php";

// ----------------------------------- Init ----------------------------------------

$client = new JiraClient();

$currentSprintId = $client->getCurrentSprint(44);

// exit if we cannot determine the sprint id
if(!$currentSprintId){
	echo "Could not determine SprintID";
	exit;
}


$bugsInQaNo = 0;

$toTestIssues = array();
$toTestIssues['QATaskless'] = array();



// ----------------------------------- Functions ----------------------------------------

function isBug($issue){

	global $bugsInQaNo;

//	check if issue has type Bug or BugSubtask and increment $bugNo
	$issueType=  $issue->fields->issuetype->name;
	if($issueType == "Bug" || $issueType == "Bug Subtask"){
		$bugsInQaNo++;
		return true;
	}
	return false;
}

function processIssue($issue){

	global $toTestIssues;

	$foundQATask = false;

//	go trough subtasks
	$subtaskCounter = 0;
	while($issue->fields->subtasks[$subtaskCounter]){

		$subtask = $issue->fields->subtasks[$subtaskCounter];
		$subtaskType = $subtask->fields->issuetype->name;

//		if subtask found process it
		if($subtaskType == "QA Task"){
			$foundQATask = true;
			processQATask($issue->fields->subtasks[$subtaskCounter], $issue->key);
			break;
		}
		$subtaskCounter++;
	}

//	if no QA task found we add issue to QATaskless node for review
	if(!$foundQATask){
		if(!in_array($issue->key, $toTestIssues['QATaskless'])){
			$toTestIssues['QATaskless'][]  = $issue->key;
		}
	}
}

function processQATask($qaTask, $parentKey){
	global $client;
	global $toTestIssues;

//	request full qa task
	$expandedQATask = $client->getIssue($qaTask->key);

//	grab assignee
	$assignee = $expandedQATask->fields->assignee->displayName;

//	if $toTestIssues doesn't contain a key for the qatask's assignee we add it
	if(!array_key_exists($assignee, $toTestIssues)){
		$toTestIssues[$assignee] = array();
	}

//	if story key is not present in the array for this assignee we push it
	if(!in_array($parentKey, $toTestIssues[$assignee])){
		$toTestIssues[$assignee][] = $parentKey;
	}
}

function prettyPrint($arr){
	$retStr = '<ul>';
	if (is_array($arr)){
		foreach ($arr as $key=>$val){
			if (is_array($val)){
				$retStr .= '<li>' . $key . ': ' . prettyPrint($val) . '</li>';
			}else{
				$retStr .= "<li><a href=\"https://newpig.atlassian.net/browse/$val\">$val</a></li>";
			}
		}
	}
	$retStr .= '</ul>';
	return $retStr;
}




// ----------------------------------- Script ----------------------------------------

// construct params to send to Jira
$params = array(
	'jql'=> "project = ER AND status = QA AND Sprint = $currentSprintId",
	'maxResults' => 1000,
	'fields' => 'subtasks'
);

// execute query
$response = $client->runQuery($params);

// for each issue if it's not a bug we process it
$issueCounter=0;
while($response->body->issues[$issueCounter]){

	if(!isBug($response->body->issues[$issueCounter])){
		processIssue($response->body->issues[$issueCounter]);
	}
	$issueCounter++;
}

// create link to JIRA with bug filter
$bugFilterURL = "$baseURL/issues/?jql=".urlencode('project = ER AND status = QA AND Sprint = '.$currentSprintId.' AND type in (Bug, "Bug Subtask")');

echo "<h3>Things in QA by assignee</h3>";
echo "<p>BUGS IN QA COLUMN: <a href='$bugFilterURL' >$bugsInQaNo</a> </p>";
echo prettyPrint($toTestIssues);










