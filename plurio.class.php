<?php

// http://jsonlint.com/
// http://xml.syyncplus.net/html/1_6/plurio_net_xml_schema_definition.html

class PlurioFeed{

	private $_data;	// store data after json decode
	private $_domain;
	private $_phoneNumber = '691442324';	// contact phone number
	private $_emailAddress = 'info@hackerspace.lu';

	//private $_plurio_categories = 'categoriesAgendaEvents.xml';
	private $_plurio_categories = 'http://www.plurio.org/XML/listings/categoriesXML.php';	// too slow
	private $_plurio_cats;

	private $_buildingId = '225269';
	private $_orgaId = '225223';

	public function __construct($input){
		$this->_data = $this->_readJsonData($input);
		$this->_domain = 'http://'.parse_url($input,PHP_URL_HOST);
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
	private function _readJsonData($input){
		return json_decode(str_replace(array("\n","\t"),'',file_get_contents($input)));
	}

	// The idea is to use their xml file for mapping. 
	// But how can we do that automatically?
	private function _mapCategory($mwc){
		//var_dump($this->_plurio_cats->german->agenda->category);
		$c = array();
		switch($mwc) {
			case 'Excursion':
			case 'Camp':
				$c[] = 442;	// leisure, excursions and hikes
			break;
			case 'Exhibiton':
				$c[] = 405;	// collections, science & technology
				$c[] = 398;	// collections, new media
			break;
			case 'Music':
				$c[] = 261;	// music, rock, hiphop, pop, electronic
			break;
			case 'Presentation':
			case 'Seminar':
			case 'Conference':
			case 'Congress':
			case 'Convention':
				$c[] = 427;	// living heritage, lectures, professional
			break;
			case 'Workshop':
			case 'Hackathon':
				$c[] = 426;	// living heritage, workshops
			break;
			case 'U19':
				$c[] = 469;	// young public, other
			break;
			// fit all category
			case 'Meeting':
			case 'Event':
			case 'Party':
			default:
				$c[] = 433;	// living heritage, other
			break;
		}
		return $c;
	}

	public function parseSemanticData(){
		$xml = '<?xml version="1.0"?><plurio xmlns:pt="plurioTypes" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="plurio.xsd" action="insert"></plurio>';
		$plurio = simplexml_load_string($xml);

		// add guide
		$this->_createGuide($plurio);

		// add agenda
		$this->_createAgenda($plurio);

		return $plurio->asXML();
	}

	private function _createGuide(&$plurio){
		$guide = $plurio->addChild('guide');

		$building = $guide->addChild('guideBuildings')->addChild('entityBuilding');
		$building->addAttribute('id',$this->_buildingId);	// is that the correct id?
		$building->addChild('name','syn2cat hackerspace');

		// short descriptions
		$descs = $building->addChild('shortDescriptions');

		$desc_en = $descs->addChild('shortDescription');
		$desc_en->addAttribute('autogenerate','true');
		$desc_en->addAttribute('language','en');

		$desc_de = $descs->addChild('shortDescription',
			'Das syn2cat Hackerspace ist ein 120m² großes Paradies für Geeks und Nerds');
		$desc_de->addAttribute('language','de');
		$desc_de->addAttribute('autogenerate','false');
		
		$desc_fr = $descs->addChild('shortDescription',
			'A suivre');
		$desc_fr->addAttribute('language','fr');
		$desc_fr->addAttribute('autogenerate','false');

		// long description
		$longdesc = "Our friendly environment enables you to work on your own or community projects. We have all the tools you'd ever require and will even buy those you don't. Produce your own objects with our Makerbot (3D printer), flash your microcontrollers with our µC programmers, pilot a quadrokopter, etch your own circuit boards, use our library, or become a member of the team that produces the Lët'z Hack radio show. There's still more services, and infrastructure the space puts at your disposal and by becoming a member, you support a unique infrastructure in all of Luxembourg.";
		$ldesc = $building->addChild('longDescriptions')->addChild('longDescription',$longdesc);
		$ldesc->addAttribute('language','en');

		// visitor Info (optional)
		$building->addChild('visitorInfo','See our webpage to find out whether we\'re open');

		// address
		$this->_addAddress($building);

		// timing is optional and not really useful in our case

		// prices
		$building->addChild('prices')->addAttribute('freeOfCharge','true');

		// contactInformation
		$this->_addContactInformation($building,'building');

		// relationsBuilding
		$relations = $building->addChild('relationsBuilding');
		$otb = $relations->addChild('organisationsToBuildings')->addChild('organisationToBuilding');
		$otb->addChild('id',$this->_orgaId);
		$otb->addChild('organisationRelBuildingTypeId','ob10');

		// relations >> building picture
		$pictures = $relations->addChild('pictures');
		$this->_addPicture($pictures, 'File:Chilllllling.jpg', 'default', 'the hackerspace');

		// relations >>  building categories
		$gcats = $relations->addChild('guideCategories');
		$gcats->addChild('guideCategoryId','616');
		$gcats->addChild('guideCategoryId','617');
		$gcats->addChild('guideCategoryId','213');
		$gcats->addChild('guideCategoryId','15');

		/*********************************************************/

		// organisation
		$org = $guide->addChild('guideOrganisations')->addChild('entityOrganisation');
		$org->addAttribute('id',$this->_orgaId);
		$org->addChild('name','syn2cat a.s.b.l.');

		// descriptions	( We should definitely try to fetch them from somewhere)
		$sdescs = $org->addChild('shortDescriptions');
		$sde = $sdescs->addChild('shortDescription',
			"Association pour l'encouragement des innovations sociales et techniques.");
		$sde->addAttribute('autogenerate','false');
		$sde->addAttribute('language','en');

		$ldescs = $org->addChild('longDescriptions');
		$lde = $ldescs->addChild('longDescription',
			'Founded in February 2009, syn<sub>2</sub>cat is a non-profit association with the purpose of creating, exploiting and maintaining a so-called hackerspace in Luxembourg. Spreading knowledge on a wide basis being one of the major goals of syn<sub>2</sub>cat, we strongly believe that this can be achieved best by sharing one single infrastructure, where everybody shares not only their know-how but also responsibility over the premises. The hackerspace should function as an open space for sharing opinions, technology, and knowledge. The space should spread enthusiasm, encourage learning and mentoring, and inspire the attending crowd with all sorts of new ideas. '
		);
		$lde->addAttribute('language','en');

		// address
		$this->_addAddress($org);

		// contactInformation
		$this->_addContactInformation($org,'organisation');
	
		// relations
		$orels = $org->addChild('relationsOrganisation');

		// organisations to Organisation (could add C3L here)
	
		// organisations to Buildings
		$bto = $orels->addChild('organisationsToBuildings')->addChild('organisationToBuilding');
		$bto->addChild('id',$this->_buildingId);
		$bto->addChild('organisationRelBuildingTypeId','ob10');

		// organisation >> relations >> syn2cat logo
		$pictures = $relations->addChild('pictures');
		$this->_addPicture($pictures, 'File:Syn2cat_Logo.png', 'default', 'syn2cat logo');

		// organisation categories
		$gcats = $orels->addChild('guideCategories');
		$gcats->addChild('guideCategoryId','507');
		$gcats->addChild('guideCategoryId','510');
		$gcats->addChild('guideCategoryId','345');
		$gcats->addChild('guideCategoryId','616');
		$gcats->addChild('guideCategoryId','617');

	}

	private function _addAddress(&$entity){
		$address = $entity->addChild('adress');	// (sic)
		$address->addChild('street','rue du Cimetière');
		$address->addChild('houseNumber','11');
		$address->addChild('placing','Pavillon "am Hueflach"');
		$address->addChild('poBox','L-8018');
		$address->addChild('localisationId','');
	}

	private function _addContactInformation(&$entity,$type){
		$childname = 'contact' . ucfirst($type);
		$contact = $entity->addChild($childname);
		$this->_addContactPhoneNumber($contact);
		$contact->addChild('websites')->addChild('website','http://www.hackerspace.lu');
		$this->_addContactEmail($contact);
	}

	private function _addContactPhoneNumber(&$contact){
		if($this->_phoneNumber != ''){
			$phoneNumber = $contact->addChild('phoneNumbers')->addChild('phoneNumber');
			$phoneNumber->addAttribute('phoneType','mobile');
			$phoneNumber->addChild('phoneNumberFunctionId','pn08');	// = Info
			$phoneNumber->addChild('phoneNumberAreaCode','+352');
			$phoneNumber->addChild('mainNumber',$this->_phoneNumber);
		}
	}

	private function _addContactEmail(&$contact){
		$email = $contact->addChild('emailAdresses')->addChild('emailAdress');	// sic
		$email->addChild('emailAdressUrl',$this->_emailAddress);	// sic
		$email->addChild('emailAdressFunctionId','ea01');	// = Info (sic)

	}

	/*
	 *  we need to get the URL for this image first. 
	 * Using the mediawiki api (which is a bit silly, really
	 */
	private function _fetchPictureUrl($title, $strip) {
		$query = $this->_domain . '/w/api.php?action=query&titles='
			. str_replace(' ','_',$title) . '&prop=imageinfo&iiprop=url&format=json';
		$data = $this->_readJsonData($query);
		$keys = array_keys(get_object_vars($data->query->pages));
		$property = $keys[0];
		$url = $data->query->pages->$property->imageinfo[0]->url;
		return $strip ? parse_url($url,PHP_URL_PATH) : $url;
	}

	private function _isHighres( $title ) {
		$file = $this->_fetchPictureUrl( $title, false );
		$res = getimagesize($file);
		return ( ($res[0]/300 > 3.5) || ($res[1]/300 > 3.5) );
	}

	private function _addPicture(&$parent, $name, $position, $label){
		$picUrl = $this->_fetchPictureUrl($name, true);
		$picture = $parent->addChild('picture');
		$picture->addAttribute('pictureType','extern');
		$picture->addChild('domain',$this->_domain);
		$picture->addChild('path',$picUrl);
		$picture->addChild('picturePosition', $position);
		$picture->addChild('pictureName',
			substr($name, strpos($name ,':')+1, -4) );
		$picture->addChild('pictureAltText','Picture for ' . $label);
		$picture->addChild('pictureDescription','Copyright by their respective owners');
	}

	private function _createAgenda(&$plurio){	
		// creating agenda
		$agenda = $plurio->addChild('agenda');

		// loop through our data, identifying properties and creating an xml object
		foreach($this->_data->items as $item){
			$event = $agenda->addChild('event');
			$event->addChild('name',$item->label);
			if($item->has_subtitle) $event->addChild('subtitleOne',$item->has_subtitle[0]);
			$event->addChild('localDescription',$item->has_location[0]);

			// XML Schema says short description must come before long description
			$shortDesc = $event->addChild('shortDescriptions')->addChild('shortDescription');
			$shortDesc->addAttribute('autogenerate','true');
			$shortDesc->addAttribute('language','en');

			$longDesc = $event->addChild('longDescriptions')->addChild('longDescription',$item->has_description[0]);
			$longDesc->addAttribute('language','en');

			// date elements, need parsing first
			$startTime = strtotime($item->startdate[0]);
			$endTime = strtotime($item->enddate[0]);
			$dateFrom = date("Y-m-d",$startTime);
			$dateTo = date("Y-m-d",$endTime);
			$timingFrom = date("H:i",$startTime);
			$timingTo = date("H:i",$endTime);

			$date = $event->addChild('date');
			$date->addChild('dateFrom',$dateFrom);
			$date->addChild('dateTo',$dateTo);
			$date->addChild('dateExclusions');

			$timing = $event->addChild('timings')->addChild('timing');
			$timing->addChild('timingDescription','Opening hours');
			$timing->addChild('timingFrom',$timingFrom);
			$timing->addChild('timingTo',$timingTo);

			// prices (if the price is 0 or something other than a numeric value, set freeOfCharge to true)
			$prices = $event->addChild('prices');
			$first = substr($item->has_cost[0],0,1);
			if ( (int) $item->has_cost[0] == 0 ) {	// everything that does not evaluate to something sensible is 0
				$prices->addAttribute('freeOfCharge','true');
			} else {
				$prices->addAttribute('freeOfCharge','false');
				$price = $prices->addChild('price');
				$price->addChild('priceDescription','Fee');
				$price->addChild('priceValue',(int) $item->has_cost[0]);
			}

			// <contactEvent/>
			$contact = $event->addChild('contactEvent');
			$this->_addContactPhoneNumber(&$contact);
			$contact->addChild('websites')->addChild('website',
				$this->_domain.'/wiki/'.str_replace(' ','_',$item->label));
			$this->_addContactEmail(&$contact);
			
			// <relationsAgenda/>
			$relations = $event->addChild('relationsAgenda');
			// our wiki doesn't support internal events as of right now, so no <internalEvents/> here either
			$place = $relations->addChild('placeOfEvent');	// mandatory
			$place->addAttribute('isOrganizer','false');	// as directed by guideline
			/** 
			 * childElement to placeOfEvent can be either
			 * an "id" if it is already on plurionet and known
			 * OR "name", only if defined in this XML export!
			 * OR "entityName", "localisationId" and "street" if it is on plurio but id isn't known
			 */
			$place->addChild('id',$this->_buildingId);	

			// no <personsToEvent/>
			// <organisationsToEvent/>
			$orga = $relations->addChild('organisationsToEvent')->addChild('organisationToEvent');
			// child to this identical to placeOfEvent, see above
			$orga->addChild('id',$this->_orgaId);
			$orga->addChild('organisationRelEventTypeId','oe07');	// = organiser

			// agenda >> event >> relations >> pictures
			if( !empty($item->has_picture[0]) || !empty($item->has_highres_picture[0]) ) {
				$pictures = $relations->addChild('pictures');
				if( !empty( $item->has_picture[0] ) ) {
					$this->_addPicture( $pictures, $item->has_picture[0], 'default', $item->label);
				}
				if( !empty( $item->has_highres_picture[0] ) && $this->_isHighres( $item->has_highres_picture[0] ) ) {;
					$this->_addPicture( $pictures, $item->has_highres_picture[0], 'additional1', $item->label);
				}
			}

			// <agendaCategores/> - can have as many as we want
			$categories = $relations->addChild('agendaCategories');
			
			// map our categories to the corresponding plurio ones
			$mwtypes = ( is_array($item->is_type[0]) ) ? $item->is_type[0] : array($item->is_type[0]);
			$mwcats = ( is_array($item->category[0]) ) ? $item->category[0] : array($item->category[0]);
			$mwcats = array_unique(array_merge($mwtypes, $mwcats));
			foreach( $mwcats as $mwc ){
				if($mwc == 'RecurringEvent') continue;	// filter recurring event category
				foreach($this->_mapCategory($mwc) as $pcats)
					$categories->addChild('agendaCategoryId',$pcats);
			}

		}
	}

}
?>
