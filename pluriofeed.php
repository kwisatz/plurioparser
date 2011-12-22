<?php

require_once 'plurio.class.php';

$feedUrl = 'https://wiki.hackerspace.lu/wiki/Special:Ask/'
	.'-5B-5BCategory:Event-5D-5D-20'
	.'-5B-5BStartDate::-3E%1$d-2D%2$s-2D%3$s-5D-5D-20'
	.'-5B-5BIs-20External::no-5D-5D-20-5B-5BDo-20Announce::yes-5D-5D'
	.'/-3FStartDate/-3FEndDate/-3FHas-20subtitle/-3FHas-20description/'
	.'-3FIs-20Event-20of-20Type%4$s'
	.'Is-20type/-3F'
	.'Has-20location/-3F'
	.'Has-20picture/-3F'
	.'Has-20highres-20picture/-3F'
	.'Has-20cost/-3F'
	.'Category/'
	.'order%4$sASC/sort%4$sStartDate/'
	.'searchlabel%4$sJSON-20(plurio)/'
	.'format%4$sjson';

$input = sprintf($feedUrl,date('Y'),date('m'),date('d'),'%3D');

$plurio = new PlurioFeed($input);
$xmlFeed = $plurio->parseSemanticData();

$plurio->send_headers();
print($xmlFeed);
?>
