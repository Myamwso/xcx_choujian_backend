<?php
require_once __DIR__."/config.php";
require_once __DIR__."/common.func.php";

global $_W;
foreach($_W['module_prefix'] as $k =>$v){
    $sql="CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_base',$k)." (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `appid` varchar(255) DEFAULT NULL COMMENT '跳转小程序appid',
  `appsecret` varchar(255) DEFAULT NULL COMMENT '密钥',
  `template_id` varchar(255) DEFAULT NULL COMMENT '模板通知',
  `appkey` varchar(255) DEFAULT NULL COMMENT '支付密钥',
  `mch_id` varchar(255) DEFAULT NULL COMMENT '商户id',
  `join_num` int(11) DEFAULT '0',
  `smoke_num` int(11) DEFAULT NULL,
  `winning_num` int(11) DEFAULT NULL,
  `poundage` int(11) DEFAULT NULL,
  `xcx_price` decimal(10,2) DEFAULT '0.00' COMMENT '小程序跳转 appid 付费价格',
  `share_num` int(11) DEFAULT NULL,
  `cheat_status` int(11) DEFAULT NULL,
  `upfile` varchar(255) DEFAULT NULL,
  `keypem` varchar(255) DEFAULT NULL,
  `envelope_draw` int(11) DEFAULT '0' COMMENT '0未开启 1已开启',
  `index_title` varchar(100) DEFAULT NULL COMMENT '首页标题',
  `type` int(1) DEFAULT NULL COMMENT '储存类型',
  `aliosskey` varchar(20) DEFAULT NULL COMMENT '阿里云OSS key id',
  `aliosssecret` varchar(35) DEFAULT NULL COMMENT '阿里云OSS Key Secret',
  `internal` int(1) DEFAULT NULL COMMENT '是否内外上传',
  `bucket` varchar(50) DEFAULT NULL COMMENT '阿里云OSS Bucket',
  `url` varchar(100) DEFAULT NULL COMMENT '阿里oss url前缀',
  `location` varchar(100) DEFAULT NULL COMMENT '图片上传远程url',
  `extensions_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '一次扩展功能付费金额',
  `app_icon` varchar(200) NOT NULL COMMENT '小程序图片',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_cheat',$k)." (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `icon` varchar(1000) DEFAULT NULL,
  `content` varchar(6666) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_cheat_nav',$k)." (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `appid` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_earnings',$k)." (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `openid` varchar(100) DEFAULT NULL,
  `money` decimal(10,2) DEFAULT NULL,
  `create_time` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_exchange',$k)." (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `goods_id` int(11) DEFAULT NULL,
  `openid` varchar(100) DEFAULT NULL,
  `status` int(11) DEFAULT '0' COMMENT '0-未领取  1-已领取',
  `create_time` varchar(100) DEFAULT NULL,
  `verification` varchar(255) DEFAULT NULL,
  `orders` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_goods',$k)." (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `goods_name` varchar(100) NOT NULL,
  `goods_num` int(11) DEFAULT NULL,
  `smoke_time` varchar(100) DEFAULT NULL COMMENT '自动开奖时间',
  `smoke_num` int(11) DEFAULT NULL COMMENT '开奖人数',
  `smoke_set` int(11) DEFAULT NULL COMMENT '开奖条件(0、按时间  1、按人数  2、手动)',
  `goods_icon` text COMMENT '商品图片',
  `goods_sponsorship` varchar(100) DEFAULT NULL COMMENT '奖品赞助商',
  `status` int(11) DEFAULT '0' COMMENT '奖品状态(1、已结束   0、正在进行中  2、已过期)',
  `goods_openid` varchar(100) DEFAULT NULL COMMENT '发起人',
  `send_time` int(11) DEFAULT NULL COMMENT '开奖的时间',
  `content` text COMMENT '奖品介绍',
  `sponsorship_text` varchar(255) DEFAULT NULL COMMENT '赞助介绍',
  `is_del` int(11) DEFAULT '1' COMMENT '1 未删除 -1 已删除',
  `The_winning` int(11) DEFAULT '0',
  `goods_winning` text CHARACTER SET utf8mb4,
  `sponsorship_appid` varchar(200) DEFAULT NULL,
  `openid_arr` text,
  `audit_status` int(11) DEFAULT '0' COMMENT '审核状态 0-审核中 1通过 -1失败',
  `mouth_command` varchar(255) DEFAULT NULL COMMENT '口令',
  `join_conditions` int(11) DEFAULT '0' COMMENT '是否需要付费参与 0 没有  1 付费 2口令',
  `price` decimal(10,2) DEFAULT '0.00' COMMENT '付费参与价格',
  `red_envelope` decimal(10,2) DEFAULT NULL COMMENT '红包金额',
  `card_info` text COMMENT '卡号',
  `goods_status` int(11) DEFAULT '0' COMMENT '0 实物 1红包 2电子卡',
  `goods_images` text COMMENT '奖品详情图',
  `sponsorship_url` varchar(150) DEFAULT NULL COMMENT '跳转小程序路径',
  `sponsorship_content` text COMMENT '赞助商介绍',
  `is_zq` int(1) DEFAULT '0' COMMENT '是否付费增强版 1、付费版 0、普通版',
  `is_pintuan` int(1) DEFAULT '0' COMMENT '是否开启拼团 1、开启拼团 0、无拼团',
  `pintuan_maxnum` int(11) DEFAULT '0' COMMENT '拼团上限人数',
  `draw_message` varchar(200) DEFAULT NULL COMMENT '抽奖说明',
  `canyunum` int(11) unsigned DEFAULT '0' COMMENT '参与抽奖总人数',
  `create_time` varchar(100) DEFAULT NULL COMMENT '奖品创建时间',
  `formid` varchar(100) DEFAULT NULL,
  `machine_canyu` tinyint(1) DEFAULT '0' COMMENT '机器人是否参与抽奖：0：否;1：是',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_machine_num',$k)." (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `goods_id` int(11) NOT NULL COMMENT '商品id',
  `machine_num` int(11) NOT NULL DEFAULT '0' COMMENT '机器人数量',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态：0：正在添加中；1：添加结束',
  `added_machine_num` int(11) NOT NULL DEFAULT '0' COMMENT '已添加的机器人数量',
  PRIMARY KEY (`id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='商品机器人关联表';
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_pay_record',$k)." (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `openid` varchar(255) DEFAULT NULL COMMENT '用户',
  `vip_id` int(11) DEFAULT NULL COMMENT '购买类别id',
  `create_time` varchar(100) DEFAULT NULL COMMENT '消费时间',
  `total` varchar(50) DEFAULT NULL COMMENT '总额',
  `num` int(11) DEFAULT NULL COMMENT '新增次数',
  `nickname` varchar(100) CHARACTER SET utf8mb4 DEFAULT NULL,
  `status` int(11) DEFAULT NULL COMMENT '1-购买发起次数   2-发起红包抽奖  3-付费抽奖   4-提现 5-中奖收益  6-支付跳转小程序 7-购买扩展功能发起次数',
  `poundage` varchar(50) DEFAULT '0' COMMENT '手续费',
  `y_total` decimal(10,2) DEFAULT NULL COMMENT '原总价',
  `goods_id` int(11) DEFAULT NULL COMMENT '奖品id',
  `avatar` varchar(666) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_problems',$k)." (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `answer` text,
  `status` int(11) DEFAULT '0' COMMENT '1推送 0未推送',
  `sort` int(11) NOT NULL COMMENT '排序',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_record',$k)." (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `goods_id` int(11) DEFAULT NULL COMMENT '奖品',
  `user_name` varchar(50) DEFAULT NULL COMMENT '收货人',
  `status` int(11) NOT NULL COMMENT '中奖状态(0、未中   1、已中   -1、已中奖超过一天未填写收货信息视为放弃) ',
  `create_time` varchar(100) DEFAULT NULL,
  `goods_name` varchar(100) DEFAULT NULL,
  `user_address` varchar(255) DEFAULT NULL COMMENT '收货地址',
  `user_zip` varchar(100) DEFAULT NULL COMMENT '邮编',
  `openid` varchar(100) DEFAULT NULL,
  `nickname` varchar(100) CHARACTER SET utf8mb4 DEFAULT NULL COMMENT '用户名',
  `avatar` varchar(255) DEFAULT NULL COMMENT '用户头像',
  `user_tel` varchar(100) DEFAULT NULL COMMENT '联系人电话',
  `finish_time` varchar(100) NOT NULL DEFAULT '0' COMMENT '开奖时间',
  `formid` varchar(100) DEFAULT NULL,
  `card_num` varchar(100) DEFAULT NULL,
  `card_password` varchar(100) DEFAULT NULL,
  `pintuan_id` int(11) NOT NULL DEFAULT '0' COMMENT '拼团ID',
  `group_verification` varchar(200) DEFAULT NULL COMMENT '组团二维码',
  `del` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0为未删除,1为删除',
  `is_machine` tinyint(1) DEFAULT '0' COMMENT '是否为机器人：0:否；1：是',
  `is_group_member` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否团成员',
  PRIMARY KEY (`id`),
  KEY `goods_id` (`goods_id`),
  KEY `status` (`status`,`user_name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_speak',$k)." (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `openid` varchar(100) DEFAULT NULL,
  `goods_id` int(11) DEFAULT NULL,
  `content` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_user',$k)." (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `openid` varchar(150) CHARACTER SET utf8 DEFAULT NULL,
  `avatar` varchar(500) CHARACTER SET utf8 DEFAULT NULL,
  `nickname` varchar(100) DEFAULT NULL,
  `create_time` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
  `yu_num` int(11) DEFAULT '0',
  `mf_num` int(11) DEFAULT NULL COMMENT '免费次数',
  `smoke_num` int(11) DEFAULT NULL,
  `smoke_share_num` int(11) DEFAULT NULL,
  `winning_num` int(11) DEFAULT NULL,
  `extensions_num` int(11) DEFAULT '0' COMMENT '扩展功能次数	',
  `send_time` varchar(50) CHARACTER SET utf8 DEFAULT NULL,
  `earnings` decimal(10,2) DEFAULT '0.00' COMMENT '收益',
  `share_num` int(11) DEFAULT NULL,
  `share_num_time` varchar(100) CHARACTER SET utf8 DEFAULT NULL COMMENT '更新时间',
  `is_machine` tinyint(1) DEFAULT '0' COMMENT '是否为机器人：0：否；1：是',
  `is_manager` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否后台管理员 0--非管理员 1--管理员',
  PRIMARY KEY (`id`),
  UNIQUE KEY `openid` (`openid`),
  KEY `is_machine` (`is_machine`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_verification',$k)." (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `verification` varchar(500) DEFAULT NULL COMMENT '二维码',
  `goods_id` int(11) DEFAULT NULL,
  `haibao` varchar(500) DEFAULT NULL,
  `group_verification` varchar(500) DEFAULT NULL COMMENT '组团二维码',
  `group_haibao` varchar(500) DEFAULT NULL COMMENT '组团海报',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_vip_num',$k)." (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `title` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `num` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_withdrawal',$k)." (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `nickname` varchar(255) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT '0.00' COMMENT '原金额',
  `status` int(11) DEFAULT '0' COMMENT '1-通过 -1拒绝',
  `create_time` varchar(100) DEFAULT NULL,
  `openid` varchar(100) DEFAULT NULL,
  `poundage` decimal(10,2) DEFAULT '0.00' COMMENT '手续费',
  `money` decimal(10,2) DEFAULT '0.00' COMMENT '实际提现金额',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_xcx',$k)." (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `icon` text COMMENT '小程序图标',
  `name` varchar(100) DEFAULT NULL COMMENT '小程序名字',
  `title` varchar(100) DEFAULT NULL COMMENT '小程序标题',
  `url` varchar(255) DEFAULT NULL COMMENT '小程序链接',
  `appid` varchar(100) DEFAULT NULL,
  `appsecret` varchar(150) DEFAULT NULL,
  `status` int(11) DEFAULT '0' COMMENT '1推荐 0不推荐',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_channel_code',$k)." (
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
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_user_share',$k)." (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT '0' COMMENT '用户id',
  `share_user_id` int(11) NOT NULL DEFAULT '0' COMMENT '分享者用户id',
  `create_at` datetime DEFAULT NULL COMMENT '创建日期',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户上下级关系表';
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_stat_user_share',$k)." (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT '0' COMMENT '引流人user_id',
  `amount` int(11) NOT NULL DEFAULT '0' COMMENT '引流人数',
  `create_at` date DEFAULT NULL COMMENT '创建日期',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户分享引流表';
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_stat_channel',$k)." (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `channel_id` int(11) NOT NULL COMMENT '推广渠道ID',
  `sweep_user` int(11) NOT NULL DEFAULT '0' COMMENT '扫码人数',
  `sweep_time` int(11) NOT NULL DEFAULT '0' COMMENT '扫码次数',
  `sweep_add` int(11) NOT NULL DEFAULT '0' COMMENT '扫码新增',
  `is_del` tinyint(1) NOT NULL DEFAULT '0' COMMENT '删除：0：未删除；1：已删除',
  `create_at` date NOT NULL COMMENT '添加时间',
  `update_at` datetime NOT NULL COMMENT '修改时间',
  `extand` varchar(1000) NOT NULL DEFAULT '' COMMENT '扩展',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='渠道数据统计表';
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_stat_user',$k)." (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_visit` int(11) NOT NULL DEFAULT '0' COMMENT '访问数量',
  `user_add` int(11) NOT NULL DEFAULT '0' COMMENT '新增用户',
  `extand` varchar(1000) NOT NULL COMMENT '	扩展',
  `create_at` date NOT NULL COMMENT '添加时间',
  `update_at` datetime NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户数据统计表';
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_brand',$k)." (
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
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_express', $k)." (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `express_name` varchar(50) NOT NULL COMMENT '物流公司名称',
  `create_at` datetime NOT NULL,
  `update_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_share_order', $k)." (
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
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_red_packets_record', $k)." (
	`id` INT NOT NULL AUTO_INCREMENT,
	`openid` VARCHAR (100) NOT NULL COMMENT '用户openid',
	`receive_money` DECIMAL (20, 2) NOT NULL COMMENT '领取的红包',
	`all_balance` DECIMAL (20, 2) NOT NULL COMMENT '红包总额',
	`balance` DECIMAL (20, 2) NOT NULL COMMENT '红包余额',
	`create_at` DATETIME NOT NULL,
	`update_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE = INNODB  DEFAULT CHARSET=utf8 COMMENT = '红包领取记录';
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_red_packets', $k)." (
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
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_default_addr', $k)." (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_ip_historical', $k)." (
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_ip_historical', $k)." (
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_ua_historical', $k)." (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ua` varchar(200) CHARACTER SET utf8mb4 DEFAULT NULL,
  `openid` varchar(150) DEFAULT NULL,
  `login_time` varchar(100) DEFAULT NULL,
  `create_time` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `openid` (`openid`) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_equipment_historical', $k)." (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `login_time` varchar(100) DEFAULT NULL,
  `openid` varchar(200) DEFAULT NULL,
  `model` varchar(100) CHARACTER SET utf8mb4 DEFAULT NULL,
  `system` varchar(100) CHARACTER SET utf8mb4 DEFAULT NULL,
  `version` varchar(100) CHARACTER SET utf8mb4 DEFAULT NULL,
  `create_time` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `openid` (`openid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_red_packets_record_repeat', $k). " (
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
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_score', $k). " (
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
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_score_record', $k). " (
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
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_wishing', $k). " (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `openid` varchar(100) DEFAULT NULL,
  `goods_name` varchar(100) NOT NULL COMMENT '商品名称',
  `goods_price` decimal(10,2) DEFAULT '0.00' COMMENT '商品价格',
  `goods_url` varchar(255) DEFAULT NULL COMMENT '商品参考链接',
  `goods_img` varchar(255) COMMENT '商品图片',
  `goods_info` varchar(255) COMMENT '商品描述',
  `likes_num` INT(11) NOT NULL DEFAULT '0' COMMENT '点赞人数',
  `formid` varchar(1000) DEFAULT NULL COMMENT '提交表单id，发通知用',
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
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_wishing_record', $k). " (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `openid` varchar(100) DEFAULT NULL,
  `wishing_id` int(11) DEFAULT NULL COMMENT '心愿商品ID',
  `share_id` INT(11) NOT NULL DEFAULT '0' COMMENT '分享用户id 默认为0 无分享用户',
  `formid` varchar(1000) DEFAULT NULL COMMENT '提交表单id，发通知用',
  `create_at` datetime NOT NULL COMMENT '创建时间',
  `update_at` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `openid` (`openid`),
  KEY `wishing_id` (`wishing_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_wishing_goods', $k). " (
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
CREATE TABLE IF NOT EXISTS ".tablename_cj('choujiang_notice', $k). " (
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
";
    pdo_run($sql);

    //获取表全部字段名称
    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_base',$k));
    //判断表字段是否存在
    if(!in_array("day_tempete_id",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_base',$k)." ADD `day_tempete_id` VARCHAR(255) NULL COMMENT '每日推荐模板id' AFTER `template_id`;");
    }

    //获取表全部字段名称
    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_base',$k));
    //判断表字段是否存在
    if(!in_array("cdn_speed",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_base',$k)." ADD `cdn_speed` INT(1) NOT NULL COMMENT 'cdn加速开关' AFTER `app_icon`, ADD `cdn_url` VARCHAR(200) NOT NULL COMMENT 'cdn域名' AFTER `cdn_speed`, ADD `img_api` VARCHAR(200) NOT NULL COMMENT '图片样式接口' AFTER `cdn_url`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_goods',$k));
    //判断表字段是否存在
    if(!in_array("max_cj_code",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_goods',$k)." ADD COLUMN `max_cj_code`  int(11) NULL DEFAULT 0 COMMENT '最大抽检号码' AFTER `machine_canyu`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_record',$k));
    //判断表字段是否存在
    if(!in_array("codes",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_record',$k)." ADD COLUMN `codes`  text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '抽奖码集合' AFTER `is_machine`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_record',$k));
    //判断表字段是否存在
    if(!in_array("winning_code",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_record',$k)." ADD COLUMN `winning_code`  varchar(255) NULL DEFAULT 0 COMMENT '中奖号码' AFTER `codes`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_record',$k));
    //判断表字段是否存在
    if(!in_array("codes_amount",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_record',$k)." ADD COLUMN `codes_amount`  int NOT NULL DEFAULT 1 COMMENT '当前拥有码的数量' AFTER `codes`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_user',$k));
    //判断表字段是否存在
    if(!in_array("tel",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_user',$k)." ADD COLUMN `tel`  varchar(30) NOT NULL DEFAULT 0 COMMENT '手机号码' AFTER `is_manager`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_record',$k));
    //判断表字段是否存在
    if(!in_array("express_no",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_record',$k)." ADD `express_no` VARCHAR(100) NULL COMMENT '物流单号' AFTER `is_group_member`, ADD `express_company` VARCHAR(50) NULL COMMENT '快递公司' AFTER `express_no`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_record',$k));
    //判断表字段是否存在
    if(!in_array("ex_create_at",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_record',$k)." ADD `ex_create_at` DATETIME NOT NULL COMMENT '物流信息更新时间' AFTER `express_company`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_base',$k));
    //判断表字段是否存在
    if(!in_array("refuse_template_id",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_base',$k)."  ADD `refuse_template_id` VARCHAR(255) NOT NULL COMMENT '晒单拒绝模板ID' AFTER `day_template_id`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_base',$k));
    //判断表字段是否存在
    if(!in_array("wechat_status",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_base',$k)." ADD `wechat_status` TINYINT(1) NULL COMMENT '拉新红包开关 0：关闭 1：开启' AFTER `img_api`, ADD `wechat_price` DECIMAL(10,2) NULL COMMENT '拉新红包金额' AFTER `wechat_status`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_user',$k));
    //判断表字段是否存在
    if(!in_array("wechat_blacklist",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_user',$k)." ADD `wechat_blacklist` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '拉新红包黑名单 0:正常 1 黑名单' AFTER `tel`;");
    }

    pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_share_order',$k)." CHANGE `status` `status` INT(1) NULL DEFAULT NULL COMMENT '0：发货、未晒单，1：已晒，-1：拒绝”，2：通过';");

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_red_packets_record',$k));
    //判断表字段是否存在
    if(!in_array("extact",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_red_packets_record',$k)." ADD `extact` VARCHAR(1000) NULL COMMENT '扩展数据' AFTER `balance`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_red_packets',$k));
    //判断表字段是否存在
    if(!in_array("get_time",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_red_packets',$k)." ADD `get_time` TINYINT(2) NULL DEFAULT '0' COMMENT '当天领取红包次数' AFTER `total_money`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_base',$k));
    //判断表字段是否存在
    if(!in_array("wechat_min",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_base',$k)." ADD `wechat_min` DECIMAL(10,2) NULL COMMENT '最低提现金额' AFTER `wechat_price`;");
    }

    //2018-09-06
    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_goods',$k));
    //判断表字段是否存在
    if(!in_array("is_area",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_goods',$k)." ADD COLUMN `is_area`  int(1) NULL COMMENT '地域限制，1：开启，0：关闭' AFTER `pintuan_maxnum`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_goods',$k));
    //判断表字段是否存在
    if(!in_array("province",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_goods',$k)." ADD COLUMN `province`  varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '省份' AFTER `is_area`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_goods',$k));
    //判断表字段是否存在
    if(!in_array("city",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_goods',$k)." ADD COLUMN `city`  varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '城市' AFTER `province`;");
    }

    pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_goods',$k)." CHANGE `is_area` `is_area` INT(1) NULL DEFAULT '0' COMMENT '地域限制，1：开启，0：关闭';");

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_base',$k));
    //判断表字段是否存在
    if(!in_array("map_ak",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_base',$k)." ADD COLUMN `map_ak`  varchar(100) NULL COMMENT '地图api ak名称' AFTER `wechat_min`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_equipment_historical',$k));
    //判断表字段是否存在
    if(!in_array("brand",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_equipment_historical',$k)." ADD `brand` VARCHAR(100) NULL COMMENT '手机品牌' AFTER `openid`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_goods',$k));
    //判断表字段是否存在
    if(!in_array("share_img",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_goods',$k)." ADD `share_img` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '晒单封面' AFTER `goods_icon`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_record',$k));
    //判断表字段是否存在
    if(!in_array("old_rownum",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_record',$k)." ADD `old_rownum` INT(11) NOT NULL DEFAULT '9999' COMMENT '抽奖码排名';");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_record',$k));
    //判断表字段是否存在
    if(!in_array("old_rownum",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_record',$k)." CHANGE `old_rownum` INT(11) NOT NULL DEFAULT '10000000' COMMENT '抽奖码排名';");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_record',$k));
    //判断表字段是否存在
    if(!in_array("order_changed",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_record',$k)." ADD `order_changed` TINYINT(1) NOT NULL DEFAULT '3' COMMENT '排名升降 1：上升 2：下降 3：不变' AFTER `ex_create_at`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_base',$k));
    //判断表字段是否存在
    if(!in_array("wechat_rand_price",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_base',$k)." ADD `wechat_rand_price` VARCHAR(20) NULL DEFAULT NULL COMMENT '随机红包金额段' AFTER `map_ak`, ADD `probability_num` VARCHAR(100) NULL DEFAULT NULL COMMENT '各个阶段概率' AFTER `wechat_rand_price`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_user_share',$k));
    //判断表字段是否存在
    if(!in_array("share_money",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_user_share',$k)." ADD `share_money` DECIMAL(10,2) NOT NULL DEFAULT '0' COMMENT '分享用户获取红包金额' AFTER `share_user_id`, ADD `new_user_money` DECIMAL(10,2) NULL DEFAULT '0.00' COMMENT '新用户专享红包' AFTER `share_money`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_red_packets',$k));
    //判断表字段是否存在
    if(!in_array("share_money",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_red_packets',$k)." ADD `share_money` DECIMAL(20,2) NOT NULL DEFAULT '0' COMMENT '分享总红包累计' AFTER `total_money`, ADD `new_money` DECIMAL(10,2) NOT NULL DEFAULT '0' COMMENT '新用户红包余额' AFTER `share_money`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_red_packets_record',$k));
    //判断表字段是否存在
    if(!in_array("pay_types",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_red_packets_record',$k)." ADD `pay_types` TINYINT(1) NULL DEFAULT NULL COMMENT '支付类型： 1-新用户专享红包，2-分享红包' AFTER `balance`, ADD `pay_status` TINYINT(1) NOT NULL DEFAULT '1' COMMENT '支付状态：１－　支付成功　２- 支付失败' AFTER `pay_types`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_red_packets_record',$k));
    //判断表字段是否存在
    if(!in_array("out_trade_no",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_red_packets_record',$k)." ADD `out_trade_no` VARCHAR(28) NULL DEFAULT NULL COMMENT '商户订单号' AFTER `balance`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_red_packets',$k));
    //判断表字段是否存在
    if(!in_array("share_success",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_red_packets',$k)." ADD `share_success` DECIMAL(20,2) NOT NULL DEFAULT '0.00' COMMENT '分享成功金额' AFTER `share_money`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_red_packets',$k));
    //判断表字段是否存在
    if(!in_array("is_get_new_money",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_red_packets',$k)." ADD `is_get_new_money` INT(1) NOT NULL DEFAULT '0' COMMENT '新用户红包是否可以领 0:：不可领取 1：可以领取' AFTER `new_money`;");
    }

    //判断表字段索引是否存在 红包统计表 用户uid索引
    $existsIndex = pdo_fetchall_cj("SHOW INDEX FROM ".tablename_cj('choujiang_red_packets',$k)." WHERE column_name LIKE 'uid'");
    if(empty($existsIndex)){
        //字段索引不存在，开始创建索引
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_red_packets',$k)." ADD INDEX(`uid`);");
    }

    //判断表字段索引是否存在 红包统计表 用户openid索引
    $existsIndex = pdo_fetchall_cj("SHOW INDEX FROM ".tablename_cj('choujiang_red_packets',$k)." WHERE column_name LIKE 'openid'");
    if(empty($existsIndex)){
        //字段索引不存在，开始创建索引
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_red_packets',$k)." ADD INDEX(`openid`);");
    }

    //判断表字段索引是否存在 用户分享表 分享用户share_user_id索引
    $existsIndex = pdo_fetchall_cj("SHOW INDEX FROM ".tablename_cj('choujiang_user_share',$k)." WHERE column_name LIKE 'share_user_id'");
    if(empty($existsIndex)){
        //字段索引不存在，开始创建索引
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_user_share',$k)." ADD INDEX(`share_user_id`);");
    }

    //判断表字段索引是否存在 抽奖记录表 查询openid索引
    $existsIndex = pdo_fetchall_cj("SHOW INDEX FROM ".tablename_cj('choujiang_recor',$k)." WHERE column_name LIKE 'openid'");
    if(empty($existsIndex)){
        //字段索引不存在，开始创建索引
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_record',$k)." ADD INDEX(`openid`);");
    }

    //判断表字段索引是否存在 红包记录表 用户openid索引
    $existsIndex = pdo_fetchall_cj("SHOW INDEX FROM ".tablename_cj('choujiang_red_packets_record',$k)." WHERE column_name LIKE 'openid'");
    if(empty($existsIndex)){
        //字段索引不存在，开始创建索引
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_red_packets_recor',$k)." ADD INDEX(`openid`);");
    }

    //判断表字段索引是否存在 红包记录表 用户openid索引
    $existsIndex = pdo_fetchall_cj("SHOW INDEX FROM ".tablename_cj('choujiang_share_order',$k)." WHERE column_name LIKE 'openid'");
    if(empty($existsIndex)){
        //字段索引不存在，开始创建索引
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_share_order',$k)." ADD INDEX(`openid`);");
    }

    //判断表字段索引是否存在 红包记录表 用户openid索引
    $existsIndex = pdo_fetchall_cj("SHOW INDEX FROM ".tablename_cj('choujiang_share_order',$k)." WHERE column_name LIKE 'goods_id'");
    if(empty($existsIndex)){
        //字段索引不存在，开始创建索引
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_share_order',$k)." ADD INDEX(`goods_id`);");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_base',$k));
    //判断表字段是否存在
    if(!in_array("share_limit",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_base',$k)." ADD COLUMN `share_limit` int NULL DEFAULT '0' COMMENT '每人每日分享上限' AFTER `probability_num`;");
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_base',$k)." ADD COLUMN `red_packets_version` int(11) NULL DEFAULT '1' COMMENT '红包版本' AFTER `share_limit`;");
    }

    $arr_fields = pdo_fetchallfields_cj(tablename_cj('choujiang_base',$k));
    //判断表字段是否存在
    if(!in_array("wishing_min",$arr_fields)) {
        //字段不存在执行添加
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_base',$k)." ADD `wishing_min` INT(11) NOT NULL DEFAULT '0' COMMENT '心愿商品最低价' AFTER `red_packets_version`, ADD `wishing_max` INT(11) NOT NULL DEFAULT '0' COMMENT '心愿商品最高价' AFTER `wishing_min`, ADD `wishing_ratio` INT(11) NOT NULL DEFAULT '0' COMMENT '心愿达成比率' AFTER `wishing_max`;");
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_base',$k)." ADD `wishing_week_release` INT(11) NOT NULL DEFAULT '0' COMMENT '每周发布心愿数' AFTER `wishing_ratio`, ADD `wishing_daily_join` INT(11) NOT NULL DEFAULT '0' COMMENT '每天想要次数' AFTER `wishing_week_release`;");
        pdo_query_cj("ALTER TABLE ".tablename_cj('choujiang_base',$k)." ADD `score` VARCHAR(500) NULL DEFAULT NULL COMMENT '积分配置' AFTER `wishing_daily_join`;");
    }
}

?>
