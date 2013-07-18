<?php

/* Generic exception class
 */

class ELOAuthException extends Exception {
    // pass
}

class ELOAuthConsumer {

    public $key;
    public $secret;

    function __construct($key, $secret, $callback_url = NULL) {
        $this->key = $key;
        $this->secret = $secret;
        $this->callback_url = $callback_url;
    }

    function __toString() {
        return "ELOAuthConsumer[key=$this->key,secret=$this->secret]";
    }

}

class ELOAuthToken {

    // access tokens and request tokens
    public $key;
    public $secret;

    /**
     * key = the token
     * secret = the token secret
     */
    function __construct($key, $secret) {
        $this->key = $key;
        $this->secret = $secret;
    }

    /**
     * generates the basic string serialization of a token that a server
     * would respond to request_token and access_token calls with
     */
    function to_string() {
        return "oauth_token=" .
                ELOAuthUtil::urlencode_rfc3986($this->key) .
                "&oauth_token_secret=" .
                ELOAuthUtil::urlencode_rfc3986($this->secret);
    }

    function __toString() {
        return $this->to_string();
    }

}

/**
 * A class for implementing a Signature Method
 * See section 9 ("Signing Requests") in the spec
 */
abstract class ELOAuthSignatureMethod {

    /**
     * Needs to return the name of the Signature Method (ie HMAC-SHA1)
     * @return string
     */
    abstract public function get_name();

    /**
     * Build up the signature
     * NOTE: The output of this function MUST NOT be urlencoded.
     * the encoding is handled in OAuthRequest when the final
     * request is serialized
     * @param ELOAuthRequest $request
     * @param ELOAuthConsumer $consumer
     * @param ELOAuthToken $token
     * @return string
     */
    abstract public function build_signature($request, $consumer, $token);

    /**
     * Verifies that a given signature is correct
     * @param ELOAuthRequest $request
     * @param ELOAuthConsumer $consumer
     * @param ELOAuthToken $token
     * @param string $signature
     * @return bool
     */
    public function check_signature($request, $consumer, $token, $signature) {
        $built = $this->build_signature($request, $consumer, $token);

        // Check for zero length, although unlikely here
        if (strlen($built) == 0 || strlen($signature) == 0) {
            return false;
        }

        if (strlen($built) != strlen($signature)) {
            return false;
        }

        // Avoid a timing leak with a (hopefully) time insensitive compare
        $result = 0;
        for ($i = 0; $i < strlen($signature); $i++) {
            $result |= ord($built{$i}) ^ ord($signature{$i});
        }

        return $result == 0;
    }

}

/**
 * The HMAC-SHA1 signature method uses the HMAC-SHA1 signature algorithm as defined in [RFC2104] 
 * where the Signature Base String is the text and the key is the concatenated values (each first 
 * encoded per Parameter Encoding) of the Consumer Secret and Token Secret, separated by an '&' 
 * character (ASCII code 38) even if empty.
 *   - Chapter 9.2 ("HMAC-SHA1")
 */
class ELOAuthSignatureMethod_HMAC_SHA1 extends ELOAuthSignatureMethod {

    function get_name() {
        return "HMAC-SHA1";
    }

    public function build_signature($request, $consumer, $token) {
        $base_string = $request->get_signature_base_string();
        $request->base_string = $base_string;

        $key_parts = array(
            $consumer->secret,
            ($token) ? $token->secret : ""
        );

        $key_parts = ELOAuthUtil::urlencode_rfc3986($key_parts);
        $key = implode('&', $key_parts);

        return base64_encode(hash_hmac('sha1', $base_string, $key, true));
    }

}

/**
 * The PLAINTEXT method does not provide any security protection and SHOULD only be used 
 * over a secure channel such as HTTPS. It does not use the Signature Base String.
 *   - Chapter 9.4 ("PLAINTEXT")
 */
class ELOAuthSignatureMethod_PLAINTEXT extends ELOAuthSignatureMethod {

    public function get_name() {
        return "PLAINTEXT";
    }

