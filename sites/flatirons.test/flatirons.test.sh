#!/bin/sh
# set local configuration for starting Solr and then start solr
#Replace {servername} with your server name and save in sites/{servername} as {servername.sh}

# needed for cases where multiple solr engines are running at the same time
# (eg. for several Pika instances)
##################################################
# Find directory function
##################################################
findDirectory()
{
OP=$1
shift
for L in $* ; do
[ $OP $L ] || continue
echo $L
break
done
}

#################################################
# Setup the call to start solr
################################################## 
export VUFIND_HOME=/usr/local/vufind-plus/sites/flatirons.test
export JETTY_HOME=/usr/local/vufind-plus/sites/default/solr/jetty

# marmot test instance of solr engine
export SOLR_HOME=/data/vufind-plus/flatirons.test/solr
export JETTY_PORT=8085

# check the right instances
JETTY_RUN=`findDirectory -w /var/run /usr/var/run /tmp`
export JETTY_RUN
export JETTY_PID=$JETTY_RUN/flatirons.test.pid

#Max memory should be at least the size of all solr indexes combined. 
export JAVA_OPTIONS="-server -Xms1024m -Xmx6144m -XX:+UseParallelGC -XX:NewRatio=5"
export JETTY_LOG=/var/log/flatirons.test/logs/jetty

exec /usr/local/vufind-plus/sites/default/vufind.sh $1 $2