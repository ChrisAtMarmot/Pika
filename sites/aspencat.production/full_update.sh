#!/bin/bash
# Script handles all aspects of a full index including extracting data from other systems.
# Should be called once per day.  Will interrupt partial reindexing.
#
# At the end of the index will email users with the results.
EMAIL=root@makhaon.marmot.org
PIKASERVER=aspencat.production
PIKADBNAME=pika
OUTPUT_FILE="/var/log/vufind-plus/${PIKASERVER}/full_update_output.log"

MINFILE1SIZE=$((955000000))

# Check for conflicting processes currently running
function checkConflictingProcesses() {
	#Check to see if the conflict exists.
	countConflictingProcesses=$(ps aux | grep -v sudo | grep -c "$1")
	countConflictingProcesses=$((countConflictingProcesses-1))

	let numInitialConflicts=countConflictingProcesses
	#Wait until the conflict is gone.
	until ((${countConflictingProcesses} == 0)); do
		countConflictingProcesses=$(ps aux | grep -v sudo | grep -c "$1")
		countConflictingProcesses=$((countConflictingProcesses-1))
		#echo "Count of conflicting process" $1 $countConflictingProcesses
		sleep 300
	done
	#Return the number of conflicts we found initially.
	echo ${numInitialConflicts};
}

#truncate the output file so you don't spend a week debugging an error from a week ago!
: > $OUTPUT_FILE;

#Check for any conflicting processes that we shouldn't do a full index during.
checkConflictingProcesses "koha_export.jar ${PIKASERVER}" >> ${OUTPUT_FILE}
checkConflictingProcesses "overdrive_extract.jar ${PIKASERVER}" >> ${OUTPUT_FILE}
checkConflictingProcesses "reindexer.jar ${PIKASERVER}" >> ${OUTPUT_FILE}

# Back-up Solr Master Index
mysqldump ${PIKADBNAME} grouped_work_primary_identifiers > /data/vufind-plus/${PIKASERVER}/grouped_work_primary_identifiers.sql
sleep 5m
tar -czf /data/vufind-plus/${PIKASERVER}/solr_master_backup.tar.gz /data/vufind-plus/${PIKASERVER}/solr_master/grouped/index/ >> ${OUTPUT_FILE}
rm /data/vufind-plus/${PIKASERVER}/grouped_work_primary_identifiers.sql

#Restart Solr
cd /usr/local/vufind-plus/sites/${PIKASERVER}; ./${PIKASERVER}.sh restart

#Extract from Hoopla
# No Aspencat libraries use hoopla, no need to copy them
#cd /usr/local/vufind-plus/vufind/cron;./GetHooplaFromMarmot.sh >> ${OUTPUT_FILE}

#Unite for Literacy
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} aspencat/unite_literacy unite_literacy/aspencat >> ${OUTPUT_FILE}

# Cloud Library
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} aspencat/cloudlibrary cloudlibrary/aspencat >> ${OUTPUT_FILE}

# EBSCO (CC of Aurora)
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} aspencat/ebsco/cca ebsco/cca >> ${OUTPUT_FILE}

#Colorado State Government Documents Updates
curl --remote-name --remote-time --silent --show-error --compressed --time-cond /data/vufind-plus/colorado_gov_docs/marc/fullexport.mrc https://cassini.marmot.org/colorado_state_docs.mrc

#Extract Lexile Data
cd /data/vufind-plus/; curl --remote-name --remote-time --silent --show-error --compressed --time-cond /data/vufind-plus/lexileTitles.txt https://cassini.marmot.org/lexileTitles.txt

#Extract AR Data
cd /data/vufind-plus/accelerated_reader; curl --remote-name --remote-time --silent --show-error --compressed --time-cond /data/vufind-plus/accelerated_reader/RLI-ARDataTAB.txt https://cassini.marmot.org/RLI-ARDataTAB.txt

#Do a full extract from OverDrive just once a week to catch anything that doesn't
#get caught in the regular extract
DAYOFWEEK=$(date +"%u")
if [ "${DAYOFWEEK}" -eq 6 ];
then
	cd /usr/local/vufind-plus/vufind/overdrive_api_extract/
	nice -n -10 java -server -XX:+UseG1GC -jar overdrive_extract.jar ${PIKASERVER} fullReload >> ${OUTPUT_FILE}
fi

#TODO: Use new nightly export
## Copy Export from ILS
/usr/local/vufind-plus/sites/${PIKASERVER}/copyExport.sh >> ${OUTPUT_FILE}
#YESTERDAY=`date +%Y%m%d --date="yesterday"`
#UPDATEFILE=/data/vufind-plus/${PIKASERVER}/marc/ascc-catalog-deleted.$YESTERDAY.marc
#DELETEFILE=/data/vufind-plus/${PIKASERVER}/marc/ascc-catalog-updated.$YESTERDAY.marc

