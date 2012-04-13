<?php
/**
 * Parser that uses data from a semantic wiki and outputs an
 * XML file for import into plurio.net
 * 
 * @author David Raison <david@hackerspace.lu>
 * @file parser.class.php
 * @ingroup plurioparser
 */

class Parser {

	private $_data;	// store data after json decode
	
	//private $_plurio_categories = 'categoriesAgendaEvents.xml';
	private $_plurio_categories = 'http://www.plurio.org/XML/listings/categoriesXML.php';	// too slow
	private $_plurio_cats;
	
	// guide elements
	private $_orgs;
	private $_buildings;				// xml object to reference the buildings section

	private $_bldLogo = 'File:Syn2wall-2.jpg';	// image illustrating building

	private $_orgaId = '225223';			// id
	private $_orgaLogo = 'File:Weareinnovative.jpg';// image illustrating association

	public function __construct($input){
		$this->_data = $this->readJsonData( $input );
		//$this->_plurio_cats = simplexml_load_file($this->_plurio_categories);
	}

	public function send_headers(){
		header('Content-Type: text/xml; charset=UTF-8');
		// force download?
		//header('Content-Disposition: attachment; filename="syn2cat.xml"');
	}

	/*
	 * Tidying and decoding json data
	 */
	public static function readJsonData( $input ){
		return json_decode(str_replace(array("\n","\t"),'',file_get_contents( $input )));
	}

	public function createFeed(){
		// apparently, simplexml has no write support for namespaces 
		// (or I couldn't really find any)
		$xml = '<?xml version="1.0"?>'
			.'<plurio xmlns:pt="plurioTypes" '
			.'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
			.'xsi:noNamespaceSchemaLocation="plurio.xsd" '
			.'action="insert"></plurio>';

		$plurio = simplexml_load_string($xml);

		// add guide section
		$this->_createGuide( $plurio );

		// add agenda section
		$this->_createAgenda( $plurio );

		// simplexml is unable to properly format its output
		$dom = new DOMDocument('1.0');
		// one needs to import the simpleXML object first,
		$plurio_dom = dom_import_simplexml( $plurio );		
		// then import it into the current DOM,
		$plurio_dom = $dom->importNode( $plurio_dom, true );	
		$dom->appendChild($plurio_dom);				// then append it
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		return $dom->saveXML();
	}

	/** 
	 * Here we create the bare structure for the guide.
	 * @var $plurio the main xml object
	 */
	private function _createGuide( &$plurio ){
		$guide = $plurio->addChild('guide');

		/********************************************************
		 * Guide >> Building					*
		 * Creating and referencing a buildings section for later use
		 *  Actual buildings will be added later on in the agenda
		 * through the building object, if we need them.
		 ********************************************************/
		$this->_buildings = $guide->addChild('guideBuildings');
		
		/********************************************************
		 * Guide >> Organisation				*
		 ********************************************************/
		$this->_orgs = $guide->addChild('guideOrganisations');
	}

	private function _createAgenda( &$plurio ){	
		// don't add an agenda element if no events are present (to validate plurio.xsd)
		if( empty($this->_data->items) ) return;

		// creating agenda node
		$agenda = $plurio->addChild( 'agenda' );

		/* loop through our data, identifying properties and creating an xml object
		 *  we pass it a reference to the buildings and organisations nodes in order
		 *  to be able to add buildings to the guide if necessary */
		foreach($this->_data->items as $item) {
			// each run adds another event child to the agenda element
			$event = new Event( $agenda, $this->_buildings, $this->_orgs );
			$event->createNewFromItem( $item );
		}
	}

}
?>
