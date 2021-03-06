<?php
/**
 * Parser that uses data from a semantic wiki and outputs an
 * XML file for import into plurio.net
 * 
 * @author David Raison <david@hackerspace.lu>
 * @file event.class.php
 * @ingroup plurioparser
 */

class Event extends Entity {
	
	private $_event;	// internal representation of event xml object
	private $_buildings;	// referrer to guide>>buildings node
	private $_orgs;		// referrer to guide>>organisations node
	
	private $_ld;		// internal referrer to long description objects
	
	/**
	 * Construct the event object and assign buildings and organisations
	 * guide sections
	 */
	public function __construct( $agenda, $buildings, $orgs ) {
		parent::__construct();
		$this->_event = $agenda->addChild('event');
		$this->_buildings = $buildings;
		$this->_orgs = $orgs;
		$this->_ld = array();
	}
	
	/**
	 * The idea is to use their xml file for mapping. 
	 * But how can we do that automatically?
	 * Science, technologie	Conférences		715
	 * Science, technologie	Evénements scolaires	733
	 * Science, technologie	Jeunes publics		731
	 * Science, technologie	Evénements thématiques	729
	 * Science, technologie	Festivals		727
	 * Science, technologie	Visites guidées		725
	 * Science, technologie	Visites labos et entreprises	723
	 * Science, technologie	Expositions temporaires	721
	 * Science, technologie	Excursions/Voyages	719
	 * Science, technologie	Ateliers		717
	 * Science, technologie	Collections		735
	 */
	private function _mapCategory( $mwc ){
		$c = array();
		if( is_numeric( substr( $mwc, 0, 1 ) ) ) {
			$c[] = $this->_addAgeCategories( $mwc, $c );
		} else {
			switch( $mwc ) {
				case 'excursion':
				case 'excursion/voyage':
				case 'camp':
					$c[] = 442;	// leisure, excursions and hikes
					$c[] = 719;	// Science, technologie > Excursions, Voyages
				break;
				case 'visite labos ou entreprises':
					$c[] = 723;	// Science, technologie > Visites labos et entreprises
				break;
				case 'exposition':	// temporary exposition vs. "collections"
				case 'exhibiton':
					$c[] = 405;	// collections, science & technology
					$c[] = 398;	// collections, new media
					$c[] = 721;	// Science, technologie > expositions temporaires (science.lu)
				break;
				case 'music':
					$c[] = 261;	// music, rock, hiphop, pop, electronic
				break;
				case 'presentation':
				case 'seminar':
				case 'conference':
				case 'conférence':
				case 'congress':
				case 'convention':
					$c[] = 427;	// living heritage, lectures, professional
					$c[] = 715; 	// Science, Technology > Conférences (science.lu)
				break;
				case 'workshop':
				case 'hackathon':
					$c[] = 449;	// living heritage, workshops
					$c[] = 717;	// Science, technologies > Ateliers (science.lu)
				break;
				case 'visite guidée':
					//$c[] = 390;	// Expositions temporaires > Visites guidées régulières
					//$c[] = 409;	// Expositions permanentes, collections > Visites guidées régulières
					$c[] = 725;	// Science, technology > Visites guidées (science.lu)
				break;
				case 'U19':
				case 'science-club':
				case 'panda-club':
					$c[] = 467;	// Junges Publikum > Freizeit, Traditionen und Anderes
					$c[] = 708;	// Altersgruppen, Junges Publikum
					$c[] = 731;	// Science, Technology > Jeunes publics
				break;
				// fit all category
				case 'meeting':
				case 'manifestation':
				case 'réunion':
				case 'event':
				case 'party':
					$c[] = 445;	// leisure, traditions and others -> other
					$c[] = 729;	// Science, technologie > Evénements thématiques
				break;
				default:
					( $debug == 'on' ) && printf('Encountered unknown category "%s"' ."\n", $mwc );
				break;
			}
		}
		return array_unique( $c, SORT_NUMERIC );
	}

