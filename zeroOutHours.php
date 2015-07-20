<?php

namespace Httpful;
include_once "./vendor/nategood/httpful/src/Httpful/Request.php";
include_once "./vendor/nategood/httpful/src/Httpful/Http.php";
include_once "./vendor/nategood/httpful/src/Httpful/Bootstrap.php";


/*The script runs a JQL query for all the issues with status in (Closed, Demo, "Dev Complete", QA) that still have remaining time
For each issue it grabs the owner and constructs an array that is later outputed in HTML format
*/



// ----------------------------------- Init ----------------------------------------

// set not to output errors or warnings as this might ruin the HTML we generate
//ini_set('display_errors', '0');


// read username, pass and base url from properties file
$ini_array = parse_ini_file("jira.properties");
$username = $ini_array['user'];
$pass = $ini_array['pass'];
$baseURL=$ini_array['baseURL'];


// ----------------------------------- Functions ----------------------------------------

// function to pretty print in HMTL format the owners and issues as a unordered list
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

// ----------------------------------- Script ----------------------------------------


// generate JQL query and encoding it (no encoding = nasty errors)
$jqlStr1 = urlencode('project = e-Replatform AND sprint = 173 and status in(Closed, Demo, "Dev Complete", QA) and remainingEstimate != 0');

// forming the URL
$uri = "$baseURL/rest/api/2/search?maxResults=1000&fields=timetracking,assignee&jql=".$jqlStr1;
// run the query using the JIRA Rest API
$response = Request::get($uri)->authenticateWithBasic($username, $pass)->send();


$issueCounter=0;
$notZeroed = array();

// process returned issues
while($response->body->issues[$issueCounter]) {
	$currentIssue = $response->body->issues[$issueCounter];

//	get issue key, time and owner
	$key = $currentIssue->key;
	$time = $currentIssue->fields->timetracking->remainingEstimateSeconds;
	$owner = $currentIssue->fields->assignee->displayName;

//	if not zeroed out make sure we have in our array a key with the owners name and push the issue key in the array
	if($time > 0){
		if(!array_key_exists($owner, $notZeroed)){
			$notZeroed[$owner] = array();
		}
		array_push ($notZeroed[$owner], $key);
	}

	$issueCounter++;
}

print_r(pp($notZeroed));

