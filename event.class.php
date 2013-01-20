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
	
	private $_pld;
	private $_sld;
	
	/**
	 * Construct the event object and assign buildings and organisations
	 * guide sections
	 */
	public function __construct( $agenda, $buildings, $orgs ) {
		parent::__construct();
		$this->_event = $agenda->addChild('event');
		$this->_buildings = $buildings;
		$this->_orgs = $orgs;
	}
	
	/**
	 * The idea is to use their xml file for mapping. 
	 * But how can we do that automatically?
	 */
	private function _mapCategory($mwc){
		$c = array();
		switch($mwc) {
			case 'excursion':
			case 'camp':
				$c[] = 467;	
				$c[] = 442;	// leisure, excursions and hikes
			break;
			case 'exposition':
			case 'exhibiton':
				$c[] = 405;	// collections, science & technology
				$c[] = 398;	// collections, new media
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
			break;
			case 'workshop':
			case 'hackathon':
				$c[] = 467;	// Junges Publikum > Freizeit, Traditionen und Anderes
				$c[] = 449;	// living heritage, workshops
			break;
			case 'U19':
				$c[] = 467;	
			break;
			// mnhn stuff
			case 'science-club':
			case 'panda-club':
                                $c[] = 467;
				$c[] = 708;	// Altersgruppen, Junges Publikum
			break;
			case '6-8':
				$c[] = 680;	// 6 (child)
				$c[] = 682;	// 7 (child)
				$c[] = 684;	// 8 (child)
			break;
			case '9-10':
				$c[] = 686;	// 9 (child)
				$c[] = 688;	// 10 (child)
			break;
			case '11-18':		// Science-Club (just doing 14-18 here cause of fall-through
				$c[] = 698;	// 14 (youth)
				$c[] = 700;	// 15 (youth)
				$c[] = 702;	// 16 (youth)
				$c[] = 704;	// 17 (youth)
				$c[] = 706;	// 18 (youth)
			case '11-13':		// Science-Club, fall through, see above
				$c[] = 690;	// 11 (child)
				$c[] = 692;	// 12 (child)
				$c[] = 696;	// 13 (youth, according to plurio)
			break;
			case '13-15':		// Science-Club (just doing 14-18 here cause of fall-through
				$c[] = 696;	// 13 (youth)
				$c[] = 698;	// 14 (youth)
				$c[] = 700;	// 15 (youth)
			break;
			case '15+':
				$c[] = 700;	// 15 (youth)
				$c[] = 702;	// 16 (youth)
				$c[] = 704;	// 17 (youth)
				$c[] = 706;	// 18 (youth)
			break;
			case 'visite guidée':
				$c[] = 387;
			break;
			// fit all category
			case 'meeting':
			case 'manifestation':
			case 'réunion':
			case 'event':
			case 'party':
				$c[] = 445;	// leisure, traditions and others -> other
			break;
			default:
			break;
		}
		return $c;
	}

	
	/**
	 * Event factory
	 */
	public function createNewFromItem( $item ) {
		global $config;

		// ampersand fix
		//$this->_event->addChild( 'name', $item->label );
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
		$this->_pld = $desc->setLongDescription( 'lu', $item->has_description[0] );
                if( !empty($item->has_description[1] ) ) {
			//$desc->setShortDescription( 'fr', substr( strip_tags( $item->has_description[1] ), 0, 64) . '...' );
                    $this->_sld = $desc->setLongDescription('fr', $item->has_description[1] );
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

		try {
		    if( !$building->_inGuide( $locid ) ){
			$buildingExtId = $building->addToGuide( 
			$this->_buildings, 
			$locid, 
			$item->has_organizer[0] );
		    } else $buildingExtId = $building->getIdFor( $locid );

		    // If adding to the guide or retrieving the Id was successful, add a reference
		    $place = $relations->addChild('placeOfEvent');	// mandatory
		    $place->addAttribute('isOrganizer','false');	// as directed by guideline
		    if( is_string( $buildingExtId ) ) {
			    $place->addChild('extId', 'mnhn' . $buildingExtId );
		    } else if ( is_array( $buildingExtId ) ) {
			    $place->addChild( 'id', $buildingExtId['id'] );
			    if ( $buildingExtId['info'] ) {
				    // don't ask me why, but editing the node directly doesn't change it in the tree.
				    // [0][0] also works
				$this->_pld[0] .= sprintf(
					'<p>D&euml;s Aktivit&eacute;it f&euml;nnt op folgender Plaz statt: %s</p>',
					$buildingExtId['info']
				);
				$this->_sld[0] .= sprintf(
					'<p>Cette activit&eacute; se d&eacute;roulera au lieu suivant: %s</p>',
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
				sprintf( 'Failed adding building for event "%s" to guide section. Removing entire event!' . "\n", 
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
			foreach($this->_mapCategory($mwc) as $pcats)
				$categories->addChild('agendaCategoryId', strtolower( $pcats ) );
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
		$timing->addChild( 'timingDescription', 'Hours' );
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
		$ticket->ticketInfo = 'Sign up or buy a ticket for ' . $event->name;
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
                    $price->addChild('priceDescription','Fee');
                    $price->addChild('priceValue',(int) $cost);
		}
	}
}
