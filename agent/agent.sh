#!/bin/bash -x

set -o errexit

#判断系统版本
check_sys(){
    local checkType=$1
    local value=$2

    local release=''
    local systemPackage=''
    local packageSupport=''

    if [[ "$release" == "" ]] || [[ "$systemPackage" == "" ]] || [[ "$packageSupport" == "" ]];then

        if [[ -f /etc/redhat-release ]];then
            release="centos"
            systemPackage="yum"
            packageSupport=true

        elif cat /etc/issue | grep -q -E -i "debian";then
            release="debian"
            systemPackage="apt"
            packageSupport=true

        elif cat /etc/issue | grep -q -E -i "ubuntu";then
            release="ubuntu"
            systemPackage="apt"
            packageSupport=true

        elif cat /etc/issue | grep -q -E -i "centos|red hat|redhat";then
            release="centos"
            systemPackage="yum"
            packageSupport=true

        elif cat /proc/version | grep -q -E -i "debian";then
            release="debian"
            systemPackage="apt"
            packageSupport=true

        elif cat /proc/version | grep -q -E -i "ubuntu";then
            release="ubuntu"
            systemPackage="apt"
            packageSupport=true

        elif cat /proc/version | grep -q -E -i "centos|red hat|redhat";then
            release="centos"
            systemPackage="yum"
            packageSupport=true

        else
            release="other"
            systemPackage="other"
            packageSupport=false
        fi
    fi

    echo -e "release=$release\nsystemPackage=$systemPackage\npackageSupport=$packageSupport\n" > /tmp/ezhttp_sys_check_result

    if [[ $checkType == "sysRelease" ]]; then
        if [ "$value" == "$release" ];then
            return 0
        else
            return 1
        fi

    elif [[ $checkType == "packageManager" ]]; then
        if [ "$value" == "$systemPackage" ];then
            return 0
        else
            return 1
        fi

    elif [[ $checkType == "packageSupport" ]]; then
        if $packageSupport;then
            return 0
        else
            return 1
        fi
    fi
}

# 安装依赖
install_depend() {
    if check_sys sysRelease ubuntu;then
        apt-get update
        apt-get -y install wget python-minimal
    elif check_sys sysRelease centos;then
        yum install -y wget python
    fi    
}

download(){
  local url1=$1
  local url2=$2
  local filename=$3

  # 检查文件是否存在
  # if [[ -f $filename ]]; then
  #   echo "$filename 文件已经存在，忽略"
  #   return
  # fi

  speed1=`curl -m 5 -L -s -w '%{speed_download}' "$url1" -o /dev/null || true`
  speed1=${speed1%%.*}
  speed2=`curl -m 5 -L -s -w '%{speed_download}' "$url2" -o /dev/null || true`
  speed2=${speed2%%.*}
  echo "speed1:"$speed1
  echo "speed2:"$speed2
  url="$url1\n$url2"
  if [[ $speed2 -gt $speed1 ]]; then
    url="$url2\n$url1"
  fi
  echo -e $url | while read l;do
    echo "using url:"$l
    wget --dns-timeout=5 --connect-timeout=5 --read-timeout=10 --tries=2 "$l" -O $filename && break
  done
  

}

get_sys_ver() {
cat > /tmp/sys_ver.py <<EOF
import platform
import re

sys_ver = platform.platform()
sys_ver = re.sub(r'.*-with-(.*)-.*',"\g<1>",sys_ver)
if sys_ver.startswith("centos-7"):
    sys_ver = "centos-7"
if sys_ver.startswith("centos-6"):
    sys_ver = "centos-6"
print sys_ver
EOF
echo `python /tmp/sys_ver.py`
}

