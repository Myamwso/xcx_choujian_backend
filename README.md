# 小程序_抽奖_后端

1. 配置基础信息
## 必须全部配置

2. 定时脚本
# 抽奖系统 -定时任务 : 7 为在微擎应用里的acuid
* * * * * /www/server/php/70/bin/php /4T/www/linsd/WeEngine/addons/choujiang_page/openLottery.php 7 Choujiang_pageModuleWxapp doPageGoodsOpenSetTime >/dev/null

3. nohup
nohup /www/server/php/56/bin/php autoMachine.nohup.php 10 > choujiang.log 2>&1 &
nohup /www/server/php/56/bin/php autoMachine.nohup.php 11 > doujiang.log 2>&1 &

4. 发起类别付费管理（次数购买配置）

#开发相关

##服务端接口格式变更，兼容客户端版本。
> 发布稳定后将新版本接口方法覆盖到旧版本接口

```
<?php
/**
 * 旅游小程序接口定义
 *
 * @author wangbosichuang
 * @url
 */
defined('IN_IA') or exit('Access Denied');
pdo_run("set names utf8mb4");

class Choujiang_pageModuleWxapp extends WeModuleWxapp
{
	protected $clientVersion = "4";

	/**
	 * 版本4 - 旧接口
	 * [getUid description]
	 * @return [type] [description]
	 */
	public function getUid()
	{
		global $_GPC;
		if (isset($_GPC['version']) && ($_GPC['version'] > $this->clientVersion)){
			$this->getUid5();
			exit;
		}

		///旧版本接口代码
	}

	/**
	 * 版本5 - 新接口
	 * [getUid5 description]
	 * @return [type] [description]
	 */
	public function getUid5()
	{

	}
}
```


## 数据库变更记录

