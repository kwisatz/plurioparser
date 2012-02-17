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
		// apparently, simplexml has no write support for namespaces (or I couldn't really find any)
		$xml = '<?xml version="1.0"?>'
			.'<plurio xmlns:pt="plurioTypes" '
			.'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
			.'xsi:noNamespaceSchemaLocation="plurio.xsd" '
			.'action="insert"></plurio>';

		$plurio = simplexml_load_string($xml);

		// add guide
		$this->_createGuide($plurio);

		// add agenda
		$this->_createAgenda($plurio);

		// simplexml is unable to properly format its output
		$dom = new DOMDocument('1.0');
		$plurio_dom = dom_import_simplexml($plurio);		// one needs to import the simpleXML object first,
		$plurio_dom = $dom->importNode($plurio_dom,true);	// then import it into the current DOM,
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
		 ********************************************************/

		// Creating and referencing a buildings section for later use
		$this->_buildings = $guide->addChild('guideBuildings');
		
		/**
		 *  Actual buildings will be added later on in the agenda
		 * through the building object, if we need them.
		 */
		
		/********************************************************
		 * Guide >> Organisation				*
		 ********************************************************/

		$this->_orgs = $guide->addChild('guideOrganisations');
		
		/**
		 * Actual organisations will be added later on in the agenda
		 * through the organisation object, if we need them.
		 * Not just yet though ;)
		 */
		 /*
		 $syn2cat = new Organisation;
		 $syn2cat->addTo( $this->_orgs );
		 $syn2cat->setOrgaId( $this->_orgaId );
		 $syn2cat->setName( 'syn2cat a.s.b.l.' );
		 $syn2cat->setShortDescription( 'fr', 
		 	"Association pour l'encouragement des innovations sociales et techniques." );
		 $syn2cat->setShortDescription( 'en', "Promoting social and technical innovations." );
		 $syn2cat->setLongDescription( 'en', 
		 	'Founded in February 2009, syn<sub>2</sub>cat is a non-profit association'
		 	.' with the purpose of creating, exploiting and maintaining a so-called'
		 	.' hackerspace in Luxembourg. Spreading knowledge on a wide basis being'
		 	.' one of the major goals of syn<sub>2</sub>cat, we strongly believe that'
		 	.' this can be achieved best by sharing one single infrastructure, where'
		 	.' everybody shares not only their know-how but also responsibility over'
		 	.' the premises. The hackerspace should function as an open space for sharing'
		 	.' opinions, technology, and knowledge. The space should spread enthusiasm,'
		 	.' encourage learning and mentoring, and inspire the attending crowd with all'
		 	.' sorts of new ideas. '
		 );
	
		$syn2cat->setAddress( 'Pavillon "Am Hueflach"', 11, 'rue du cimetière', 'L-8018', 'Strassen' );
		$syn2cat->setContact();
		
		$building = new Building;
		$syn2cat->tieToBuilding( $building->getIdFor( 'Hackerspace, Strassen' ) );
		$syn2cat->addLogo( $this->_orgaLogo );
		$syn2cat->addCategories( array( 507, 510, 345, 616, 617 ) );
		$syn2cat->setUserSpecific( $syn2cat->getIdFor( 'syn2cat a.s.b.l.' );	
		*/	
	}

	private function _createAgenda( &$plurio ){	
		// don't add an agenda element if no events are present (to validate plurio.xsd)
		if( empty($this->_data->items) ) return;

		// creating agenda node
		$agenda = $plurio->addChild( 'agenda' );

		// loop through our data, identifying properties and creating an xml object
		// we pass it a reference to the buildings node to be able to add buildings to the guide if necessary
		foreach($this->_data->items as $item){
			$event = new Event( $agenda, $this->_buildings, $this->_orgs );
			$event->createNewFromItem( $item );
		}
	}

}
?>