    /**
     * oauth_signature is set to the concatenated encoded values of the Consumer Secret and 
     * Token Secret, separated by a '&' character (ASCII code 38), even if either secret is 
     * empty. The result MUST be encoded again.
     *   - Chapter 9.4.1 ("Generating Signatures")
     *
     * Please note that the second encoding MUST NOT happen in the SignatureMethod, as
     * OAuthRequest handles this!
     */
    public function build_signature($request, $consumer, $token) {
        $key_parts = array(
            $consumer->secret,
            ($token) ? $token->secret : ""
        );

        $key_parts = ELOAuthUtil::urlencode_rfc3986($key_parts);
        $key = implode('&', $key_parts);
        $request->base_string = $key;

        return $key;
    }

}

/**
 * The RSA-SHA1 signature method uses the RSASSA-PKCS1-v1_5 signature algorithm as defined in 
 * [RFC3447] section 8.2 (more simply known as PKCS#1), using SHA-1 as the hash function for 
 * EMSA-PKCS1-v1_5. It is assumed that the Consumer has provided its RSA public key in a 
 * verified way to the Service Provider, in a manner which is beyond the scope of this 
 * specification.
 *   - Chapter 9.3 ("RSA-SHA1")
 */
abstract class ELOAuthSignatureMethod_RSA_SHA1 extends ELOAuthSignatureMethod {

    public function get_name() {
        return "RSA-SHA1";
    }

    // Up to the SP to implement this lookup of keys. Possible ideas are:
    // (1) do a lookup in a table of trusted certs keyed off of consumer
    // (2) fetch via http using a url provided by the requester
    // (3) some sort of specific discovery code based on request
    //
  // Either way should return a string representation of the certificate
    protected abstract function fetch_public_cert(&$request);

    // Up to the SP to implement this lookup of keys. Possible ideas are:
    // (1) do a lookup in a table of trusted certs keyed off of consumer
    //
  // Either way should return a string representation of the certificate
    protected abstract function fetch_private_cert(&$request);

    public function build_signature($request, $consumer, $token) {
        $base_string = $request->get_signature_base_string();
        $request->base_string = $base_string;

        // Fetch the private key cert based on the request
        $cert = $this->fetch_private_cert($request);

        // Pull the private key ID from the certificate
        $privatekeyid = openssl_get_privatekey($cert);

        // Sign using the key
        $ok = openssl_sign($base_string, $signature, $privatekeyid);

        // Release the key resource
        openssl_free_key($privatekeyid);

        return base64_encode($signature);
    }

    public function check_signature($request, $consumer, $token, $signature) {
        $decoded_sig = base64_decode($signature);

        $base_string = $request->get_signature_base_string();

        // Fetch the public key cert based on the request
        $cert = $this->fetch_public_cert($request);

        // Pull the public key ID from the certificate
        $publickeyid = openssl_get_publickey($cert);

        // Check the computed signature against the one passed in the query
        $ok = openssl_verify($base_string, $decoded_sig, $publickeyid);

        // Release the key resource
        openssl_free_key($publickeyid);

        return $ok == 1;
    }

}

class ELOAuthRequest {

    protected $parameters;
    protected $http_method;
    protected $http_url;
    // for debug purposes
    public $base_string;
    public static $version = '1.0';
    public static $POST_INPUT = 'php://input';

    function __construct($http_method, $http_url, $parameters = NULL) {
        $parameters = ($parameters) ? $parameters : array();
        $parameters = array_merge(ELOAuthUtil::parse_parameters(parse_url($http_url, PHP_URL_QUERY)), $parameters);
        $this->parameters = $parameters;
        $this->http_method = $http_method;
        $this->http_url = $http_url;
    }

