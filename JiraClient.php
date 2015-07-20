<?php
/**
 * Created by PhpStorm.
 * User: optaros
 * Date: 7/9/15
 * Time: 2:58 PM
 */

namespace Httpful;
include_once __DIR__."/vendor/nategood/httpful/src/Httpful/Request.php";
include_once __DIR__."/vendor/nategood/httpful/src/Httpful/Http.php";
include_once __DIR__."/vendor/nategood/httpful/src/Httpful/Bootstrap.php";


class JiraClient {


	private $username;
	private $pass;
	private $baseURL;


	function __construct() {
		$ini_array = parse_ini_file("jira.properties");

		$this->username = $ini_array['user'];
		$this->pass = $ini_array['pass'];
		$this->baseURL =$ini_array['baseURL'];

	}

	function runQuery($params){
//		build url
		$uri = $this->baseURL.'/rest/api/2/search?'.http_build_query($params);

		print_r($uri);

//		request query
		$response = Request::get($uri)->authenticateWithBasic($this->username, $this->pass)->send()->body;

//		return response body
		return $response;
	}

	function getIssue($issueId){
//		build url
		$uri = $this->baseURL.'/rest/api/2/issue/'.$issueId;

//		request issue
		$response = Request::get($uri)->authenticateWithBasic($this->username, $this->pass)->send()->body;

//		return response body
		return $response;
	}

	/*function relies on the agile board to provide active sprint, takes as param the agile board id*/
	function getCurrentSprint($boardID){
//		create URL based in the agile board ID
		$sprintUrl = $this->baseURL."/rest/greenhopper/latest/sprintquery/".$boardID;

//		get all list sprints
		$sprints =  Request::get($sprintUrl)->authenticateWithBasic($this->username, $this->pass)->send()->body->sprints;

//		find the active sprint that has in his name the word "Sprint"
//		the second verification is specific to Newpig, it can be removed for other projects
		$currentSprintId = null;
		foreach ($sprints as $sprint){
			if($sprint->state == "ACTIVE" && strpos($sprint->name,'Sprint') !== false){
				$currentSprintId = $sprint->id;
			}
		}
		return $currentSprintId;
	}






}