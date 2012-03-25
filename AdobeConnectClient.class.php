<?php
/*
 * AdobeConnect 8 api client
 * @see https://github.com/sc0rp10/AdobeConnect-php-api-client
 * @see http://help.adobe.com/en_US/connect/8.0/webservices/index.html
 * @version 0.1a
 *
 * Copyright 2012, sc0rp10
 * https://weblab.pro
 *
 * Licensed under the MIT license:
 * http://www.opensource.org/licenses/MIT
 *
 */

class AdobeConnectClient {
	/**
	 * adobe connect username
	 */
	const USERNAME = '';

	/**
	 * @const
	 * adobe connect password
	 */
	const PASSWORD = '';

	/**
	 * @const
	 * your personally api URL
	 */
	const BASE_DOMAIN = 'https://example.adobeconnect.com/api/';

	/**
	 * @const
	 * your personally root-folder id
	 * @see http://forums.adobe.com/message/2620180#2620180
	 */
	const ROOT_FOLDER_ID = 0; //root folder id

	/**
	 * @var string filepath to cookie-jar file
	 */
	private $cookie;

	/**
	 * @var resource
	 */
	private $curl;

	/**
	 * @var bool
	 */
	private $is_authorized = false;

	/**
	 *
	 */
	public function __construct () {
		$this->cookie = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cookie_'.time().'.txt'; // вынести в параметр
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_REFERER, self::BASE_DOMAIN);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$this->curl = $ch;
		$this->makeAuth();
	}

	/**
	 * make auth-request with stored username and password
	 *
	 * @return AdobeConnectClient
	 */
	public function makeAuth() {
		$this->makeRequest('login',
			array(
				'login'    => self::USERNAME,
				'password' => self::PASSWORD
			)
		);
		$this->is_authorized = true;
		return $this;
	}

	/**
	 * get common info about current user
	 *
	 * @return array
	 */
	public function getCommonInfo() {
		return $this->makeRequest('common-info');
	}

	/**
	 * create user
	 *
	 * @param string $email
	 * @param string $password
	 * @param string $first_name
	 * @param string $last_name
	 * @param string $type
	 *
	 * @return array
	 */
	public function createUser ($email, $password, $first_name, $last_name, $type = 'user') {
		$result = $this->makeRequest('principal-update', 
			array(
				'first-name'   => $first_name,
				'last-name'    => $last_name,
				'email'        => $email,
				'password'     => $password,
				'type'         => $type,
				'has-children' => 0
			)
		);
		return $result;
	}


	/**
	 * @param string $email
	 * @param bool   $only_id
	 *
	 * @return mixed
	 *
	 * @throws Exception
	 *
	 */
	public function getUserByEmail($email, $only_id = false) {
		$result = $this->makeRequest('principal-list',
			array(
				'filter-email' => $email
			)
		);
		if (empty($result['principal-list'])) {
			throw new Exception('Cannot find user');
		}
		if ($only_id) {
			return $result['principal-list']['principal']['@attributes']['principal-id'];
		}
		return $result;
	}

	/**
	 * update user fields
	 *
	 * @param string $email
	 * @param array  $data
	 *
	 * @return mixed
	 */
	public function updateUser($email, array $data = array()) {
		$principal_id = $this->getUserByEmail($email, true);
		$data['principal-id'] = $principal_id;
		return $this->makeRequest('principal-update', $data);
	}

	/**
	 * get all users list
	 *
	 * @return array
	 */
	public function getUsersList() {
		$users = $this->makeRequest('principal-list');
		$result = array();
		foreach($users['principal-list']['principal'] as $key => $value) {
			$result[$key] = $value['@attributes'] + $value;
		};
		unset($result[$key]['@attributes']);
		return $result;
	}

	/**
	 * get all meetings
	 *
	 * @return array
	 */public function getAllMeetings() {
		return $this->makeRequest('report-my-meetings');
	}

	/**
	 * create meeting-folder
	 *
	 * @param string $name
	 * @param string $url
	 *
	 * @return array
	 */
	public function createFolder($name, $url) {
		$result = $this->makeRequest('sco-update', 
			array(
				'type'       => 'folder',
				'name'       => $name,
				'folder-id'  => self::FOLDER_ID,
				'depth'      => 1,
				'url-path'   => $url
			)
		);
		return $result['sco']['@attributes']['sco-id'];
	}

	/**
	 * create meeting
	 *
	 * @param int    $folder_id
	 * @param string $name
	 * @param string $date_begin
	 * @param string $date_end
	 * @param string $url
	 *
	 * @return array
	 */
	public function createMeeting($folder_id, $name, $date_begin, $date_end, $url) {
		$result = $this->makeRequest('sco-update', 
			array(
				'type'       => 'meeting',
				'name'       => $name,
				'folder-id'  => $folder_id,
				'date-begin' => $date_begin,
				'date-end'   => $date_end,
				'url-path'   => $url
			)
		);
		return $result['sco']['@attributes']['sco-id'];
	}

	/**
	 * invite user to meeting
	 *
	 * @param int    $meeting_id
	 * @param string $email
	 *
	 * @return mixed
	 */
	public function inviteUserToMeeting($meeting_id, $email) {
		$user_id = $this->getUserByEmail($email, true);

		$result = $this->makeRequest('permissions-update', 
			array(
				'principal-id'  => $user_id,
				'acl-id'        => $meeting_id,
				'permission-id' => 'view'
			)
		);
		return $result;
}

	public function __destruct() {
		@curl_close($this->curl);
	}

	/**
	 * @param       $action
	 * @param array $params
	 * @return mixed
	 * @throws Exception
	 */private function makeRequest($action, array $params = array()) {
		$url = self::BASE_DOMAIN;
		$url .= 'xml?action='.$action;
		$url .= '&'.http_build_query($params);
		curl_setopt($this->curl, CURLOPT_URL, $url);
		$result = curl_exec($this->curl);
		$xml = simplexml_load_string($result);

		$json = json_encode($xml);
		$data = json_decode($json, TRUE); // nice hack!
		
		if (!isset($data['status']['@attributes']['code']) || $data['status']['@attributes']['code'] !== 'ok') {
			throw new Exception('Coulnd\'t perform the action: '.$action);
		}

		return $data;
	}
}