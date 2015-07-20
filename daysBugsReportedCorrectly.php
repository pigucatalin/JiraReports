<?php

namespace Httpful;
include_once "./vendor/nategood/httpful/src/Httpful/Request.php";
include_once "./vendor/nategood/httpful/src/Httpful/Http.php";
include_once "./vendor/nategood/httpful/src/Httpful/Bootstrap.php";


/*The script runs a JQL query for all the bugs reported since yesterday.
For each bug it checks 5 fields to be according to the standard set on Confluence in the QA guidelines page under the bugs section
https://newpig.atlassian.net/wiki/display/EREP/QA+Guidelines#QAGuidelines-2.Bugs

The fields are :
 - Story (or Task) linked to bug
 - Epic linked to bug
 - Priority
 - Severity
 - Environment

For each field there is a function performing the check (the names are obvious)
  */


// set not to output errors or warnings as this might ruin the HTML we generate
ini_set('display_errors', '0');

// read username, pass and base url from properties file
$ini_array = parse_ini_file("jira.properties");

$username = $ini_array['user'];
$pass = $ini_array['pass'];
$baseURL=$ini_array['baseURL'];


// formatting yesterday's date (it's actually the last working day before today)
$yesterday = date('Y-m-d', strtotime('last weekday today'));

// generate JQL query and encoding it (no encoding = nasty errors)
$jqlStr = urlencode('project = e-Replatform AND type = Bug and status not in (Closed, Resolved) AND createdDate >='.$yesterday);

// forming the URL
$uri = "$baseURL/rest/api/2/search?jql=".$jqlStr."&maxResults=1000";

// running the request with the desired JQL
$response = Request::get($uri)->authenticateWithBasic($username, $pass)->send();

// initializing vars for stats
$issueCounter = 0;
$issueNoStory = 0;
$issueNoEpic = 0;
$issuePrioIssue = 0;
$issueSevIssue = 0;
$issueEnvIssue = 0;


// define style for tables we are about to output and echo out the starting table tag
echo "<style>table, td, th { border: 1px solid;}  td { padding: 15px;} </style><h2>Bugs:</h2>";
echo "<table>";

// process returned issues until there are no more
while($response->body->issues[$issueCounter]){

	// request each issue
	$issueResp = Request::get($response->body->issues[$issueCounter]->self)->authenticateWithBasic($username, $pass)->send();

//	write a table row for each issue
	echo "<tr>";

//	first cell contains issue key and link to issue
	echo "<td> <a href='https://newpig.atlassian.net/browse/".$issueResp->body->key."'>".$issueResp->body->key."</a></td>";

//	assume all fields are correct
	$allGood = true;

//	create cell to output if issue has fields that were not filled correctly
	echo "<td> ";

//	perform  checks and output errors found
	if(!checkPriority($issueResp)){
		$issuePrioIssue++;
		$allGood = false;
		echo 'Priority set to: "'. getPriority($issueResp)."\"</br>";
	}

	if(!hasSeverity($issueResp)){
		$issueSevIssue++;
		$allGood = false;
		echo 'Severity set to: "'.getSeverity($issueResp)."\"</br>";
	}

	if(!hasEpic($issueResp)){
		$issueNoEpic++;
		$allGood = false;
		echo "Epic link not set. </br>";
	}

	if(!hasStory($issueResp)){
		$issueNoStory++;
		$allGood = false;
		echo "Story not set. </br>";
	}

	if(!checkEnvironment($issueResp)){
		$issueEnvIssue++;
		$allGood = false;
		echo "Environment might not be set according to standard. </br>";
	}

//	if all fields were filled properly we output success message
	if($allGood){
		echo 'All good!!'."</br>";
	}

//	close cell
	echo "</td> ";

//	close row
	echo "</tr>\n";

//	move to the nex issue
	$issueCounter++;
}

// close issue table
echo "</table>";

// Stats: title
echo "<h2>Stats:</h2>";

// open stats table
echo "<table>";

// row containing statistic and value
echo "<tr><td> Total issues:</td><td> $issueCounter</td></tr>";

// row containing statistic and value
echo "<tr><td> NO STORY linked:</td><td> $issueNoStory</td></tr>";

// row containing statistic and value
echo "<tr><td> NO EPIC linked:</td><td> $issueNoEpic</td></tr>";

// row containing statistic and value
echo "<tr><td> PRIORITY issues:</td><td> $issuePrioIssue</td></tr>";

// row containing statistic and value
echo "<tr><td> SEVERITY issues:</td><td> $issueSevIssue</td></tr>";

// row containing statistic and value
echo "<tr><td> ENVIRONMENT issues:</td><td> $issueEnvIssue</td></tr>";

// close stats table
echo "</table>";

// echo some \n for style purposes
echo"</br></br></br>";


function getPriority($issueResp){
//	given an JSON object representing an issue it will return the priority name
	return $issueResp->body->fields->priority->name;
}

function getSeverity($issueResp){
	//	given an JSON object representing an issue it will return the severity name
	return $issueResp->body->fields->customfield_11700->value;
}

function checkPriority($issueResp){
//	get issue priority
	$prioName = getPriority($issueResp);

//	list of accepted values
	$acceptedValues = array("Blocker", "Critical", "Medium", "Trivial");

//	check if our value is in the array of accepted values
//	this was done this way rather than returning the ouput of the in_array
// function to make sure we have standard returned values
	if(!in_array($prioName, $acceptedValues, false)){
		return false;
	}
	return true;
}

function hasStory($issueResp){
	$count = 0;
//	cycle trough all the links listed by issue and check if any are of type Story, Task, Improvement
	while(isset($issueResp->body->fields->issuelinks[$count])){
		$linkedTo = $issueResp->body->fields->issuelinks[$count]->outwardIssue->fields->issuetype->name;

//		if any link is found to be of the desired type we return true
		if($linkedTo == "Story" || $linkedTo == "Task" || $linkedTo == "Improvement"){
			return true;
		}
		$count++;
	}
//	no link found to be of the desired type, we return false
	return false;
}

function hasEpic($issueResp){
//	if anything is found under the Epic Link field we return true
	if(isset($issueResp->body->fields->customfield_10008)){
		return true;
	}
	return false;
}

function hasSeverity($issueResp){
//	get severity string
	$setSev = getSeverity($issueResp);

//	define accepted severities
	$severities = array("Sev1", "Sev2", "Sev3");

//	if severity set to one of the desired values we return true
	if(in_array($setSev, $severities, false)){
		return true;
	}
	return false;
}

function checkEnvironment($issueResp){
//	get sring under environment node
	$setEnv = $issueResp->body->fields->environment;
	$contains = array();
//	check if string contains the four sections we desire and push content in result array
	array_push($contains, preg_match('/Env:/', $setEnv));
	array_push($contains, preg_match('/Build:/', $setEnv));
	array_push($contains, preg_match('/Browser:/', $setEnv));
	array_push($contains, preg_match('/Mobile:/', $setEnv));

//	if result array contains at least one false we return false
	if(in_array(false, $contains, false)){
		return false;
	}
	return true;
}