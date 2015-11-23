<?php
/*
 * AdobeConnect 9 api client
 * @see https://github.com/sc0rp10/AdobeConnect-php-api-client
 * @see http://help.adobe.com/en_US/connect/9.0/webservices/index.html
 * @version 0.1a
 *
 * Copyright 2012, sc0rp10
 * https://weblab.pro
 *
 * Licensed under the MIT license:
 * http://www.opensource.org/licenses/MIT
 *
 */

/**
 * Class AdobeConnectClient
 */
class AdobeConnectClient {
	const PERMISSION_VIEW = 'view';
	const PERMISSION_HOST = 'host';

	protected $curl;
	protected $is_authorized = false;
	protected $breezecookie = null;
	protected $login;
	protected $password;
	protected $root_folder;
	protected $template_folder;
	protected $host;

	/**
	 * @param $host
	 * @param $login
	 * @param $password
	 * @param $root_folder
	 * @param $template_folder
	 */
	public function __construct ($host, $login, $password, $root_folder = null, $template_folder = null) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_REFERER, $host);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$this->curl = $ch;

		$this->login = $login;
		$this->password = $password;
		$this->root_folder = $root_folder;
		$this->template_folder = $template_folder;
		$this->host = $host;
	}

	/**
	 *
	 * @param null $login
	 * @param null $password
	 *
	 * @return AdobeConnectClient
	 */
	public function makeAuth ($login = null, $password = null) {
		$this->login = $login ?: $this->login;
		$this->password = $password ?: $this->password;

		$result = null;

		if (!$this->breezecookie) {
			try {
				$this->makeRequest('login', [
					'login' => $this->login,
					'password' => $this->password
				]);
			} catch (Exception $e) {
				$e = new Exception(sprintf('Cannot auth with credentials: [%s:%s@%s]', $this->login, $this->password, $this->host), 0, $e);
				$e->setHost($this->host);
				$e->setLogin($this->login);
				$e->setPassword($this->password);

				throw $e;
			}
		};

		$this->is_authorized = true;

		return $this;
	}

	/**
	 * get common info about current user
	 *
	 * @return array
	 */
	public function getCommonInfo () {
		return $this->makeRequest('common-info');
	}

	/**
	 * create user
	 *
	 * @param string $email
	 * @param string $password
	 * @param string $name
	 * @param string $type
	 *
	 * @return array
	 */
	public function createUser ($email, $password, $name, $type = 'user') {
		$result = $this->makeRequest('principal-update', [
			'name' => $name,
			'email' => $email,
			'password' => $password,
			'type' => $type,
			'has-children' => 0,
		]);

		return $result['principal']['@attributes']['principal-id'];
	}

	/**
	 * @return null
	 */
	public function getBreezeCookie () {
		return $this->breezecookie;
	}


	/**
	 * @param string $email
	 * @param bool $only_id
	 *
	 * @return mixed
	 *
	 * @throws Exception
	 *
	 */
	public function getUserByEmail ($email, $only_id = false) {
		$result = $this->makeRequest('principal-list', [
			'filter-email' => $email
		]);

		if (empty($result['principal-list'])) {
			throw new Exception(sprintf('Cannot find user [%s]', $email));
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
	 * @param array $data
	 *
	 * @return mixed
	 */
	public function updateUser ($email, array $data = []) {
		$principal_id = $this->getUserByEmail($email, true);
		$data['principal-id'] = $principal_id;

		return $this->makeRequest('principal-update', $data);
	}

	/**
	 * get all users list
	 *
	 * @return array
	 */
	public function getUsersList () {
		$users = $this->makeRequest('principal-list');
		$result = [];

		foreach ($users['principal-list']['principal'] as $key => $value) {
			$result[$key] = $value['@attributes'] + $value;
		};

		return $result;
	}

	/**
	 * get all meetings
	 *
	 * @return array
	 */
	public function getAllMeetings () {
		return $this->makeRequest('report-my-meetings');
	}

	/**
	 * get shortcuts
	 *
	 * @return array
	 */
	public function getShortcuts () {
		return $this->makeRequest('sco-shortcuts')['shortcuts']['sco'];
	}

	/**
	 * get shortcuts
	 *
	 * @param $sco_id
	 *
	 * @return array
	 */
	public function getScoInfo ($sco_id) {
		return $this->makeRequest('sco-info', ['sco-id' => $sco_id]);
	}

	/**
	 * create meeting-folder
	 *
	 * @param string $name
	 * @param string $url
	 *
	 * @return array
	 */
	public function createFolder ($name, $url) {
		$result = $this->makeRequest('sco-update', [
			'type' => 'folder',
			'name' => $name,
			'folder-id' => $this->root_folder,
			'depth' => 1,
			'url-path' => $url
		]);

		return $result['sco']['@attributes']['sco-id'];
	}

	/**
	 *
	 * @return mixed
	 */
	public function getTemplates () {
		return $this->makeRequest('sco-contents', [
			'sco-id' => $this->template_folder
		])['scos']['sco'];
	}

	/**
	 * create meeting
	 *
	 * @param int $folder_id
	 * @param string $name
	 * @param DateTime $date_begin
	 * @param DateTime $date_end
	 * @param string $url
	 * @param null $template_sco_id
	 *
	 * @return int
	 */
	public function createMeeting (
		$folder_id,
		$name,
		DateTime $date_begin,
		DateTime $date_end,
		$url,
		$template_sco_id = null
	) {
		$data = [
			'type' => 'meeting',
			'name' => $name,
			'folder-id' => $folder_id,
			'date-begin' => $date_begin->format(\DateTime::ISO8601),
			'date-end' => $date_end->format(DateTime::ISO8601),
			'url-path' => $url
		];

		if ($template_sco_id) {
			$data['source-sco-id'] = $template_sco_id;
		}
		$result = $this->makeRequest('sco-update', $data);

		return $result['sco']['@attributes']['sco-id'];
	}

	/**
	 * get info about meeting
	 * @param $meeting_id
	 *
	 * @return array
	 */
	public function getMeetingInfo ($meeting_id) {
		$result = $this->makeRequest('sco-info', [
			'sco-id' => $meeting_id
		]);

		return $result['sco'];
	}


	/**
	 * get meeting archives
	 *
	 * @param int $meeting_id
	 * @param DateTime $from
	 * @param DateTime $to
	 *
	 * @return array
	 */
	public function getMeetingArchive (
		$meeting_id,
		DateTime $from = null,
		DateTime $to = null
	) {
		$request = [
			'sco-id' => $meeting_id,
			'filter-icon' => 'archive'
		];

		if ($from) {
			$request['filter-gte-date-end'] = $from->format(DateTime::ISO8601);
		}

		if ($to) {
			$request['filter-lt-date-end'] = $to->format(DateTime::ISO8601);
		}

		return $this->makeRequest('sco-contents', $request);
	}

	/**
	 * @return mixed
	 */
	public function getTemplateFolder () {
		return $this->template_folder;
	}

	/**
	 * @return mixed
	 */
	public function getRootFolder () {
		return $this->root_folder;
	}

	/**
	 * @return mixed
	 */
	public function getHost () {
		return $this->host;
	}

	/**
	 * @param $principal_id
	 * @param $group_id
	 * @return mixed
	 */
	public function addUserToGroup ($principal_id, $group_id) {
		return $this->makeRequest('group-membership-update', [
			'group-id' => $group_id,
			'principal-id' => $principal_id,
			'is-member' => true,
		]);
	}
	/**
	 * @param $principal_id
	 * @param $group_id
	 * @return mixed
	 */
	public function removeUserFromGroup ($principal_id, $group_id) {
		return $this->makeRequest('group-membership-update', [
			'group-id' => $group_id,
			'principal-id' => $principal_id,
			'is-member' => false,
		]);
	}

	/**
	 * invite user to meeting
	 *
	 * @param int $meeting_id
	 * @param string $email
	 *
	 * @param string $permission
	 * @return mixed
	 */
	public function inviteUserToMeeting ($meeting_id, $email, $permission = self::PERMISSION_VIEW) {
		$user_id = $this->getUserByEmail($email, true);

		$result = $this->makeRequest('permissions-update', [
			'principal-id' => $user_id,
			'acl-id' => $meeting_id,
			'permission-id' => $permission,
		]);

		return $result;
	}

	/**
	 * invite user to meeting
	 *
	 * @param int $meeting_id
	 * @param $group_id
	 * @param string $permission
	 *
	 * @return mixed
	 */
	public function inviteGroupToMeeting (
		$meeting_id,
		$group_id,
		$permission = self::PERMISSION_VIEW
	) {
		$result = $this->makeRequest('permissions-update', [
			'principal-id' => $group_id,
			'acl-id' => $meeting_id,
			'permission-id' => $permission
		]);

		return $result;
	}

	/**
	 *
	 */
	public function __destruct () {
		curl_close($this->curl);
	}

	/**
	 * @param       $action
	 * @param array $params
	 * @return mixed
	 * @throws Exception
	 * @throws Exception
	 */
	protected function makeRequest ($action, array $params = []) {
		if ($this->breezecookie) {
			$params['session'] = $this->breezecookie;
		};

		$url = $this->host;
		$url .= 'api/xml?action=' . $action;
		$url .= '&' . http_build_query($params);

		curl_setopt($this->curl, CURLOPT_URL, $url);
		curl_setopt($this->curl, CURLOPT_HEADER, 1);

		$response = curl_exec($this->curl);

		if ($response === false) {
			throw new Exception('Coulnd\'t perform the action: ' . $action . ' with [empty result]; ' . $url);
		}

		preg_match('/BREEZESESSION=(\w+);/', $response, $m);

		if (isset($m[1])) {
			$this->breezecookie = $m[1];
		}

		$temp = explode("\r\n\r\n", $response);

		$result = '';

		if (isset($temp[1])) {
			$result = $temp[1];
		}

		libxml_use_internal_errors();
		$xml = simplexml_load_string($result);
		$errors = libxml_get_errors();

		$json = json_encode($xml);
		$data = json_decode($json, true);

		if (
			count($errors) > 0
				||
			!isset($data['status']['@attributes']['code'])
				||
			$data['status']['@attributes']['code'] !== 'ok'
		) {
			throw new Exception('Coulnd\'t perform the action: ' . $action . ' with [ ' . var_export(trim($response), true) . ' ]; ' . $url);
		};

		return $data;
	}

}
