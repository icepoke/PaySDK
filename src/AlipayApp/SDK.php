<?php
namespace Yurun\PaySDK\AlipayApp;

use \Yurun\PaySDK\Base;
use \Yurun\PaySDK\Lib\Encrypt\AES;

class SDK extends Base
{
	/**
	 * 公共参数
	 * @var \Yurun\PaySDK\AlipayApp\Params\PublicParams
	 */
	public $publicParams;

	/**
	 * 处理执行接口的数据
	 * @param $params
	 * @param &$data
	 * @param &$url
	 * @return array
	 */
	public function __parseExecuteData($params, &$data, &$url)
	{
		$data = \array_merge((array)$this->publicParams, (array)$params);
		unset($data['apiDomain'], $data['appID'], $data['businessParams'], $data['appPrivateKey'], $data['appPrivateKeyFile'], $data['appPublicKey'], $data['appPublicKeyFile'], $data['_syncResponseName'], $data['_method'], $data['_isSyncVerify'], $data['aesKey'], $data['isUseAES']);
		$data['app_id'] = $this->publicParams->appID;
		$data['biz_content'] = $params->businessParams->toString();
		if($this->publicParams->isUseAES)
		{
			$data['biz_content'] = AES::encrypt($data['biz_content'], \base64_decode($this->publicParams->aesKey));
		}
		$data['timestamp'] = date('Y-m-d H:i:s');
		$data['sign'] = $this->sign($data);
		$url = $this->publicParams->apiDomain;
	}

	/**
	 * 签名
	 * @param $data
	 * @return string
	 */
	public function sign($data)
	{
		$content = $this->parseSignData($data);
		if(empty($this->publicParams->appPrivateKeyFile))
		{
			$key = $this->publicParams->appPrivateKey;
			$method = 'encryptPrivate';
		}
		else
		{
			$key = $this->publicParams->appPrivateKeyFile;
			$method = 'encryptPrivateFromFile';
		}
		switch($this->publicParams->sign_type)
		{
			case 'RSA':
				$result = \Yurun\PaySDK\Lib\Encrypt\RSA::$method($content, $key);
				break;
			case 'RSA2':
				$result = \Yurun\PaySDK\Lib\Encrypt\RSA2::$method($content, $key);
				break;
			default:
				throw new \Exception('未知的加密方式：' . $this->publicParams->sign_type);
		}
		return \base64_encode($result);
	}
	
	/**
	 * 验证回调通知是否合法
	 * @param $data
	 * @return bool
	 */
	public function verifyCallback($data)
	{
		if(!isset($data['sign'], $data['sign_type']))
		{
			return false;
		}
		unset($data['sign_type']);
		$content = $this->parseSignData($data);
		if(empty($this->publicParams->appPublicKeyFile))
		{
			$key = $this->publicParams->appPublicKey;
			$method = 'verifyPublic';
		}
		else
		{
			$key = $this->publicParams->appPublicKeyFile;
			$method = 'verifyPublicFromFile';
		}
		switch($this->publicParams->sign_type)
		{
			case 'RSA':
				return \Yurun\PaySDK\Lib\Encrypt\DSA::$method($content, $key, \base64_decode($data['sign']));
			case 'RSA2':
				return \Yurun\PaySDK\Lib\Encrypt\RSA2::$method($content, $key, \base64_decode($data['sign']));
			default:
				throw new \Exception('未知的加密方式：' . $this->publicParams->sign_type);
		}
	}

	/**
	 * 验证同步返回内容
	 * @param AlipayRequestBase $params
	 * @param array $data
	 * @return bool
	 */
	public function verifySync($params, $data)
	{
		if(!isset($data['sign']))
		{
			return true;
		}
		$content = \json_encode($data[$params->_syncResponseName], JSON_UNESCAPED_UNICODE);
		if(empty($this->publicParams->appPublicKeyFile))
		{
			$key = $this->publicParams->appPublicKey;
			$method = 'verifyPublic';
		}
		else
		{
			$key = $this->publicParams->appPublicKeyFile;
			$method = 'verifyPublicFromFile';
		}
		switch($this->publicParams->sign_type)
		{
			case 'RSA':
				return \Yurun\PaySDK\Lib\Encrypt\DSA::$method($content, $key, \base64_decode($data['sign']));
			case 'RSA2':
				return \Yurun\PaySDK\Lib\Encrypt\RSA2::$method($content, $key, \base64_decode($data['sign']));
			default:
				throw new \Exception('未知的加密方式：' . $this->publicParams->sign_type);
		}
	}

	public function parseSignData($data)
	{
		unset($data['sign']);
		\ksort($data);
		$content = '';
		foreach ($data as $k => $v){
			if($v != "" && !is_array($v)){
				$content .= $k . "=" . $v . "&";
			}
		}
		return trim($content, '&');
	}

	
	/**
	 * 调用执行接口
	 * @param mixed $params
	 * @param string $method
	 * @return mixed
	 */
	public function execute($params, $format = 'JSON')
	{
		$result = parent::execute($params, $format);
		if($this->publicParams->isUseAES && isset($result[$params->_syncResponseName]))
		{
			$result[$params->_syncResponseName] = json_decode(AES::decrypt($result[$params->_syncResponseName], \base64_decode($this->publicParams->aesKey)), true);
		}
		return $result;
	}
}