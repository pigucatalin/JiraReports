<?php

namespace Httpful;
include_once "./vendor/nategood/httpful/src/Httpful/Request.php";
include_once "./vendor/nategood/httpful/src/Httpful/Http.php";
include_once "./vendor/nategood/httpful/src/Httpful/Bootstrap.php";





$ini_array = parse_ini_file("jira.properties");
$username = $ini_array['user'];
$pass = $ini_array['pass'];
$baseURL=$ini_array['baseURL'];

$sprintUrl = $baseURL."/rest/greenhopper/latest/sprintquery/44";

$sprints =  Request::get($sprintUrl)->authenticateWithBasic($username, $pass)->send()->body->sprints;


$currentSprintId = null;
foreach ($sprints as $sprint){
	if($sprint->state == "ACTIVE" && strpos($sprint->name,'Sprint') !== false){
		$currentSprintId = $sprint->id;
	}
}

echo $currentSprintId;