#if [[ -f $UPDATEFILE && -f $DELETEFILE ]]; then
#	# if the update and delete files are found, merge them into the fullexport file.
#	echo "Merging updates and deletes." >> ${OUTPUT_FILE}
#	cd /usr/local/vufind-plus/vufind/cron/; java -jar cron.jar ${PIKASERVER} MergeMarcUpdatesAndDeletes >> ${OUTPUT_FILE}
##	NUMOFADDSORDELETEFILESREMAINING=$(ls *.marc|wc -l)
##	if [ $NUMOFADDSORDELETEFILESREMAINING -gt 0 ]; then
##		echo "There are $NUMOFADDSORDELETEFILESREMAINING Add or Delete files left after merge process";
##	fi
#else
#		if [ ! -f $UPDATEFILE ]; then
#		 echo "Update File $UPDATEFILE was not found." >> ${OUTPUT_FILE}
#		fi
#		if [ ! -f $DELETEFILE ]; then
#		 echo "Delete File $DELETEFILE was not found." >> ${OUTPUT_FILE}
#		fi
#	echo "Not merging updates and deletes." >> ${OUTPUT_FILE}
#fi

# if the update/delete files aren't found merging won't occur, which would have updated the timestamp on the fullexport file.
# therefore the next if block, is a good check for everyday of the week.
FILE=$(find /data/vufind-plus/${PIKASERVER}/marc/ -name fullexport.mrc -mtime -1 | sort -n | tail -1)
if [ -n "$FILE" ]; then
  #check file size
	FILE1SIZE=$(wc -c <"$FILE")
	if [ $FILE1SIZE -ge $MINFILE1SIZE ]; then

		echo "Latest full export file is " $FILE >> ${OUTPUT_FILE}
		DIFF=$(($FILE1SIZE - $MINFILE1SIZE))
		PERCENTABOVE=$((100 * $DIFF / $MINFILE1SIZE))
		echo "The export file is $PERCENTABOVE (%) larger than the minimum size check." >> ${OUTPUT_FILE}

		#Validate the export
		cd /usr/local/vufind-plus/vufind/cron; java -server -XX:+UseG1GC -jar cron.jar ${PIKASERVER} ValidateMarcExport >> ${OUTPUT_FILE}

		#Full Regroup
		cd /usr/local/vufind-plus/vufind/record_grouping; java -server -Xmx6G -XX:+UseG1GC -jar record_grouping.jar ${PIKASERVER} fullRegroupingNoClear >> ${OUTPUT_FILE}

		#Full Reindex
		cd /usr/local/vufind-plus/vufind/reindexer; nice -n -3 java -server -XX:+UseG1GC -jar reindexer.jar ${PIKASERVER} fullReindex >> ${OUTPUT_FILE}

		# Delete any exports over 7 days
		find /data/vufind-plus/${PIKASERVER}/marc_backup/ -mindepth 1 -maxdepth 1 -name *.marc -type f -mtime +7 -delete

		# Truncate Continuous Reindexing list of changed items
		cat /dev/null >| /data/vufind-plus/${PIKASERVER}/marc/changed_items_to_process.csv

		NEWLEVEL=$(($FILE1SIZE * 97 / 100))
		echo "" >> ${OUTPUT_FILE}
		echo "Based on today's export file, a new minimum filesize check level should be set to $NEWLEVEL" >> ${OUTPUT_FILE}

	else
		echo $FILE " size " $FILE1SIZE "is less than minimum size :" $MINFILE1SIZE "; Export was not moved to data directory, Full Regrouping & Full Reindexing skipped." >> ${OUTPUT_FILE}
	fi
else
	echo "Did not find a export file from the last 24 hours, Full Regrouping & Full Reindexing skipped." >> ${OUTPUT_FILE}
#TODO: update this error message when export is setup
#	echo "The full export file has not been updated in the last 24 hours, meaning the full export file or the add/deletes files were not delivered. Full Regrouping & Full Reindexing skipped." >> ${OUTPUT_FILE}
#	echo "The full export is delivered Saturday Mornings. The adds/deletes are delivered every night except Friday night." >> ${OUTPUT_FILE}
fi

# Clean-up Solr Logs
find /usr/local/vufind-plus/sites/default/solr/jetty/logs -name "solr_log_*" -mtime +7 -delete
find /usr/local/vufind-plus/sites/default/solr/jetty/logs -name "solr_gc_log_*" -mtime +7 -delete

#Restart Solr
cd /usr/local/vufind-plus/sites/${PIKASERVER}; ./${PIKASERVER}.sh restart

#Email results
FILESIZE=$(stat -c%s ${OUTPUT_FILE})
if [[ ${FILESIZE} > 0 ]]
then
	# send mail
	mail -s "Full Extract and Reindexing - ${PIKASERVER}" $EMAIL < ${OUTPUT_FILE}
fi

