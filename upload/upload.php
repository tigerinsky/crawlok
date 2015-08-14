<?php

require_once dirname(__FILE__).'/conf/conf.php';
require_once dirname(__FILE__).'/oss/oss.php';
require_once dirname(__FILE__).'/mysql_operator.php';
require_once dirname(__FILE__).'/uis/uis_client.php';
require_once dirname(__FILE__).'/RedisProxy.php';

$spec_province = array("北京", "天津", "上海", "重庆");

//请求uis获取id
function get_id($topic) {
    $uis_client = new UisClient();
    $uid = $uis_client->get_id($topic);
    unset($uis_client);
    return $uid;
}

//读取图片
function read_img($file_path, $file_name) {
    $filename = $file_path."/".$file_name;
    $handle = fopen($filename, "rb");

    //通过filesize取得文件大小，将整个文件一下子读到一个字符串中
    $contents = fread($handle, filesize($filename));
    fclose($handle);
    return $contents;
}

//解析每一个用户
function parse_line($line_buffer) {
    $user_info = explode("\t", $line_buffer);
    $loc_info = explode(" ", $user_info[3]);
    //$loc_info = generate_user_loc();
    $loc = array('', '');
    if (count($loc_info) == 2) {
        $loc = array("province" => $loc_info[0], "city" => $loc_info[1]);
    }
    $user_info[3] = $loc;
    return $user_info;
}

//添加用户信息到user表
//与前端约定机器人账号的login_type为2
function add_user($mysql) {
    $table = 'ci_user';
    $pass_word = md5("1234");
    $sql_insert = sprintf("insert into %s(`pass_word`, `pass_mark`, `login_type`, `create_time`) values('%s', '%s', '%s', '%s');", $table, $pass_word, "1234", "2", time());
    $mysql->connect();
    $ret = $mysql->execute_sql_mysql($sql_insert);
    $last_id = $mysql->last_insert_id();
    $mysql->dis_connect();
    return $last_id;
}

// 上传用户详情
function add_user_detail($mysql, $uid, $user_info, $img) {
    $table = 'ci_user_detail';
    $gender = ($user_info[2] == "女") ? 2 : 1;
    $sql_insert = sprintf("insert into %s(`uid`, `sname`, `avatar`, `province`, `city`, `intro`, `school`, `ukind`, `ukind_verify`) values('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');", $table, $uid, $user_info[1], $img, $user_info[3]["province"], $user_info[3]["city"], "", "", "0", "0");
    $mysql->connect();
    $ret = $mysql->execute_sql_mysql($sql_insert);
    $mysql->dis_connect();
}

// 上传图片
function add_resource($mysql, $rid, $img, $description) {
    $table = 'ci_resource';
    $sql_insert = sprintf("insert into %s(`rid`, `img`, `description`) values('%s', '%s', '%s');", $table, $rid, $img, $description);
    $mysql->connect();
    $ret = $mysql->execute_sql_mysql($sql_insert);
    $mysql->dis_connect();
}

// 添加帖子
// approval : 1表示已审核，2表示未审核
// source : 表示来源，1:真人；2:nice
// base_value: 帖子热度参数
// interaction: 是否互动，1:已互动; 2:未互动
function add_tweet($mysql, $uid, $content, $resource_id, $lng, $lat, $add_time) {
    $table = 'ci_tweet';
    srand(mktime(true) * 1000);
    $score = rand(100, 300);
    $tid = get_id("tweet");
    $sql_insert = sprintf("insert into %s(`tid`, `uid`, `content`, `ctime`, `is_del`, `resource_id`, `lon`, `lat`, `score`, `approval`, `base_value`, `source`, `interaction`) values('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');", $table, $tid, $uid, $content, $add_time, "0", $resource_id, $lng, $lat, $score, "2", "0", "2", "2");
    $mysql->connect();
    $ret = $mysql->execute_sql_mysql($sql_insert);
    $mysql->dis_connect();
}

// 添加已经上传的用户或图片记录到数据库中，避免重复上传
function add_mis_crawled($mysql, $md5, $uid, $type) {
    $table = 'ci_mis_crawled';
    $sql_insert = sprintf("insert into %s(`md5`, `type`, `host_name`, `user_id`, `create_time`) values('%s', '%s', '%s', '%s', '%s');", $table, $md5, $type, "nice", $uid, time());
    $mysql->connect();
    $ret = $mysql->execute_sql_mysql($sql_insert);
    $mysql->dis_connect();
}

//上传用户及其图片，创建新帖
function upload_user($user_info, $img_info, $business_area) {
    Global $avatar_files_path;
    Global $img_files_path;
    GLOBAL $mysql_conf;
    $handle = fopen($user_info, "r");

    $oss_service = new OSS();
    $mysql_service = new MysqlOperate();
    if (!$mysql_service->init($mysql_conf)) {
        echo "mysql service init error."."\n";
        return -1;
    }

    $user_num = 1;
    while (!feof($handle)) {
        echo "processing ".$user_num."\n";
        $buffer = trim(fgets($handle, 4096));
        if (!$buffer) {
            continue;
        }

        //处理每个用户
        $user_info = parse_line($buffer);

        //上传用户头像
        $new_user = ($user_info[11] == 1);
        $uid = 0;
        if ($new_user) {
            $avatar_file_name = $avatar_files_path.'/'.$user_info[8];
            $img_url = $oss_service->upload_user_pic($avatar_file_name, $user_info[8]);
            //var_dump($img_url);

            //写入到数据库, $uid用户id是数据库自增的
            $uid = add_user($mysql_service);
            $img_url_dict = array("img" => $img_url);
            $avatar = json_encode($img_url_dict);
            add_user_detail($mysql_service, $uid, $user_info, $avatar);

            //记录已上传用户
            $user_md5 = $user_info[9];
            add_mis_crawled($mysql_service, $user_md5, $uid, "avatar");

        } else { // 查数据库获取uid
            $uid = $user_info[10];
        }

        //上传用户图片
        $user_name = $user_info[0];
        $user_city = $user_info[3];
        $user_imgs = $img_info[$user_name];
        $num = 0;
        foreach ($user_imgs as $key=>$val) {
            generate_loc($business_area, $user_city, $lng, $lat);
            if ($lng == 0 || $lat == 0) {
                echo "get loc wrong \n";
                continue;
            }

            $img_file_name = $img_files_path.'/'.$user_name.'/'.$val[0];
            $img_url2 = $oss_service->upload_tweet_pic($img_file_name, $val[0]);
            $rid = get_id("resource");
            add_resource($mysql_service, $rid, json_encode($img_url2), "");

            $content = $val[1];
            $md5 = $val[2];
            $add_time = $val[3];
            add_tweet($mysql_service, $uid, $content, $rid, $lng, $lat, $add_time);
            add_mis_crawled($mysql_service, $md5, $uid, "pic");
            /*if ($num > 5) {
                break;
            }
            $num++;*/
        }
        $user_num++;
    }
    fclose($handle);
}