sync_time(){
    echo "start to sync time and add sync command to cronjob..."

    if check_sys sysRelease ubuntu || check_sys sysRelease debian;then
        apt-get -y update
        apt-get -y install ntpdate wget
        /usr/sbin/ntpdate -u pool.ntp.org || true
        ! grep -q "/usr/sbin/ntpdate -u pool.ntp.org" /var/spool/cron/crontabs/root > /dev/null 2>&1 && echo '*/10 * * * * /usr/sbin/ntpdate -u pool.ntp.org > /dev/null 2>&1 || (date_str=`curl update.cdnfly.cn/common/datetime` && timedatectl set-ntp false && echo $date_str && timedatectl set-time "$date_str" )'  >> /var/spool/cron/crontabs/root
        service cron restart
    elif check_sys sysRelease centos; then
        yum -y install ntpdate wget
        /usr/sbin/ntpdate -u pool.ntp.org || true
        ! grep -q "/usr/sbin/ntpdate -u pool.ntp.org" /var/spool/cron/root > /dev/null 2>&1 && echo '*/10 * * * * /usr/sbin/ntpdate -u pool.ntp.org > /dev/null 2>&1 || (date_str=`curl update.cdnfly.cn/common/datetime` && timedatectl set-ntp false && echo $date_str && timedatectl set-time "$date_str" )' >> /var/spool/cron/root
        service crond restart
    fi

    # 时区
    rm -f /etc/localtime
    ln -sf /usr/share/zoneinfo/Asia/Shanghai /etc/localtime

    if /sbin/hwclock -w;then
        return
    fi 


}

need_sys() {
    SYS_VER=`python -c "import platform;import re;sys_ver = platform.platform();sys_ver = re.sub(r'.*-with-(.*)-.*','\g<1>',sys_ver);print sys_ver;"`
    if [[ $SYS_VER =~ "Ubuntu-16.04" ]];then
      echo "$sys_ver"
    elif [[ $SYS_VER =~ "centos-7" ]]; then
      SYS_VER="centos-7"
      echo $SYS_VER
    else  
      echo "目前只支持ubuntu-16.04和Centos-7"
      exit 1
    fi
}

install_depend
need_sys
sync_time

# 解析命令行参数
TEMP=`getopt -o h --long help,master-ver:,agent-ver:,master-ip:,es-ip:,es-pwd:,ignore-ntp -- "$@"`
if [ $? != 0 ] ; then echo "Terminating..." >&2 ; exit 1 ; fi
eval set -- "$TEMP"

while true ; do
    case "$1" in
        -h|--help) help ; exit 1 ;;
        --master-ver) MASTER_VER=$2 ; shift 2 ;;
        --agent-ver) AGENT_VER=$2 ; shift 2 ;;
        --) shift ; break ;;
        *) break ;;
    esac
done


if [[ $MASTER_VER == "" ]]; then
    if [[ $AGENT_VER == "" ]]; then
        echo "--master-ver或--agent-ver至少提供一个"
        exit 1
    fi

    # 指定了agent版本
    if [[ ! `echo "$AGENT_VER" | grep -P "^v\d+\.\d+\.\d+$"` ]]; then
        echo "指定的版本格式不正确，应该类似为v3.0.1"
        exit 1
    fi

    dir_name="cdnfly-agent-$AGENT_VER"
    tar_gz_name="$dir_name-$(get_sys_ver).tar.gz"

else
    # 指定了主控版本
    # 根据master安装指定agent
    # 由version_name转换成version_num
    first_part=${MASTER_VER:1:1}
    second_part=$(printf "%02d\n" `echo $MASTER_VER  | awk -F'.' '{print $2}'`)
    third_part=$(printf "%02d\n" `echo $MASTER_VER  | awk -F'.' '{print $3}'`)
    version_num="$first_part$second_part$third_part"
    agent_ver=`(curl -s -m 5 "https://github.com/LoveesYe/cdnflydadao/raw/main/web/upgrades?version_num=$version_num" || curl -s -m 5 "https://github.com/LoveesYe/cdnflydadao/raw/main/web/upgrades?version_num=$version_num") | grep -Po '"agent_ver":"\d+"' | grep -Po "\d+" || true`
    if [[ "$agent_ver" == "" ]]; then
        echo "无法获取agent版本"
        exit 1
    fi

    first_part=${agent_ver:0:1}
    let second_part=10#${agent_ver:1:2} || true
    let third_part=10#${agent_ver:3:2} || true
    agent_version_name="v$first_part.$second_part.$third_part"
    echo "根据主控版本$MASTER_VER得到agent需要安装的版本为$agent_version_name"
    dir_name="cdnfly-agent-$agent_version_name"
    tar_gz_name="$dir_name-$(get_sys_ver).tar.gz"

fi

cd /opt

download "https://github.com/LoveesYe/cdnflydadao/raw/main/agent/$tar_gz_name" "https://github.com/LoveesYe/cdnflydadao/raw/main/agent/$tar_gz_name" "$tar_gz_name"

rm -rf $dir_name
tar xf $tar_gz_name
rm -rf cdnfly
mv $dir_name cdnfly

# 开始安装
cd /opt/cdnfly/agent
chmod +x install.sh
./install.sh $@
