<?php
/* *
 * 功能：支付宝手机网站alipay.trade.close (统一收单交易关闭接口)业务参数封装
 * 版本：2.0
 * 修改日期：2016-11-01
 * 说明：
 * 以下代码只是为了方便商户测试而提供的样例代码，商户可以根据自己网站的需要，按照技术文档编写,并非一定要使用该代码。
 */

namespace AlipaySDK;

use Psr\Log\LoggerInterface;
use AlipaySDK\aop\AopCertClient;
use AlipaySDK\aop\AopClient;
use AlipaySDK\aop\request\AlipayTradeWapPayRequest;
use AlipaySDK\aop\request\AlipayTradeQueryRequest;
use AlipaySDK\aop\request\AlipayTradeRefundRequest;
use AlipaySDK\aop\request\AlipayTradeCloseRequest;
use AlipaySDK\aop\request\AlipayTradeFastpayRefundQueryRequest;
use AlipaySDK\aop\request\AlipayDataDataserviceBillDownloadurlQueryRequest;

class AlipayTradeService {

	public $is_cert = false;

	public $alipay_cert_path = '';
	public $root_cert_path = '';
	public $app_cert_path = '';

	//支付宝网关地址
	public $gateway_url = "https://openapi.alipay.com/gateway.do";

	//支付宝公钥
	public $alipay_public_key;

	//商户私钥
	public $private_key;

	//应用id
	public $appid;

	//编码格式
	public $charset = "UTF-8";

	public $token = NULL;
	
	//返回数据格式
	public $format = "json";

	//签名方式
	public $signtype = "RSA";

	public LoggerInterface $log;

	function __construct($config){
		$this->appid = $config['app_id'];
		$this->private_key = $config['merchant_private_key'];
		
		if(isset($config['gatewayUrl']))$this->gateway_url = $config['gatewayUrl'];
		if(isset($config['charset']))$this->charset = $config['charset'];
		if(isset($config['sign_type']))$this->signtype=$config['sign_type'];

		$this->is_cert = empty($config['is_cert'])?0:1;

		if($this->is_cert){
			$this->app_cert_path = $config['app_cert_path'];
			$this->root_cert_path = $config['root_cert_path'];
			$this->alipay_cert_path = $config['alipay_cert_path'];
		}else{
			
			$this->alipay_public_key = $config['alipay_public_key'];
			if(empty($this->alipay_public_key)||trim($this->alipay_public_key)==""){
				throw new \Exception("alipay_public_key should not be NULL!");
			}
		}
	

		if(empty($this->appid)||trim($this->appid)==""){
			throw new \Exception("appid should not be NULL!");
		}
		if(empty($this->private_key)||trim($this->private_key)==""){
			throw new \Exception("private_key should not be NULL!");
		}
		if(empty($this->charset)||trim($this->charset)==""){
			throw new \Exception("charset should not be NULL!");
		}
		if(empty($this->gateway_url)||trim($this->gateway_url)==""){
			throw new \Exception("gateway_url should not be NULL!");
		}

	}
	function AlipayWapPayService($config) {
		$this->__construct($config);
	}

	protected $aop = null;
	function getClient(){
		if(is_null($this->aop)){
			if($this->is_cert){
				require_once __DIR__.'/aop/AopCertClient.php';
				$this->aop = new AopCertClient ();
			}else{
				require_once __DIR__.'/aop/AopClient.php';
				$this->aop = new AopClient ();
			}
			// 开启页面信息输出
			$this->aop->debugInfo=false;
		}
		return $this->aop;
	}

	/**
	 * alipay.trade.wap.pay
	 * @param $builder 业务参数，使用builder中的对象生成。
	 * @param $return_url 同步跳转地址，公网可访问
	 * @param $notify_url 异步通知地址，公网可以访问
	 * @return $response 支付宝返回的信息
 	*/
	function wapPay($builder,$return_url,$notify_url,$ispage=true,$method='post') {
	
		$biz_content=$builder->getBizContent();
		//打印业务参数
		$this->writeLog($biz_content);

		require_once __DIR__.'/aop/request/AlipayTradeWapPayRequest.php';
	
		$request = new AlipayTradeWapPayRequest();
	
		$request->setNotifyUrl($notify_url);
		$request->setReturnUrl($return_url);
		$request->setBizContent ( $biz_content );
	
		// 首先调用支付api
		$response = $this->aopclientRequestExecute ($request, $ispage,$method);
		// $response = $response->alipay_trade_wap_pay_response;
		return $response;
	}

	 function aopclientRequestExecute($request,$ispage=false,$method='post') {
		$aop = $this->getClient();
		
		$aop->gatewayUrl = $this->gateway_url;
		$aop->appId = $this->appid;
		$aop->rsaPrivateKey =  $this->private_key;
		if($this->is_cert){
			$aop->alipayrsaPublicKey = $aop->getPublicKey($this->alipay_cert_path);
			$aop->isCheckAlipayPublicCert = true;
			$aop->appCertSN = $aop->getCertSN($this->app_cert_path);//调用getCertSN获取证书序列号
			$aop->alipayRootCertSN = $aop->getRootCertSN($this->root_cert_path);//调用getRootCertSN获取支付宝根证书序列号
		}else{
			$aop->alipayrsaPublicKey = $this->alipay_public_key;
		}
		$aop->apiVersion ="1.0";
		$aop->postCharset = $this->charset;
		$aop->format= $this->format;
		$aop->signType=$this->signtype;

		if($ispage)
		{
			$result = $aop->pageExecute($request,$method);
			//echo $result;
		}
		else 
		{
			$result = $aop->Execute($request);
		}
        
		//打开后，将报文写入log文件
		$this->writeLog("response: ".var_export($result,true));
		return $result;
	}

