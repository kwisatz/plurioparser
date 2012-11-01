#!/bin/bash

EXE=pluriofeed.php
SCHEMA="tools/plurio.xsd"
OUT=plurio.xml
MAILTO="david@raison.lu"
LOGFILE=plurio.log
TODAY=`date +%F`

echo "Running plurio.net export-and-verify... "
rm $OUT

`which php` $EXE 

[ -f $OUT ] && `which xmllint` --noout --schema $SCHEMA $OUT > ${LOGFILE} 2>&1

mail -s "plurio xml export for ${TODAY}" ${MAILTO} < ${LOGFILE}

cat ${LOGFILE}
