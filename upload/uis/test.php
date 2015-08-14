<?php

require_once ( './uis_client.php' );

//echo '$output';
echo "Hello from the CLI\n";
$topic = "picture";
$test_client = new UisClient();
$uid = $test_client->get_id("picture");
echo $uid."\n";
$uid = $test_client->get_id("picture");
echo $uid."\n";
$uid = $test_client->get_id("follow");
echo $uid."\n";
$uid = $test_client->get_id("");
echo $uid."\n";
