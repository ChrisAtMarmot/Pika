#!/bin/sh
# set local configuration for starting Solr and then start solr
# Replace {servername} with your server name and save in sites/{servername} as {servername.sh} 
export VUFIND_HOME=/usr/local/VuFind-Plus/sites/catalog.library.nashville.org
export JETTY_HOME=/usr/local/VuFind-Plus/sites/default/solr/jetty
export SOLR_HOME=/data/vufind-plus/catalog.library.nashville.org/solr     
export JETTY_PORT=8080
# Max memory should be at least he size of all solr indexes combined. 
export JAVA_OPTIONS="-server -Xms1g -Xmx16g -XX:+UseParallelGC -XX:NewRatio=5"
export JETTY_LOG=/var/log/vufind-plus/catalog.library.nashville.org/jetty

exec /usr/local/VuFind-Plus/sites/default/vufind.sh $1 $2