	/**
		$c[] = 680;	// 6 (child)
		$c[] = 682;	// 7 (child)
		$c[] = 684;	// 8 (child)
		$c[] = 686;	// 9 (child)
		$c[] = 688;	// 10 (child)
		$c[] = 690;	// 11 (child)
		$c[] = 692;	// 12 (child)
		$c[] = 696;	// 13 (youth, according to plurio)
		$c[] = 698;	// 14 (youth)
		$c[] = 700;	// 15 (youth)
		$c[] = 702;	// 16 (youth)
		$c[] = 704;	// 17 (youth)
		$c[] = 706;	// 18 (youth)
	 */
	private function _addAgeCategories( $mwc, &$c ){
		$base = 6;
		$top = 18;
		if( strstr( $mwc, '-') ) {
			list( $min, $max ) = explode( '-', $mwc );
		} elseif ( strstr( $mwc, '+') ) {
			$min = substr( $mwc, 0, strpos( $mwc, '+') );
			$max = $top;
		} else {
			throw new Exception( 'Encountered unknown age definition', 701);
		}

		// Extremes
		if( $max < $base ) {
			$c[] = 678;	// enfance
		} elseif ( ( $max > $top ) && ( $max < 25 ) ) {
			$c[] = 710;	// Etudiants, jeunes adultes
		} elseif( $min > 60 ) {
			$c[] = 712;	// senior 60+
		} else {	// regular categories
			for( $i = $min; $i <= $max; $i++ ) {
				$cat = 680 + ( ( $i - $base ) * 2 );
				$c[] = ( $i > 12 ) ? $cat + 2 : $cat;	// There is a gap of 2 between 12 (child) and 13 (youth)
			}
			$c[] = 708;	// jeune public
		}
	}
	
