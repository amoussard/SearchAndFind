#!/bin/bash

# Import location from geoname

GEONAME_DIR=tmp/geoname
BASE_DIR=`pwd`
. ./scripts/include.sh

f_launch_cmd "php app/console drassuom:import tmp/geoname/continents.txt geoname_continent"
f_launch_cmd "php app/console drassuom:import tmp/geoname/countries.txt geoname_country"

for COUNTRY in "CA" "FR" "US"
do
    FILE=$COUNTRY.txt
    ZIP=$COUNTRY.zip
    cd $GEONAME_DIR
    if [ -f $FILE ]
    then
       echo -e "\033[31m$FILE EXISTS\033[0m, maybe already imported"
    else
       wget http://download.geonames.org/export/dump/$COUNTRY.zip -O $ZIP
       unzip -o $ZIP
           rm $ZIP
    fi
    cd $BASE_DIR
    FILE=$GEONAME_DIR/$FILE
    f_launch_cmd "php app/console drassuom:import $FILE geoname_country2"
    f_launch_cmd "php app/console drassuom:import $FILE geoname_adm1"
    f_launch_cmd "php app/console drassuom:import $FILE geoname_adm2"
#       f_launch_cmd "php app/console nova:import $FILE geoname_city"
#       f_launch_cmd "php app/console nova:import $FILE geoname_city --include=adm_city --include=capital"
#       rm $FILE
done