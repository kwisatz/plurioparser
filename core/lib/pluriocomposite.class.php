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

namespace Core\Lib;

class PlurioComposite {

	private $_eventlist;        // store data from source
	
	//private $_plurio_categories = 'categoriesAgendaEvents.xml';
	private $_plurio_categories = 'http://www.plurio.org/XML/listings/categoriesXML.php';	// too slow?
	private $_plurio_cats;
	
	// guide elements
	private $_orgs;
	private $_buildings;				// xml object to reference the buildings section

	public function __construct( $data ){
		$this->_eventlist = $data;
	}

	public function send_headers(){
		header('Content-Type: text/xml; charset=UTF-8');
	}

    /**
     * We create a plurio node and then 
     * Events are only appended to the agenda once they've been succesfully created
     */
       
        
	/**
	 * Creates the feed and populates it from our source
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

		//$plurio = simplexml_load_string($xml);

                $dom = new DOMDocument('1.0', 'utf-8');

                $dom->loadXML( $xml);
                
		// append guide section
		//$this->_createGuide( $plurio );
                $guide = $this->_createGuide();

		// append agenda section
		//$this->_createAgenda( $plurio );
                $agenda = $this->_createAgenda();

		// simplexml is unable to properly format its output
		$dom = new DOMDocument('1.0');
		// 1. one needs to import the simpleXML object first,
		$plurio_dom = dom_import_simplexml( $plurio );

		// 2. then import it into the current DOM.. well yeah
		$plurio_dom = $dom->importNode( $plurio_dom, true );

		// 3. then append it.. doesn't make sense? No, not really.
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
		$this->_buildings = $guide->addChild('guideBuildings');
		
		/********************************************************
		 * Guide >> Organisation				*
		 ********************************************************/
		$this->_orgs = $guide->addChild('guideOrganisations');

		return true;
	}

	/** 
	 * Here we create the bare structure for the agenda, then populate it.
	 * @var $plurio the main xml object to be populated
	 * @return void
	 */
	private function _createAgenda( &$plurio ){	

		// don't do anything if no events are present (to validate plurio.xsd)
		if( empty($this->_eventlist->items) ) return;

		// else create agenda node
		$agenda = $plurio->addChild( 'agenda' );

		/** Loop through our data, identifying properties and creating an xml object.
		 *  We pass it as a reference to the buildings and organisations nodes in order
		 *  to be able to add buildings to the previously created guide if necessary */
		foreach($this->_eventlist->items as $event ) {
			// each run adds another event child to the agenda element
			//$entry = new EventComponent( $agenda, $this->_buildings, $this->_orgs );
			//$entry->createNewFromItem( $item );
                        $entry = new EventComponent( $this, $event );
                        $agenda->appendChild( $entry );
		}$dom_sxe = dom_import_simplexml($sxe);
if (!$dom_sxe) {
    echo 'Error while converting XML';
    exit;
}

$dom = new DOMDocument('1.0');
$dom_sxe = $dom->importNode($dom_sxe, true);
$dom_sxe = $dom->appendChild($dom_sxe);
                
                
                
	}
}
?>
