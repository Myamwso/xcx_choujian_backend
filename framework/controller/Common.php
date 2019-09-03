<?php

class Common extends WeModuleSite
{
    public function __construct()
    {
        $this->modulename = 'choujiang_page';
        $this->__define = IA_ROOT.'/addons/choujiang_page/template/';

        global $_W;
        $uniacid = $_W['uniacid'];
        $this->baseConfig = $item = pdo_fetch_cj("SELECT * FROM " . tablename_cj('choujiang_base') . " WHERE uniacid = :uniacid", array(':uniacid' => $uniacid));
        $priceArr =explode('-',$this->baseConfig['wechat_rand_price']);
        $priceList =json_decode($this->baseConfig['probability_num'],true);
        $this->baseConfig['loopPrice']=['min'=>$priceArr[0],'max'=>$priceArr[1],'floorNum'=>$this->floorNum,'pirceList'=>$priceList];
        $this->baseConfig['score'] = json_decode($this->baseConfig['score'], true);
        if ($item['type']) {
            $this->attachurl = $item['url'];
        } else {
            $this->attachurl = $_W['attachurl'];
        }
    }

    public function result($errno, $message, $data = '') {
        exit(json_encode(array(
            'errno' => $errno,
            'message' => $message,
            'data' => $data,
        )));
    }

    public function https_curl_json($url, $data, $type)
    {
        if ($type == 'json') {
            $headers = array("Content-type: application/json;charset=UTF-8", "Accept: application/json", "Cache-Control: no-cache", "Pragma: no-cache");
            $data = json_encode($data);
        }
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $output = curl_exec($curl);
        if (curl_errno($curl)) {
            echo 'Errno' . curl_error($curl);//捕抓异常
        }
        curl_close($curl);
        return $output;
    }

    /**
     * 返回图片路径
     * @param $img
     * @return bool|string
     */
    public function getImgPath($img){
        if (strpos($img, "?") == true || strpos($img, ".com/") == true) {
            $start = strpos($img, ".com/") + 5;
            $length = strpos($img, "?") - $start;
            $img = strpos($img, "?") == false ? substr($img, $start) : substr($img, $start, $length);
        }
        return $img;
    }

    /**
     * 返回img 数组
     * @param $str json格式 或字符串
     * @return array|mixed
     */
    public function getImgArray($str){
        $img_array = json_decode($str);
        if(!$img_array){
            $img_array[] = $str;
        }
        foreach ($img_array as $k=>$v){
            $imgUrl[] = $this->getImage($v);
        }
        return $imgUrl;
    }

    //服务器图片是否存在
    public function getImage($img, $isStyle = true)
    {
        global $_W;
        $item = pdo_get_cj('choujiang_base', ["uniacid" => $_W['uniacid']]);
        if(strstr($img,'https://') !== false || strstr($img,'http://') !== false) {
            return $img;
        }else{
            if ($item['type'] == 1) {  //aliyun osss
                if($item['cdn_speed'] && $item['cdn_url']){ //cdn加速开启 cdn域名和图片接口存在
                    $url = $item['cdn_url'].$img;
                }else{ ///oss
                    $url = $item['url'] . $img;
                }

                if ($item['img_api'] && $isStyle) {//图片样式
                    $url = $url .'?x-oss-process=style/'.$item['img_api'];
                }

                return $url;
            } else { //本地存储
                $item['url'] = $_W['siteroot'];
                return $item['url'] . $img;
            }
        }
    }

}