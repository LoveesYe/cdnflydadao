<?php
error_reporting(0);
date_default_timezone_set("PRC");
header('Content-Type: application/json; charset=UTF-8');
define('AES_KEY','L6DYHZ3NEb2QUL6D');
define('AES_KEY2','kQ3vaLGnZ8sgyd5T');
define('KEY_APPEND', 'rbkgp46j53');

if (function_exists("set_time_limit"))
{
	@set_time_limit(0);
}

$url = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

if(strpos($url, '/common/timestamp2') !== false && $method=='POST'){
    $param = parse_input2();
    $data = ['now'=>time(), 'rnd'=>$param['rnd']];
    echo generate_output2($data);
}
elseif(strpos($url, '/auth2') !== false && $method=='POST'){
    $param = parse_input2();
    $data = ['nodes'=>99999, 'machine_code'=>$param['machine_code'], 'end_at'=>time()+3600*24*100*3650];
    echo generate_output2($data);
}
elseif(strpos($url, '/common/timestamp') !== false && $method=='POST'){
    $param = parse_input();
    $data = ['now'=>time(), 'rnd'=>$param['rnd']];
    echo generate_output($data);
}
elseif(strpos($url, '/auth') !== false && $method=='POST'){
    $param = parse_input();
    $data = ['nodes'=>99999, 'machine_code'=>$param['machine_code'], 'end_at'=>time()+3600*24*100*365];
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


function parse_input2(){
    $post = file_get_contents('php://input');
    $de_text = text_decrypt($post, get_aes_key());
    $param = json_decode($de_text, true);
    return $param;
}
function generate_output2($data){
    $json = json_encode($data);
    $cipher = text_encrypt($json, get_aes_key());
    $data = ['code'=>0, 'data'=>$cipher, 'msg'=>''];
    return json_encode($data);
}
function get_aes_key(){
    $key = trim($_GET['key']);
    $aes_key = substr(md5($key.KEY_APPEND), 0, 16);
    return $aes_key;
}
function parse_input(){
    $post = file_get_contents('php://input');
    $param = json_decode(text_decrypt($post, AES_KEY), true);
    return $param;
}
function generate_output($data){
    $cipher = text_encrypt(json_encode($data), AES_KEY);
    $data = ['code'=>0, 'data'=>$cipher, 'msg'=>''];
    return json_encode($data);
}
function text_encrypt($data, $key){
    return openssl_encrypt($data, 'aes-128-cbc', $key, 0, $key);
}
function text_decrypt($data, $key){
    return openssl_decrypt($data, 'aes-128-cbc', $key, 0, $key);
}
