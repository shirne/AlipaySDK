<?php
/**
 * 配置格式示例
 */

$config = array (	
	//应用ID,您的APPID。
	'app_id' => "2021001104611408",

	//商户私钥，您的原始格式RSA私钥
	'merchant_private_key' => "",
	
	//异步通知地址
	'notify_url' => "https://domain.com/alipay-sdk-PHP-4.2.0/notify_url.php",
	
	//同步跳转
	'return_url' => "https://domain.com/alipay-sdk-PHP-4.2.0/return_url.php",

	//编码格式
	'charset' => "UTF-8",

	//签名方式
	'sign_type'=>"RSA2",

	//支付宝网关
	'gatewayUrl' => "https://openapi.alipay.com/gateway.do",

	//支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
	'alipay_public_key' => "",


	'is_cert'=>1,
	'app_cert_path'=>__DIR__.'/../../cert/appCertPublicKey_xxx.crt',
	'root_cert_path'=>__DIR__.'/../../cert/alipayRootCert.crt',
	'alipay_cert_path'=>__DIR__.'/../../cert/alipayCertPublicKey_RSA2.crt'
		
);