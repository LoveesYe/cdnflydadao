<?php
error_reporting(0);
header('Content-Type: application/json; charset=UTF-8');

include 'monitor.php';

$post = file_get_contents('php://input');
$param = json_decode($post, true);
$result = [];
if($param && count($param)>0){
    foreach($param as $target){
        $result[] = node_monitor_local('1', $target);
    }
}
$data = ['msg'=>$result];
echo json_encode($data);