// 根据城市，随机一个商圈中的经纬度作为图片的地点
function generate_loc($business_area, $user_city, &$lng, &$lat) {
    GLOBAL $spec_province;
    if (!is_array($business_area) || (count($business_area) == 0)) {
        echo "business_area size is 0 wrong\n";
        $lng = $lat = 0;
        return;
    }
    $province = $user_city["province"];
    if (in_array($province, $spec_province)) {
        $city = $province;
        $district = $user_city["city"];
    } else {
        $city = $user_city["city"];
        $district = '';
    }

    // 获取城市
    if (!array_key_exists($city, $business_area)) {
        $lng = $lat = 0;
        return;
    } else {
        $city_info = $business_area[$city];
    }
    // 查看区是否存在
    if (array_key_exists($district, $city_info)) {
        $district_list = $city_info[$district];
    } else {
        $district_list = $city_info['total'];
    } 

    srand(microtime(true) * 1000);
    $index = rand(0, count($district_list));
    $bound_array = $district_list[$index];
    srand(microtime(true) * 1000);
    $lng = rand($bound_array[0]*1000000, $bound_array[2]*1000000) / 1000000;
    srand(microtime(true) * 1000);
    $lat = rand($bound_array[1]*1000000, $bound_array[3]*1000000) /1000000;

    if ($lng == 0 || $lat == 0) {
        echo "lng or lat is 0\n";
        var_dump($bound_array);
    }
}

function generate_user_loc() {
    Global $user_loc;
    srand(microtime(true) * 1000);
    $index = rand(0, count($user_loc));
    return $user_loc[$index];
}

function read_img_des() {
    Global $img_des_file;
    $handle = fopen($img_des_file, 'r');
    $num = 0;
    $img_info_map = array();
    while (!feof($handle)) {
        $buffer = trim(fgets($handle, 4096));
        if (!$buffer) {
            continue;
        }
        $img_info = explode("\t", $buffer);
        if (count($img_info) < 9) {
            continue;
        }
        $user_name = $img_info[1];
        $content = trim($img_info[2]);
        $add_time = $img_info[5];
        $md5 = $img_info[7];
        $img_file = $img_info[1].'_'.$img_info[0].'.jpg';
        $info_item = array($img_file, $content, $md5, $add_time);
        if (!array_key_exists($user_name, $img_info_map)) {
            $img_info_map[$user_name] = array($info_item);
        } else {
            array_push($img_info_map[$user_name], $info_item);
        }
        /*if ($num > 20) {
            break;
        }
        $num += 1;*/
    }
    //var_dump($img_info_map);
    fclose($handle);
    return $img_info_map;
}

function read_business_area_info() {
    Global $business_area_info_file;
    $handle = fopen($business_area_info_file, 'r');
    $business_area = array();
    while (!feof($handle)) {
        $buffer = trim(fgets($handle, 8192));
        if (!$buffer) {
            continue;
        }
        $business_info = explode("\t", $buffer);
        #$bound = explode("|", $business_info[6])[1];
        $city = $business_info[2];
        $district = $business_info[3];
        if ($district == '') {
            $district = 'default';
        }
        $bound = $business_info[5];
        sscanf($bound, "%f,%f;%f,%f", $x1, $y1, $x2, $y2);
        if ($x1 <= 0.0 || $y1 <= 0.0 || $x2 <= 0.0 || $y2 <= 0.0) {
            continue;
        }
        if (!array_key_exists($city, $business_area)) {
            $city_info = array();
        } else {
            $city_info = $business_area[$city];
        }
        if (!array_key_exists($district, $city_info)) {
            $district_info = array();
        } else {
            $district_info = $city_info[$district];
        }
        $total = 'total';
        if (!array_key_exists($total, $city_info)) {
            $total_info = array();
        } else {
            $total_info = $city_info[$total];
        }
        array_push($district_info, array($x1, $y1, $x2, $y2));
        array_push($total_info, array($x1, $y1, $x2, $y2));
        $city_info[$district] = $district_info;
        $city_info[$total] = $total_info;
        $business_area[$city] = $city_info;
    }
    fclose($handle);
    #var_dump($business_area);
    return $business_area;
}

echo "hello\n";
$img_info = read_img_des();
$business_area = read_business_area_info();
echo "img_info size = ".count($img_info)."\n";
echo "business_area size = ".count($business_area)."\n";
upload_user($user_info_file, $img_info, $business_area);
echo "done\n";
#echo get_id("picture");
?>