    /**
     * attempt to build up a request from what was passed to the server
     */
    public static function from_request($http_method = NULL, $http_url = NULL, $parameters = NULL) {
        $scheme = (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != "on") ? 'http' : 'https';
        $http_url = ($http_url) ? $http_url : $scheme .
                '://' . $_SERVER['SERVER_NAME'] .
                ':' .
                $_SERVER['SERVER_PORT'] .
                $_SERVER['REQUEST_URI'];
        $http_method = ($http_method) ? $http_method : $_SERVER['REQUEST_METHOD'];

        // We weren't handed any parameters, so let's find the ones relevant to
        // this request.
        // If you run XML-RPC or similar you should use this to provide your own
        // parsed parameter-list
        if (!$parameters) {
            // Find request headers
            $request_headers = ELOAuthUtil::get_headers();

            // Parse the query-string to find GET parameters
            $parameters = ELOAuthUtil::parse_parameters($_SERVER['QUERY_STRING']);

            // It's a POST request of the proper content-type, so parse POST
            // parameters and add those overriding any duplicates from GET
            if ($http_method == "POST"
                    && isset($request_headers['Content-Type'])
                    && strstr($request_headers['Content-Type'], 'application/x-www-form-urlencoded')
            ) {
                $post_data = ELOAuthUtil::parse_parameters(
                                file_get_contents(self::$POST_INPUT)
                );
                $parameters = array_merge($parameters, $post_data);
            }

            // We have a Authorization-header with OAuth data. Parse the header
            // and add those overriding any duplicates from GET or POST
            if (isset($request_headers['Authorization']) && substr($request_headers['Authorization'], 0, 6) == 'OAuth ') {
                $header_parameters = ELOAuthUtil::split_header(
                                $request_headers['Authorization']
                );
                $parameters = array_merge($parameters, $header_parameters);
            }
        }

        return new ELOAuthRequest($http_method, $http_url, $parameters);
    }

    /**
     * pretty much a helper function to set up the request
     */
    public static function from_consumer_and_token($consumer, $token, $http_method, $http_url, $parameters = NULL) {
        $parameters = ($parameters) ? $parameters : array();
        $defaults = array("oauth_version" => ELOAuthRequest::$version,
            "oauth_nonce" => ELOAuthRequest::generate_nonce(),
            "oauth_timestamp" => ELOAuthRequest::generate_timestamp(),
            "oauth_consumer_key" => $consumer->key);
        if ($token)
            $defaults['oauth_token'] = $token->key;

        $parameters = array_merge($defaults, $parameters);

        return new ELOAuthRequest($http_method, $http_url, $parameters);
    }

    public $response = null;

    public function curlit($url = null, $headers = array()) {
        $c = curl_init();
        curl_setopt_array($c, array(
//            CURLOPT_USERAGENT => $this->config['user_agent'],
//            CURLOPT_CONNECTTIMEOUT => $this->config['curl_connecttimeout'],
//            CURLOPT_TIMEOUT => $this->config['curl_timeout'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => false,
//            CURLOPT_PROXY => $this->config['curl_proxy'],
            CURLOPT_ENCODING => '',
            CURLOPT_URL => $url == null ? $this->http_url : $url,
            // process the headers
            CURLOPT_HEADERFUNCTION => array($this, 'curlHeader'),
            CURLOPT_HEADER => false,
            CURLINFO_HEADER_OUT => true,
        ));

        // do it!
        $response = curl_exec($c);
        $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
        $info = curl_getinfo($c);
        $error = curl_error($c);
        $errno = curl_errno($c);
        curl_close($c);

        // store the response
        $this->response['code'] = $code;
        $this->response['response'] = $response;
        $this->response['info'] = $info;
        $this->response['error'] = $error;
        $this->response['errno'] = $errno;

        if (!isset($this->response['raw'])) {
            $this->response['raw'] = '';
        }
        $this->response['raw'] .= $response;
        return $this->response;
    }

    public function curlHeader($ch, $header) {
        if (!isset($this->response['raw'])) {
            $this->response['raw'] = '';
        }
        $this->response['raw'] .= $header;

        list($key, $value) = array_pad(explode(':', $header, 2), 2, null);
        $this->response['headers'][trim($key)] = trim($value);

        return strlen($header);
    }

    /**
     * Extracts and decodes OAuth parameters from the passed string
     *
     * @param string $body the response body from an OAuth flow method
     * @return array the response body safely decoded to an array of key => values
     */
    public function extract_params($body) {
        $kvs = explode('&', $body);
        $decoded = array();
        foreach ($kvs as $kv) {
            $kv = explode('=', $kv, 2);
            $kv[0] = $this->safe_decode($kv[0]);
            $kv[1] = $this->safe_decode($kv[1]);
            $decoded[$kv[0]] = $kv[1];
        }
        return $decoded;
    }

