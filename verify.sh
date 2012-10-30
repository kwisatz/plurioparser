#!/bin/bash

#URL="http://www.hackerspace.lu/plurio/"
SCHEMA=plurio.xsd
OUT=plurio.xml
MAILTO="david@hackerspace.lu"
LOGFILE=plurio.log
TODAY=`date +%F`

echo "Running plurio.net export-and-verify... "
rm $OUT

#`which wget` -q -O $OUT $URL
`which php` pluriofeed.php

[ -f $OUT ] && `which xmllint` --noout --schema $SCHEMA $OUT > ${LOGFILE} 2>&1

mail -s "plurio xml export for ${TODAY}" ${MAILTO} < ${LOGFILE}

cat ${LOGFILE}
