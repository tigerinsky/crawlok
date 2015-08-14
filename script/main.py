#!/user/bin/env python
#coding: utf-8

import os
import sys
import time
import log
import ConfigParser
import spider

reload(sys)
sys.setdefaultencoding('utf8')

if __name__ == "__main__":
    cf = ConfigParser.ConfigParser()
    #cf.read("./conf/nice.conf")
    cf.read(sys.argv[1])
    #初始logging
    log.init_log(cf.get("nice", "BASE") + cf.get("nice", "LOG_FILE"), log.logging.INFO)
    log.logging.info("read conf ok [%s]" % time.strftime('%Y-%m-%d %H:%M:%S', time.localtime(time.time())))
   
    crawled_avatar_conf = sys.argv[2]
    crawled_pic_conf = sys.argv[3]
    Spider = spider.Spider(cf)
    #读取种子用户和已爬取的头像和图片
    Spider.prepare(crawled_avatar_conf, crawled_pic_conf)

    #爬取
    time_now = int(time.time())
    log.logging.info("spider nice job start [%s]", time.strftime('%Y-%m-%d %H:%M:%S', time.localtime(time_now)))
    Spider.work(time_now)

    #保存用户爬取情况
    Spider.finish()

    log.logging.info("spider nice job done [%s]" % time.strftime('%Y-%m-%d %H:%M:%S', time.localtime((time.time()))))