    /**
     * Decodes the string or array from it's URL encoded form
     * If an array is passed each array value will will be decoded.
     *
     * @param mixed $data the scalar or array to decode
     * @return $data decoded from the URL encoded form
     */
    private function safe_decode($data) {
        if (is_array($data)) {
            return array_map(array($this, 'safe_decode'), $data);
        } else if (is_scalar($data)) {
            return rawurldecode($data);
        } else {
            return '';
        }
    }

    public function set_parameter($name, $value, $allow_duplicates = true) {
        if ($allow_duplicates && isset($this->parameters[$name])) {
            // We have already added parameter(s) with this name, so add to the list
            if (is_scalar($this->parameters[$name])) {
                // This is the first duplicate, so transform scalar (string)
                // into an array so we can add the duplicates
                $this->parameters[$name] = array($this->parameters[$name]);
            }

            $this->parameters[$name][] = $value;
        } else {
            $this->parameters[$name] = $value;
        }
    }

    public function get_parameter($name) {
        return isset($this->parameters[$name]) ? $this->parameters[$name] : null;
    }

    public function get_parameters() {
        return $this->parameters;
    }

    public function unset_parameter($name) {
        unset($this->parameters[$name]);
    }

    /**
     * The request parameters, sorted and concatenated into a normalized string.
     * @return string
     */
    public function get_signable_parameters() {
        // Grab all parameters
        $params = $this->parameters;

        // Remove oauth_signature if present
        // Ref: Spec: 9.1.1 ("The oauth_signature parameter MUST be excluded.")
        if (isset($params['oauth_signature'])) {
            unset($params['oauth_signature']);
        }

        return ELOAuthUtil::build_http_query($params);
    }

    /**
     * Returns the base string of this request
     *
     * The base string defined as the method, the url
     * and the parameters (normalized), each urlencoded
     * and the concated with &.
     */
    public function get_signature_base_string() {
        $parts = array(
            $this->get_normalized_http_method(),
            $this->get_normalized_http_url(),
            $this->get_signable_parameters()
        );

        $parts = ELOAuthUtil::urlencode_rfc3986($parts);

        return implode('&', $parts);
    }

    /**
     * just uppercases the http method
     */
    public function get_normalized_http_method() {
        return strtoupper($this->http_method);
    }

    /**
     * parses the url and rebuilds it to be
     * scheme://host/path
     */
    public function get_normalized_http_url() {
        $parts = parse_url($this->http_url);

        $scheme = (isset($parts['scheme'])) ? $parts['scheme'] : 'http';
        $port = (isset($parts['port'])) ? $parts['port'] : (($scheme == 'https') ? '443' : '80');
        $host = (isset($parts['host'])) ? strtolower($parts['host']) : '';
        $path = (isset($parts['path'])) ? $parts['path'] : '';

        if (($scheme == 'https' && $port != '443')
                || ($scheme == 'http' && $port != '80')) {
            $host = "$host:$port";
        }
        return "$scheme://$host$path";
    }

    /**
     * builds a url usable for a GET request
     */
    public function to_url() {
        $post_data = $this->to_postdata();
        $out = $this->get_normalized_http_url();
        if ($post_data) {
            $out .= '?' . $post_data;
        }
        return $out;
    }

    /**
     * builds the data one would send in a POST request
     */
    public function to_postdata() {
        return ELOAuthUtil::build_http_query($this->parameters);
    }

    /**
     * builds the Authorization: header
     */
    public function to_header($realm = null) {
        $first = true;
        if ($realm) {
            $out = 'Authorization: OAuth realm="' . ELOAuthUtil::urlencode_rfc3986($realm) . '"';
            $first = false;
        } else
            $out = 'Authorization: OAuth';

        $total = array();
        foreach ($this->parameters as $k => $v) {
            if (substr($k, 0, 5) != "oauth")
                continue;
            if (is_array($v)) {
                throw new ELOAuthException('Arrays not supported in headers');
            }
            $out .= ($first) ? ' ' : ',';
            $out .= ELOAuthUtil::urlencode_rfc3986($k) .
                    '="' .
                    ELOAuthUtil::urlencode_rfc3986($v) .
                    '"';
            $first = false;
        }
        return $out;
    }

