<?php
/**
 *  @消息中心client for php
 *  @Author : dingchuan
 *  @Create : 2015-01-31
 */
$_GLOBALS['THRIFT_ROOT'] = "/home/meihua/thrift/php/";
//echo dirname( __FILE__)."\n";
//echo __FILE__."\n";
$genDir = dirname(__FILE__)."/gen-php";

require_once $_GLOBALS['THRIFT_ROOT'].'/Thrift/ClassLoader/ThriftClassLoader.php';
require_once $_GLOBALS['THRIFT_ROOT'].'/Thrift/Transport/TTransport.php';
require_once $_GLOBALS['THRIFT_ROOT'].'/Thrift/Transport/TSocket.php';
require_once $_GLOBALS['THRIFT_ROOT'].'/Thrift/Transport/TBufferedTransport.php';  

require_once $_GLOBALS['THRIFT_ROOT'].'/Thrift/Protocol/TProtocol.php';
require_once $_GLOBALS['THRIFT_ROOT'].'/Thrift/Protocol/TBinaryProtocol.php';

require_once $_GLOBALS['THRIFT_ROOT'].'/Thrift/Type/TMessageType.php';  
require_once $_GLOBALS['THRIFT_ROOT'].'/Thrift/Type/TType.php';  

require_once $_GLOBALS['THRIFT_ROOT'].'/Thrift/Factory/TStringFuncFactory.php';  

require_once $_GLOBALS['THRIFT_ROOT'].'/Thrift/StringFunc/TStringFunc.php';  
require_once $_GLOBALS['THRIFT_ROOT'].'/Thrift/StringFunc/Core.php';

require_once $_GLOBALS['THRIFT_ROOT'].'/Thrift/Exception/TException.php';  
require_once $_GLOBALS['THRIFT_ROOT'].'/Thrift/Exception/TTransportException.php';  
require_once $_GLOBALS['THRIFT_ROOT'].'/Thrift/Exception/TProtocolException.php';

//require_once $genDir ."/uis/UidServer.php";
require_once $genDir ."/uis/Types.php";

use Thrift\ClassLoader\ThriftClassLoader;
use Thrift\Protocol\TBinaryProtocol as TBinaryProtocol;  
use Thrift\Transport\TSocket as TSocket;  
use Thrift\Transport\TSocketPool as TSocketPool;  
use Thrift\Transport\TFramedTransport as TFramedTransport;  
use Thrift\Transport\TBufferedTransport as TBufferedTransport;  


$loader = new ThriftClassLoader();
$loader->registerNamespace('Thrift', $_GLOBALS['THRIFT_ROOT'] );
$loader->registerDefinition('uis', $genDir );
$loader->register();

class UisClient{

    private $socket = null;
    private $transport = null;
    private $protocol = null;
    private $client = null;

    function __construct() {
        //$this -> connect();
    }

    function __destruct() {
        //$this -> dis_connect();
    }

    /**
    * @Theme  : 
    * @Return : boolean
    */
    private function connect() {
        //$socket = new TSocket( Yii::app()->params['odw']['machine'],Yii::app()->params['odw']['port'] );
        $this->socket = new TSocket('127.0.0.1', 6060);
        $this->socket->setSendTimeout(10000);
        $this->socket->setRecvTimeout(20000);

        //$this->transport = new TBufferedTransport($this->socket);
        $this->transport = new TFramedTransport($this->socket);
        $this->protocol = new TBinaryProtocol($this->transport);
        $this->client = new \uis\UidServerClient($this->protocol);

        $this->transport->open();
        //$socket -> setDebug(TRUE);

    }
    private function dis_connect() {
        $this->transport->close();
        $this->client = null;
        $this->protocol = null;
        $this->transport = null;
        $this->socket = null;
    }
    /**
    * @Theme  : 
    * @Params : string $jsonString
    * @Return : boolean
    */

    public function get_id($topic = '') {
        try {

            $this->connect();     
            $uid = $this->client->get_id($topic);
            $this->dis_connect();
            return $uid;
        } catch (Exception $e) {
            echo $e-> getMessage()."\n";
        }
    }
}


