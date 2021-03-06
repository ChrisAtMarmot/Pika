#!/bin/bash
# Mark Noble, Marmot Library Network
# James Staub, Nashville Public Library
# 20150218
# Script executes continuous re-indexing.
# Millennium 1.6_3

# CONFIGURATION
# PLEASE SET CONFLICTING PROCESSES AND PROHIBITED TIMES IN FUNCTION CALLS IN SCRIPT MAIN DO LOOP
# this version emails script output as a round finishes
EMAIL=root@dione
PIKASERVER=aacpl.test
OUTPUT_FILE="/var/log/vufind-plus/${PIKASERVER}/extract_and_reindex_output.log"

# Check for conflicting processes currently running
function checkConflictingProcesses() {
	#Check to see if the conflict exists.
	countConflictingProcesses=$(ps aux | grep -v sudo | grep -c "$1")
	#subtract one to get rid of our grep command
	countConflictingProcesses=$((countConflictingProcesses-1))

	let numInitialConflicts=countConflictingProcesses
	#Wait until the conflict is gone.
	until ((${countConflictingProcesses} == 0)); do
		countConflictingProcesses=$(ps aux | grep -v sudo | grep -c "$1")
		#subtract one to get rid of our grep command
		countConflictingProcesses=$((countConflictingProcesses-1))
		#echo "Count of conflicting process" $1 $countConflictingProcesses
		sleep 300
	done
	#Return the number of conflicts we found initially.
	echo ${numInitialConflicts};
}

# Prohibited time ranges - for, e.g., ILS backup
function checkProhibitedTimes() {
	start=$(date --date=$1 +%s)
	stop=$(date --date=$2 +%s)
	NOW=$(date +%H:%M:%S)
	NOW=$(date --date=$NOW +%s)

	let hasConflicts=0
	if (( $start < $stop ))
	then
		if (( $NOW > $start && $NOW < $stop ))
		then
			#echo "Sleeping:" $(($stop - $NOW))
			sleep $(($stop - $NOW))
			hasConflicts = 1
		fi
	elif (( $start > $stop ))
	then
		if (( $NOW < $stop ))
		then
			sleep $(($stop - $NOW))
			hasConflicts = 1
		elif (( $NOW > $start ))
		then
			sleep $(($stop + 86400 - $NOW))
			hasConflicts = 1
		fi
	fi
	echo ${hasConflicts};
}

while true
do
	#####
	# Check to make sure this is a good time to run.
	#####

	# Make sure we are not running a Full Record Group/Reindex process
	hasConflicts=$(checkConflictingProcesses "full_update.sh")
	#If we did get a conflict, restart the loop to make sure that all tests run
	if (($? != 0)); then
		continue
	fi

	# Do not run while the export from symphony is running to prevent inconsistencies with MARC records
	# export starts at 10 pm the file is copied to the FTP server at about 11:40
	#TODO verify this is correct
	hasConflicts=$(checkProhibitedTimes "21:50" "23:40")
	#If we did get a conflict, restart the loop to make sure that all tests run
	if (($? != 0)); then
		continue
	fi

	#####
	# Start of the actual indexing code
	#####

	#truncate the file
	: > $OUTPUT_FILE;

	#echo "Starting new extract and index - `date`" > ${OUTPUT_FILE}
	# reset the output file each round

	#Fetch partial updates from FTP server
	mount 10.1.2.7:/ftp/aacpl /mnt/ftp >> ${OUTPUT_FILE}
	find /mnt/ftp/symphony-updates -maxdepth 1 -mmin -60 -name *.mrc| while FILES= read FILE; do
		#Above find is for test only. Copy any partial exports from the last 30 minutes because of the moving out the partials is only done in production

		#find /mnt/ftp/symphony-updates -maxdepth 1 -name *.mrc| while FILES= read FILE; do
		#Above find is for production only. Copy any partial exports from the last 30 minutes
		# Note: the space after the equals is important in  "while FILES= read FILE;"
		if test "`find $FILE -mmin -1`"; then
			echo "$FILE was modified less than 1 minute ago, waiting to copy "
		else
			cp --update --preserve=timestamps $FILE /data/vufind-plus/${PIKASERVER}/marc_updates/ >> ${OUTPUT_FILE}
		fi
	done

	#Get orders file from the FTP server
	cp --update --preserve=timestamps /mnt/ftp/PIKA-onorderfile.txt /data/vufind-plus/${PIKASERVER}/marc/ >> ${OUTPUT_FILE}

	umount /mnt/ftp >> ${OUTPUT_FILE}

	#Get holds files from Google Drive
	cd /data/vufind-plus/aacpl.test/marc
#	wget -q "https://drive.google.com/uc?export=download&id=0B_xqNQMfUrAzanJUZkNXekgtU2s" -O "Pika_Hold_Periodicals.csv"
#	wget -q "https://drive.google.com/uc?export=download&id=0B_xqNQMfUrAzNGJrajJzQWs3ZGs" -O "Pika_Holds.csv"
	wget -q "https://drive.google.com/uc?export=download&id=1OOS8p8cZcWoHPVnt1jQlpcR9NwRnjlam" -O "Pika_Hold_Periodicals.csv"
	wget -q "https://drive.google.com/uc?export=download&id=1aT8jXgDd3jG0K3xTYYcFi0hgBRWW6fj2" -O "Pika_Holds.csv"

	cd /usr/local/vufind-plus/vufind/symphony_export/
	java -server -jar symphony_export.jar  ${PIKASERVER} >> ${OUTPUT_FILE}

	#export from overdrive
	#echo "Starting OverDrive Extract - `date`" >> ${OUTPUT_FILE}
	cd /usr/local/vufind-plus/vufind/overdrive_api_extract/
	nice -n -10 java -server -XX:+UseG1GC -jar overdrive_extract.jar ${PIKASERVER} >> ${OUTPUT_FILE}

	#run reindex
	#echo "Starting Reindexing - `date`" >> ${OUTPUT_FILE}
	cd /usr/local/vufind-plus/vufind/reindexer
	nice -n -5 java -server -XX:+UseG1GC -jar reindexer.jar ${PIKASERVER} >> ${OUTPUT_FILE}

	# add any logic wanted for when to send the emails here. (eg errors only)
	FILESIZE=$(stat -c%s ${OUTPUT_FILE})
	if [[ ${FILESIZE} > 0 ]]
	then
			# send mail
			mail -s "Continuous Extract and Reindexing - ${PIKASERVER}" $EMAIL < ${OUTPUT_FILE}
	fi

		#end block
done
