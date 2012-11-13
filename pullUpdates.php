<?php

$updater = new Updater;
$updater->updateLocalisationFile();
$updater->updateCategoriesFile();

class Updater {

	private $_config;

	public function __construct() {
		$this->_config = parse_ini_file( 'config/config.ini', false );
	}

	public function updateLocalisationFile() {
		print( "Updating localisation IDs..." );
		$url = $this->_config['localisation.src'];
		$filter = $this->_config['localisation.filter'];
		$destination = $this->_config['localisation.dst'];

		$response = $this->_curlRequest( $url, $filter );
		if( $response ) {
			file_put_contents( $destination, $response );
			print( "done.\n" );
		} else print( "FAILED!\n" );
	}

	public function updateCategoriesFile() {
		print( "Updating agenda categories..." );
		$url = $this->_config['categories.src'];
		$filter = $this->_config['categories.filter'];
		$destination = $this->_config['categories.dst'];

		$response = $this->_curlRequest( $url, $filter );
		if( $response ) {
			file_put_contents( $destination, $response );
			print( "done.\n" );
		} else print( "FAILED!\n" );
	}

	private function _curlRequest( $url, $filter, $lang='de', $xml='get_as_xml' ) {
		$ch = curl_init( $url );
		 
		$fields = array(
			'filterView' => urlencode( $filter ),
			'type' => urlencode( $filter ),		// just because the plurio.net API sucks a tiny bit
			'lang' => urlencode( $lang ),
			'xml' => urlencode( $xml )
		);

		$query = http_build_query( $fields );
		curl_setopt($ch,CURLOPT_POST, count($fields) );
		curl_setopt($ch,CURLOPT_POSTFIELDS, $query );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		 
		$response = curl_exec($ch);
		curl_close($ch);

		return $response;
	}
}

?>
