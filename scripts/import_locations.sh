#!/bin/bash

# Import location from geoname

GEONAME_DIR=tmp/geoname
BASE_DIR=`pwd`
. ./scripts/include.sh
for COUNTRY in "CA"
do
    FILE=$COUNTRY.txt
    ZIP=$COUNTRY.zip
    cd $GEONAME_DIR
    if [ -f $FILE ]
    then
       echo -e "\033[31m$FILE EXISTS\033[0m, maybe already imported"
       cd $BASE_DIR
    else
       wget http://download.geonames.org/export/dump/$COUNTRY.zip -O $ZIP
       unzip -o $ZIP
           rm $ZIP
       cd $BASE_DIR
       FILE=$GEONAME_DIR/$FILE
       f_launch_cmd "php app/console drassuom:import $FILE geoname_district1"
#       f_launch_cmd "php app/console nova:import $FILE geoname_district2"
#       f_launch_cmd "php app/console nova:import $FILE geoname_city"
#       f_launch_cmd "php app/console nova:import $FILE geoname_city --include=adm_city --include=capital"
       rm $FILE
    fi
done