<?php
// 更新cdnfly最新版本信息

error_reporting(0);
header('Content-Type: text/html; charset=UTF-8');

$url = 'https://update.cdnfly.cn/master/upgrades?version_num=';
$data = send_request($url);
$arr = json_decode($data, true);
if(!$arr)exit('获取cdnfly版本信息失败，json解析失败');
if($arr['code']!=0 || !$arr['data'] || count($arr['data'])==0)exit('获取cdnfly版本信息失败：'.$data);

$info = $arr['data'][0];
if(file_put_contents('version.json', json_encode($info))){
    exit('保存cdnfly版本信息成功！');
}else{
    exit('保存cdnfly版本信息失败，可能无文件写入权限');
}


function send_request($url){
    $ch=curl_init($url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $res=curl_exec($ch);
    curl_close($ch);
    return $res;
}