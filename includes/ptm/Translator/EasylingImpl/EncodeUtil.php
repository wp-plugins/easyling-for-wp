<?php

/**
 * User: Atesz
 * Date: 2012.12.12.
 * Time: 17:05
 */

class EncodeUtil {

	public static function normalizeSpaces($plainText) {
		//return trim(preg_WHITESPACE_BLOCK.matcher(plainText).replaceAll(" ").trim();
		return trim(preg_replace("/\\s+/"," ",$plainText));
	}

	public static function matcherForClassNames() {
		$classNames = func_get_args();

		$pattern = "^(?:.*\\s)?";
		$first = true;

		foreach ($classNames as $className)
		{
			if($first)
				$first = false;
			else
				$pattern.="|";
			// TODO: implement correct quote method
			$pattern.=$className;
		}

		$pattern.="(?:\\s.*)?$";

		return new Pattern($pattern);
	}

	public static function htmlEscape($str)
	{
		return htmlspecialchars($str);
	}

	private static function buildURL($urlParts, $parts)
	{
		$parsed_url = array_merge($urlParts, $parts);
		$scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
		$host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
		$port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
		$user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
		$pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
		$pass     = ($user || $pass) ? "$pass@" : '';
		$path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
		$query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
		$fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
		return "$scheme$user$pass$host$port$path$query$fragment";
	}

	/**
	 * @param $url
	 * @param array|null $ignoreParams
	 * @param bool $ignoreHash
	 * @param null $canonicalHost
	 * @param array|null $hostAliases
	 * @return string
	 */
	public static function simplifyURL($url, $ignoreParams = null, $ignoreHash = false,
	                                   $canonicalHost = null, $hostAliases = null) {
		//return simplifyURL(s, null, null, null);
		$urlParts = parse_url($url);

		// TODO: exception???
		if ($urlParts === FALSE)
			return $url;

		$host = $urlParts['host'];

		if($canonicalHost != null && $hostAliases != null && $canonicalHost != $host)
		{
			// only simplify valid aliases
			if(in_array($host, $hostAliases)) {
				$urlParts['host'] = $canonicalHost;
			}
			else
				return $url;
		}

		$hash = null;
		$hash = (isset($urlParts['fragment']) && !$ignoreHash) ? $urlParts['fragment'] : null;
		$urlParts['fragment'] = $hash;

		$path = isset($urlParts['path']) ? $urlParts['path'] : null;
		if($path == null || strlen($path) == 0)
			$path = "/";

		$query = isset($urlParts['query']) ? $urlParts['query'] : null;
		if($query != null)
		{
			if($ignoreParams != null && !empty($ignoreParams))
			{
				if(count($ignoreParams) == 1 && $ignoreParams[0] == "*")
					return self::buildURL($urlParts, array('path'=>$path, 'query'=>null));

				// complex but it tries to create the least amount of objects

				$newQuery = "";
				$i = 0;
				$lastOK = $i;
				while($i >= 0 && $i < strlen($query))
				{
					$eq = strpos($query, '=', $i);
					$amp = strpos($query, '&', $i);

					if ($eq === false)
						$eq = -1;

					if ($amp === false)
						$amp = -1;

					if($eq < 0 || $amp >= 0 && $amp < $eq)
					{
						$eq = $amp;
					}

					if($amp < 0)
						$amp = strlen($query);

					if($eq < 0)
						$eq = strlen($query);

					if(in_array(urldecode(substr($query, $i, $eq-$i)), $ignoreParams))
					{
						if($i > $lastOK)
						{
							$newQuery .= substr($query, $lastOK, $i-$lastOK);
						}

						$i = $amp+1;
						$lastOK = $i;
					} else
					{
						$i = $amp+1;
					}
				}

				if($newQuery != "")
				{
					if($i > $lastOK && $lastOK < strlen($query))
					{
						$newQuery .= substr($query, $lastOK, Math::min(strlen($query), $i)-$lastOK);
					}

					if($newQuery{strlen($newQuery)-1} == '&')
						$newQuery = substr($newQuery, 0, -1);

					$query = $newQuery;
				} else
				{
					if($lastOK >= strlen($query))
						return self::buildURL($urlParts, array('path'=>$path, 'query'=>null));

					if($lastOK > 0)
					{
						$l = strlen($query);
						$query = substr($query, $lastOK, ($query{$l-1} == '&' ? $l-1 : $l)-$lastOK);
					}
				}
			}

			return self::buildURL($urlParts, array('path'=>$path, 'query'=>$query, 'fragment'=>$hash));
		}

		return self::buildURL($urlParts, array('path'=>$path));
	}

}