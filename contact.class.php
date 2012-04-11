<?php
/**
 * Parser that uses data from a semantic wiki and outputs an
 * XML file for import into plurio.net
 * 
 * @author David Raison <david@hackerspace.lu>
 * @file contact.class.php
 * @ingroup plurioparser
 */

class Contact {

	private $_areaCode = '+352';			// phone number area code
	private $_phoneNumber = '691442324';		// contact phone number
	private $_url = 'http://www.hackerspace.lu';	// store custom url
	private $_emailAddress = 'info@hackerspace.lu';	// store custom email address
	

	public function addTo( $entity, $caller ){
		$childname = 'contact' . ucfirst( get_class( $caller ) );
		$contact = $entity->addChild( $childname );
		$this->_addContactPhoneNumbers( $contact );	// 1
		$this->_addWebsite( $contact );			// 2 order is important!
		$this->_addContactEmail( $contact );		// 3
	}
	
	/**
	 * Allows to set a specific url
	 */
	public function setWebsiteUrl( $url ){
		// we should probably do regex here first 
		// we should also test for arrays like this in address.class
		$this->_url = is_array( $url ) ? $url[0] : $url;
	}
	
	public function setEmailAddress( $address ){
		// we should probably do regex here first
		$this->_emailAddress = is_array( $address ) ? $address[0] : $address;
	}
	
	public function setPhoneNumber( $phoneNumber ){
		$phoneNumber = is_array( $phoneNumber ) ? $phoneNumber[0] : $phoneNumber;
		if( preg_match( '/^(\+\d{1,3})\.(\d+)$/', $phoneNumber, $matches) ) {
			$this->_areaCode = $matches[1];
			$this->_phoneNumber = $matches[2];
		} else 	$this->_phoneNumber = $phoneNumber;
	}

	/**
	 * Used by the event class for ticketing
	 */
	public function addPhoneNumber( $entity ) {
		$this->_addSingleNumber( $entity );
	}
	
	private function _addContactPhoneNumbers( $contact ){
		if( $this->_phoneNumber != '' ) {
			$phoneNumbers = $contact->addChild( 'phoneNumbers' );
			$this->_addSingleNumber( $phoneNumbers );
		}
	}

	private function _addSingleNumber( $parent ) {
		$phoneNumber = $parent->addChild( 'phoneNumber' );
		$phoneNumber->addAttribute('phoneType','mobile');
		$phoneNumber->addChild('phoneNumberFunctionId','pn08');	// = Info
		$phoneNumber->addChild('phoneNumberAreaCode', $this->_areaCode );
		$phoneNumber->addChild('mainNumber', $this->_phoneNumber);
	}
	
	private function _addContactEmail( $contact ){
		$email = $contact->addChild('emailAdresses')->addChild('emailAdress');	// sic
		$email->addChild('emailAdressUrl',$this->_emailAddress);	// sic
		$email->addChild('emailAdressFunctionId','ea01');	// = Info (sic)

	}

	private function _addWebsite( $contact ) {
		$contact->addChild( 'websites' )->addChild( 'website', $this->_url);
	}
		
}
