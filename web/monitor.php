<?php

//执行全部监控
function node_monitor_all($targets){
    global $monitor_config;
    $result = [];
    $target_group_1 = [];
    $target_group_2 = [];
    foreach($targets as $target){
        if($target['node_group'] == '2') $target_group_2[] = $target;
        else $target_group_1[] = $target;
    }
    if(count($monitor_config['group_1']) > 0 && count($target_group_1) > 0){
        foreach($monitor_config['group_1'] as $node){
            if($node['type'] == 'local'){
                foreach($target_group_1 as $target){
                    $result[] = node_monitor_local($node['node_id'], $target);
                }
            }elseif($node['type'] == 'remote'){
                $result = array_merge($result, node_monitor_remote($node['node_id'], $node['api'], $target_group_1));
            }
        }
    }
    if(count($monitor_config['group_2']) > 0 && count($target_group_2) > 0){
        foreach($monitor_config['group_2'] as $node){
            if($node['type'] == 'local'){
                foreach($target_group_2 as $target){
                    $result[] = node_monitor_local($node['node_id'], $target);
                }
            }elseif($node['type'] == 'remote'){
                $result = array_merge($result, node_monitor_remote($node['node_id'], $node['api'], $target_group_2));
            }
        }
    }
    return $result;
}

//批量执行远程监控
function node_monitor_remote($node_id, $apiurl, $targets){
    $json = json_encode($targets);
    $data = send_request($apiurl, $json);
    $arr = json_decode($data, true);
    if(!$arr || !isset($arr['msg'])) return [];
    $result = [];
    foreach($arr['msg'] as $target){
        $target['node_id'] = $node_id;
        $result[] = $target;
    }
    return $result;
}

//单个执行本地监控
function node_monitor_local($node_id, $target){
    $status = false;
    if($target['type'] == 'http'){
        $status = check_http($target['target'], $target['port'], $target['path'], $target['host'], $target['timeout']);
    }elseif($target['type'] == 'ping'){
        $status = check_ping($target['target'], $target['port'], $target['timeout']);
    }else{ //tcp
        $status = check_tcp($target['target'], $target['port'], $target['timeout']);
    }
    return ['node_id'=>$node_id, 'success'=>$status?1:0, 'target'=>$target['target']];
}

function check_http($target, $port, $path, $host = '', $timeout = 3){
    if($timeout > 3) $timeout = 3;
    if(!$port) $port = 80;
    if(!$path) $path = '/';
    if($port == 80){
        $url = 'http://'.$target.$path;
    }else{
        $url = 'http://'.$target.':'.$port.$path;
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $httpheader[] = "Accept: */*";
    $httpheader[] = "Accept-Language: zh-CN,zh;q=0.8";
    $httpheader[] = "Connection: close";
    if(!empty($host)){
        $httpheader[] = "Host: ".$host;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_exec($ch);
    $errno = curl_errno($ch);
    if($errno) {
        curl_close($ch);
        return false;
    }
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if($httpcode>=200 && $httpcode<400){
        return true;
    }
    return false;
}

function check_tcp($target, $port, $timeout = 3){
    if($timeout > 3) $timeout = 3;
    if(!$port) $port = 80;
    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_set_nonblock($sock);
    socket_connect($sock, $target, $port);
    socket_set_block($sock);
    $status = socket_select($r = array($sock), $w = array($sock), $f = array($sock), $timeout);
    return $status === 1;
}

function check_ping($target, $port, $timeout = 2){
    if(!function_exists('exec') || PHP_OS == 'WINNT') return check_tcp($target, $port, $timeout);
    if($timeout > 2) $timeout = 2;
    exec('ping -c 1 -w '.$timeout.' '.$target.' > /dev/null', $output, $return_var);
    if($return_var === 0) return true;
    return false;
}

function send_request($url, $json){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
    $ret = curl_exec($ch);
    curl_close($ch);
    return $ret;
}