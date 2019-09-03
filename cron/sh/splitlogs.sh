#!/bin/bash
#日志切割shell
### 日志统计定时任务
### 0 2 * * * /4T/www/linsd/WeEngine/addons/choujiang_page/cron/sh/splitlogs.sh test.byify.cn 7 /www/wwwlogs >> /4T/www/linsd/WeEngine/addons/choujiang_page/cron/sh/choujiang.log

function genAlldate
{
 if [[ $# -ne 3 ]]; then
 echo "Usage: genAlldate 2017-04-01 2017-06-14 [-] or genAlldate 20170401 20170614 [-] ."
 exit 1
 fi

 START_DAY_TMP=${1}
 END_DAY_TMP=${2}
 SPLITER_TMP=${3}
 I_DATE_ARRAY_INDX=0

 # while [[ "${START_DAY}" -le "${END_DAY}" ]]; do
 while (( "${START_DAY_TMP}" <= "${END_DAY_TMP}" )); do
 cur_day=$(date -d @${START_DAY_TMP} +"%Y${SPLITER_TMP}%m${SPLITER_TMP}%d")
 DATE_ARRAY[${I_DATE_ARRAY_INDX}]=${cur_day}

 # We should use START_DAY_TMP other ${START_DAY_TMP} here.
 START_DAY_TMP=$((${START_DAY_TMP}+86400))
 ((I_DATE_ARRAY_INDX++))

 #sleep 1
 done
}



function getLogSplit
{
 if [[ $# -ne 6 ]]; then
 echo "错误参数"
 exit 1
 fi

 model=$2
 timeData=$5
 fileTime=$6

 rootDir=$(cd `dirname $4`; pwd);
 #创建日志目录
 if [ ! -d "${rootDir}/logs/${timeData}/" ];then
         mkdir -p ${rootDir}/logs/${timeData}/
 fi
 #创建日志文件
 if [ -f "${rootDir}/logs/${timeData}.newlogs.txt" ];then
 	rm -f ${rootDir}/logs/${timeData}/newlogs.$fileTime.txt
 fi

 #echo "/www/wwwlogs/$1/$2/$timeData.$fileTime.log"


pathFile=$3/$1/$model/$5.$6.log

if [ ! -f "${pathFile}" ];then

echo ${pathFile}"日志文件不存在"

else

 cat ${pathFile} | while read line
 do
 	#if [[ $line =~ "do=Participate" && $line =~ "info=" ]]
 	if [[ $line =~ "do=IndexList" || $line =~ "do=GetInfo" ]]
 	#if [[ $line =~ "do=Participate" ]]
 	then
 		OLD_IFS="$IFS"
 		IFS='"';
 		arr=($line)
 		IFS="$OLD_IFS"

 		##通过计算日志切割长度判断新旧日志格式  长度大于6位新日志
 		dataLen=${#arr[@]}

 		#for((i=0;i<dataLen;i++))
 		#{
 		#    echo ${arr[$i]}
 		#}
 		#exit 0

        if [[ $dataLen -gt 6 ]]
        then
            ip="${arr[1]%%,*}"
            getTime=`echo ${arr[2]} | sed 's/.* \[\(.*\) +0800\].*/\1/g' | sed 's/^\(.*\)\/\(.*\)\/\(.*\):\(.*\):\(.*\):\(.*\)$/\3-\2-\1 \4:\5:\6/'`
        else
            ip="${arr[0]%% *}"
            getTime=`echo ${arr[0]} | sed 's/.* \[\(.*\) +0800\].*/\1/g' | sed 's/^\(.*\)\/\(.*\)\/\(.*\):\(.*\):\(.*\):\(.*\)$/\3-\2-\1 \4:\5:\6/'`
        fi

 		if [[ $getTime =~ "Jan" ]]
 		then
 			getTime=`echo $getTime | sed 's/Jan/01/'`
 		fi
                 if [[ $getTime =~ "Feb" ]]
                 then
                         getTime=`echo $getTime | sed 's/Feb/02/'`
                 fi
                 if [[ $getTime =~ "Mar" ]]
                 then
                         getTime=`echo $getTime | sed 's/Mar/03/'`
                 fi
                 if [[ $getTime =~ "Apr" ]]
                 then
                         getTime=`echo $getTime | sed 's/Apr/04/'`
                 fi
                 if [[ $getTime =~ "May" ]]
                 then
                         getTime=`echo $getTime | sed 's/May/05/'`
                 fi
                 if [[ $getTime =~ "Jun" ]]
                 then
                         getTime=`echo $getTime | sed 's/Jun/06/'`
                 fi
                 if [[ $getTime =~ "Jul" ]]
                 then
                         getTime=`echo $getTime | sed 's/Jul/07/'`
                 fi
                 if [[ $getTime =~ "Aug" ]]
                 then
                         getTime=`echo $getTime | sed 's/Aug/08/'`
                 fi
                 if [[ $getTime =~ "Sep" ]]
                 then
                         getTime=`echo $getTime | sed 's/Sep/09/'`
                 fi
                 if [[ $getTime =~ "Oct" ]]
                 then
                         getTime=`echo $getTime | sed 's/Oct/10/'`
                 fi
                 if [[ $getTime =~ "Nov" ]]
                 then
                         getTime=`echo $getTime | sed 's/Nov/11/'`
                 fi
                 if [[ $getTime =~ "Dec" ]]
                 then
                         getTime=`echo $getTime | sed 's/Dec/12/'`
                 fi
        if [[ $dataLen -gt 6 ]]
        then
            if [[ $line =~ "info=" ]]
            then
                phoneInfo=$(echo ${arr[3]} | sed 's/.*info\=\(.*\)\&.*/\1/g' | cut -d '&' -f 1)
            else
                phoneInfo=""
            fi

            openId=`echo ${arr[3]} | sed 's/.*openid\=\(.*\)\&.*/\1/g' | cut -d '&' -f 1`

            userA="${arr[9]%% webview*}"
        else
            if [[ $line =~ "info=" ]]
            then
                phoneInfo=$(echo ${arr[1]} | sed 's/.*info\=\(.*\)\&.*/\1/g' | cut -d '&' -f 1)
            else
                phoneInfo=""
            fi

            openId=`echo ${arr[1]} | sed 's/.*openid\=\(.*\)\&.*/\1/g' | cut -d '&' -f 1`

            userA="${arr[5]%% webview*}"
        fi

 		if [[ $openId ]]; then
 		    echo "$ip@$getTime@$phoneInfo@$openId@$userA" >> ${rootDir}/logs/${timeData}/newlogs.$fileTime.txt
 		fi

 	fi
 done

    if [ -f "${rootDir}/logs/${timeData}/newlogs.$fileTime.txt" ];then

        if [ -f "${rootDir}/logs/${timeData}/iptext.$fileTime.txt" ];then
        rm -f ${rootDir}/logs/${timeData}/iptext.$fileTime.txt
        fi

        sort -t $'@' -k 4r,4 -k 2,2 ${rootDir}/logs/${timeData}/newlogs.$fileTime.txt | awk -F "@" '!a[$4 $1]++' | awk -F "@" '{print $4"#"$2"#"$1}' | while read iptext
        do
             OLD_IFS="$IFS"
             IFS='#';
             arr=($iptext)
        cityInfo=`php ${rootDir}/ipipDatx/ipDecode.php ${arr[2]}`
        #echo "$iptext#$cityInfo"
        echo "$iptext#$cityInfo" >> ${rootDir}/logs/${timeData}/iptext.$fileTime.txt
        done

        if [ -f "${rootDir}/logs/${timeData}/uaInfo.$fileTime.txt" ];then
        rm -f ${rootDir}/logs/${timeData}/uaInfo.$fileTime.txt
        fi

        sort -t $'@' -k 4r,4 -k 2,2 ${rootDir}/logs/${timeData}/newlogs.$fileTime.txt | awk -F "@" '!a[$4 $5]++' | awk -F "@" '{print $4"#"$2"#"$5}' | while read uaInfo
        do
        #echo "$uaInfo"
        echo "$uaInfo" >> ${rootDir}/logs/${timeData}/uaInfo.$fileTime.txt
        done

        if [ -f "${rootDir}/logs/${timeData}/phone.$fileTime.txt" ];then
            rm -f ${rootDir}/logs/${timeData}/phone.$fileTime.txt
        fi

        sort -t $'@' -k 4r,4 -k 2,2 ${rootDir}/logs/${timeData}/newlogs.$fileTime.txt | awk -F "@" '!a[$4 $3]++' | awk -F "@" '{print $4"#"$2"#"$3}' | while read phone
        do
            if [[ $phone ]]; then
                echo "$phone" >> ${rootDir}/logs/${timeData}/phone.$fileTime.txt
            fi
        done

        php ${rootDir}/addLogsToMysql.php $model ip ${rootDir}/logs/${timeData}/iptext.${fileTime}.txt
        php ${rootDir}/addLogsToMysql.php $model ua ${rootDir}/logs/${timeData}/uaInfo.${fileTime}.txt
        php ${rootDir}/addLogsToMysql.php $model ph ${rootDir}/logs/${timeData}/phone.${fileTime}.txt
    fi

fi
}



if [[ $4 ]]
then
    dataInfo=$4

    ##分割日期
    OLD_IFS="$IFS"
    IFS="@"
    dateArray=($dataInfo)
    IFS="$OLD_IFS"

    dateLen=${#dateArray[@]}

    ##日期是否为时间段
    if [[ $dateLen -eq 2 ]]; then
        ##日期为时间段执行
        # echo $dateLen

        START_DAY=$(date -d "${dateArray[0]}" +%s)
        END_DAY=$(date -d "${dateArray[1]}" +%s)
        # The spliter bettwen year, month and day.
        SPLITER="-"

        # Declare an array to store all the date during the two days you inpute.
        declare -a DATE_ARRAY

        # Call the funciotn to generate date during the two days you inpute.
        genAlldate "${START_DAY}" "${END_DAY}" "${SPLITER}"

        i=0
        num=24
        numnum=2
        while [ $i -lt ${#DATE_ARRAY[@]} ]
        do
         #echo ${DATE_ARRAY[$i]}
         for ((ii=0;ii<num;ii++))
         {
            for ((iii=0;iii<numnum;iii++))
            {
                if [[ $ii -lt 10 ]]
                then
                    hour=0${ii}
                else
                    hour=${ii}
                fi
                if [[ $iii -eq 0 ]]
                then
                    sec=00
                else
                    sec=30
                fi
                #echo ${DATE_ARRAY[$i]}.${hour}.${sec}
                timeData=${DATE_ARRAY[$i]}
                fileTime=${hour}.${sec}
                getLogSplit "${1}" "${2}" "${3}" "${0}" "${timeData}" "${fileTime}"
            }
         }
         let i++
        done
        exit 0
    else
        ##日期为一天执行
        timeData=$4
    fi
else
	timeData=$(date "+%Y-%m-%d")
fi

if [[ $5 ]]
then
	fileTime=$5
else
    nownum=$(date "+%M")
    #echo ${nownum}666666666666
    if [[ $nownum -gt 1 ]] && [[ $nownum -lt 30 ]];then
	    fileTime=$(date "+%H.00")
	fi
    if [[ $nownum -gt 30 ]] && [[ $nownum -lt 59 ]];then
        fileTime=$(date "+%H.30")
    fi
fi
#echo ${fileTime}5555555555
#exit



getLogSplit "${1}" "${2}" "${3}" "${0}" "${timeData}" "${fileTime}"

exit 0