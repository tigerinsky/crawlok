#!/usr/bin/env python
#coding: utf-8
'''
Created on 2015-06-24

@author: root
'''
import os
import traceback
import errno
import sys
import json
import urllib2
import time
import random
from StringIO import StringIO
import gzip
import re
from bs4 import BeautifulSoup
import Queue
import ConfigParser
import hashlib
import log
import niceio

reload(sys)
sys.setdefaultencoding('utf8')

class Spider(object):
    def __init__(self, cf):
        self._cf = cf
        self._reader = niceio.Reader()
        self._writer = niceio.Writer()
        self._crawled_keys = {}

    def prepare(self, crawled_avatar_conf, crawled_img_conf):
        #读取种子用户和已爬取用户
        self._user_file = self._cf.get("nice", "BASE") + self._cf.get("nice", "USERS_FILE")
        self._user_queue = self._reader.read_queue(self._user_file)
        self._crawled_user_file = self._cf.get("nice", "BASE") + self._cf.get("nice", "CRAWLED_USERS_FILE")
        #self._crawled_dict = self._reader.read_dict(self._crawled_user_file)
        self._crawled_dict = {}
        log.logging.info("user_queue size = %d" % (self._user_queue.qsize()))
        #读取结果保存路径
        self._avatar_dir = self._cf.get("nice", "BASE") + self._cf.get("nice", "AVATAR_DIR")
        self._mkdir_p(self._avatar_dir)
        self._imgs_dir = self._cf.get("nice", "BASE") + self._cf.get("nice", "IMGS_DIR")
        self._mkdir_p(self._imgs_dir)
        self._users_dir = self._cf.get("nice", "BASE") + self._cf.get("nice", "USERS_DIR")
        self._mkdir_p(self._users_dir)
        self._imgdes_dir = self._cf.get("nice", "BASE") + self._cf.get("nice", "IMGDES_DIR")
        self._mkdir_p(self._imgdes_dir)

        #读取已经爬取的用户头像和图片的签名
        self._crawled_keys = self._reader.read_dict_col2(crawled_img_conf)
        self._crawled_avatar_keys = self._reader.read_dict_col2(crawled_avatar_conf)

    def work(self, time_now):
        #单张图片 url = 'http://www.oneniceapp.com/show/140001000'
        #用户     url = 'http://www.oneniceapp.com/user/7p7H7F'
        #首页图片 url = 'http://www.oneniceapp.com/user/7p7H7F/shows/40/0'
    
        nice_host = "www.oneniceapp.com"
        user_info = []
        img_info = []
        user_num = 0
        new_user_dict = {}
        while not self._user_queue.empty():
            user = self._user_queue.get()
            log.logging.info("processing: %s" % user)
            if user in self._crawled_dict:
                continue
            self._crawled_dict[user] = 1
            
            # 处理用户主页
            user_url = 'http://www.oneniceapp.com/user/' + user
            user_data = self._download(nice_host, user_url)
            if not user_data:
                log.logging.warning("get user [%s] home null" % user)
                continue
            (user_name, gender, loc, avatar_url, nums) = self._parse_user(user_data)
            pic_host = avatar_url.split("//")[1].split("/")[0] 
            avatar_md5 = self._calc_md5("avatar_" + user);

            need_add_user = 0
            faxian_user_id = -1
            # download用户头像并保存
            if avatar_md5 not in self._crawled_avatar_keys:
                need_add_user = 1
                pic_data = self._download(pic_host, avatar_url)
                if not pic_data:
                    continue
                self._write_file(self._avatar_dir, user + "_avatar.jpg", pic_data)
            else:
                faxian_user_id = self._crawled_avatar_keys[avatar_md5]
            
            info_list = [user, user_name, gender, loc]
            info_list.extend(nums)
            info_list.append(user + "_avatar.jpg")
            info_list.append(avatar_md5)
            info_list.append(faxian_user_id)
            info_list.append(need_add_user)
    
            # 获取用户的图片
            img_dir = self._imgs_dir + "/" + user
            self._mkdir_p(img_dir)
            nextkey = "0"
            img_num = 0
            process_time = 0
            while len(nextkey)!= 0 and process_time < 1:
                img_url = "http://www.oneniceapp.com/user/%s/shows/40/%s" % (user, nextkey)
                img_data = self._download(nice_host, img_url)
                if not img_data:
                    break
                (nextkey, img_dict) = self._parse_json(img_data)
                log.logging.info(img_url + " pic_num: " + str(len(img_dict)))
                #print len(img_dict)
                img_num += self._save_user_img(nice_host, img_dir, img_dict, img_info, new_user_dict)
                process_time += 1
    
            # 有有效的图片信息才增添用户信息
            user_info.append(info_list)
            user_num += 1
            sleep_time = random.uniform(0, 3)
            log.logging.info("user sleeping %f" % sleep_time)
            time.sleep(sleep_time)
    
        #保存用户信息
        self._dump_dict("%s/user_info" % (self._users_dir), user_info)
        #保存图片信息
        self._dump_dict("%s/img_info" % (self._imgdes_dir), img_info)

    def finish(self):
        #保存用户爬取情况
        self._writer.write_dict_keys(self._crawled_dict, self._crawled_user_file)
        #self._writer.write_queue(self._user_queue, self._user_file)

    def _calc_md5(self, key_buf):
        #key_buf = str(img_id) + str(uid) + str(pic_url)
        m = hashlib.md5()
        m.update(key_buf)
        return m.hexdigest()

    def _save_user_img(self, nice_host, img_dir, img_dict, img_info, new_user_dict):
        img_num = 0
        for value in img_dict.values():
            img_id = value[0]
            uid = value[1]
            pic_url = value[-1]
            pic_host = pic_url.split("//")[1].split("/")[0]
            key_buf = str(img_id) + str(uid) + str(pic_url)
            md5_sign = self._calc_md5(key_buf)
            # 已经爬取过
            if md5_sign in self._crawled_keys:
                log.logging.info("img_id[%s] uid[%s] sign[%s] do not crawled repeatly" % (img_id, uid, md5_sign))
                continue
            
            value.append(md5_sign)#追加md5信息
            #download图片文件并保存
            sleep_time = random.uniform(0, 3)
            log.logging.debug("pic[%d] %s sleeping %f" % (img_num, pic_url, sleep_time))
            time.sleep(sleep_time)
            pic_data = self._download(pic_host, pic_url)
            if not pic_data:
                continue
            self._write_file(img_dir, uid +"_" + img_id + ".jpg", pic_data)
            log.logging.debug("write down pic : " + uid+"_"+img_id+".jpg")

            # 解析图片
            pic_show_url = 'http://www.oneniceapp.com/show/' + img_id
            pic_show_data = self._download(nice_host, pic_show_url)
            log.logging.info("download %s" % pic_show_url)
            praise_num = 0
            if pic_show_data:
                (res, img_alt, img_url, praise_num, next_url, new_users) = self._parse_img(pic_show_data)
                if res is False:
                    continue
                log.logging.info("parse img : %s len(new_users) = %d" % (pic_show_url, len(new_users)))
                value.append(praise_num)#追加点赞信息
                # 图片描述信息
                img_info.append(value)
                # 添加新用户到需要爬取的队列中
                #for new_user in new_users:
                #    if self._user_queue.full():
                #        break
                #    if new_user not in self._crawled_dict and new_user not in new_user_dict:
                #        self._user_queue.put(new_user)
                #        new_user_dict[new_user] = 1
            img_num += 1
        return img_num

    def _write_file(self,filepath, filename, data):
        try:
            #path = os.path.dirname(os.path.abspath(__file__))
            #config_path = os.path.join(path, 'config')
            config_file = os.path.join(filepath, filename)
            fw = open(config_file, 'w')
            fw.write(data)
            fw.flush()
            os.fsync(fw)
            fw.close()
        except Exception, e:
            print e

    def _get_html_tag(self, html_doc, tag_name, attrs_key, attrs_value):
        return html_doc.find(tag_name, attrs={attrs_key : attrs_value}).get_text()

    def _parse_user(self, data):
        '''解析用户界面
        url为http://www.oneniceapp.com/user/aaBtaY
        '''
        try:
            html_doc = BeautifulSoup(data, from_encoding='utf-8')
            #用户名称
            user_name = self._get_html_tag(html_doc, "h2", "class", "user-name")
            #loc-info
            loc_info = self._get_html_tag(html_doc, "div", "class", "loc-info")
            loc_info_list = loc_info.split(u'，')#注意是中文的"，"
            if len(loc_info_list) == 2:
                gender = loc_info_list[0]
                loc = loc_info_list[1]
            else:
                gender = ''
                loc = loc_info
            #用户头像
            avatar_img = html_doc.find("img", attrs={"class":"bd-rs-normal avatar "})
            avatar_url = avatar_img.get("src")
    
            #照片数, 点赞等等
            user_data = html_doc.find("ul", attrs={"class" : "widget-dataset clearfix user-data"})
            num_spans = user_data.find_all("span", attrs={"class" : "num"})
            #照片, 标签, 关注, 粉丝
            nums = [int(item.get_text()) for item in num_spans]
            return (user_name, gender, loc, avatar_url, nums)
        except Exception as e:
            print e
   
    # 去除"@张三 "这个@功能
    def _replace_at(self, content):
        at_pos = content.find('@')
        blank_pos = -1
        if at_pos != -1:
            blank_pos = content.find(' ', at_pos)
        while at_pos != -1:
            if blank_pos == -1: # 则取blank_pos为最末
                blank_pos = len(content) - 1
            sub_str = content[at_pos : blank_pos + 1]
            content = content.replace(sub_str, '')
            at_pos = content.find('@')
            blank_pos = -1
            if at_pos != -1:
                blank_pos = content.find(' ', at_pos)
        return content

    def _parse_json(self, data):
        '''解析 http://www.oneniceapp.com/user/7p7H7F/shows/40/0
           这个接口返回的json格式的图片数据
        '''
        try:
            result_dict = dict()
            data = json.loads(data)
            nextkey = data['data']['nextkey']

            imgs = data['data']['shows']
            for img in imgs:
                img_id = img.get("id")
                uid = img.get("uid")
                #content = img.get("content").decode('uft-8', "ignore").replace('\n', ' ')
                content = img.get("content").replace('\n', ' ')
                content = self._replace_at(content)
                latitude = img.get("latitude")
                longitude = img.get("longitude")
                add_time = img.get("add_time")
                pic_url = img.get("pic_url")
                result_dict[img_id] = [img_id, uid, content, latitude, longitude, add_time, pic_url]
            return (nextkey, result_dict)
        except Exception as e:
            print e
    
    def _parse_img(self, data):
        '''解析单张图片展示的页面
           http://www.oneniceapp.com/show/kfnUYT
        '''
        try:
            soup = BeautifulSoup(data, from_encoding="utf-8")
            #print soup.title
            
            #当前图片
            img_divs = soup.find_all("div", attrs={"class" : "wrap-img loading-block"})
            for item in img_divs:
                #图片url
                img = item.img
                if not img:
                    continue
   
                # 如下的标签会经常变，如原始图片就从data-original变为了src
                #img_url = img.get('data-original').strip().rstrip()
                img_url = img.get('src').strip().rstrip()
                img_name = img_url.split('/')[-1].split('.')[0]
    
                #图片属性
                img_alt = img.get('alt').strip().rstrip()
    
                #下一张图片的url
                next_a = item.find("a", attrs = {"class" : "arrow next"})
                if next_a:
                    next_url = "http://www.oneniceapp.com" + next_a.get('href')
                else:
                    next_url = ''
            #当前图片的点赞数
            praise_num = 0
            praise_span = soup.find("span", attrs = {"class": "bd-rs-normal praise-num"})
            if praise_num:
                praise_num = int(praise_span.contents[0])
            #获取点赞的用户
            new_users = []
            praise_users = soup.find_all("span", attrs = {"class" : "widget-user-avatar"})
            if praise_users:
                for i in praise_users:
                    #<a href="/user/aaBtaY">
                    if i.a:
                        new_users.append(i.a.get('href').split("/")[-1])
            else:
                log.logging.warning("praise_users is none")
            return (True, img_alt, img_url, praise_num, next_url, new_users)
        except Exception, e:
            log.logging.error(e)
            return (False, "", "", 0, "", [])
    
    def _download(self, host, url):
        '''单纯的下载给定url的内容
           @return 页面内容
                        print i.a.get('href')
        '''
        try:
            headers = {
                    "Accept":"text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
                    "Accept-Encoding":"gzip, deflate, sdch",
                    "Accept-Language":"zh-CN,zh;q=0.8,en;q=0.6",
                    "Cache-Control":"no-cache",
                    "Connection":"keep-alive",
                    "Cookie":"lang=zh-cn; nuid=CgoKDFVR9QY9zYRhc4acAg==; Hm_lvt_0a615c950ee719977112f4bc5166ea0c=1431434641,1431760661; Hm_lpvt_0a615c950ee719977112f4bc5166ea0c=1431761165",
                    "Host":host,
                    "Pragma":"no-cache",
                    "User-Agent":"Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2272.118 Safari/537.36"
            }
            
            request = urllib2.Request(url=url, headers=headers)
            fp = urllib2.urlopen(request, timeout=5)
    
            if fp.info().get('Content-Encoding') == 'gzip':
                buf = StringIO( fp.read())
                f = gzip.GzipFile(fileobj=buf)
                data = f.read().encode('utf-8')
            else:
                data = fp.read()
            fp.close()
            return data
                
        except Exception, e:
            log.logging.error(e)
            return None
    
    def _dump_dict(self, output_file, info_dict):
        with open(output_file, "w") as fp_out:
            for item in info_dict:
                fp_out.write('\t'.join(str(i) for i in item) + '\n')
    
    def _mkdir_p(self, path):
        try:
            os.makedirs(path)
        except OSError as exc:
            if exc.errno == errno.EEXIST and os.path.isdir(path):
                log.logging.info(path + "exists")
    

