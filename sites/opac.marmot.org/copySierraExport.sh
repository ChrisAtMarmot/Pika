#!/bin/sh

#Retrieve marc records from the FTP server
#mount 10.1.2.6:/ftp/sierra /mnt/ftp
# ftp1.marmot.org
mount 10.1.2.7:/ftp/sierra /mnt/ftp
# sftp.marmot.org server

cp --preserve=timestamps --update /mnt/ftp/fullexport.marc /data/vufind-plus/opac.marmot.org/marc/fullexport.mrc
umount /mnt/ftp

