<?php

namespace LiamW\XenForoLicenseVerification;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

/**
 * @property string validation_token
 * @property string customer_token
 * @property string|null license_token
 * @property string|null subscription_token
 * @property bool can_transfer
 * @property string test_domain
 * @property bool domain_match
 * @property bool is_valid
 * @property bool is_cloud
 */
class XFApi
{
	const VALIDATION_URL = "https://xenforo.com/customer-api/license-lookup.json";

	/** @var Client */
	protected $httpClient;
	protected $rawResponse  = '';
	protected $responseCode = 500;
	protected $responseJson = [];

	/** @var string */
	protected $token;
	/** @var string|null */
	protected $domain;

	public function __construct(Client $httpClient, string $token, ?string $domain)
	{
		$this->httpClient = $httpClient;

		$this->setToken($token);
		$this->setDomain($domain);
	}

	public function setToken(string $token)
	{
		$this->token = $token;
	}

	public function setDomain(?string $domain)
	{
		$this->domain = $domain ?? '';
	}

	public function validate()
	{
		$this->responseJson = [];
		try
		{
			$requestOptions = [
				'form_params' => [
					'token'  => $this->token,
					'domain' => $this->domain,
				]
			];

			$this->rawResponse = $this->httpClient->post(self::VALIDATION_URL, $requestOptions);

			$this->responseCode = $this->rawResponse->getStatusCode();
			$json = \json_decode($this->rawResponse->getBody(), true);
			if (\is_array($json))
			{
				$this->responseJson = $json;
                $this->responseJson['is_cloud'] = $this->validation_token !== null && \strpos($this->validation_token, 'cl_') === 0 ||
                                                  $this->subscription_token !== null;
			}
			else
			{
				$this->responseCode = 500;
				\XF::logError('Error validating '.$this->token . ' - '. $this->domain . ' - Non-json result');
			}
		}
		catch (ClientException $e)
		{
			$this->responseCode = $e->getCode();
		}
		catch (ServerException $e)
		{
			\XF::logException($e, false, 'Error validating '.$this->token . ' - '. $this->domain);
			$this->responseCode = $e->getCode();
		}
	}

	public function getResponseCode(): int
	{
		return $this->responseCode;
	}

	public function __get($name)
	{
		return $this->responseJson[$name] ?? null;
	}

	public function __isset($name)
	{
		return isset($this->responseJson[$name]);
	}

	public function __set($name, $value)
	{
		throw new \BadMethodCallException("Cannot set values on LicenseValidator");
	}

	public function __unset($name)
	{
		throw new \BadMethodCallException("Cannot unset values on LicenseValidator");
	}
}