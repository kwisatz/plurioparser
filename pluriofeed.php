<?php

// control debug mode
define('DEBUG',false);

function __autoload( $name ) {
	require_once dirname( __FILE__) . '/'. strtolower( $name ) . '.class.php';
}

$feedUrl = 'http://wiki.hackerspace.lu/wiki/Special:Ask/'
	.'-5B-5BCategory:Event-5D-5D-20'
	.'-5B-5BStartDate::-3E%1$s-2D%2$s-2D%3$s-5D-5D-20'
	.'-3Cq-3E'
		.'-5B-5BHas-20organizer::Organisation:Syn2cat-5D-5D-20'
		.'OR-20'
		.'-5B-5BIs-20External::no-5D-5D-20'
		.'-3C-2Fq-3E-20'
	.'-5B-5BDo-20Announce::yes-5D-5D/'
	.'-3FStartDate/'
	.'-3FEndDate/'
	.'-3FHas-20subtitle/'
	.'-3FHas-20description/'
	.'-3FIs-20Event-20of-20Type/'
	.'-3FIs-20type/'
	.'-3FHas-20location/'
	.'-3FHas-20organizer/'
	.'-3FUrl/'
	.'-3FHas-20picture/'
	.'-3FHas-20alternate-20picture/'
	.'-3FHas-20cost/'
	.'-3FCategory/'
	.'order=ASC/'
	.'sort=StartDate/'
	.'limit=50/'
	.'format=json';

$input = sprintf($feedUrl, date('Y'), date('m'), date('d'));

$plurio = new Parser($input);
$xmlFeed = $plurio->createFeed();

$plurio->send_headers();
print($xmlFeed);

?>
