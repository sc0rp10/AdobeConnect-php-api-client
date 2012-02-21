<?php
class AdobeConnectClient {
	const USERNAME = '';
	const PASSWORD = '';
	const BASE_DOMAIN = 'https://example.adobeconnect.com/api/'; //вынести в конфиги
	const ROOT_FOLDER_ID = 0; //ИД корневой папки
	private $cookie;
	private $curl;
	private $is_authorized = false;
	private $is_debug = false;

	public function __construct () {
		$this->cookie = sfConfig::get('sf_cache_dir'). DIRECTORY_SEPARATOR .'cookie_'.time().'.txt'; // вынести в параметр
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_REFERER, 'http://profile.weblab.pro/');
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$this->curl = $ch;
		$this->makeAuth();
	}
	public function enableDebug() {
		$this->is_debug = true;
		return $this;
	}
	public function makeAuth() {
		$result = $this->makeRequest('login', 
			array(
				'login' => self::USERNAME, 
				'password' => self::PASSWORD
			)
		);
		$this->is_authorized = true;
		return $this;
	}
	public function getCommonInfo() {
		return $this->makeRequest('common-info');
	}
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
	public function getInfoAboutMe() {
		$result = $this->getCommonInfo();
		$r = array();
		$r['login'] = $result['common']['user']['login'];
		$r['name'] = $result['common']['user']['name'];
		return $result['common']['user']['@attributes'] + $r;
	}
	public function getUserByEmail($email, $only_id = false) {
		$result = $this->makeRequest('principal-list', array('filter-email' => $email));
		if (empty($result['principal-list'])) {
			throw new Exception('Пользователь не найден');
		}
		if ($only_id) {
			return $result['principal-list']['principal']['@attributes']['principal-id'];
		}
		return $result;
	}
	public function updateUser($email, array $data = array()) {
		$principal_id = $this->getUserByEmail($email, true);
		$data['principal-id'] = $principal_id;
		return $this->makeRequest('principal-update', $data);
	}
	public function getUsersList() {
		$users = $this->makeRequest('principal-list');
		$result = array();
		foreach($users['principal-list']['principal'] as $key => $value) {
			$result[$key] = $value['@attributes'] + $value;
		};
		unset($result[$key]['@attributes']);
		return $result;
	}
	public function getAllMeetings() {
		$this->makeRequest('report-my-meetings');
	}
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
	public function invitePeopleToMeeting($meeting_id, $email) {
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
	private function makeRequest($action, array $params = array()) {
		$url = self::BASE_DOMAIN;
		$url .= 'xml?action='.$action;
		$url .= '&'.http_build_query($params);
		curl_setopt($this->curl, CURLOPT_URL, $url);
		$result = curl_exec($this->curl);
		$xml = simplexml_load_string($result);

		$json = json_encode($data);
		$data = json_decode($json, TRUE); // nice hack!
		
		if (!isset($data['status']['@attributes']['code']) || $data['status']['@attributes']['code'] !== 'ok') {
			throw new Exception('Coulnd\'t perform the action: '.$action);
		}

		return $data;
	}
}