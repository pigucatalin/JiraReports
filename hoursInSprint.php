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

$jqlStr = urlencode('project = e-Replatform AND type = "QA Task" AND Sprint = 136 and status not in (Closed,"Customer Testing", Resolved)');
//$jqlStr = urlencode('key = ER-1683');
$uri = "$baseURL/rest/api/2/search?maxResults=1000&jql=".$jqlStr;
$response = Request::get($uri)->authenticateWithBasic($username, $pass)->send();

//print_r($response->body);

/*
 * $response->body->issues[0]->key
 * $response->body->issues[0]->timeestimate
 * $response->body->issues[0]->fields->aggregatetimeestimate
 * $response->body->issues[0]->fields->assignee->name
 *
 */


$issueCounter=0;
$assignedTimes = array();
while($response->body->issues[$issueCounter]){
	$issue = $response->body->issues[$issueCounter];
	$assignedTimes[$issue->fields->assignee->name] += $issue->fields->aggregatetimeestimate;
	$issueCounter++;
}

foreach(array_keys($assignedTimes) as $key){
	echo "$key -- ". $assignedTimes[$key]/3600 . "\n";
}