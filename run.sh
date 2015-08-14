#!/bin/bash
. ./conf/conf.sh

mkdir -p ${LOG_DIR}
rm -rf ${LOG_DIR}*

timestamp=`date +%s`
img_info="./result/imgdes/img_info"
user_info="./result/users/user_info"
if [ -f ${img_info} ]
then
    mv ${img_info} ${img_info}.${timestamp}
fi
if [ -f ${user_info} ]
then
    mv ${user_info} ${user_info}.${timestamp}
fi

date
echo 'get crawled info from mysql'
#******** get crawled info from mysql *****
sh ${SCRIPT_DIR}/get_data.sh \
    ${CRAWLED_AVATAR_CONF} \
    ${CRAWLED_PIC_CONF}

#******** crawl *****
date
echo 'spidering ...'
python ${SCRIPT_DIR}/main.py \
    ./conf/spider.conf \
    ${CRAWLED_AVATAR_CONF} \
    ${CRAWLED_PIC_CONF}

date
echo 'uploading ...'
#******** upload data *********
php -c /home/meihua/php/etc/php.ini ./upload/upload.php

#cp ./data/users.2.bak ./data/users.2
