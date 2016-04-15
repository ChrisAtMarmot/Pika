#!/bin/sh

# This script is for moving a marc full export file from on the ftp server to data directory on the pika server

if [[ $# -ne 2 ]]; then
	echo "To use, add the ftp source directory for the first parameter, the data directory destination as the second parameter."
	echo "$0 source destination"
	echo "eg: $0 hoopla hoopla"
else

	# Source & Destination set by command line options
	SOURCE=$1
	DESTINATION=$2

	LOG="logger -t $0"
	# tag logging with script name and command line options

	$LOG "Testing the script."

	REMOTE="10.1.2.6:/ftp"
	LOCAL="/mnt/ftp"

	$LOG "~~ mount $REMOTE $LOCAL"
	mount $REMOTE $LOCAL

	if [ -d "$LOCAL/$SOURCE/" ]; then
		if [ -d "/data/vufind-plus/$DESTINATION/marc/" ]; then

		$LOG "~~ Copy fullexport marc file(s)."
		$LOG "~~ cp $LOCAL/$SOURCE/*.mrc /data/vufind-plus/$DESTINATION/marc/"
		cp $LOCAL/$SOURCE/*.mrc /data/vufind-plus/$DESTINATION/marc/

		if [ $? -ne 0 ]; then
			$LOG "~~ Moving marc files failed."
			echo "Moving marc files failed."
		fi

#TODO: Implement Old Marc File check. Need to change initial parameter test
#		if [[ -z $3 ]]; then
#			# Check that the Marc File(s) are newer than $3 days
#			OLDMARC=$(find /data/vufind-plus/$DESTINATION/marc/ -name "*.mrc" -mtime +$3)
#			if [ -n "$OLDMARC" ]; then
#				echo "There are Marc files older than $3 days : "
#				echo "$OLDMARC"
#			fi
#		fi

		else
			echo "Path /data/vufind-plus/$DESTINATION/marc/ doesn't exist."
		fi

	else
		echo "Path $LOCAL/$SOURCE/ doesn't exist."
	fi

	# Make sure we undo the mount every time it is mounted in the first place.
	$LOG "~~ umount $LOCAL"
	umount $LOCAL

	$LOG "Finished $0 $*"

fi