	/**
	 * alipay.trade.query (统一收单线下交易查询)
	 * @param $builder 业务参数，使用builder中的对象生成。
	 * @return $response 支付宝返回的信息
 	*/
	function Query($builder){
		$biz_content=$builder->getBizContent();
		//打印业务参数
		$this->writeLog($biz_content);

		require_once __DIR__.'/aop/request/AlipayTradeQueryRequest.php';
		$request = new AlipayTradeQueryRequest();
		$request->setBizContent ( $biz_content );

		// 首先调用支付api
		$response = $this->aopclientRequestExecute ($request);
		$response = $response->alipay_trade_query_response;
		//var_dump($response);
		return $response;
	}
	
	/**
	 * alipay.trade.refund (统一收单交易退款接口)
	 * @param $builder 业务参数，使用buildmodel中的对象生成。
	 * @return $response 支付宝返回的信息
	 */
	function Refund($builder){
		$biz_content=$builder->getBizContent();
		//打印业务参数
		$this->writeLog($biz_content);
		require_once __DIR__.'/aop/request/AlipayTradeRefundRequest.php';
		$request = new AlipayTradeRefundRequest();
		$request->setBizContent ( $biz_content );
	
		// 首先调用支付api
		$response = $this->aopclientRequestExecute ($request);
		$response = $response->alipay_trade_refund_response;
		//var_dump($response);
		return $response;
	}

	/**
	 * alipay.trade.close (统一收单交易关闭接口)
	 * @param $builder 业务参数，使用buildmodel中的对象生成。
	 * @return $response 支付宝返回的信息
	 */
	function Close($builder){
		$biz_content=$builder->getBizContent();
		//打印业务参数
		$this->writeLog($biz_content);

		require_once __DIR__.'/aop/request/AlipayTradeCloseRequest.php';
		$request = new AlipayTradeCloseRequest();
		$request->setBizContent ( $biz_content );
	
		// 首先调用支付api
		$response = $this->aopclientRequestExecute ($request);
		$response = $response->alipay_trade_close_response;
		//var_dump($response);
		return $response;
	}
	
	/**
	 * 退款查询   alipay.trade.fastpay.refund.query (统一收单交易退款查询)
	 * @param $builder 业务参数，使用buildmodel中的对象生成。
	 * @return $response 支付宝返回的信息
	 */
	function refundQuery($builder){
		$biz_content=$builder->getBizContent();
		//打印业务参数
		$this->writeLog($biz_content);

		require_once __DIR__.'/aop/request/AlipayTradeFastpayRefundQueryRequest.php';
		$request = new AlipayTradeFastpayRefundQueryRequest();
		$request->setBizContent ( $biz_content );
	
		// 首先调用支付api
		$response = $this->aopclientRequestExecute ($request);
		//var_dump($response);
		return $response;
	}
	/**
	 * alipay.data.dataservice.bill.downloadurl.query (查询对账单下载地址)
	 * @param $builder 业务参数，使用buildmodel中的对象生成。
	 * @return $response 支付宝返回的信息
	 */
	function downloadurlQuery($builder){
		$biz_content=$builder->getBizContent();
		//打印业务参数
		$this->writeLog($biz_content);

		require_once __DIR__.'/aop/request/AlipayDataDataserviceBillDownloadurlQueryRequest.php';
		$request = new AlipayDataDataserviceBillDownloadurlQueryRequest();
		$request->setBizContent ( $biz_content );
	
		// 首先调用支付api
		$response = $this->aopclientRequestExecute ($request);
		$response = $response->alipay_data_dataservice_bill_downloadurl_query_response;
		//var_dump($response);
		return $response;
	}

	/**
	 * 验签方法
	 * @param $arr 验签支付宝返回的信息，使用支付宝公钥。
	 * @return boolean
	 */
	function check($arr){
		$aop = $this->getClient();
		if($this->is_cert){
			$aop->alipayrsaPublicKey = $aop->getPublicKey($this->alipay_cert_path);
			$aop->isCheckAlipayPublicCert = true;
			$aop->appCertSN = $aop->getCertSN($this->app_cert_path);//调用getCertSN获取证书序列号
			$aop->alipayRootCertSN = $aop->getRootCertSN($this->root_cert_path);//调用getRootCertSN获取支付宝根证书序列号
		}else{
			$aop->alipayrsaPublicKey = $this->alipay_public_key;
		}
		
		$result = $aop->rsaCheckV1($arr, $this->alipay_public_key, $this->signtype);
		return $result;
	}
	
	//请确保项目文件有可写权限，不然打印不了日志。
	function writeLog($message, $level = 'trace') {
		if(empty($this->log))return;
		
		$this->log->log($level,$message);
	}
	

	/** *利用google api生成二维码图片
	 * $content：二维码内容参数
	 * $size：生成二维码的尺寸，宽度和高度的值
	 * $lev：可选参数，纠错等级
	 * $margin：生成的二维码离边框的距离
	 */
	function create_erweima($content, $size = '200', $lev = 'L', $margin= '0') {
		$content = urlencode($content);
		$image = '<img src="http://chart.apis.google.com/chart?chs='.$size.'x'.$size.'&amp;cht=qr&chld='.$lev.'|'.$margin.'&amp;chl='.$content.'"  widht="'.$size.'" height="'.$size.'" />';
		return $image;
	}
}


//end