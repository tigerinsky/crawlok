<?php

#****************** 爬取的结果存放路径************************
$base_path = "/home/meihua/dingchuan/gitnice/crawlNice/result";
$avatar_files_path = $base_path."/avatar";
$img_files_path = $base_path."/imgs";
$user_info_file = $base_path."/users/user_info";
$img_des_file = $base_path.'/imgdes/img_info';

#****************** 商圈相关的数据 ***************************
#$business_area_info_file ='/home/meihua/dingchuan/upload/conf/data/business_area_info.bj';
$business_area_info_file = dirname(__FILE__).'/data/business_area_info.ll.area.utf8';

#****************** mysql 连接配置 ***************************
$mysql_conf = array(
    "host" => "",
	"user" => "",
	"passwd" => "",
    "db" => ""
);

?>
