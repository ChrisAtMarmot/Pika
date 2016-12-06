#!/bin/bash
# Copy Aspencat Extracts from ftp server
# runs after files are received on the ftp server
#-------------------------------------------------------------------------
# 19 Dec 14 sml expanded script to copy updated & deleted marc files from
#               ftp server and added variable/constants declarations
# 20 Dec 14 sml added output to track progression of the script as it runs
#-------------------------------------------------------------------------
# declare variables and constants
#-------------------------------------------------------------------------
REMOTE="10.1.2.6:/ftp"
LOCAL="/mnt/ftp"
DEST="/data/vufind-plus/aspencat.test/marc"
DATE=`date +%Y%m%d --date="yesterday"`
LOG="logger -t copyExport "

#-------------------------------------------------------------------------

$LOG "~> starting copyExport.sh"

$LOG "~~ remove old deleted and updated marc record files"
rm -f $DEST/ascc-catalog-deleted.* $DEST/ascc-catalog-updated.*
$LOG "~~ exit code " $?

$LOG "~~ mount $REMOTE $LOCAL"
mount $REMOTE $LOCAL
$LOG "~~ exit code " $?

$LOG "~~ unzip ascc-catalog-full marc file to fullexport.mrc"
gunzip -c $LOCAL/aspencat/ascc-catalog-full.marc.gz > $DEST/fullexport.mrc
$LOG "~~ exit code " $?

$LOG "~~ copy ascc-catalog-deleted marc file"
cp $LOCAL/aspencat/ascc-catalog-deleted.$DATE.marc $DEST
$LOG "~~ exit code " $?

$LOG "~~ copy ascc-catalog-updated marc file"
cp $LOCAL/aspencat/ascc-catalog-updated.$DATE.marc $DEST
$LOG "~~ exit code " $?

$LOG "~~ umount $LOCAL"
umount $LOCAL
$LOG "~~ exit code " $?

$LOG "~> finished copyExport.sh"

#-------------------------------------------------------------------------
#-- eof --