	/**
	 * Event factory
	 */
	public function createNewFromItem( $item ) {
		global $config;

		$this->_event->name = $item->label;

		if( !empty( $item->has_subtitle ) )
			$this->_event->addChild( 'subtitleOne', $item->has_subtitle[0] );
			
		// not nice, but functional FIXME in v.3.0
		$locid = !empty( $item->has_location_id[0] ) ? $item->has_location_id[0] : $item->has_location[0];
		$linfo = $this->fetchLocationInfo( $locid );
                $this->_event->localDescription = $linfo->has_localDescription[0];  // NEW STYLE

		// XML Schema says short description must come before long description
		$desc = new Descriptions( $this->_event );

		//if( !empty( $item->has_subtitle[0] ) )
		//$desc->setShortDescription( 'lu', substr( strip_tags( $item->has_description[0] ), 0, 64) . '...' );
		
		$this->_ld['lb'] = $desc->setLongDescription( 'lu', $item->has_description['lb'] );
                if( !empty($item->has_description['fr'] ) ) {
			//$desc->setShortDescription( 'fr', substr( strip_tags( $item->has_description[1] ), 0, 64) . '...' );
			$this->_ld['fr'] = $desc->setLongDescription('fr', $item->has_description['fr'] );
                }
                if( !empty($item->has_description['de'] ) ) {
			//$desc->setShortDescription( 'fr', substr( strip_tags( $item->has_description[1] ), 0, 64) . '...' );
			$this->_ld['de'] = $desc->setLongDescription('de', $item->has_description['de'] );
                }

		// Add date and time
		$this->_setDateTime( $item->startdate[0], $item->enddate[0] );

		// Add prices
		$this->_setPrices( $this->_event, $item->has_cost[0] );

		// Add ticketing (if available)
		if( !empty( $item->has_ticket_url[0] ) ) {
			$this->_addTicketing( 
				$this->_event, 
				$item->has_ticket_url[0], 
				$item->startdate[0]
			);
		}
		
		/****** <contactEvent/> ******/
		$contact = new Contact;

		// use external website if supplied, else the webbase from the config + entity label
		// FIXME: if ( RegXor::isWebsite( $website ) );
		if ( !empty( $item->url[0] ) ) {
			$contact->setWebsiteUrl( $item->url[0] );
		} elseif ( $config['events.havewebsite'] === 'true' && !empty( $config['events.webbase'] ) ) {
			$contact->setWebsiteUrl( $config['org.webbase'] . str_replace( ' ', '_', $item->label ) );
		} else {
			$contact->setWebsiteUrl( null );	// effectively removes url
		}

		// add Email Address if one was supplied, else use the default from the config
		if ( !empty( $item->has_contact[0] ) && RegXor::isValidEmail( $item->has_contact[0] ) ) {
			$contact->setEmailAddress( $item->has_contact[0] );
		} elseif ( !empty( $config['org.email'] ) ) {
			$contact->setEmailAddress( $config['org.email'] );
		}

		// Finally add contact xml subtree to the event tree
		$contact->addTo( $this->_event, $this );

		/****** <relationsAgenda/> ******/
		$relations = $this->_event->addChild('relationsAgenda');

		/***** RelationsAgenda :: BUILDING ******/
		/**
		 * Our wiki semantics don't support internal events right now, 
		 * so no <internalEvents/> here either
		 * But we do have multiple locations, so for each location, 
		 * we need to check whether it already exists in the guide,
		 * and if not, add it
		 *
		 * i.e. add a new building to the guide if it's not already
		 * in there - or at least try to
		 */
		$building = new Building;
		$locid = !empty( $item->has_location_id[0] ) ? $item->has_location_id[0] : $item->has_location[0];
		$eventData = array( 
			'organisation' => $item->has_organizer[0], 
			'in_charge' => $item->is_in_charge[0],
			'label' => $item->label
		);

		try {
		    if( !$building->_inGuide( $locid ) ){
			$buildingExtId = $building->addToGuide( 
			$this->_buildings, 
			$locid, 
			$eventData );
		    } else $buildingExtId = $building->getIdFor( $locid );

		    // If adding to the guide or retrieving the Id was successful, add a reference
		    $place = $relations->addChild('placeOfEvent');	// mandatory
		    $place->addAttribute('isOrganizer','false');	// as directed by guideline
		    if( is_string( $buildingExtId ) ) {
			    $place->addChild('extId', 'mnhn' . $buildingExtId );
		    } else if ( is_array( $buildingExtId ) ) {
			    $place->addChild( 'id', $buildingExtId['id'] );
			    if ( $buildingExtId['info'] ) {
				    // $this->_ld['lb'] is a simpleXML object with two elements (@attributes and [0])
                                $this->_ld['lb'] && $this->_ld['lb'][0] .= sprintf(
					'<p>D&euml;s Aktivit&eacute;it f&euml;nnt op folgender Plaz statt: %s</p>',
					$buildingExtId['info']
				);
				$this->_ld['fr'] && $this->_ld['fr'][0] .= sprintf(
					'<p>Cette activit&eacute; se d&eacute;roulera au lieu suivant: %s</p>',
					$buildingExtId['info']
				);
				$this->_ld['de'] && $this->_ld['de'][0] .= sprintf(
					'<p>Diese Veranstaltung findet an folgendem Ort statt: %s</p>',
					$buildingExtId['info']
				);
			    }
		    } else {
			    throw new Exception('Oops, got an unexpected reply from building::addToGuide', 321);
		    }
		} catch ( Exception $e ) {
			if( $e->getCode() == 900 || $e->getCode() == 501 ) {
				// that's bad! Remove the entire event since we're unable to reference it to a location
				print( $e->getMessage() );
				throw new Exception( 
				sprintf( 'Failed adding building for event "%s" to guide section. Removing entire event!' . "\n\r", 
					$item->label ),
				334 );
			} else throw $e;

		}

		/****** RelationsAgenda :: <personsToEvent/> ******/
		// none right now

		/****** RelationsAgenda :: ORGANISATION	<organisationsToEvent/> ******/
		$orga = $relations->addChild('organisationsToEvent')->addChild('organisationToEvent');

		// FIXME FIXME FIXME: put this check into Organisation(Builder) FIXME
		$organisation = new Organisation;
		if ( $organisation->_inGuide( $item->has_organizer[0] ) ) {
			$organisationExtId = $organisation->getIdFor( $item->has_organizer[0] );
		} else {
			$organisationExtId = $organisation->addToGuide( $this->_orgs, $item->has_organizer[0] );
		}

		// MNHN hack (only using plurio ids)
		if ( $organisationExtId ) {
			$orga->addChild( 'id', $organisationExtId );
		        $orga->addChild( 'organisationRelEventTypeId', 'oe07');
		}
		
		/*if ( $organisationExtId ) {
			$orga->addChild('extId',$organisationExtId );
			$orga->addChild('organisationRelEventTypeId','oe07');	// = organiser
		}
                 */

		// FIXME: put into private method
		// agenda >> event >> relations >> pictures
		if( !empty($item->has_picture[0]) || !empty($item->has_alternate_picture[0]) ) {
		$count = 0;
		$pictures = $relations->addChild('pictures');
		
			if( !empty( $item->has_picture[0] ) ) {
				$count++;	// we increase but don't use this here
				$picture = new Picture;
				$picture->name = $item->has_picture[0];
                                //$picture->category = $item->has_organizer[0];
                                $picture->category = strtolower( $item->Categorie );
				$picture->position = 'default';
				$picture->label = $item->label;
				$picture->addTo( $pictures );
			}

			if( !empty( $item->has_alternate_picture[0] ) ) {
				$picture = new Picture;
				$picture->name = $item->has_alternate_picture[0];
                                //$picture->category = $item->has_organizer[0];
                                $picture->category = strtolower( $item->Categorie );
				$picture->position = 'additional' . $count++;
				$picture->label = $item->label;
				$picture->addTo( $pictures );
			}

			if( empty($pictures) )
				unset($relations->pictures);
		}

		// <agendaCategories/> - can have as many as we want
		$this->_addCategories( $relations, $item->is_event_of_type, $item->category );

		// set userspecific (unique ids)
		try {
			$us = $this->_event->addChild('userspecific');
			$pid = 'ev' . $this->getIdFor( $item->label );
			$us->addChild( 'entityId', $pid);
			$us->addChild( 'entityInfo', $config['org.name'] . ' event id ' . $pid );

			return true;
		} catch ( Exception $e ) {
			print($e->getMessage());
		}
	}