    public function __toString() {
        return $this->to_url();
    }

    public function sign_request($signature_method, $consumer, $token) {
        $this->set_parameter(
                "oauth_signature_method", $signature_method->get_name(), false
        );
        $signature = $this->build_signature($signature_method, $consumer, $token);
        $this->set_parameter("oauth_signature", $signature, false);
    }

    public function build_signature($signature_method, $consumer, $token) {
        $signature = $signature_method->build_signature($this, $consumer, $token);
        return $signature;
    }

    /**
     * util function: current timestamp
     */
    private static function generate_timestamp() {
        return time();
    }

    /**
     * util function: current nonce
     */
    private static function generate_nonce() {
        $mt = microtime();
        $rand = mt_rand();

        return md5($mt . $rand); // md5s look nicer than numbers
    }

}

class ELOAuthDataStore {

    function lookup_consumer($consumer_key) {
        // implement me
    }

    function lookup_token($consumer, $token_type, $token) {
        // implement me
    }

    function lookup_nonce($consumer, $token, $nonce, $timestamp) {
        // implement me
    }

    function new_request_token($consumer, $callback = null) {
        // return a new token attached to this consumer
    }

    function new_access_token($token, $consumer, $verifier = null) {
        // return a new access token attached to this consumer
        // for the user associated with this token if the request token
        // is authorized
        // should also invalidate the request token
    }

}

class ELOAuthUtil {

    public static function php_self($dropqs = true) {
        $protocol = 'http';
        if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') {
            $protocol = 'https';
        } elseif (isset($_SERVER['SERVER_PORT']) && ($_SERVER['SERVER_PORT'] == '443')) {
            $protocol = 'https';
        }

        $url = sprintf('%s://%s%s', $protocol, $_SERVER['SERVER_NAME'], $_SERVER['REQUEST_URI']
        );

        $parts = parse_url($url);

        $port = $_SERVER['SERVER_PORT'];
        $scheme = $parts['scheme'];
        $host = $parts['host'];
        $path = @$parts['path'];
        $qs = @$parts['query'];

        $port or $port = ($scheme == 'https') ? '443' : '80';

