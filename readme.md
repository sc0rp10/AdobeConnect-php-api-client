# Adobe Connect 8 API client
### Overview

Simply PHP-client.
### Requirements
* [PHP 5.3+](http://php.net/releases/5_3_0.php)
* [curl](http://php.net/manual/en/book.curl.php)
* [SimpleXML](http://php.net/manual/en/book.simplexml.php)

###Usage
All methods are throwable and you should wrap API-calls to try-catch blocks

	$client = new AdobeConnectClient();
	$acc->createUser('user@domain.tld', 'p4s$w0rD', 'Firstname', 'Lastname');
	$acc->getUserByEmail('user@domain.tld', true);
	$folder_id = $acc->createFolder('test folder', 'api_test_folder');
	$meeting_id = $acc->createMeeting(
		$folder_id, 
		'test meeting', 
		'2012-11-04T09:00', 
		'2012-11-04T11:00', 
		'api_test'
	);
	$acc->inviteUserToMeeting($meeting_id, 'sc0rp10@yandex.kz');
---
###Contacts
* <dev@weblab.pro>
* <https://github.com/sc0rp10>