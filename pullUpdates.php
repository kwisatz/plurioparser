<?php

$updater = new Updater;

class Updater {

	public function getLocalisationXML() {

	}

	private function _curlRequest() {
		$url = 'http://api.flickr.com/services/xmlrpc/';
		$ch = curl_init($url);
		 
		$fields = array(
			'lname' => urlencode($last_name),
			'fname' => urlencode($first_name),
		);

		http_build_query();
		curl_setopt($ch,CURLOPT_POST, count($fields));
		curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);

		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		 
		$response = curl_exec($ch);
		curl_close($ch);
	}
}

?>
