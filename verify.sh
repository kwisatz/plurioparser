#!/bin/bash

URL="http://www.hackerspace.lu/plurio/"
SCHEMA=plurio.xsd
OUT=plurio.xml
MAILTO="david@hackerspace.lu"
LOGFILE=plurio.log
TODAY=`date +%F`

`which wget` -O $OUT $URL

[ -f $OUT ] && `which xmllint` --noout --schema $SCHEMA $OUT > plurio.log 2>&1

mail -s "plurio xml export for ${TODAY}" ${MAILTO} < ${LOGFILE}
