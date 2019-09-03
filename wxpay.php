<?php 


	class WeixinPay {


    protected $appid;
    protected $mch_id;
    protected $key;
    protected $openid;
    protected $out_trade_no;
    protected $body;
    protected $total_fee;
    function __construct($appid, $openid, $mch_id, $key,$out_trade_no,$body,$total_fee) {
        $this->appid = $appid;
        $this->openid = $openid;
        $this->mch_id = $mch_id;
        $this->key = $key;
        $this->out_trade_no = $out_trade_no;
        $this->body = $body;
        $this->total_fee = $total_fee;
    }


    public function pay() {
        //统一下单接口
        $return = $this->weixinapp();
        return $return;
    }


    //统一下单接口
    private function unifiedorder() {
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $parameters = array(
            'appid' => $this->appid, //小程序ID
            'mch_id' => $this->mch_id, //商户号
            'nonce_str' => $this->createNoncestr(), //随机字符串
//            'body' => 'test', //商品描述
            'body' => $this->body,
//            'out_trade_no' => '2015450806125348', //商户订单号
            'out_trade_no'=> $this->out_trade_no,
//            'total_fee' => floatval(0.01 * 100), //总金额 单位 分
            'total_fee' => $this->total_fee,
            'spbill_create_ip' => $_SERVER['REMOTE_ADDR'], //终端IP
           // 'spbill_create_ip' => '192.168.0.161', //终端IP
            'notify_url' => 'https://dev.ymify.com', //通知地址  确保外网能正常访问
            'openid' => $this->openid, //用户openid
            'trade_type' => 'JSAPI' //交易类型
        );
        //统一下单签名
        $parameters['sign'] = $this->getSign($parameters);
        $xmlData = $this->arrayToXml($parameters);
        $return = $this->xmlToArray($this->postXmlCurl($xmlData, $url, 60));
        return $return;
    }


        /**
         * 企业商户付款到零钱
         * @param $data
         * @return mixed
         * @throws \Exception
         */
        public function transfers($data){
            try {
//                if (!$data['partner_trade_no']) {
//                    throw new \Exception("缺少统企业付款商户订单号！" . "<br>");
//                } else if (!$data['openid']) {
//                    throw new \Exception("缺少统企业付款用户openid！" . "<br>");
//                }



//                else if ($this->transfers['check_name'] == 'NO_CHECK') {
//                    unset($data['re_user_name']);
//                } else if ($this->transfers['check_name'] == 'FORCE_CHECK' && !$data['re_user_name']) {
//                    throw new \Exception("缺少统企业付款收款用户真实姓名！" . "<br>");
//                }


//                else if (!$data['amount']) {
//                    throw new \Exception("缺少统企业付款金额！" . "<br>");
//                } else if (!$data['desc']) {
//                    throw new \Exception("缺少统企业付款企业付款描述信息！" . "<br>");
//                }
                $post_data = [
                    'amount' => $this->total_fee,
                    'check_name' => 'NO_CHECK',
                    'desc' => $data['desc'],
                    'mch_appid' => $this->appid, //小程序ID
                    'mchid' =>$this->mch_id ,//商户号
                    'nonce_str'=> $this->createNoncestr(),// 随机字符串
                    'partner_trade_no' => $this->out_trade_no,
                    'openid' => $this->openid,
//                're_user_name'=>$data['re_user_name']??'',
                    'spbill_create_ip'=>$this->get_client_ip(),
                ];


                $post_data['sign'] = $this->getSign( $post_data );
                $xmlData = $this->arrayToXml( $post_data );
                $url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';
                $parameter = [
                    'is_post'=>false,
                    'pt'=>true,
                    'sslcert'=>$data['sslcert'],
                    'sslkey'=>$data['sslkey'],
                ];
                $return = $this->xmlToArray($this->postXmlCurl($xmlData, $url, 60,$parameter));

                return $return;
//                $response = curl_data($url, $xml, 30,['is_post'=>true,'pt'=>true]);
//                $response = json_decode(json_encode(simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
//                return $response+$post_data;
            }catch (Exception $e) {
                die($e->getMessage());
            }
            return false;
        }

        /**
         * 获取客户端IP地址
         * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
         * @param boolean $adv 是否进行高级模式获取（有可能被伪装）
         * @return mixed
         */
        public function get_client_ip($type = 0,$adv=false) {
            $type       =  $type ? 1 : 0;
            static $ip  =   NULL;
            if ($ip !== NULL) return $ip[$type];
            if($adv){
                if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $arr    =   explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                    $pos    =   array_search('unknown',$arr);
                    if(false !== $pos) unset($arr[$pos]);
                    $ip     =   trim($arr[0]);
                }elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                    $ip     =   $_SERVER['HTTP_CLIENT_IP'];
                }elseif (isset($_SERVER['REMOTE_ADDR'])) {
                    $ip     =   $_SERVER['REMOTE_ADDR'];
                }
            }elseif (isset($_SERVER['REMOTE_ADDR'])) {
                $ip     =   $_SERVER['REMOTE_ADDR'];
            }
            // IP地址合法验证
            $long = sprintf("%u",ip2long($ip));
            $ip   = $long ? array($ip, $long) : array('0.0.0.0', 0);
            return $ip[$type];
        }


    private static function postXmlCurl($xml, $url, $second = 30,$useCert=[])
    {
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); //严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);


        //是否提交普通商户证书
        if($useCert['pt'] == true){
            //使用证书：cert 与 key 分别属于两个.pem文件
            curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
            curl_setopt($ch,CURLOPT_SSLCERT, $useCert['sslcert']);
            curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
            curl_setopt($ch,CURLOPT_SSLKEY, $useCert['sslkey']);
        }
        //是否提交企业商户证书
        if($useCert['is_post'] == true){
            if($useCert['qy']){
                $sslcert = $useCert['sslcert'];
                $sslkey = $useCert['sslkey'];
            }else{
                $sslcert = $useCert['sslcert'];
                $sslkey = $useCert['sslkey'];
            }
            //使用证书：cert 与 key 分别属于两个.pem文件
            curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
            curl_setopt($ch,CURLOPT_SSLCERT, $sslcert);
            curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
            curl_setopt($ch,CURLOPT_SSLKEY, $sslkey);
        }


        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);


        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        set_time_limit(0);


        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            throw new WxPayException("curl出错，错误码:$error");
        }
    }
    
    
    
    //数组转换成xml
    private function arrayToXml($arr) {
        $xml = "<root>";
        foreach ($arr as $key => $val) {
            if (is_array($val)) {
                $xml .= "<" . $key . ">" . arrayToXml($val) . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            }
        }
        $xml .= "</root>";
        return $xml;
    }


    //xml转换成数组
    private function xmlToArray($xml) {


        //禁止引用外部xml实体 


        libxml_disable_entity_loader(true);


        $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);


        $val = json_decode(json_encode($xmlstring), true);


        return $val;
    }


    //微信小程序接口
    private function weixinapp() {
        //统一下单接口
        $unifiedorder = $this->unifiedorder();
//        print_r($unifiedorder);
        $parameters = array(
            'appId' => $this->appid, //小程序ID
            'timeStamp' => '' . time() . '', //时间戳
            'nonceStr' => $this->createNoncestr(), //随机串
            'package' => 'prepay_id=' . $unifiedorder['prepay_id'], //数据包
            'signType' => 'MD5'//签名方式
        );
        //签名
        $parameters['paySign'] = $this->getSign($parameters);
        return $parameters;
    }


    //作用：产生随机字符串，不长于32位
    private function createNoncestr($length = 32) {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }


    //作用：生成签名
    private function getSign($Obj) {
        foreach ($Obj as $k => $v) {
            $Parameters[$k] = $v;
        }
        //签名步骤一：按字典序排序参数
        ksort($Parameters);
        $String = $this->formatBizQueryParaMap($Parameters, false);
        //签名步骤二：在string后加入KEY
        $String = $String . "&key=" . $this->key;
        //签名步骤三：MD5加密
        $String = md5($String);
        //签名步骤四：所有字符转为大写
        $result_ = strtoupper($String);
        return $result_;
    }


    ///作用：格式化参数，签名过程需要使用
    private function formatBizQueryParaMap($paraMap, $urlencode) {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v) {
            if ($urlencode) {
                $v = urlencode($v);
            }
            $buff .= $k . "=" . $v . "&";
        }
        $reqPar;
        if (strlen($buff) > 0) {
            $reqPar = substr($buff, 0, strlen($buff) - 1);
        }
        return $reqPar;
    }


}		
			
		
