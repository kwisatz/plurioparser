<?php
/**
 * Parser that uses data from a semantic wiki and outputs an
 * XML file for import into plurio.net
 * 
 * @author David Raison <david@hackerspace.lu>
 * @file picture.class.php
 * @ingroup plurioparser
 */

class Picture extends Entity {
	
	private $_values;
	
	public function __construct(){
		parent::__construct();
		$this->_values = array();
	}
	
	/**
	 * Uses _mwApiQuery()
	 * We need to get the URL for this image first. 
	 * Using the mediawiki api (which is a bit silly, really)
	 * Or the database and the config file
	 * FIXME FIXME hmm... stripping?
	 */
	private function _fetchPictureUrl( $title, $strip ) {
            $category = strtolower( $this->_values['category'] );
            $url = $this->fetchPictureInfo( $title, $category );
            return $strip ? parse_url($url,PHP_URL_PATH) : $url;
	}

	private function _getDomain() {
		global $config;
		$url = parse_url( $config['media.path'] );
		return $url['scheme'] . '://' . $url['host'];
	}

	// Verifiy that an image has more than 1150 px in either width or height
	// Not really used right now.
	private function _isHighres( $title ) {
		$file = $this->_fetchPictureUrl( $title, false );
		$res = getimagesize($file);
		return ( ($res[0]/300 > 3.5) || ($res[1]/300 > 3.5) );
	}
	
	public function __set( $name, $value ){
		$this->_values[$name] = $value;
	}

	public function addTo( &$parent ) {
		if ( empty($this->_values['name'] ) ) 
			throw new Exception( "FATAL ERROR: No organisation logo set in your config?\n" );

		$picUrl = $this->_fetchPictureUrl($this->_values['name'], true);
		$picture = $parent->addChild('picture');
		$picture->addAttribute('pictureType','extern');
		$picture->addChild('domain', $this->_getDomain() );
		$picture->addChild('path',$picUrl);
		$picture->addChild('picturePosition', $this->_values['position']);
		$picture->addChild('pictureName', $this->_getPictureName( $this->_values['name'] ) );
		$picture->addChild('pictureAltText', $this->_values['label']);
		$picture->addChild('pictureDescription','Copyright by their respective owners');
	}

	private function _getPictureName( $value ) {
		if ( strpos( $value, ':' ) ) {
			return substr( $value, strpos( $value ,':') + 1, -4);	// removes File: from wiki Files FIXME
		} else return substr( $value, 0, -4 );
	}
}
