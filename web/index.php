<?php
error_reporting(0);
date_default_timezone_set("PRC");
header('Content-Type: application/json; charset=UTF-8');
define('AES_KEY','L6DYHZ3NEb2QUL6D');

if (function_exists("set_time_limit"))
{
	@set_time_limit(0);
}

$url = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

if(strpos($url, '/common/timestamp') !== false && $method=='POST'){
    $param = parse_input();
    $data = ['now'=>time(), 'rnd'=>$param['rnd']];
    echo generate_output($data);
}
elseif(strpos($url, '/auth') !== false && $method=='POST'){
    $param = parse_input();
    $data = ['nodes'=>10000, 'machine_code'=>$param['machine_code'], 'end_at'=>time()+3600*24*365];
    echo generate_output($data);
}
elseif(strpos($url, '/check') !== false && $method=='POST'){
    require 'config.php';
    require 'monitor.php';
    $post = file_get_contents('php://input');
    $param = json_decode($post, true);
    $result = node_monitor_all($param);
    $data = ['msg'=>$result];
    echo json_encode($data);
}
elseif(strpos($url, '/common/datetime') !== false && $method=='GET'){
    return date('Y-m-d H:i:s');
}
elseif(strpos($url, '/master/upgrades') !== false){
    $version_data = file_get_contents('version.json');
    if(!$version_data) exit(json_encode(['code'=>-1, 'data'=>[], 'ip'=>$_SERVER['REMOTE_ADDR'], 'msg'=>'版本信息文件不存在']));
    $version_info = json_decode($version_data, true);
    if(!$version_info) exit(json_encode(['code'=>-1, 'data'=>[], 'ip'=>$_SERVER['REMOTE_ADDR'], 'msg'=>'解析版本信息文件失败']));
    $data = ['code'=>0, 'count'=>1, 'data'=>[$version_info], 'ip'=>$_SERVER['REMOTE_ADDR']];
    echo json_encode($data);
}


function parse_input(){
    $post = file_get_contents('php://input');
    $param = json_decode(text_decrypt($post), true);
    return $param;
}
function generate_output($data){
    $cipher = text_encrypt(json_encode($data));
    $data = ['code'=>0, 'data'=>$cipher, 'msg'=>''];
    return json_encode($data);
}
function text_encrypt($data){
    return openssl_encrypt($data, 'aes-128-cbc', AES_KEY, 0, AES_KEY);
}
function text_decrypt($data){
    return openssl_decrypt($data, 'aes-128-cbc', AES_KEY, 0, AES_KEY);
}
