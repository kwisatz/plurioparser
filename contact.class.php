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
	
	private $_phoneNumber = '691442324';			// contact phone number
	private $_url = 'http://www.hackerspace.lu';	// store custom url
	private $_emailAddress = 'info@hackerspace.lu';	// store custom email address
	
	public function __construct() {
	}

	public function addTo( &$entity ){
		$childname = 'contact' . ucfirst( get_class( $entity ) );
		$contact = $entity->addChild( $childname );
		$this->_addContactPhoneNumber( $contact );
		$contact->addChild('websites')->addChild('website', $this->_url);
		$this->_addContactEmail($contact);
	}
	
	/**
	 * Allows to set a specific url
	 */
	public function setWebsiteUrl( $url ){
		// we should probably do regex here first
		$this->_url = $url;
	}
	
	public function setEmailAddress( $address ){
		// we should probably do regex here first
		$this->_emailAddress = $address;
	}
	
	public function setPhoneNumber( $phoneNumber ){
		// we should probably do regex here first
		$this->_phoneNumber = $phoneNumber;
	}
	
	private function _addContactPhoneNumber( &$contact ){
		if($this->_phoneNumber != ''){
			$phoneNumber = $contact->addChild('phoneNumbers')->addChild('phoneNumber');
			$phoneNumber->addAttribute('phoneType','mobile');
			$phoneNumber->addChild('phoneNumberFunctionId','pn08');	// = Info
			$phoneNumber->addChild('phoneNumberAreaCode','+352');
			$phoneNumber->addChild('mainNumber', $this->_phoneNumber);
		}
	}
	
	private function _addContactEmail( &$contact ){
		$email = $contact->addChild('emailAdresses')->addChild('emailAdress');	// sic
		$email->addChild('emailAdressUrl',$this->_emailAddress);	// sic
		$email->addChild('emailAdressFunctionId','ea01');	// = Info (sic)

	}

}
