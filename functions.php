<?php

	function read_config($configFile="config.ini", $checkDepth=3)
	{
		$configPath = "";

		while ($checkDepth > 0) {
			if (file_exists($configPath . $configFile) ) {
				break;
			}
			$configPath .= ".." . DIRECTORY_SEPARATOR;
			$checkDepth -= 1;
		}

		return parse_ini_file($configPath . $configFile, true);
	}

	function set_multichain_chain($chain)
	{
		global $multichain_chain;
		
		$multichain_chain=$chain;

		global $multichain;

		$multichain = new Multichain($multichain_chain['rpcuser'], $multichain_chain['rpcpassword'],
			$multichain_chain['rpchost'], $multichain_chain['rpcport']);
	}
	
	function multichain($method) // other params read from func_get_args()
	{
		global $multichain;
		
		$args = func_get_args();
		
		return call_user_func_array(array($multichain, $method), array_slice($args, 1));
	}
	
	function output_rpc_error($code, $message)
	{
		echo '<div class="bg-danger" style="padding:1em;">Error: '.html($code).'<br/>'.html($message).'</div>';
	}
	
	function output_success_text($success)
	{
		echo '<div class="bg-success" style="padding:1em;">'.html($success).'</div>';
	}
	
	function no_displayed_error_result(&$result, $response)
	{
		global $multichain;

		if (!empty($multichain->error)) {
			$result = null;
			output_rpc_error($multichain->status, $multichain->error);
			return false;
		} else {
			$result = $multichain->response['result'];
			return true;
		}
	}
	
	function html($string)
	{
		return htmlspecialchars($string);
	}
	
	function chain_page_url_html($chain, $page=null, $params=array())
	{
		$url='./?chain='.$chain;
		
		if (strlen($page))
			$url.='&page='.$page;
			
		foreach ($params as $key => $value)
			$url.='&'.rawurlencode($key).'='.rawurlencode($value);
			
		return html($url);
	}
	
	function array_get_column($array, $key) // see array_column() in recent versions of PHP
	{
		$result=array();
		
		foreach ($array as $index => $element)
			if (array_key_exists($key, $element))
				$result[$index]=$element[$key];
		
		return $result;
	}
	
	function multichain_getinfo()
	{
		global $multichain_getinfo;
		
		if (!is_array($multichain_getinfo))
			no_displayed_error_result($multichain_getinfo, multichain('getinfo'));
		
		return $multichain_getinfo;
	}
	
	function multichain_labels()
	{
		global $multichain_labels;
		
		if (!is_array($multichain_labels)) {
			if (no_displayed_error_result($items, multichain('liststreampublishers', 'root', '*', true, 10000))) {
				$multichain_labels=array();
				foreach ($items as $item)
					$multichain_labels[$item['publisher']]=pack('H*', $item['last']['data']);
			}
		}
		
		return $multichain_labels;
	}
	
	function multichain_max_data_size()
	{
		global $multichain_max_data_size;
		
		if (!isset($multichain_max_data_size))
			if (no_displayed_error_result($params, multichain('getblockchainparams')))
				$multichain_max_data_size=min(
					$params['maximum-block-size']-80-320,
					$params['max-std-tx-size']-320,
					$params['max-std-op-return-size']
				);
		
		return $multichain_max_data_size;
	}	
	
	function format_address_html($address, $local, $labels, $link=null)
	{
		$label=@$labels[$address];
		
		if (strlen($link)) {
			$prefix='<a href="'.html($link).'">';
			$suffix='</a>';
		} else {
			$prefix='';
			$suffix='';
		}
		
		if (isset($label))
			$string=html($label).' ('.$prefix.html($address).$suffix.($local ? ', local' : '').')';
		else
			$string=$prefix.html($address).$suffix.($local ? ' (local)' : '');
			
		return $string;
	}
	
	function string_to_txout_bin($string)
	{
		return ltrim($string, "\x00"); // ensures that first byte 0x00 means it's a file
	}
	
	function file_to_txout_bin($filename, $mimetype, $content)
	{
		return "\x00".$filename."\x00".$mimetype."\x00".$content;
	}
	
	function txout_bin_to_file($data)
	{
		$parts=explode("\x00", $data, 4);
		
		if ( (count($parts)!=4) || ($parts[0]!='') )
			return null;
		
		return array(
			'filename' => $parts[1],
			'mimetype' => $parts[2],
			'content' => $parts[3],
		);
	}
	
	function fileref_to_string($vout, $filename, $mimetype, $filesize)
	{
		return "\x00".$vout."\x00".$filename."\x00".$mimetype."\x00".$filesize;
	}
	
	function string_to_fileref($string)
	{
		$parts=explode("\x00", $string);
		
		if ( (count($parts)!=5) || ($parts[0]!='') )
			return null;
			
		return array(
			'vout' => $parts[1],
			'filename' => $parts[2],
			'mimetype' => $parts[3],
			'filesize' => $parts[4],
		);
	}