<?php
/**
 * Class that uses data from any source and outputs an
 * XML file for import into plurio.net
 * 
 * @author David Raison <david@raison.lu>
 * @file plurio_xml_builder.class.php
 * @ingroup plurioparser
 * @version 1.1
 */

class PlurioXMLBuilder {

	private $_data;	// store data from source
	
	//private $_plurio_categories = 'categoriesAgendaEvents.xml';
	private $_plurio_categories = 'http://www.plurio.org/XML/listings/categoriesXML.php';	// too slow?
	private $_plurio_cats;
	
	// guide elements
	private $_orgs;
	private $_buildings;				// xml object to reference the buildings section

	public function __construct( $data ){
		$this->_data = $data;
		//$this->_plurio_cats = simplexml_load_file($this->_plurio_categories);
	}

	public function send_headers(){
		header('Content-Type: text/xml; charset=UTF-8');
		// force download?
		//header('Content-Disposition: attachment; filename="syn2cat.xml"');
	}

	/**
	 * Creates the feed and populates it from our source
	 * BuildOrder: Guide, then Agenda
	 * @return the finished XML string
	 */
	public function createFeed(){
		// apparently, simplexml has no write support for namespaces 
		// that's why we're using DOM at the start
		$xml = '<?xml version="1.0"?>'
			.'<plurio xmlns:pt="plurioTypes" '
			.'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
			.'xsi:noNamespaceSchemaLocation="plurio.xsd" '
			.'action="insert"></plurio>';

		$plurio = simplexml_load_string($xml);

		// append guide section
		$this->_createGuide( $plurio );

		// append agenda section
		$this->_createAgenda( $plurio );

		// simplexml is unable to properly format its output
		$dom = new DOMDocument('1.0');
		// one needs to import the simpleXML object first,
		$plurio_dom = dom_import_simplexml( $plurio );

		// then import it into the current DOM,
		$plurio_dom = $dom->importNode( $plurio_dom, true );

		// then append it
		$dom->appendChild($plurio_dom);
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		return $dom->saveXML();
	}

	/** 
	 * Here we create the bare structure for the guide.
	 * It's later populated by the methods called by _createAgenda()
	 * @var $plurio the main xml object to be populated
	 * @return void
	 */
	private function _createGuide( &$plurio ){

		// don't do anything if no events are present (to validate plurio.xsd)
		if( empty($this->_data->items) ) return;

		// else, add a guide section
		$guide = $plurio->addChild('guide');

		/********************************************************
		 * Guide >> Building					*
		 * Creating and referencing a buildings section for later use
		 * Actual buildings will be added later on in the agenda
		 * through the building object, if we need them.
		 ********************************************************/
                
                // Another UGLY MNHN hack
                foreach($this->_data->items as $item ){
                    $locid = !empty( $item->has_location_id[0] ) ? $item->has_location_id[0] : $item->has_location[0];
                    if( $locid != "2" ) {
                        $this->_buildings = $guide->addChild('guideBuildings');
                        break;
                    }
                }
		
		/********************************************************
		 * Guide >> Organisation				*
		 ********************************************************/
                // MNHN hack FIXME (we'll only be using existing ids)
		//$this->_orgs = $guide->addChild('guideOrganisations');

		return true;
	}

	/** 
	 * Here we create the bare structure for the agenda, then populate it.
	 * @var $plurio the main xml object to be populated
	 * @return void
	 */
	private function _createAgenda( &$plurio ){	

		// don't do anything if no events are present (to validate plurio.xsd)
		if( empty($this->_data->items) ) return;

		// else create agenda node
		$agenda = $plurio->addChild( 'agenda' );

		/** Loop through our data, identifying properties and creating an xml object.
		 *  We pass it as a reference to the buildings and organisations nodes in order
		 *  to be able to add buildings to the previously created guide if necessary */
		foreach($this->_data->items as $item) {
			// each run adds another event child to the agenda element
			$event = new Event( $agenda, $this->_buildings, $this->_orgs );
			$event->createNewFromItem( $item );
		}
	}
}
?>
