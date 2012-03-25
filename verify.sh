#!/bin/bash

URL="http://www.hackerspace.lu/plurio/"
SCHEMA=plurio.xsd
OUT=plurio.xml

`which wget` -O $OUT $URL

[ -f $OUT ] && `which xmllint` --noout --schema $SCHEMA $OUT
