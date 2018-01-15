<?php

class Multichain
{
	// Multichain configuration settings
	private $username;
	private $password;
	private $protocol;
	private $host;
	private $port;
	private $path;
	private $certificate;

	// CURL variables
	public $error;
	public $raw_response;
	public $response;
	public $status;

	// Each call requires an ID
	protected $id = 0;

	public function __construct($username, $password, $host = 'localhost', $port = 8332, $path = null)
	{
		$this->username = $username;
		$this->password = $password;
		$this->host = $host;
		$this->port = $port;
		$this->path = $path;

		// Default protocol is HTTP
		$this->protocol = 'http';
		$this->certificate = null;
	}

	public function setSSL($certificate = null)
	{
		$this->protocol = 'https'; // Use HTTPS
		$this->certificate = $certificate;
	}

	public function __call($method, $params)
	{
		$this->status = null;
		$this->error = null;
		$this->raw_response = null;
		$this->response = null;

		$params = array_values($params);

		$this->id = time();

		$multichain_payload = json_encode(array(
			'id' => $this->id,
			'method' => $method,
			'params' => $params,
		));

		$curl = curl_init("{$this->protocol}://{$this->host}:{$this->port}/{$this->path}");

		$curl_options = array(
			CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
			CURLOPT_USERPWD        => $this->username . ':' . $this->password,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_HTTPHEADER     => array('Content-Type: application/json',
				'Content-Length: ' . strlen($multichain_payload)),
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $multichain_payload
		);

		// Use the CA Certificate if it was provided
		if ($this->protocol == 'https' && !empty($this->certificate)) {
			$curl_options[CURLOPT_CAINFO] = $this->certificate;
			$curl_options[CURLOPT_CAPATH] = dirname($this->certificate);
		}

		curl_setopt_array($curl, $curl_options);

		$this->raw_response = curl_exec($curl);

		$this->response = json_decode($this->raw_response, true);

		$this->status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		$curl_error = curl_error($curl);

		curl_close($curl);

		if (!empty($curl_error)) {
			$this->error = $curl_error;
		}

		if ($this->response['error']) {
			$this->error = $this->response['error']['message'];
		}

		return ($this->error ? false : $this->response['result']);
	}

	public function multichainCommand($method, $params=null)
	{
		$args = func_get_args();

		return call_user_func_array(array($this, $method), array_slice($args, 1));
	}

}