CREATE TABLE `ims_choujiang_machine_num` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `goods_id` int(11) NOT NULL COMMENT '商品id',
  `machine_num` int(11) NOT NULL DEFAULT '0' COMMENT '机器人数量',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态：0：正在添加中；1：添加结束',
  `added_machine_num` int(11) NOT NULL DEFAULT '0' COMMENT '已添加的机器人数量',
  PRIMARY KEY (`id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='商品机器人关联表';

ALTER TABLE `ims_choujiang_user`
ADD COLUMN `is_machine`  tinyint(1) NULL COMMENT '是否为机器人：0：否；1：是' AFTER `share_num_time`;

ALTER TABLE `ims_choujiang_user`
ADD INDEX `is_machine` (`is_machine`) ;

ALTER TABLE `ims_choujiang_user`
MODIFY COLUMN `is_machine`  tinyint(1) NULL DEFAULT 0 COMMENT '是否为机器人：0：否；1：是' AFTER `share_num_time`;

####2018-7-5 10:03
ALTER TABLE `ims_choujiang_record` ADD `group_verification` VARCHAR(200) NULL COMMENT '组团二维码' AFTER `pintuan_id`;

####2018-7-5 14:12
ALTER TABLE `ims_choujiang_record`
ADD COLUMN `is_machine`  tinyint(1) NULL DEFAULT 0 COMMENT '是否为机器人：0:否；1：是' AFTER `del`;

ALTER TABLE `ims_choujiang_goods`
ADD COLUMN `machine_canyu`  tinyint(1) NULL COMMENT '机器人是否参与抽奖：0：否;1：是' AFTER `formid`;

####2018-7-5 15:30
ALTER TABLE `ims_choujiang_record` ADD `really_winning` INT(1) NOT NULL DEFAULT '0' COMMENT '真正中奖者' AFTER `is_machine`;

####2018-7-6 10:07
update ims_choujiang_record set pintuan_id = 0 where pintuan_id is NULL;
ALTER TABLE `ims_choujiang_record`
MODIFY COLUMN `pintuan_id`  int(11) NULL DEFAULT 0 COMMENT '拼团ID' AFTER `card_password`;
ALTER TABLE `ims_choujiang_record`
MODIFY COLUMN `pintuan_id`  int(11) NOT NULL DEFAULT 0 COMMENT '拼团ID' AFTER `card_password`;

####2018-7-6 11:27
ALTER TABLE `ims_choujiang_record` DROP `really_winning`;
ALTER TABLE `ims_choujiang_record` ADD `is_group_member` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '是否团成员' AFTER `is_machine`;
UPDATE `ims_choujiang_record` SET `is_group_member` = 1 WHERE `id` != `pintuan_id` AND `pintuan_id` != 0;

####2018-7-6 15:33
ALTER TABLE `ims_choujiang_record`
ADD INDEX `goods_id` (`goods_id`) ;

####2018-7-6 17:32
UPDATE ims_choujiang_record SET `finish_time` = 0 WHERE `finish_time` is null;
ALTER TABLE `ims_choujiang_record` CHANGE `finish_time` `finish_time` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '0' COMMENT '开奖时间';


####2018-7-11 8:36
ALTER TABLE `ims_choujiang_goods`
MODIFY COLUMN `canyunum`  int(11) UNSIGNED NULL DEFAULT 0 COMMENT '参与抽奖总人数' AFTER `draw_message`;

####2018-7-12 11:55
ALTER TABLE `ims_choujiang_user` ADD `is_manager` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '是否后台管理员 0--非管理员 1--管理员' AFTER `is_machine`;

####2018-7-12 15:48
CREATE TABLE `ims_choujiang_channel_code` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(300) NOT NULL COMMENT '推广名',
  `channel` varchar(32) NOT NULL COMMENT '推广标识',
  `wx_code` varchar(600) NOT NULL COMMENT '小程序码',
  `is_del` tinyint(1) NOT NULL DEFAULT '0' COMMENT '删除：0：未删除；1：已删除',
  `create_at` datetime NOT NULL COMMENT '添加时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='推广码表';

####2018-8-8 10:14
ALTER TABLE `ims_choujiang_channel_code` ADD `size` INT(5) NULL DEFAULT NULL COMMENT '二维码生成尺寸' AFTER `channel`, ADD `page_url` VARCHAR(500) NULL DEFAULT NULL COMMENT '页面url' AFTER `size`;

####2018-8-8 15:06
ALTER TABLE `ims_choujiang_channel_code` CHANGE `channel` `channel` VARCHAR(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '推广标识';
ALTER TABLE `ims_choujiang_channel_code` CHANGE `wx_code` `wx_code` VARCHAR(600) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '小程序码';

####2018-8-8 16:27
CREATE TABLE `ims_choujiang_channel_grapher` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `channel_id` int(11) NOT NULL COMMENT '推广渠道ID',
  `sweep_user` int(11) NOT NULL DEFAULT '0' COMMENT '扫码人数',
  `sweep_time` int(11) NOT NULL DEFAULT '0' COMMENT '扫码次数',
  `sweep_add` int(11) NOT NULL DEFAULT '0' COMMENT '扫码新增',
  `is_del` tinyint(1) NOT NULL DEFAULT '0' COMMENT '删除：0：未删除；1：已删除',
  `create_at` datetime NOT NULL COMMENT '添加时间',
  `update_at` datetime NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='数据统计表';

## 发布
### v1

##手动添加商品二维码 7为uniacid， 55为商品ID
/www/server/php/70/bin/php /4T/www/linsd/WeEngine/addons/choujiang_page/doVerification.php 7 55

### v4.0.7

#### 数据库变更
```
ALTER TABLE `ims_choujiang_base` ADD `day_tempete_id` VARCHAR(255) NULL COMMENT '每日推荐模板id' AFTER `template_id`;

CREATE TABLE `ims_choujiang_channel_code` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(300) NOT NULL COMMENT '推广名', 
  `channel` varchar(32) DEFAULT NULL COMMENT '推广标识',
  `size` int(5) DEFAULT NULL COMMENT '二维码生成尺寸',
  `page_url` varchar(500) DEFAULT NULL COMMENT '页面url',
  `wx_code` varchar(600) DEFAULT NULL COMMENT '小程序码',
  `is_del` tinyint(1) NOT NULL DEFAULT '0' COMMENT '删除：0：未删除；1：已删除',
  `create_at` datetime NOT NULL COMMENT '添加时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='推广码表';

CREATE TABLE `ims_choujiang_user_share` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT '0' COMMENT '用户id',
  `share_user_id` int(11) NOT NULL DEFAULT '0' COMMENT '分享者用户id',
  `create_at` datetime DEFAULT NULL COMMENT '创建日期',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户上下级关系表';

CREATE TABLE `ims_choujiang_stat_user_share` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT '0' COMMENT '引流人user_id',
  `amount` int(11) NOT NULL DEFAULT '0' COMMENT '引流人数',
  `create_at` date DEFAULT NULL COMMENT '创建日期',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户分享引流表';

CREATE TABLE `ims_choujiang_stat_channel` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `channel_id` int(11) NOT NULL COMMENT '推广渠道ID',
  `sweep_user` int(11) NOT NULL DEFAULT '0' COMMENT '扫码人数',
  `sweep_time` int(11) NOT NULL DEFAULT '0' COMMENT '扫码次数',
  `sweep_add` int(11) NOT NULL DEFAULT '0' COMMENT '扫码新增',
  `is_del` tinyint(1) NOT NULL DEFAULT '0' COMMENT '删除：0：未删除；1：已删除',
  `create_at` date NOT NULL COMMENT '添加时间',
  `update_at` datetime NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='渠道数据统计表';

CREATE TABLE `ims_choujiang_stat_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_visit` int(11) NOT NULL DEFAULT '0' COMMENT '访问数量',
  `user_add` int(11) NOT NULL DEFAULT '0' COMMENT '新增用户',
  `extand` varchar(1000) NOT NULL COMMENT '	扩展',
  `create_at` date NOT NULL COMMENT '添加时间',
  `update_at` datetime NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户数据统计表';
```


### v5.0.0
```
##去除奖品拼团相关字段
ALTER TABLE `ims_choujiang_goods`
DROP COLUMN `is_pintuan`,
DROP COLUMN `pintuan_maxnum`;

ALTER TABLE `ims_choujiang_goods`
DROP COLUMN `max_cj_code`,
ADD COLUMN `max_cj_code`  int(11) NULL DEFAULT 0 COMMENT '最大抽检号码' AFTER `machine_canyu`;

ALTER TABLE `ims_choujiang_record`
DROP COLUMN `pintuan_id`,
DROP COLUMN `group_verification`,
DROP COLUMN `is_group_member`;

ALTER TABLE `ims_choujiang_record`
DROP COLUMN `codes`,
ADD COLUMN `codes`  text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '抽奖码集合' AFTER `is_machine`;

ALTER TABLE `ims_choujiang_record`
ADD COLUMN `winning_code`  varchar(255) NULL DEFAULT 0 COMMENT '中奖号码' AFTER `codes`;

CREATE TABLE `ims_choujiang_brand` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `openid` varchar(100) NOT NULL COMMENT '用户openid',
  `real_name` varchar(60) NOT NULL COMMENT '用户真实姓名',
  `tel` varchar(30) NOT NULL COMMENT '联系号码',
  `qq` varchar(30) NOT NULL,
  `brand` varchar(300) NOT NULL COMMENT '品牌名称',
  `form_id` varchar(300) NOT NULL COMMENT '微信form_id',
  `create_at` datetime NOT NULL COMMENT '创建时间',
  `update_at` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='品牌(我要上首页)';

ALTER TABLE `ims_choujiang_record`
ADD COLUMN `codes_amount`  int NOT NULL DEFAULT 1 COMMENT '当前拥有码的数量' AFTER `codes`;

ALTER TABLE `ims_choujiang_user`
ADD COLUMN `tel`  varchar(30) NOT NULL DEFAULT 0 COMMENT '手机号码' AFTER `is_manager`;
```


### 2018-08-20 15:26 cdn域名、图片样式接口
ALTER TABLE `ims_choujiang_base` ADD `cdn_speed` INT(1) NOT NULL COMMENT 'cdn加速开关' AFTER `app_icon`, ADD `cdn_url` VARCHAR(200) NOT NULL COMMENT 'cdn域名' AFTER `cdn_speed`, ADD `img_api` VARCHAR(200) NOT NULL COMMENT '图片样式接口' AFTER `cdn_url`;
update ims_choujiang_user set avatar=REPLACE (avatar,'https://rw-byify-com.oss-cn-shanghai.aliyuncs.com/','/');

### 2018-08-20 15:53 nohup 缓存用户头像
nohup /www/server/php/56/bin/php autoGetAvatar.nohup.php 10 > choujiang.log 2>&1 &
/www/server/php/56/bin/php autoGetAvatar.nohup.php 10 init > choujiang.log 2>&1 & //初始化用户数据


### 2018-08-22 10:20 发货单号和公司
ALTER TABLE `ims_choujiang_record` ADD `express_no` VARCHAR(100) NULL COMMENT '物流单号' AFTER `is_group_member`, ADD `express_company` VARCHAR(50) NULL COMMENT '快递公司' AFTER `express_no`;
ALTER TABLE `ims_choujiang_record` ADD `ex_create_at` DATETIME NOT NULL COMMENT '物流信息更新时间' AFTER `express_company`;

### 2018-08-22 11:00 物流表
CREATE TABLE `ims_choujiang_express` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `express_name` varchar(50) NOT NULL COMMENT '物流公司名称',
  `create_at` datetime NOT NULL,
  `update_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

### 2018-08-22 15:00 晒单表
CREATE TABLE `ims_choujiang_share_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `goods_id` int(11) NOT NULL COMMENT '奖品id',
  `goods_icon` text COMMENT '奖品缩略图',
  `cover_img` varchar(100) DEFAULT NULL COMMENT '晒单封面',
  `goods_name` varchar(200) NOT NULL COMMENT '奖品名称',
  `openid` varchar(100) NOT NULL COMMENT '用户openid',
  `nickname` varchar(100) CHARACTER SET utf8mb4 DEFAULT NULL COMMENT '用户名',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像',
  `content` varchar(200) DEFAULT NULL COMMENT '评价内容',
  `img` varchar(1000) DEFAULT NULL COMMENT '评价图片',
  `status` int(1) NOT NULL DEFAULT '0' COMMENT '0：发货、未晒单，1：已晒，-1：拒绝”，2：通过',
  `create_at` datetime NOT NULL,
  `update_at` datetime NOT NULL,
  `sort` int(11) DEFAULT '0' COMMENT '排序',
  `formid` varchar(100) DEFAULT NULL COMMENT 'formid',
  `refuse_reason` text NOT NULL COMMENT '拒绝理由',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


### 2018-08-23 18:21 奖品置顶功能
```
ALTER TABLE `ims_choujiang_goods`
ADD COLUMN `stick_time`  timestamp NULL DEFAULT 0 ON UPDATE CURRENT_TIMESTAMP COMMENT '置顶时间' AFTER `create_time`;
```
ALTER TABLE `ims_choujiang_base` ADD `refuse_template_id` VARCHAR(255) NOT NULL COMMENT '晒单拒绝模板ID' AFTER `day_template_id`;


##v5 发布步骤
发布后台代码

发布前端

前端审核通过

初始化抽奖码
/www/server/php/70/bin/php /4T/www/linsd/WeEngine/addons/choujiang_page/nohup/changeOpenTypeInit.php 11 奖品id

系统cron：1.每月刷新用户中奖次数
1 3 * * * /www/server/php/56/bin/php /www/wwwroot/wx.ymify.com/addons/choujiang_page/cron/cjSystem.cron.php 11 > /www/wwwroot/wx.ymify.com/addons/choujiang_page/cron/cjSystem.cron.log

### 2018-08-30 09:24
ALTER TABLE `ims_choujiang_share_order` CHANGE `content` `content` VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '评价内容';


### 2018-08-30 11:24
ALTER TABLE `ims_choujiang_base` ADD `wechat_status` TINYINT(1) NULL COMMENT '拉新红包开关 0：关闭 1：开启' AFTER `img_api`, ADD `wechat_price` DECIMAL(10,2) NULL COMMENT '拉新红包金额' AFTER `wechat_status`;
ALTER TABLE `ims_choujiang_user` ADD `wechat_blacklist` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '拉新红包黑名单 0:正常 1 黑名单' AFTER `tel`;
CREATE TABLE `we7_linsd`.`ims_choujiang_red_packets_record` ( `id` INT NOT NULL AUTO_INCREMENT , `openid` VARCHAR(100) NOT NULL COMMENT '用户openid' , `receive_money` DECIMAL(20,2) NOT NULL COMMENT '领取的红包' , `all_balance` DECIMAL(20,2) NOT NULL COMMENT '红包总额' , `balance` DECIMAL(20,2) NOT NULL COMMENT '红包余额' , `create_at` DATETIME NOT NULL , `update_at` DATETIME NOT NULL , PRIMARY KEY (`id`)) ENGINE = InnoDB COMMENT = '红包领取记录';
CREATE TABLE `ims_choujiang_red_packets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL COMMENT '用户id',
  `openid` varchar(100) NOT NULL COMMENT '用户openid',
  `nickname` varchar(255) CHARACTER SET utf8mb4 NOT NULL COMMENT '用户名',
  `pay_money` decimal(20,2) NOT NULL DEFAULT '0.00' COMMENT '已支付金额',
  `total_money` decimal(20,2) NOT NULL COMMENT '红包总额',
  `get_time` tinyint(2) DEFAULT '0' COMMENT '当天领取红包次数',
  `create_at` datetime NOT NULL,
  `update_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='红包列表';
ALTER TABLE `ims_choujiang_share_order` CHANGE `status` `status` INT(1) NULL DEFAULT NULL COMMENT '0：发货、未晒单，1：已晒，-1：拒绝”，2：通过';


### 2018-08-31 15:22
ALTER TABLE `ims_choujiang_red_packets_record` ADD `extact` VARCHAR(1000) NULL COMMENT '扩展数据' AFTER `balance`;
ALTER TABLE `ims_choujiang_red_packets` ADD `get_time` TINYINT(2) NULL DEFAULT '0' COMMENT '当天领取红包次数' AFTER `total_money`;
ALTER TABLE `ims_choujiang_base` ADD `wechat_min` DECIMAL(10,2) NULL COMMENT '最低提现金额' AFTER `wechat_price`;



### 2018-09-03 17:52
ALTER TABLE `ims_choujiang_goods`
ADD COLUMN `is_area`  int(1) NULL COMMENT '地域限制，1：开启，0：关闭' AFTER `pintuan_maxnum`,
ADD COLUMN `province`  varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '省份' AFTER `is_area`,
ADD COLUMN `city`  varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '城市' AFTER `province`,
ADD COLUMN `district`  varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '区域' AFTER `city`;
小程序request域名增加名增加https://api.map.baidu.com
ALTER TABLE `ims_choujiang_base`
ADD COLUMN `map_ak`  varchar(100) NULL COMMENT '地图api ak名称' AFTER `wechat_min`;
ALTER TABLE `ims_choujiang_goods` CHANGE `is_area` `is_area` INT(1) NULL DEFAULT '0' COMMENT '地域限制，1：开启，0：关闭';


### 2018-09-04 11:48
CREATE TABLE `ims_choujiang_default_addr` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `openid` varchar(255) DEFAULT NULL,
  `uid` int(11) DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 DEFAULT NULL COMMENT '联系人',
  `tel` varchar(30) CHARACTER SET utf8mb4 NOT NULL DEFAULT '0' COMMENT '手机号码',
  `area` varchar(50) DEFAULT NULL COMMENT '地区',
  `address` varchar(50) DEFAULT NULL COMMENT '详细地址',
  `zip_code` varchar(11) DEFAULT NULL COMMENT '邮政编码',
  `alert_show` int(5) NOT NULL COMMENT '是否授权',
  `update_time` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8;


### 2018-09-06 16:00
CREATE TABLE `ims_choujiang_ip_historical` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(20) DEFAULT NULL,
  `openid` varchar(150) DEFAULT NULL,
  `country` varchar(20) DEFAULT NULL,
  `province` varchar(20) DEFAULT NULL,
  `city` varchar(20) DEFAULT NULL,
  `login_time` varchar(100) DEFAULT NULL,
  `create_time` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `openid` (`openid`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=15 DEFAULT CHARSET=utf8;


### 2018-09-06 16:00
CREATE TABLE `ims_choujiang_ip_historical` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(20) DEFAULT NULL,
  `openid` varchar(150) DEFAULT NULL,
  `country` varchar(20) DEFAULT NULL,
  `province` varchar(20) DEFAULT NULL,
  `city` varchar(20) DEFAULT NULL,
  `login_time` varchar(100) DEFAULT NULL,
  `create_time` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `openid` (`openid`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=15 DEFAULT CHARSET=utf8;


### 2018-09-06 16:00
CREATE TABLE `ims_choujiang_ua_historical` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ua` varchar(200) CHARACTER SET utf8mb4 DEFAULT NULL,
  `openid` varchar(150) DEFAULT NULL,
  `login_time` varchar(100) DEFAULT NULL,
  `create_time` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `openid` (`openid`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;



### 2018-09-06 16:00
CREATE TABLE `ims_choujiang_equipment_historical` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `login_time` varchar(100) DEFAULT NULL,
  `openid` varchar(200) DEFAULT NULL,
  `model` varchar(100) CHARACTER SET utf8mb4 DEFAULT NULL,
  `system` varchar(100) CHARACTER SET utf8mb4 DEFAULT NULL,
  `version` varchar(100) CHARACTER SET utf8mb4 DEFAULT NULL,
  `create_time` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `openid` (`openid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;


### 日志统计定时任务
0 2 * * * /4T/www/linsd/WeEngine/addons/choujiang_page/cron/sh/splitlogs.sh test.byify.cn 7 /www/wwwlogs >> /4T/www/linsd/WeEngine/addons/choujiang_page/cron/sh/choujiang.log


### 2018-09-11 15:56
ALTER TABLE `ims_choujiang_equipment_historical` ADD `brand` VARCHAR(100) NULL COMMENT '手机品牌' AFTER `openid`;


### 2018-09-18 18:41
ALTER TABLE `ims_choujiang_goods` ADD `share_img` VARCHAR(255) NOT NULL COMMENT '晒单封面' AFTER `goods_icon`;

### 2018-09-22 18:41
ALTER TABLE `ims_choujiang_record` ADD `old_rownum` INT(11) NOT NULL DEFAULT '9999' COMMENT '抽奖码排名';

### 2018-09-22 15:13
ALTER TABLE `ims_choujiang_record` CHANGE `old_rownum` INT(11) NOT NULL DEFAULT '10000000' COMMENT '抽奖码排名';


### 2018-09-25 10:30
#ALTER TABLE `ims_choujiang_record` DROP `old_rownum`;
ALTER TABLE `ims_choujiang_record` ADD `order_changed` TINYINT(1) NOT NULL DEFAULT '3' COMMENT '排名升降 1：上升 2：下降 3：不变' AFTER `ex_create_at`;

### 2018-09-28 08:52
ALTER TABLE `ims_choujiang_base` ADD `wechat_rand_price` VARCHAR(20) NULL DEFAULT NULL COMMENT '随机红包金额段' AFTER `map_ak`, ADD `probability_num` VARCHAR(100) NULL DEFAULT NULL COMMENT '各个阶段概率' AFTER `wechat_rand_price`;
ALTER TABLE `ims_choujiang_user_share` ADD `share_money` DECIMAL(10,2) NOT NULL DEFAULT '0' COMMENT '分享用户获取红包金额' AFTER `share_user_id`, ADD `new_user_money` DECIMAL(10,2) NULL DEFAULT '0.00' COMMENT '新用户专享红包' AFTER `share_money`;
ALTER TABLE `ims_choujiang_red_packets` ADD `share_money` DECIMAL(20,2) NOT NULL DEFAULT '0' COMMENT '分享总红包累计' AFTER `total_money`, ADD `new_money` DECIMAL(10,2) NOT NULL DEFAULT '0' COMMENT '新用户红包余额' AFTER `share_money`;

### 2018-09-29 09:59
ALTER TABLE `ims_choujiang_red_packets_record` ADD `pay_types` TINYINT(1) NULL DEFAULT NULL COMMENT '支付类型： 1-新用户专享红包，2-分享红包' AFTER `balance`, ADD `pay_status` TINYINT(1) NOT NULL DEFAULT '1' COMMENT '支付状态：１－　支付成功　２- 支付失败' AFTER `pay_types`;

### 2018-10-07 15:50
ALTER TABLE `ims_choujiang_red_packets_record` ADD `out_trade_no` VARCHAR(28) NULL DEFAULT NULL COMMENT '商户订单号' AFTER `balance`;


### 2018-10-15 17:27
ALTER TABLE `ims_choujiang_red_packets` ADD `share_success` DECIMAL(20,2) NOT NULL DEFAULT '0.00' COMMENT '分享成功金额' AFTER `share_money`;
//ALTER TABLE `ims_choujiang_user` ADD `participated_times` INT(11) NOT NULL DEFAULT '0' COMMENT '参与抽奖次数' AFTER `wechat_blacklist`;


### 2018-10-18 08:51
ALTER TABLE `ims_choujiang_user_share` ADD INDEX(`share_user_id`);

### 2018-10-22 16:34
ALTER TABLE `ims_choujiang_red_packets` ADD `is_get_new_money` INT(1) NOT NULL DEFAULT '0' COMMENT '新用户红包是否可以领 0:：不可领取 1：可以领取' AFTER `new_money`;


### 2018-10-23 09:30新红包发布流程
1、备份数据表 ：dj_choujiang_red_packets,dj_choujiang_red_packets_record,dj_choujiang_user_share
2、清空红包历史数据
DELETE FROM `dj_choujiang_red_packets` WHERE 1;
ALTER TABLE `dj_choujiang_red_packets` AUTO_INCREMENT=1;
DELETE FROM `dj_choujiang_red_packets_record` WHERE 1;
ALTER TABLE `dj_choujiang_red_packets_record` AUTO_INCREMENT=1;
DELETE FROM `dj_choujiang_user_share` WHERE 1;
ALTER TABLE `dj_choujiang_user_share` AUTO_INCREMENT=1;


3、创建补发红包记录表
CREATE TABLE `ims_choujiang_red_packets_record_repeat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `openid` varchar(100) NOT NULL COMMENT '用户openid',
  `receive_money` decimal(20,2) NOT NULL COMMENT '领取的红包',
  `all_balance` decimal(20,2) NOT NULL COMMENT '红包总额',
  `balance` decimal(20,2) NOT NULL COMMENT '红包余额',
  `out_trade_no` varchar(28) DEFAULT NULL COMMENT '商户订单号',
  `old_out_trade_no` varchar(28) DEFAULT NULL COMMENT '原来商户订单号',
  `pay_types` tinyint(1) DEFAULT NULL COMMENT '支付类型： 1-新用户专享红包，2-分享红包',
  `pay_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '支付状态：１－　支付成功　２- 支付失败',
  `extact` varchar(1000) DEFAULT NULL COMMENT '扩展数据',
  `operator` varchar(50) DEFAULT NULL COMMENT '补发操作者',
  `ip_address` varchar(100) DEFAULT NULL COMMENT 'ip地址',
  `create_at` datetime NOT NULL,
  `update_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `openid` (`openid`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

//旧记录补发表 斗奖小程序单独发布，已经执行sql名 ：2018-11-02 09：12
CREATE TABLE `ims_choujiang_red_packets_record_repeat_old` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `openid` varchar(100) NOT NULL COMMENT '用户openid',
  `receive_money` decimal(20,2) NOT NULL COMMENT '领取的红包',
  `all_balance` decimal(20,2) NOT NULL COMMENT '红包总额',
  `balance` decimal(20,2) NOT NULL COMMENT '红包余额',
  `out_trade_no` varchar(28) DEFAULT NULL COMMENT '商户订单号',
  `old_out_trade_no` varchar(28) DEFAULT NULL COMMENT '原来商户订单号',
  `pay_types` tinyint(1) DEFAULT NULL COMMENT '支付类型： 1-新用户专享红包，2-分享红包',
  `pay_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '支付状态：１－　支付成功　２- 支付失败',
  `extact` varchar(1000) DEFAULT NULL COMMENT '扩展数据',
  `operator` varchar(50) DEFAULT NULL COMMENT '补发操作者',
  `ip_address` varchar(100) DEFAULT NULL COMMENT 'ip地址',
  `create_at` datetime NOT NULL,
  `update_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `openid` (`openid`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

4、上传需要补发的旧数据，已经上传新表 ：2018-11-02 09：08
上传分享用户三表数据 ：ims_choujiang_red_packets,ims_choujiang_red_packets_record,ims_choujiang_user_share 重命名 ims_choujiang_red_packets_old,ims_choujiang_red_packets_record_old,ims_choujiang_user_share_old

5、红包黑名单用户标记 已经执行sql名 ：2018-11-02 09：15
UPDATE `dj_choujiang_user` AS t1 LEFT JOIN `dj_choujiang_user_share_old` AS t2 ON t1.id=t2.user_id set t1.`wechat_blacklist`=1 WHERE t2.`share_user_id` in ( '65580','66033','73380','73406','65575','57329'); 结果影响7471行
红包黑名单用户处理：
        $blackUser = array(
            '65580',
            '66033',
            '73380',
            '73406',
            '65575',
            '57329',
        );
UPDATE `ims_choujiang_user_online` AS t1 LEFT JOIN  `ims_choujiang_user_share_old` AS t2 ON t1.id=t2.user_id set t1.`wechat_blacklist`=1 WHERE t2.`share_user_id` in ( '65580','66033','73380','73406','65575','57329')

6、索引
ALTER TABLE `ims_choujiang_red_packets_old` ADD INDEX(`uid`);
ALTER TABLE `ims_choujiang_red_packets` ADD INDEX(`openid`);
ALTER TABLE `ims_choujiang_red_packets` ADD INDEX(`uid`);
ALTER TABLE `ims_choujiang_user_share` ADD INDEX(`share_user_id`);

更新文件是已经修改
上线前，后台要把choujiang_user_online表名替换成choujiang_user


### 2018-12-04 16:43
```
CREATE TABLE `ims_choujiang_score` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL COMMENT '用户id',
  `openid` varchar(100) NOT NULL COMMENT '用户openid',
  `nickname` varchar(255) CHARACTER SET utf8mb4 NOT NULL COMMENT '用户名',
  `use_score` int(11) NOT NULL DEFAULT '0' COMMENT '已使用积分',
  `total_score` int(11) NOT NULL COMMENT '积分总额',
  `create_at` datetime NOT NULL,
  `update_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `openid` (`openid`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='用户积分表';

CREATE TABLE `ims_choujiang_score_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `openid` varchar(100) NOT NULL COMMENT '用户openid',
  `achieve_score` int(11) NOT NULL COMMENT '本次积分',
  `all_score` int(11) NOT NULL COMMENT '积分总额',
  `balance_score` int(11) NOT NULL COMMENT '积分余额',
  `score_types` tinyint(1) DEFAULT NULL COMMENT '积分类型： 1-获取积分，2-使用积分',
  `score_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '积分状态：１－　成功　２- 失败',
  `extact` varchar(1000) DEFAULT NULL COMMENT '扩展数据',
  `create_at` datetime NOT NULL,
  `update_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `score_types` (`score_types`),
  KEY `openid` (`openid`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='用户积分记录表';
```

### 2018-12-05 15:57
```
CREATE TABLE `ims_choujiang_wishing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `openid` varchar(100) DEFAULT NULL,
  `goods_name` varchar(100) NOT NULL COMMENT '商品名称',
  `goods_price` decimal(10,2) DEFAULT '0.00' COMMENT '商品价格',
  `goods_url` varchar(255) DEFAULT NULL COMMENT '商品参考链接',
  `goods_img` varchar(255) COMMENT '商品图片',
  `goods_info` varchar(255) COMMENT '商品描述',
  `likes_num` INT(11) NOT NULL DEFAULT '0' COMMENT '点赞人数',
  `formid` varchar(500) DEFAULT NULL COMMENT '提交表单id，发通知用',
  `accomplish_wishing` INT(11) NOT NULL DEFAULT '0' COMMENT '达成心愿点赞数',
  `release_goods` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '商品是否已经发布 默认未发布 0 ，已经发布 1',
  `release_at` datetime DEFAULT NULL COMMENT '奖品发布时间',
  `status` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '心愿状态 0 待审核 1 审核通过 2 拒绝 3 拒绝并不可编辑',
  `refuse_reason` VARCHAR(200) NULL COMMENT '拒绝消息',
  `is_del` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '是否删除 0 正常 1删除',
  `create_at` datetime NOT NULL COMMENT '创建时间',
  `update_at` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `openid` (`openid`),
  KEY `status` (`status`),
  KEY `is_del` (`is_del`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;


CREATE TABLE `ims_choujiang_wishing_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `openid` varchar(100) DEFAULT NULL,
  `wishing_id` int(11) DEFAULT NULL COMMENT '心愿商品ID',
  `share_id` INT(11) NOT NULL DEFAULT '0' COMMENT '分享用户id 默认为0 无分享用户',
  `formid` varchar(500) DEFAULT NULL COMMENT '提交表单id，发通知用',
  `create_at` datetime NOT NULL COMMENT '创建时间',
  `update_at` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `openid` (`openid`),
  KEY `wishing_id` (`wishing_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;


CREATE TABLE `ims_choujiang_wishing_goods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `goods_id` int(11) DEFAULT NULL COMMENT '奖品ID',
  `wishing_id` int(11) DEFAULT NULL COMMENT '心愿商品ID',
  `is_notice` TINYINT(1) DEFAULT '0' COMMENT '通知记录 0-未通知，1-已通知',
  `create_at` datetime NOT NULL COMMENT '创建时间',
  `update_at` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `goods_id` (`goods_id`),
  KEY `wishing_id` (`wishing_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
```

### 2018-12-06 14:15
ALTER TABLE `ims_choujiang_base` ADD `wishing_min` INT(11) NOT NULL DEFAULT '0' COMMENT '心愿商品最低价' AFTER `red_packets_version`, ADD `wishing_max` INT(11) NOT NULL DEFAULT '0' COMMENT '心愿商品最高价' AFTER `wishing_min`, ADD `wishing_ratio` INT(11) NOT NULL DEFAULT '0' COMMENT '心愿达成比率' AFTER `wishing_max`;
ALTER TABLE `ims_choujiang_base` ADD `wishing_week_release` INT(11) NOT NULL DEFAULT '0' COMMENT '每周发布心愿数' AFTER `wishing_ratio`, ADD `wishing_daily_join` INT(11) NOT NULL DEFAULT '0' COMMENT '每天想要次数' AFTER `wishing_week_release`;
ALTER TABLE `ims_choujiang_base` ADD `score` VARCHAR(500) NULL DEFAULT NULL COMMENT '积分配置' AFTER `wishing_daily_join`;



### 心愿商品发布自动参与抽奖
nohup /www/server/php/70/bin/php /4T/www/linsd/WeEngine/addons/choujiang_page/nohup/wishingReleaseGoods.nohup.php 7 >> wishingReleaseGoods.log 2>&1 &

### 定时发送心愿奖品发布提醒
```
* * * * * /www/server/php/70/bin/php /4T/www/linsd/WeEngine/addons/choujiang_page/cron/pushWishingMessage.cron.php 7 >> /4T/www/linsd/WeEngine/addons/choujiang_page/projectRecord/pushWishingMessage.cron.log
*/15 * * * * /4T/www/linsd/WeEngine/addons/choujiang_page/nohup/heartbeat.sh >> /4T/www/linsd/WeEngine/addons/choujiang_page/projectRecord/heartbeat.log
```

### 心愿墙发布说明
1、升级文件更新并升级
2、配置心跳检测文件，并布置corntab命令运行检测脚本


### 公告栏
```
CREATE TABLE `ims_choujiang_notice` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message` varchar(500) DEFAULT NULL COMMENT '公告详情',
  `sort_num` int(11) DEFAULT '0' COMMENT '排序',
  `start_at` date NOT NULL COMMENT '开始时间',
  `end_at` date NOT NULL COMMENT '结束时间',
  `is_del` TINYINT(1) DEFAULT '0' COMMENT '0-正常，1-删除',
  `create_at` datetime NOT NULL COMMENT '创建时间',
  `update_at` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
```