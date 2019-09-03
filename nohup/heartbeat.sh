#!/bin/bash
#心跳监测脚本 线上运行命令要调整
### */15 * * * * /4T/www/linsd/WeEngine/addons/choujiang_page/nohup/heartbeat.sh >> /4T/www/linsd/WeEngine/addons/choujiang_page/projectRecord/heartbeat.log
### nohup /www/server/php/70/bin/php /4T/www/linsd/WeEngine/addons/choujiang_page/nohup/wishingReleaseGoods.nohup.php 7 >> /4T/www/linsd/WeEngine/addons/choujiang_page/projectRecord/wishingReleaseGoods.log 2>&1 &

if [ "$1"x == "dev"x ]; then
    ### 命令数组
    CMD[0]="/www/server/php/70/bin/php /4T/www/linsd/WeEngine/addons/choujiang_page/nohup/wishingReleaseGoods.nohup.php 7"

    ### 日志数组
    LOG[0]="/4T/www/linsd/WeEngine/addons/choujiang_page/projectRecord/wishingReleaseGoods.log"
else
    ### 命令数组
    CMD[0]="/www/server/php/56/bin/php /www/wwwroot/wx.ymify.com/addons/choujiang_page/nohup/wishingReleaseGoods.nohup.php 11"

    ### 日志数组
    LOG[0]="/www/wwwlogs/cj_wishingReleaseGoods.log"
fi

function checkAndStart
{
    ### 判断参数是否正确
    if [[ $# -ne 3 ]]; then
        echo "错误参数"
        exit 1
    fi

    findCmdPer=$1
    findCmd=$2
    CmdLog=$3
    ### 查询进程的行数排除grep进程
	result=$(eval ${findCmdPer}"'"${findCmd}"'| grep -v 'grep' | wc -l")

    if [ $result -eq 0 ]; then
        ### 进程返回的行数为0，说明进程已经结束，需要重启进程
        startCmd="nohup "${findCmd}" >> "${CmdLog}" 2>&1 &"
        eval ${startCmd}

        CmdResult=$(echo $?)
        dateTime=$(date "+%Y-%m-%d %H:%M:%S")

        ### 判断重启进程命令是否执行成功，并记录结果
        if [ $CmdResult == "0" ]; then
            echo "${dateTime} restart CMD \"${startCmd}\" SUCCESS."
        else
            echo "${dateTime} restart CMD \"${startCmd}\" FAIL."
        fi
    fi
}


### 查询命令前缀
psStr="ps -ef | grep "

i=0
while [[ i -lt ${#CMD[@]} ]]; do
    checkAndStart "${psStr}" "${CMD[i]}" "${LOG[i]}"
	let i++
done

exit 0
