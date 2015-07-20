<?php

namespace Httpful;
include_once "../vendor/nategood/httpful/src/Httpful/Request.php";
include_once "../vendor/nategood/httpful/src/Httpful/Http.php";
include_once "../vendor/nategood/httpful/src/Httpful/Bootstrap.php";


$ini_array = parse_ini_file("../jira.properties");
$username = $ini_array['user'];
$pass = $ini_array['pass'];
$baseURL=$ini_array['baseURL'];


// ----------------------------------- Functions ----------------------------------------

function getQuery($jql){
	global $username;
	global $pass;
	global $baseURL;

//	echo "Running query $jql \n";

	$uri = "$baseURL/rest/api/2/search?maxResults=1000&fields=timetracking,status&jql=".urlencode($jql);
	$response = Request::get($uri)->authenticateWithBasic($username, $pass)->send()->body;

	return $response;
}

function getIssue($url){
	global $username;
	global $pass;

//	echo "Getting issue $url \n";

	$response = Request::get($url.'?fields=timetracking,status')->authenticateWithBasic($username, $pass)->send()->body;

	return $response;
}

function getRemainingTime($issue){
	return $issue->fields->timetracking->remainingEstimateSeconds;
}

function getIssueStatus($issue){
	return $issue->fields->status->name;
}

function getOriginalEstimatedTime($issue){
	return $issue->fields->timetracking->originalEstimateSeconds;
}

function prettyPrint(){
	global $reportMap;
	global $originalEstimate;
	global $remainingEstimate;

//	echo "PRETTY PRINTTT \n";

	echo "<style> table, th, td { border: 1px solid black; border-collapse: collapse;} th, td { padding: 15px; } th{ background:#008080; }</style>\n";
	echo "<table>\n";
	echo "<tr><th>Epic ID</th><th>Epic Name</th><th>% complete</th><th>Remaining Days</th><th>Total Estimated Days</th></tr>\n";


	for($j=0; $j<count(array_keys($reportMap)); $j++){
		$epicID = array_keys($reportMap)[$j];



		$originalEstimateDays = round( (($reportMap[$epicID][$originalEstimate]/3600)/6),1, PHP_ROUND_HALF_UP);
		$remainingEstimateDays = round( (($reportMap[$epicID][$remainingEstimate]/3600)/6),1, PHP_ROUND_HALF_UP);

		if($reportMap[$epicID][$originalEstimate] >0){
			$percent = $reportMap[$epicID][$remainingEstimate]/$reportMap[$epicID][$originalEstimate];
			$percent_friendly = number_format(100 - ($percent * 100), 0 ) . '%';
		}else{
			$percent_friendly = 'N/A';
		}

		$outputLine = $epicID."\t".$percent_friendly."\t". $remainingEstimateDays."\t".$originalEstimateDays;

		$htmlLine = '<tr><td>'.str_replace("\t", '</td><td>', $outputLine).'</td></tr>'."\n";
		echo $htmlLine;


	}

	echo "</table>";



}




// ----------------------------------- Script ----------------------------------------

// -------- initialize result array
$reportMap = array();
$originalEstimate = "OriginalEstimate";
$remainingEstimate = "RemainingEstimate";


// --------  Open file containing epic ids sorted as in confluence
$myfile = fopen("epics.tsv", "r") or die("Unable to open file!");
$fileLine = trim(fgets($myfile));
while($fileLine){

	// -------- initialize report for epic
//	echo "Processing epic $fileLine \n";
	$reportMap[$fileLine] = array();

	// -------- compose JQL query
	$epic = explode("\t", $fileLine)[0];
	$jql = 'Project = e-Replatform AND ("Epic Link" in ('.$epic.') or parent in tempoEpicIssues('.$epic.')) and type not in (Bug, "Bug Subtask", "QA Task") and sprint != 156';

	// -------- gets listed issues
	$response = getQuery($jql);

	$noOfIssues = $response->total;
//	echo "Processing $noOfIssues issues for $fileLine \n";

	// -------- array containing the actual issues resulted from the query
	$issues = $response->issues;


	for ($i = 0; $i < $noOfIssues; $i++) {

		// -------- get each issue to find remaining time and original estimate
//		echo "Processing issues $i \n";
		$currentIssue = $issues[$i]; //getIssue( $issues[$i]->self);

		$currentStatus = getIssueStatus($currentIssue);

		// -------- adding original estimate to epic stats
		$reportMap[$fileLine][$originalEstimate] += getOriginalEstimatedTime($currentIssue);

		// -------- accounting for finished issues that have remaining time !=0
		// -------- if issue has one of these statuses remaining devel time is considered 0 by default
		if($currentStatus != "Closed" && $currentStatus != "Demo" && $currentStatus != "QA" && $currentStatus != "Dev Complete"){
			$reportMap[$fileLine][$remainingEstimate] += getRemainingTime($currentIssue);
		}

	}

	// -------- moving on to the next epic
	$fileLine = trim(fgets($myfile));


}
fclose($myfile);

// -------- printing report in friendly format
prettyPrint();