        if (($scheme == 'https' && $port != '443')
                || ($scheme == 'http' && $port != '80')) {
            $host = "$host:$port";
        }
        $url = "$scheme://$host$path";
        if (!$dropqs)
            return "{$url}?{$qs}";
        else
            return $url;
    }

    public static function urlencode_rfc3986($input) {
        if (is_array($input)) {
            return array_map(array('ELOAuthUtil', 'urlencode_rfc3986'), $input);
        } else if (is_scalar($input)) {
            return str_replace(
                            '+', ' ', str_replace('%7E', '~', rawurlencode($input))
            );
        } else {
            return '';
        }
    }

    // This decode function isn't taking into consideration the above
    // modifications to the encoding process. However, this method doesn't
    // seem to be used anywhere so leaving it as is.
    public static function urldecode_rfc3986($string) {
        return urldecode($string);
    }

    // Utility function for turning the Authorization: header into
    // parameters, has to do some unescaping
    // Can filter out any non-oauth parameters if needed (default behaviour)
    // May 28th, 2010 - method updated to tjerk.meesters for a speed improvement.
    //                  see http://code.google.com/p/oauth/issues/detail?id=163
    public static function split_header($header, $only_allow_oauth_parameters = true) {
        $params = array();
        if (preg_match_all('/(' . ($only_allow_oauth_parameters ? 'oauth_' : '') . '[a-z_-]*)=(:?"([^"]*)"|([^,]*))/', $header, $matches)) {
            foreach ($matches[1] as $i => $h) {
                $params[$h] = ELOAuthUtil::urldecode_rfc3986(empty($matches[3][$i]) ? $matches[4][$i] : $matches[3][$i]);
            }
            if (isset($params['realm'])) {
                unset($params['realm']);
            }
        }
        return $params;
    }

    // helper to try to sort out headers for people who aren't running apache
    public static function get_headers() {
        if (function_exists('apache_request_headers')) {
            // we need this to get the actual Authorization: header
            // because apache tends to tell us it doesn't exist
            $headers = apache_request_headers();

            // sanitize the output of apache_request_headers because
            // we always want the keys to be Cased-Like-This and arh()
            // returns the headers in the same case as they are in the
            // request
            $out = array();
            foreach ($headers AS $key => $value) {
                $key = str_replace(
                        " ", "-", ucwords(strtolower(str_replace("-", " ", $key)))
                );
                $out[$key] = $value;
            }
        } else {
            // otherwise we don't have apache and are just going to have to hope
            // that $_SERVER actually contains what we need
            $out = array();
            if (isset($_SERVER['CONTENT_TYPE']))
                $out['Content-Type'] = $_SERVER['CONTENT_TYPE'];
            if (isset($_ENV['CONTENT_TYPE']))
                $out['Content-Type'] = $_ENV['CONTENT_TYPE'];

            foreach ($_SERVER as $key => $value) {
                if (substr($key, 0, 5) == "HTTP_") {
                    // this is chaos, basically it is just there to capitalize the first
                    // letter of every word that is not an initial HTTP and strip HTTP
                    // code from przemek
                    $key = str_replace(
                            " ", "-", ucwords(strtolower(str_replace("_", " ", substr($key, 5))))
                    );
                    $out[$key] = $value;
                }
            }
        }
        return $out;
    }

    // This function takes a input like a=b&a=c&d=e and returns the parsed
    // parameters like this
    // array('a' => array('b','c'), 'd' => 'e')
    public static function parse_parameters($input) {
        if (!isset($input) || !$input)
            return array();

        $pairs = explode('&', $input);

        $parsed_parameters = array();
        foreach ($pairs as $pair) {
            $split = explode('=', $pair, 2);
            $parameter = ELOAuthUtil::urldecode_rfc3986($split[0]);
            $value = isset($split[1]) ? ELOAuthUtil::urldecode_rfc3986($split[1]) : '';

            if (isset($parsed_parameters[$parameter])) {
                // We have already recieved parameter(s) with this name, so add to the list
                // of parameters with this name

                if (is_scalar($parsed_parameters[$parameter])) {
                    // This is the first duplicate, so transform scalar (string) into an array
                    // so we can add the duplicates
                    $parsed_parameters[$parameter] = array($parsed_parameters[$parameter]);
                }

                $parsed_parameters[$parameter][] = $value;
            } else {
                $parsed_parameters[$parameter] = $value;
            }
        }
        return $parsed_parameters;
    }

    public static function build_http_query($params) {
        if (!$params)
            return '';

        // Urlencode both keys and values
        $keys = ELOAuthUtil::urlencode_rfc3986(array_keys($params));
        $values = ELOAuthUtil::urlencode_rfc3986(array_values($params));
        $params = array_combine($keys, $values);

        // Parameters are sorted by name, using lexicographical byte value ordering.
        // Ref: Spec: 9.1.1 (1)
        uksort($params, 'strcmp');

        $pairs = array();
        foreach ($params as $parameter => $value) {
            if (is_array($value)) {
                // If two or more parameters share the same name, they are sorted by their value
                // Ref: Spec: 9.1.1 (1)
                // June 12th, 2010 - changed to sort because of issue 164 by hidetaka
                sort($value, SORT_STRING);
                foreach ($value as $duplicate_value) {
                    $pairs[] = $parameter . '=' . $duplicate_value;
                }
            } else {
                $pairs[] = $parameter . '=' . $value;
            }
        }
        // For each parameter, the name is separated from the corresponding value by an '=' character (ASCII code 61)
        // Each name-value pair is separated by an '&' character (ASCII code 38)
        return implode('&', $pairs);
    }

}

?>
