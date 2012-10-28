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
	 */
	private function _fetchPictureUrl( $title, $strip ) {
		$url = $this->fetchPictureInfo( $title );
		return $strip ? parse_url($url,PHP_URL_PATH) : $url;
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

	public function addTo( &$parent ){
		$picUrl = $this->_fetchPictureUrl($this->_values['name'], true);
		$picture = $parent->addChild('picture');
		$picture->addAttribute('pictureType','extern');
		$picture->addChild('domain', $this->_domain );
		$picture->addChild('path',$picUrl);
		$picture->addChild('picturePosition', $this->_values['position']);
		$picture->addChild('pictureName',
			substr($this->_values['name'], strpos($this->_values['name'] ,':') + 1, -4) );
		$picture->addChild('pictureAltText', $this->_values['label']);
		$picture->addChild('pictureDescription','Copyright by their respective owners');
	}
}
