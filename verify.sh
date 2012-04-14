#!/bin/bash
URL="http://www.hackerspace.lu/plurio/"
SCHEMA=plurio.xsd
OUT=plurio.xml
MAILTO="david@hackerspace.lu"
LOGFILE=plurio.log
TODAY=`date +%F`

echo -n "Running plurio.net export-and-verify... "

`which wget` -q -O $OUT $URL

[ -f $OUT ] && `which xmllint` --noout --schema $SCHEMA $OUT > ${LOGFILE} 2>&1

mail -s "plurio xml export for ${TODAY}" ${MAILTO} < ${LOGFILE}

cat ${LOGFILE}