	/**
	 * Add agendaCategories to the Event
	 * FIXME: this is still pretty mediawiki specific
	 */
	protected function _addCategories( &$relations, $eventType, $eventCategory ) {
		$categories = $relations->addChild('agendaCategories');
			
		// map our categories and event types to the corresponding plurio ones
		$types = is_array( $eventType ) ? $eventType : array( $eventType );
		$cats = is_array( $eventCategory) ? $eventCategory : array( $eventCategory );

		array_walk( $cats, 'self::_removeCategoryPrefix' );	// remove "Category:" from smw data
		// For the mnhn version, $types holds the age categories and must thus follow the main (= first) category.
		$cats = array_unique( array_merge( $cats, $types ) );

		foreach( $cats as $mwc ) {
			if($mwc == 'RecurringEvent') continue;	// filter recurring event category
			foreach($this->_mapCategory( strtolower( $mwc ) ) as $pcats ) {
				$categories->addChild('agendaCategoryId', $pcats );
			}
		}
	}

	/**
	 * Removes the substring "Category:" from the category property
	 */
	private function _removeCategoryPrefix( &$value ) {
		if ( strpos( $value, ':') ) {
			$value = substr( $value, strpos( $value, ':') + 1);
		}
	}
	
		
	private function _setDateTime( $startdate, $enddate ) {
		list( $dateFrom, $timingFrom ) = $startdate;
		list( $dateTo, $timingTo ) = $enddate;

		$date = $this->_event->addChild('date');
		$date->addChild('dateFrom',$dateFrom);
		$date->addChild('dateTo',$dateTo);
		$date->addChild('dateExclusions');

		$timing = $this->_event->addChild('timings')->addChild('timing');
		//$timing->addChild( 'timingDescription', 'Hours' );	// Disabled as per mnhn request (26.04.13)
		$timing->addChild( 'timingFrom', $timingFrom );
		$timing->addChild( 'timingTo', $timingTo );
	}

	/**
	 * Ok, this is difficult for early-bird unless 
	 * we add distinct prices AND dates to the wiki pages.. 
	 * And we don't really want to do that now.
	 */
	private function _addTicketing( $event, $ticket_url, $startdate ) {
		$ticket = $event->addChild('tickets')->addChild('ticket');
		$ticket->addChild( 'datetime', date( 'c', strtotime( $startdate[0] . ' ' . $startdate[1] ) ) );
		$ticket->addChild( 'ticketUrl', $ticket_url );
		$contact = new Contact;
		$contact->addPhoneNumber( $ticket );
		// ampersand fix
		//$ticket->addChild( 'ticketInfo', 'Sign up or buy a ticket for ' . $event->name );
		//$ticket->ticketInfo = 'Sign up or buy a ticket for ' . $event->name;	// Disabled as per mnhn request (26.04.13)
	}
	
	private function _setPrices( $event, $cost ){
		// prices (if the price is 0 or something other than a numeric value, set freeOfCharge to true)
		$prices = $event->addChild('prices');
		// $first = substr( $cost, 0, 1 );
		// everything that does not evaluate to something sensible is 0
		if ( (int) $cost == 0 ) {
			$prices->addAttribute( 'freeOfCharge','true' );
		} else {
                    $prices->addAttribute( 'freeOfCharge', 'false' );
                    $price = $prices->addChild('price');
                    //$price->addChild('priceDescription','Fee');	// Disabled as per mnhn request (26.04.13)
                    $price->addChild('priceValue',(int) $cost);
		}
	}
}
