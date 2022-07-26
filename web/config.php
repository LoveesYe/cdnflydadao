<?php

/**
 * 多节点监控配置文件
 * 节点分为国内组和国外组，可分别设置
 * 组内节点数最好不要多余5个，否则可能会超时
 * 远程节点只需要上传当前目录的 api.php、monitor.php 文件
 */

$monitor_config = [
    //国内组
    'group_1' => [
        ['node_id'=>'1', 'type'=>'local'], //本地节点
        // ['node_id'=>'2', 'type'=>'remote', 'api'=>'http://www.example.com/api.php'], //远程节点，需填写监控接口地址
    ],

    //国外组
    'group_2' => [
        ['node_id'=>'1', 'type'=>'local'], //本地节点
        // ['node_id'=>'2', 'type'=>'remote', 'api'=>'http://www.example.com/api.php'], //远程节点，需填写监控接口地址
    ],
];