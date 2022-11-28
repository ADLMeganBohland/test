<?php
namespace cmi5;

/**
 * RESTful cURL class
 *
 * This is a wrapper class for curl, it is quite easy to use:
 * <code>
 * $c = new curl;
 * // enable cache
 * $c = new curl(array('cache'=>true));
 * // enable cookie
 * $c = new curl(array('cookie'=>true));
 * // enable proxy
 * $c = new curl(array('proxy'=>true));
 *
 * // HTTP GET Method
 * $html = $c->get('http://example.com');
 * // HTTP POST Method
 * $html = $c->post('http://example.com/', array('q'=>'words', 'name'=>'moodle'));
 * // HTTP PUT Method
 * $html = $c->put('http://example.com/', array('file'=>'/var/www/test.txt');
 * </code>
 *
 * @package   core_files
 * @category files
 * @copyright Dongsheng Cai <dongsheng@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class curl {
    /** @var bool Caches http request contents */
    public  $cache    = false;
    /** @var bool Uses proxy, null means automatic based on URL */
    public  $proxy    = null;
    /** @var string library version */
    public  $version  = '0.4 dev';
    /** @var array http's response */
    public  $response = array();
    /** @var array Raw response headers, needed for BC in download_file_content(). */
    public $rawresponse = array();
    /** @var array http header */
    public  $header   = array();
    /** @var string cURL information */
    public  $info;
    /** @var string error */
    public  $error;
    /** @var int error code */
    public  $errno;
    /** @var bool Perform redirects at PHP level instead of relying on native cURL functionality. Always true now. */
    public $emulateredirects = null;

    /** @var array cURL options */
    private $options;

    /** @var string Proxy host */
    private $proxy_host = '';
    /** @var string Proxy auth */
    private $proxy_auth = '';
    /** @var string Proxy type */
    private $proxy_type = '';
    /** @var bool Debug mode on */
    private $debug    = false;
    /** @var bool|string Path to cookie file */
    private $cookie   = false;
    /** @var bool tracks multiple headers in response - redirect detection */
    private $responsefinished = false;
    /** @var security helper class, responsible for checking host/ports against allowed/blocked entries.*/
    private $securityhelper;
    /** @var bool ignoresecurity a flag which can be supplied to the constructor, allowing security to be bypassed. */
    private $ignoresecurity;
    /** @var array $mockresponses For unit testing only - return the head of this list instead of making the next request. */
    private static $mockresponses = [];

    /**
     * Curl constructor.
     *
     * Allowed settings are:
     *  proxy: (bool) use proxy server, null means autodetect non-local from url
     *  debug: (bool) use debug output
     *  cookie: (string) path to cookie file, false if none
     *  cache: (bool) use cache
     *  module_cache: (string) type of cache
     *  securityhelper: (\core\files\curl_security_helper_base) helper object providing URL checking for requests.
     *  ignoresecurity: (bool) set true to override and ignore the security helper when making requests.
     *
     * @param array $settings
     */
    public function __construct($settings = array()) {
        global $CFG;
        if (!function_exists('curl_init')) {
            $this->error = 'cURL module must be enabled!';
            trigger_error($this->error, E_USER_ERROR);
            return false;
        }

        // All settings of this class should be init here.
        $this->resetopt();
        if (!empty($settings['debug'])) {
            $this->debug = true;
        }
        if (!empty($settings['cookie'])) {
            if($settings['cookie'] === true) {
                $this->cookie = $CFG->dataroot.'/curl_cookie.txt';
            } else {
                $this->cookie = $settings['cookie'];
            }
        }
        if (!empty($settings['cache'])) {
            if (class_exists('curl_cache')) {
                if (!empty($settings['module_cache'])) {
                    $this->cache = new curl_cache($settings['module_cache']);
                } else {
                    $this->cache = new curl_cache('misc');
                }
            }
        }
        if (!empty($CFG->proxyhost)) {
            if (empty($CFG->proxyport)) {
                $this->proxy_host = $CFG->proxyhost;
            } else {
                $this->proxy_host = $CFG->proxyhost.':'.$CFG->proxyport;
            }
            if (!empty($CFG->proxyuser) and !empty($CFG->proxypassword)) {
                $this->proxy_auth = $CFG->proxyuser.':'.$CFG->proxypassword;
                $this->setopt(array(
                            'proxyauth'=> CURLAUTH_BASIC | CURLAUTH_NTLM,
                            'proxyuserpwd'=>$this->proxy_auth));
            }
            if (!empty($CFG->proxytype)) {
                if ($CFG->proxytype == 'SOCKS5') {
                    $this->proxy_type = CURLPROXY_SOCKS5;
                } else {
                    $this->proxy_type = CURLPROXY_HTTP;
                    $this->setopt(array('httpproxytunnel'=>false));
                }
                $this->setopt(array('proxytype'=>$this->proxy_type));
            }

            if (isset($settings['proxy'])) {
                $this->proxy = $settings['proxy'];
            }
        } else {
            $this->proxy = false;
        }

        // All redirects are performed at PHP level now and each one is checked against blocked URLs rules. We do not
        // want to let cURL naively follow the redirect chain and visit every URL for security reasons. Even when the
        // caller explicitly wants to ignore the security checks, we would need to fall back to the original
        // implementation and use emulated redirects if open_basedir is in effect to avoid the PHP warning
        // "CURLOPT_FOLLOWLOCATION cannot be activated when in safe_mode or an open_basedir". So it is better to simply
        // ignore this property and always handle redirects at this PHP wrapper level and not inside the native cURL.
        $this->emulateredirects = true;

        // Curl security setup. Allow injection of a security helper, but if not found, default to the core helper.
        if (isset($settings['securityhelper']) && $settings['securityhelper'] instanceof \core\files\curl_security_helper_base) {
            $this->set_security($settings['securityhelper']);
        } else {
            $this->set_security(new \core\files\curl_security_helper());
        }
        $this->ignoresecurity = isset($settings['ignoresecurity']) ? $settings['ignoresecurity'] : false;
    }

    /**
     * Resets the CURL options that have already been set
     */
    public function resetopt() {
        $this->options = array();
        $this->options['CURLOPT_USERAGENT']         = \core_useragent::get_moodlebot_useragent();
        // True to include the header in the output
        $this->options['CURLOPT_HEADER']            = 0;
        // True to Exclude the body from the output
        $this->options['CURLOPT_NOBODY']            = 0;
        // Redirect ny default.
        $this->options['CURLOPT_FOLLOWLOCATION']    = 1;
        $this->options['CURLOPT_MAXREDIRS']         = 10;
        $this->options['CURLOPT_ENCODING']          = '';
        // TRUE to return the transfer as a string of the return
        // value of curl_exec() instead of outputting it out directly.
        $this->options['CURLOPT_RETURNTRANSFER']    = 1;
        $this->options['CURLOPT_SSL_VERIFYPEER']    = 0;
        $this->options['CURLOPT_SSL_VERIFYHOST']    = 2;
        $this->options['CURLOPT_CONNECTTIMEOUT']    = 30;

        if ($cacert = self::get_cacert()) {
            $this->options['CURLOPT_CAINFO'] = $cacert;
        }
    }

    /**
     * Get the location of ca certificates.
     * @return string absolute file path or empty if default used
     */
    public static function get_cacert() {
        global $CFG;

        // Bundle in dataroot always wins.
        if (is_readable("$CFG->dataroot/moodleorgca.crt")) {
            return realpath("$CFG->dataroot/moodleorgca.crt");
        }

        // Next comes the default from php.ini
        $cacert = ini_get('curl.cainfo');
        if (!empty($cacert) and is_readable($cacert)) {
            return realpath($cacert);
        }

        // Windows PHP does not have any certs, we need to use something.
        if ($CFG->ostype === 'WINDOWS') {
            if (is_readable("$CFG->libdir/cacert.pem")) {
                return realpath("$CFG->libdir/cacert.pem");
            }
        }

        // Use default, this should work fine on all properly configured *nix systems.
        return null;
    }

    /**
     * Reset Cookie
     */
    public function resetcookie() {
        if (!empty($this->cookie)) {
            if (is_file($this->cookie)) {
                $fp = fopen($this->cookie, 'w');
                if (!empty($fp)) {
                    fwrite($fp, '');
                    fclose($fp);
                }
            }
        }
    }

    /**
     * Set curl options.
     *
     * Do not use the curl constants to define the options, pass a string
     * corresponding to that constant. Ie. to set CURLOPT_MAXREDIRS, pass
     * array('CURLOPT_MAXREDIRS' => 10) or array('maxredirs' => 10) to this method.
     *
     * @param array $options If array is null, this function will reset the options to default value.
     * @return void
     * @throws coding_exception If an option uses constant value instead of option name.
     */
    public function setopt($options = array()) {
        if (is_array($options)) {
            foreach ($options as $name => $val) {
                if (!is_string($name)) {
                    throw new coding_exception('Curl options should be defined using strings, not constant values.');
                }
                if (stripos($name, 'CURLOPT_') === false) {
                    // Only prefix with CURLOPT_ if the option doesn't contain CURLINFO_,
                    // which is a valid prefix for at least one option CURLINFO_HEADER_OUT.
                    if (stripos($name, 'CURLINFO_') === false) {
                        $name = strtoupper('CURLOPT_'.$name);
                    }
                } else {
                    $name = strtoupper($name);
                }
                $this->options[$name] = $val;
            }
        }
    }

    /**
     * Reset http method
     */
    public function cleanopt() {
        unset($this->options['CURLOPT_HTTPGET']);
        unset($this->options['CURLOPT_POST']);
        unset($this->options['CURLOPT_POSTFIELDS']);
        unset($this->options['CURLOPT_PUT']);
        unset($this->options['CURLOPT_INFILE']);
        unset($this->options['CURLOPT_INFILESIZE']);
        unset($this->options['CURLOPT_CUSTOMREQUEST']);
        unset($this->options['CURLOPT_FILE']);
    }

    /**
     * Resets the HTTP Request headers (to prepare for the new request)
     */
    public function resetHeader() {
        $this->header = array();
    }

    /**
     * Set HTTP Request Header
     *
     * @param array $header
     */
    public function setHeader($header) {
        if (is_array($header)) {
            foreach ($header as $v) {
                $this->setHeader($v);
            }
        } else {
            // Remove newlines, they are not allowed in headers.
            $newvalue = preg_replace('/[\r\n]/', '', $header);
            if (!in_array($newvalue, $this->header)) {
                $this->header[] = $newvalue;
            }
        }
    }

    /**
     * Get HTTP Response Headers
     * @return array of arrays
     */
    public function getResponse() {
        return $this->response;
    }

    /**
     * Get raw HTTP Response Headers
     * @return array of strings
     */
    public function get_raw_response() {
        return $this->rawresponse;
    }

    /**
     * private callback function
     * Formatting HTTP Response Header
     *
     * We only keep the last headers returned. For example during a redirect the
     * redirect headers will not appear in {@link self::getResponse()}, if you need
     * to use those headers, refer to {@link self::get_raw_response()}.
     *
     * @param resource $ch Apparently not used
     * @param string $header
     * @return int The strlen of the header
     */
    private function formatHeader($ch, $header) {
        $this->rawresponse[] = $header;

        if (trim($header, "\r\n") === '') {
            // This must be the last header.
            $this->responsefinished = true;
        }

        if (strlen($header) > 2) {
            if ($this->responsefinished) {
                // We still have headers after the supposedly last header, we must be
                // in a redirect so let's empty the response to keep the last headers.
                $this->responsefinished = false;
                $this->response = array();
            }
            $parts = explode(" ", rtrim($header, "\r\n"), 2);
            $key = rtrim($parts[0], ':');
            $value = isset($parts[1]) ? $parts[1] : null;
            if (!empty($this->response[$key])) {
                if (is_array($this->response[$key])) {
                    $this->response[$key][] = $value;
                } else {
                    $tmp = $this->response[$key];
                    $this->response[$key] = array();
                    $this->response[$key][] = $tmp;
                    $this->response[$key][] = $value;

                }
            } else {
                $this->response[$key] = $value;
            }
        }
        return strlen($header);
    }

    /**
     * Set options for individual curl instance
     *
     * @param resource $curl A curl handle
     * @param array $options
     * @return resource The curl handle
     */
    private function apply_opt($curl, $options) {
        // Clean up
        $this->cleanopt();
        // set cookie
        if (!empty($this->cookie) || !empty($options['cookie'])) {
            $this->setopt(array('cookiejar'=>$this->cookie,
                            'cookiefile'=>$this->cookie
                             ));
        }

        // Bypass proxy if required.
        if ($this->proxy === null) {
            if (!empty($this->options['CURLOPT_URL']) and is_proxybypass($this->options['CURLOPT_URL'])) {
                $proxy = false;
            } else {
                $proxy = true;
            }
        } else {
            $proxy = (bool)$this->proxy;
        }

        // Set proxy.
        if ($proxy) {
            $options['CURLOPT_PROXY'] = $this->proxy_host;
        } else {
            unset($this->options['CURLOPT_PROXY']);
        }

        $this->setopt($options);

        // Reset before set options.
        curl_setopt($curl, CURLOPT_HEADERFUNCTION, array(&$this,'formatHeader'));

        // Setting the User-Agent based on options provided.
        $useragent = '';

        if (!empty($options['CURLOPT_USERAGENT'])) {
            $useragent = $options['CURLOPT_USERAGENT'];
        } else if (!empty($this->options['CURLOPT_USERAGENT'])) {
            $useragent = $this->options['CURLOPT_USERAGENT'];
        } else {
            $useragent = \core_useragent::get_moodlebot_useragent();
        }

        // Set headers.
        if (empty($this->header)) {
            $this->setHeader(array(
                'User-Agent: ' . $useragent,
                'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
                'Connection: keep-alive'
                ));
        } else if (!in_array('User-Agent: ' . $useragent, $this->header)) {
            // Remove old User-Agent if one existed.
            // We have to partial search since we don't know what the original User-Agent is.
            if ($match = preg_grep('/User-Agent.*/', $this->header)) {
                $key = array_keys($match)[0];
                unset($this->header[$key]);
            }
            $this->setHeader(array('User-Agent: ' . $useragent));
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->header);

        if ($this->debug) {
            echo '<h1>Options</h1>';
            var_dump($this->options);
            echo '<h1>Header</h1>';
            var_dump($this->header);
        }

        // Do not allow infinite redirects.
        if (!isset($this->options['CURLOPT_MAXREDIRS'])) {
            $this->options['CURLOPT_MAXREDIRS'] = 0;
        } else if ($this->options['CURLOPT_MAXREDIRS'] > 100) {
            $this->options['CURLOPT_MAXREDIRS'] = 100;
        } else {
            $this->options['CURLOPT_MAXREDIRS'] = (int)$this->options['CURLOPT_MAXREDIRS'];
        }

        // Make sure we always know if redirects expected.
        if (!isset($this->options['CURLOPT_FOLLOWLOCATION'])) {
            $this->options['CURLOPT_FOLLOWLOCATION'] = 0;
        }

        // Limit the protocols to HTTP and HTTPS.
        if (defined('CURLOPT_PROTOCOLS')) {
            $this->options['CURLOPT_PROTOCOLS'] = (CURLPROTO_HTTP | CURLPROTO_HTTPS);
            $this->options['CURLOPT_REDIR_PROTOCOLS'] = (CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }

        // Set options.
        foreach($this->options as $name => $val) {
            if ($name === 'CURLOPT_FOLLOWLOCATION') {
                // All the redirects are emulated at PHP level.
                curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 0);
                continue;
            }
            $name = constant($name);
            curl_setopt($curl, $name, $val);
        }

        return $curl;
    }

    /**
     * Download multiple files in parallel
     *
     * Calls {@link multi()} with specific download headers
     *
     * <code>
     * $c = new curl();
     * $file1 = fopen('a', 'wb');
     * $file2 = fopen('b', 'wb');
     * $c->download(array(
     *     array('url'=>'http://localhost/', 'file'=>$file1),
     *     array('url'=>'http://localhost/20/', 'file'=>$file2)
     * ));
     * fclose($file1);
     * fclose($file2);
     * </code>
     *
     * or
     *
     * <code>
     * $c = new curl();
     * $c->download(array(
     *              array('url'=>'http://localhost/', 'filepath'=>'/tmp/file1.tmp'),
     *              array('url'=>'http://localhost/20/', 'filepath'=>'/tmp/file2.tmp')
     *              ));
     * </code>
     *
     * @param array $requests An array of files to request {
     *                  url => url to download the file [required]
     *                  file => file handler, or
     *                  filepath => file path
     * }
     * If 'file' and 'filepath' parameters are both specified in one request, the
     * open file handle in the 'file' parameter will take precedence and 'filepath'
     * will be ignored.
     *
     * @param array $options An array of options to set
     * @return array An array of results
     */
    public function download($requests, $options = array()) {
        $options['RETURNTRANSFER'] = false;
        return $this->multi($requests, $options);
    }

    /**
     * Returns the current curl security helper.
     *
     * @return \core\files\curl_security_helper instance.
     */
    public function get_security() {
        return $this->securityhelper;
    }

    /**
     * Sets the curl security helper.
     *
     * @param \core\files\curl_security_helper $securityobject instance/subclass of the base curl_security_helper class.
     * @return bool true if the security helper could be set, false otherwise.
     */
    public function set_security($securityobject) {
        if ($securityobject instanceof \core\files\curl_security_helper) {
            $this->securityhelper = $securityobject;
            return true;
        }
        return false;
    }

    /**
     * Multi HTTP Requests
     * This function could run multi-requests in parallel.
     *
     * @param array $requests An array of files to request
     * @param array $options An array of options to set
     * @return array An array of results
     */
    protected function multi($requests, $options = array()) {
        $count   = count($requests);
        $handles = array();
        $results = array();
        $main    = curl_multi_init();
        for ($i = 0; $i < $count; $i++) {
            if (!empty($requests[$i]['filepath']) and empty($requests[$i]['file'])) {
                // open file
                $requests[$i]['file'] = fopen($requests[$i]['filepath'], 'w');
                $requests[$i]['auto-handle'] = true;
            }
            foreach($requests[$i] as $n=>$v) {
                $options[$n] = $v;
            }
            $handles[$i] = curl_init($requests[$i]['url']);
            $this->apply_opt($handles[$i], $options);
            curl_multi_add_handle($main, $handles[$i]);
        }
        $running = 0;
        do {
            curl_multi_exec($main, $running);
        } while($running > 0);
        for ($i = 0; $i < $count; $i++) {
            if (!empty($options['CURLOPT_RETURNTRANSFER'])) {
                $results[] = true;
            } else {
                $results[] = curl_multi_getcontent($handles[$i]);
            }
            curl_multi_remove_handle($main, $handles[$i]);
        }
        curl_multi_close($main);

        for ($i = 0; $i < $count; $i++) {
            if (!empty($requests[$i]['filepath']) and !empty($requests[$i]['auto-handle'])) {
                // close file handler if file is opened in this function
                fclose($requests[$i]['file']);
            }
        }
        return $results;
    }

    /**
     * Helper function to reset the request state vars.
     *
     * @return void.
     */
    protected function reset_request_state_vars() {
        $this->info             = array();
        $this->error            = '';
        $this->errno            = 0;
        $this->response         = array();
        $this->rawresponse      = array();
        $this->responsefinished = false;
    }

    /**
     * For use only in unit tests - we can pre-set the next curl response.
     * This is useful for unit testing APIs that call external systems.
     * @param string $response
     */
    public static function mock_response($response) {
        if ((defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
            array_push(self::$mockresponses, $response);
        } else {
            throw new coding_exception('mock_response function is only available for unit tests.');
        }
    }

    /**
     * check_securityhelper_blocklist.
     * Checks whether the given URL is blocked by checking both plugin's security helpers
     * and core curl security helper or any curl security helper that passed to curl class constructor.
     * If ignoresecurity is set to true, skip checking and consider the url is not blocked.
     * This augments all installed plugin's security helpers if there is any.
     *
     * @param string $url the url to check.
     * @return string - an error message if URL is blocked or null if URL is not blocked.
     */
    protected function check_securityhelper_blocklist(string $url): ?string {

        // If curl security is not enabled, do not proceed.
        if ($this->ignoresecurity) {
            return null;
        }

        // Augment all installed plugin's security helpers if there is any.
        // The plugin's function has to be defined as plugintype_pluginname_curl_security_helper in pluginname/lib.php.
        $plugintypes = get_plugins_with_function('curl_security_helper');

        // If any of the security helper's function returns true, treat as URL is blocked.
        foreach ($plugintypes as $plugins) {
            foreach ($plugins as $pluginfunction) {
                // Get curl security helper object from plugin lib.php.
                $pluginsecurityhelper = $pluginfunction();
                if ($pluginsecurityhelper instanceof \core\files\curl_security_helper_base) {
                    if ($pluginsecurityhelper->url_is_blocked($url)) {
                        $this->error = $pluginsecurityhelper->get_blocked_url_string();
                        return $this->error;
                    }
                }
            }
        }

        // Check if the URL is blocked in core curl_security_helper or
        // curl security helper that passed to curl class constructor.
        if ($this->securityhelper->url_is_blocked($url)) {
            $this->error = $this->securityhelper->get_blocked_url_string();
            return $this->error;
        }

        return null;
    }

    /**
     * Single HTTP Request
     *
     * @param string $url The URL to request
     * @param array $options
     * @return bool
     */
    protected function request($url, $options = array()) {
        // Reset here so that the data is valid when result returned from cache, or if we return due to a blocked URL hit.
        $this->reset_request_state_vars();

        if ((defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
            $mockresponse = array_pop(self::$mockresponses);
            if ($mockresponse !== null) {
                $this->info = [ 'http_code' => 200 ];
                return $mockresponse;
            }
        }

        if (empty($this->emulateredirects)) {
            // Just in case someone had tried to explicitly disable emulated redirects in legacy code.
            debugging('Attempting to disable emulated redirects has no effect any more!', DEBUG_DEVELOPER);
        }

        $urlisblocked = $this->check_securityhelper_blocklist($url);
        if (!is_null($urlisblocked)) {
            return $urlisblocked;
        }

        // Set the URL as a curl option.
        $this->setopt(array('CURLOPT_URL' => $url));

        // Create curl instance.
        $curl = curl_init();

        $this->apply_opt($curl, $options);
        if ($this->cache && $ret = $this->cache->get($this->options)) {
            return $ret;
        }

        $ret = curl_exec($curl);
        $this->info  = curl_getinfo($curl);
        $this->error = curl_error($curl);
        $this->errno = curl_errno($curl);
        // Note: $this->response and $this->rawresponse are filled by $hits->formatHeader callback.

        if (intval($this->info['redirect_count']) > 0) {
            // For security reasons we do not allow the cURL handle to follow redirects on its own.
            // See setting CURLOPT_FOLLOWLOCATION in {@see self::apply_opt()} method.
            throw new coding_exception('Internal cURL handle should never follow redirects on its own!',
                'Reported number of redirects: ' . $this->info['redirect_count']);
        }

        if ($this->options['CURLOPT_FOLLOWLOCATION'] && $this->info['http_code'] != 200) {
            $redirects = 0;

            while($redirects <= $this->options['CURLOPT_MAXREDIRS']) {

                if ($this->info['http_code'] == 301) {
                    // Moved Permanently - repeat the same request on new URL.

                } else if ($this->info['http_code'] == 302) {
                    // Found - the standard redirect - repeat the same request on new URL.

                } else if ($this->info['http_code'] == 303) {
                    // 303 See Other - repeat only if GET, do not bother with POSTs.
                    if (empty($this->options['CURLOPT_HTTPGET'])) {
                        break;
                    }

                } else if ($this->info['http_code'] == 307) {
                    // Temporary Redirect - must repeat using the same request type.

                } else if ($this->info['http_code'] == 308) {
                    // Permanent Redirect - must repeat using the same request type.

                } else {
                    // Some other http code means do not retry!
                    break;
                }

                $redirects++;

                $redirecturl = null;
                if (isset($this->info['redirect_url'])) {
                    if (preg_match('|^https?://|i', $this->info['redirect_url'])) {
                        $redirecturl = $this->info['redirect_url'];
                    } else {
                        // Emulate CURLOPT_REDIR_PROTOCOLS behaviour which we have set to (CURLPROTO_HTTP | CURLPROTO_HTTPS) only.
                        $this->errno = CURLE_UNSUPPORTED_PROTOCOL;
                        $this->error = 'Redirect to a URL with unsuported protocol: ' . $this->info['redirect_url'];
                        curl_close($curl);
                        return $this->error;
                    }
                }
                if (!$redirecturl) {
                    foreach ($this->response as $k => $v) {
                        if (strtolower($k) === 'location') {
                            $redirecturl = $v;
                            break;
                        }
                    }
                    if (preg_match('|^https?://|i', $redirecturl)) {
                        // Great, this is the correct location format!

                    } else if ($redirecturl) {
                        $current = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
                        if (strpos($redirecturl, '/') === 0) {
                            // Relative to server root - just guess.
                            $pos = strpos('/', $current, 8);
                            if ($pos === false) {
                                $redirecturl = $current.$redirecturl;
                            } else {
                                $redirecturl = substr($current, 0, $pos).$redirecturl;
                            }
                        } else {
                            // Relative to current script.
                            $redirecturl = dirname($current).'/'.$redirecturl;
                        }
                    }
                }

                $urlisblocked = $this->check_securityhelper_blocklist($redirecturl);
                if (!is_null($urlisblocked)) {
                    $this->reset_request_state_vars();
                    curl_close($curl);
                    return $urlisblocked;
                }

                // If the response body is written to a seekable stream resource, reset the stream pointer to avoid
                // appending multiple response bodies to the same resource.
                if (!empty($this->options['CURLOPT_FILE'])) {
                    $streammetadata = stream_get_meta_data($this->options['CURLOPT_FILE']);
                    if ($streammetadata['seekable']) {
                        ftruncate($this->options['CURLOPT_FILE'], 0);
                        rewind($this->options['CURLOPT_FILE']);
                    }
                }

                curl_setopt($curl, CURLOPT_URL, $redirecturl);
                $ret = curl_exec($curl);

                $this->info  = curl_getinfo($curl);
                $this->error = curl_error($curl);
                $this->errno = curl_errno($curl);

                $this->info['redirect_count'] = $redirects;

                if ($this->info['http_code'] === 200) {
                    // Finally this is what we wanted.
                    break;
                }
                if ($this->errno != CURLE_OK) {
                    // Something wrong is going on.
                    break;
                }
            }
            if ($redirects > $this->options['CURLOPT_MAXREDIRS']) {
                $this->errno = CURLE_TOO_MANY_REDIRECTS;
                $this->error = 'Maximum ('.$this->options['CURLOPT_MAXREDIRS'].') redirects followed';
            }
        }

        if ($this->cache) {
            $this->cache->set($this->options, $ret);
        }

        if ($this->debug) {
            echo '<h1>Return Data</h1>';
            var_dump($ret);
            echo '<h1>Info</h1>';
            var_dump($this->info);
            echo '<h1>Error</h1>';
            var_dump($this->error);
        }

        curl_close($curl);

        if (empty($this->error)) {
            return $ret;
        } else {
            return $this->error;
            // exception is not ajax friendly
            //throw new moodle_exception($this->error, 'curl');
        }
    }

    /**
     * HTTP HEAD method
     *
     * @see request()
     *
     * @param string $url
     * @param array $options
     * @return bool
     */
    public function head($url, $options = array()) {
        $options['CURLOPT_HTTPGET'] = 0;
        $options['CURLOPT_HEADER']  = 1;
        $options['CURLOPT_NOBODY']  = 1;
        return $this->request($url, $options);
    }

    /**
     * HTTP PATCH method
     *
     * @param string $url
     * @param array|string $params
     * @param array $options
     * @return bool
     */
    public function patch($url, $params = '', $options = array()) {
        $options['CURLOPT_CUSTOMREQUEST'] = 'PATCH';
        if (is_array($params)) {
            $this->_tmp_file_post_params = array();
            foreach ($params as $key => $value) {
                if ($value instanceof stored_file) {
                    $value->add_to_curl_request($this, $key);
                } else {
                    $this->_tmp_file_post_params[$key] = $value;
                }
            }
            $options['CURLOPT_POSTFIELDS'] = $this->_tmp_file_post_params;
            unset($this->_tmp_file_post_params);
        } else {
            // The variable $params is the raw post data.
            $options['CURLOPT_POSTFIELDS'] = $params;
        }
        return $this->request($url, $options);
    }

    /**
     * HTTP POST method
     *
     * @param string $url
     * @param array|string $params
     * @param array $options
     * @return bool
     */
    public function post($url, $params = '', $options = array()) {
        $options['CURLOPT_POST']       = 1;
        if (is_array($params)) {
            $this->_tmp_file_post_params = array();
            foreach ($params as $key => $value) {
                if ($value instanceof stored_file) {
                    $value->add_to_curl_request($this, $key);
                } else {
                    $this->_tmp_file_post_params[$key] = $value;
                }
            }
            $options['CURLOPT_POSTFIELDS'] = $this->_tmp_file_post_params;
            unset($this->_tmp_file_post_params);
        } else {
            // $params is the raw post data
            $options['CURLOPT_POSTFIELDS'] = $params;
        }
        return $this->request($url, $options);
    }

    /**
     * HTTP GET method
     *
     * @param string $url
     * @param array $params
     * @param array $options
     * @return bool
     */
    public function get($url, $params = array(), $options = array()) {
        $options['CURLOPT_HTTPGET'] = 1;

        if (!empty($params)) {
            $url .= (stripos($url, '?') !== false) ? '&' : '?';
            $url .= http_build_query($params, '', '&');
        }
        return $this->request($url, $options);
    }

    /**
     * Downloads one file and writes it to the specified file handler
     *
     * <code>
     * $c = new curl();
     * $file = fopen('savepath', 'w');
     * $result = $c->download_one('http://localhost/', null,
     *   array('file' => $file, 'timeout' => 5, 'followlocation' => true, 'maxredirs' => 3));
     * fclose($file);
     * $download_info = $c->get_info();
     * if ($result === true) {
     *   // file downloaded successfully
     * } else {
     *   $error_text = $result;
     *   $error_code = $c->get_errno();
     * }
     * </code>
     *
     * <code>
     * $c = new curl();
     * $result = $c->download_one('http://localhost/', null,
     *   array('filepath' => 'savepath', 'timeout' => 5, 'followlocation' => true, 'maxredirs' => 3));
     * // ... see above, no need to close handle and remove file if unsuccessful
     * </code>
     *
     * @param string $url
     * @param array|null $params key-value pairs to be added to $url as query string
     * @param array $options request options. Must include either 'file' or 'filepath'
     * @return bool|string true on success or error string on failure
     */
    public function download_one($url, $params, $options = array()) {
        $options['CURLOPT_HTTPGET'] = 1;
        if (!empty($params)) {
            $url .= (stripos($url, '?') !== false) ? '&' : '?';
            $url .= http_build_query($params, '', '&');
        }
        if (!empty($options['filepath']) && empty($options['file'])) {
            // open file
            if (!($options['file'] = fopen($options['filepath'], 'w'))) {
                $this->errno = 100;
                return get_string('cannotwritefile', 'error', $options['filepath']);
            }
            $filepath = $options['filepath'];
        }
        unset($options['filepath']);
        $result = $this->request($url, $options);
        if (isset($filepath)) {
            fclose($options['file']);
            if ($result !== true) {
                unlink($filepath);
            }
        }
        return $result;
    }

    /**
     * HTTP PUT method
     *
     * @param string $url
     * @param array $params
     * @param array $options
     * @return bool
     */
    public function put($url, $params = array(), $options = array()) {
        $file = '';
        $fp = false;
        if (isset($params['file'])) {
            $file = $params['file'];
            if (is_file($file)) {
                $fp   = fopen($file, 'r');
                $size = filesize($file);
                $options['CURLOPT_PUT']        = 1;
                $options['CURLOPT_INFILESIZE'] = $size;
                $options['CURLOPT_INFILE']     = $fp;
            } else {
                return null;
            }
            if (!isset($this->options['CURLOPT_USERPWD'])) {
                $this->setopt(array('CURLOPT_USERPWD' => 'anonymous: noreply@moodle.org'));
            }
        } else {
            $options['CURLOPT_CUSTOMREQUEST'] = 'PUT';
            $options['CURLOPT_POSTFIELDS'] = $params;
        }

        $ret = $this->request($url, $options);
        if ($fp !== false) {
            fclose($fp);
        }
        return $ret;
    }

    /**
     * HTTP DELETE method
     *
     * @param string $url
     * @param array $param
     * @param array $options
     * @return bool
     */
    public function delete($url, $param = array(), $options = array()) {
        $options['CURLOPT_CUSTOMREQUEST'] = 'DELETE';
        if (!isset($options['CURLOPT_USERPWD'])) {
            $options['CURLOPT_USERPWD'] = 'anonymous: noreply@moodle.org';
        }
        $ret = $this->request($url, $options);
        return $ret;
    }

    /**
     * HTTP TRACE method
     *
     * @param string $url
     * @param array $options
     * @return bool
     */
    public function trace($url, $options = array()) {
        $options['CURLOPT_CUSTOMREQUEST'] = 'TRACE';
        $ret = $this->request($url, $options);
        return $ret;
    }

    /**
     * HTTP OPTIONS method
     *
     * @param string $url
     * @param array $options
     * @return bool
     */
    public function options($url, $options = array()) {
        $options['CURLOPT_CUSTOMREQUEST'] = 'OPTIONS';
        $ret = $this->request($url, $options);
        return $ret;
    }

    /**
     * Get curl information
     *
     * @return string
     */
    public function get_info() {
        return $this->info;
    }

    /**
     * Get curl error code
     *
     * @return int
     */
    public function get_errno() {
        return $this->errno;
    }

    /**
     * When using a proxy, an additional HTTP response code may appear at
     * the start of the header. For example, when using https over a proxy
     * there may be 'HTTP/1.0 200 Connection Established'. Other codes are
     * also possible and some may come with their own headers.
     *
     * If using the return value containing all headers, this function can be
     * called to remove unwanted doubles.
     *
     * Note that it is not possible to distinguish this situation from valid
     * data unless you know the actual response part (below the headers)
     * will not be included in this string, or else will not 'look like' HTTP
     * headers. As a result it is not safe to call this function for general
     * data.
     *
     * @param string $input Input HTTP response
     * @return string HTTP response with additional headers stripped if any
     */
    public static function strip_double_headers($input) {
        // I have tried to make this regular expression as specific as possible
        // to avoid any case where it does weird stuff if you happen to put
        // HTTP/1.1 200 at the start of any line in your RSS file. This should
        // also make it faster because it can abandon regex processing as soon
        // as it hits something that doesn't look like an http header. The
        // header definition is taken from RFC 822, except I didn't support
        // folding which is never used in practice.
        $crlf = "\r\n";
        return preg_replace(
                // HTTP version and status code (ignore value of code).
                '~^HTTP/[1-9](\.[0-9])?.*' . $crlf .
                // Header name: character between 33 and 126 decimal, except colon.
                // Colon. Header value: any character except \r and \n. CRLF.
                '(?:[\x21-\x39\x3b-\x7e]+:[^' . $crlf . ']+' . $crlf . ')*' .
                // Headers are terminated by another CRLF (blank line).
                $crlf .
                // Second HTTP status code, this time must be 200.
                '(HTTP/[1-9](\.[0-9])? 200)~', '$2', $input);
    }
}

/**
 * This class is used by cURL class, use case:
 *
 * <code>
 * $CFG->repositorycacheexpire = 120;
 * $CFG->curlcache = 120;
 *
 * $c = new curl(array('cache'=>true), 'module_cache'=>'repository');
 * $ret = $c->get('http://www.google.com');
 * </code>
 *
 * @package   core_files
 * @copyright Dongsheng Cai <dongsheng@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class curl_cache {
    /** @var string Path to cache directory */
    public $dir = '';

    /**
     * Constructor
     *
     * @global stdClass $CFG
     * @param string $module which module is using curl_cache
     */
    public function __construct($module = 'repository') {
        global $CFG;
        if (!empty($module)) {
            $this->dir = $CFG->cachedir.'/'.$module.'/';
        } else {
            $this->dir = $CFG->cachedir.'/misc/';
        }
        if (!file_exists($this->dir)) {
            mkdir($this->dir, $CFG->directorypermissions, true);
        }
        if ($module == 'repository') {
            if (empty($CFG->repositorycacheexpire)) {
                $CFG->repositorycacheexpire = 120;
            }
            $this->ttl = $CFG->repositorycacheexpire;
        } else {
            if (empty($CFG->curlcache)) {
                $CFG->curlcache = 120;
            }
            $this->ttl = $CFG->curlcache;
        }
    }

    /**
     * Get cached value
     *
     * @global stdClass $CFG
     * @global stdClass $USER
     * @param mixed $param
     * @return bool|string
     */
    public function get($param) {
        global $CFG, $USER;
        $this->cleanup($this->ttl);
        $filename = 'u'.$USER->id.'_'.md5(serialize($param));
        if(file_exists($this->dir.$filename)) {
            $lasttime = filemtime($this->dir.$filename);
            if (time()-$lasttime > $this->ttl) {
                return false;
            } else {
                $fp = fopen($this->dir.$filename, 'r');
                $size = filesize($this->dir.$filename);
                $content = fread($fp, $size);
                return unserialize($content);
            }
        }
        return false;
    }

    /**
     * Set cache value
     *
     * @global object $CFG
     * @global object $USER
     * @param mixed $param
     * @param mixed $val
     */
    public function set($param, $val) {
        global $CFG, $USER;
        $filename = 'u'.$USER->id.'_'.md5(serialize($param));
        $fp = fopen($this->dir.$filename, 'w');
        fwrite($fp, serialize($val));
        fclose($fp);
        @chmod($this->dir.$filename, $CFG->filepermissions);
    }

    /**
     * Remove cache files
     *
     * @param int $expire The number of seconds before expiry
     */
    public function cleanup($expire) {
        if ($dir = opendir($this->dir)) {
            while (false !== ($file = readdir($dir))) {
                if(!is_dir($file) && $file != '.' && $file != '..') {
                    $lasttime = @filemtime($this->dir.$file);
                    if (time() - $lasttime > $expire) {
                        @unlink($this->dir.$file);
                    }
                }
            }
            closedir($dir);
        }
    }
    /**
     * delete current user's cache file
     *
     * @global object $CFG
     * @global object $USER
     */
    public function refresh() {
        global $CFG, $USER;
        if ($dir = opendir($this->dir)) {
            while (false !== ($file = readdir($dir))) {
                if (!is_dir($file) && $file != '.' && $file != '..') {
                    if (strpos($file, 'u'.$USER->id.'_') !== false) {
                        @unlink($this->dir.$file);
                    }
                }
            }
        }
    }
}

/**
 * This function delegates file serving to individual plugins
 *
 * @param string $relativepath
 * @param bool $forcedownload
 * @param null|string $preview the preview mode, defaults to serving the original file
 * @param boolean $offline If offline is requested - don't serve a redirect to an external file, return a file suitable for viewing
 *                         offline (e.g. mobile app).
 * @param bool $embed Whether this file will be served embed into an iframe.
 * @todo MDL-31088 file serving improments
 */
function file_pluginfile($relativepath, $forcedownload, $preview = null, $offline = false, $embed = false) {
    global $DB, $CFG, $USER;
    // relative path must start with '/'
    if (!$relativepath) {
        throw new \moodle_exception('invalidargorconf');
    } else if ($relativepath[0] != '/') {
        throw new \moodle_exception('pathdoesnotstartslash');
    }

    // extract relative path components
    $args = explode('/', ltrim($relativepath, '/'));

    if (count($args) < 3) { // always at least context, component and filearea
        throw new \moodle_exception('invalidarguments');
    }

    $contextid = (int)array_shift($args);
    $component = clean_param(array_shift($args), PARAM_COMPONENT);
    $filearea  = clean_param(array_shift($args), PARAM_AREA);

    list($context, $course, $cm) = get_context_info_array($contextid);

    $fs = get_file_storage();

    $sendfileoptions = ['preview' => $preview, 'offline' => $offline, 'embed' => $embed];

    // ========================================================================================================================
    if ($component === 'blog') {
        // Blog file serving
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            send_file_not_found();
        }
        if ($filearea !== 'attachment' and $filearea !== 'post') {
            send_file_not_found();
        }

        if (empty($CFG->enableblogs)) {
            throw new \moodle_exception('siteblogdisable', 'blog');
        }

        $entryid = (int)array_shift($args);
        if (!$entry = $DB->get_record('post', array('module'=>'blog', 'id'=>$entryid))) {
            send_file_not_found();
        }
        if ($CFG->bloglevel < BLOG_GLOBAL_LEVEL) {
            require_login();
            if (isguestuser()) {
                throw new \moodle_exception('noguest');
            }
            if ($CFG->bloglevel == BLOG_USER_LEVEL) {
                if ($USER->id != $entry->userid) {
                    send_file_not_found();
                }
            }
        }

        if ($entry->publishstate === 'public') {
            if ($CFG->forcelogin) {
                require_login();
            }

        } else if ($entry->publishstate === 'site') {
            require_login();
            //ok
        } else if ($entry->publishstate === 'draft') {
            require_login();
            if ($USER->id != $entry->userid) {
                send_file_not_found();
            }
        }

        $filename = array_pop($args);
        $filepath = $args ? '/'.implode('/', $args).'/' : '/';

        if (!$file = $fs->get_file($context->id, $component, $filearea, $entryid, $filepath, $filename) or $file->is_directory()) {
            send_file_not_found();
        }

        send_stored_file($file, 10*60, 0, true, $sendfileoptions); // download MUST be forced - security!

    // ========================================================================================================================
    } else if ($component === 'grade') {

        require_once($CFG->libdir . '/grade/constants.php');

        if (($filearea === 'outcome' or $filearea === 'scale') and $context->contextlevel == CONTEXT_SYSTEM) {
            // Global gradebook files
            if ($CFG->forcelogin) {
                require_login();
            }

            $fullpath = "/$context->id/$component/$filearea/".implode('/', $args);

            if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);

        } else if ($filearea == GRADE_FEEDBACK_FILEAREA || $filearea == GRADE_HISTORY_FEEDBACK_FILEAREA) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                send_file_not_found();
            }

            require_login($course, false);

            $gradeid = (int) array_shift($args);
            $filename = array_pop($args);
            if ($filearea == GRADE_HISTORY_FEEDBACK_FILEAREA) {
                $grade = $DB->get_record('grade_grades_history', ['id' => $gradeid]);
            } else {
                $grade = $DB->get_record('grade_grades', ['id' => $gradeid]);
            }

            if (!$grade) {
                send_file_not_found();
            }

            $iscurrentuser = $USER->id == $grade->userid;

            if (!$iscurrentuser) {
                $coursecontext = context_course::instance($course->id);
                if (!has_capability('moodle/grade:viewall', $coursecontext)) {
                    send_file_not_found();
                }
            }

            $fullpath = "/$context->id/$component/$filearea/$gradeid/$filename";

            if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);
        } else {
            send_file_not_found();
        }

    // ========================================================================================================================
    } else if ($component === 'tag') {
        if ($filearea === 'description' and $context->contextlevel == CONTEXT_SYSTEM) {

            // All tag descriptions are going to be public but we still need to respect forcelogin
            if ($CFG->forcelogin) {
                require_login();
            }

            $fullpath = "/$context->id/tag/description/".implode('/', $args);

            if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, true, $sendfileoptions);

        } else {
            send_file_not_found();
        }
    // ========================================================================================================================
    } else if ($component === 'badges') {
        require_once($CFG->libdir . '/badgeslib.php');

        $badgeid = (int)array_shift($args);
        $badge = new badge($badgeid);
        $filename = array_pop($args);

        if ($filearea === 'badgeimage') {
            if ($filename !== 'f1' && $filename !== 'f2' && $filename !== 'f3') {
                send_file_not_found();
            }
            if (!$file = $fs->get_file($context->id, 'badges', 'badgeimage', $badge->id, '/', $filename.'.png')) {
                send_file_not_found();
            }

            \core\session\manager::write_close();
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);
        } else if ($filearea === 'userbadge'  and $context->contextlevel == CONTEXT_USER) {
            if (!$file = $fs->get_file($context->id, 'badges', 'userbadge', $badge->id, '/', $filename.'.png')) {
                send_file_not_found();
            }

            \core\session\manager::write_close();
            send_stored_file($file, 60*60, 0, true, $sendfileoptions);
        }
    // ========================================================================================================================
    } else if ($component === 'calendar') {
        if ($filearea === 'event_description'  and $context->contextlevel == CONTEXT_SYSTEM) {

            // All events here are public the one requirement is that we respect forcelogin
            if ($CFG->forcelogin) {
                require_login();
            }

            // Get the event if from the args array
            $eventid = array_shift($args);

            // Load the event from the database
            if (!$event = $DB->get_record('event', array('id'=>(int)$eventid, 'eventtype'=>'site'))) {
                send_file_not_found();
            }

            // Get the file and serve if successful
            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, $component, $filearea, $eventid, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);

        } else if ($filearea === 'event_description' and $context->contextlevel == CONTEXT_USER) {

            // Must be logged in, if they are not then they obviously can't be this user
            require_login();

            // Don't want guests here, potentially saves a DB call
            if (isguestuser()) {
                send_file_not_found();
            }

            // Get the event if from the args array
            $eventid = array_shift($args);

            // Load the event from the database - user id must match
            if (!$event = $DB->get_record('event', array('id'=>(int)$eventid, 'userid'=>$USER->id, 'eventtype'=>'user'))) {
                send_file_not_found();
            }

            // Get the file and serve if successful
            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, $component, $filearea, $eventid, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 0, 0, true, $sendfileoptions);

        } else if ($filearea === 'event_description' and $context->contextlevel == CONTEXT_COURSECAT) {
            if ($CFG->forcelogin) {
                require_login();
            }

            // Get category, this will also validate access.
            $category = core_course_category::get($context->instanceid);

            // Get the event ID from the args array, load event.
            $eventid = array_shift($args);
            $event = $DB->get_record('event', [
                'id' => (int) $eventid,
                'eventtype' => 'category',
                'categoryid' => $category->id,
            ]);

            if (!$event) {
                send_file_not_found();
            }

            // Retrieve file from storage, and serve.
            $filename = array_pop($args);
            $filepath = $args ? '/' . implode('/', $args) .'/' : '/';
            $file = $fs->get_file($context->id, $component, $filearea, $eventid, $filepath, $filename);
            if (!$file || $file->is_directory()) {
                send_file_not_found();
            }

            // Unlock session during file serving.
            \core\session\manager::write_close();
            send_stored_file($file, HOURSECS, 0, $forcedownload, $sendfileoptions);
        } else if ($filearea === 'event_description' and $context->contextlevel == CONTEXT_COURSE) {

            // Respect forcelogin and require login unless this is the site.... it probably
            // should NEVER be the site
            if ($CFG->forcelogin || $course->id != SITEID) {
                require_login($course);
            }

            // Must be able to at least view the course. This does not apply to the front page.
            if ($course->id != SITEID && (!is_enrolled($context)) && (!is_viewing($context))) {
                //TODO: hmm, do we really want to block guests here?
                send_file_not_found();
            }

            // Get the event id
            $eventid = array_shift($args);

            // Load the event from the database we need to check whether it is
            // a) valid course event
            // b) a group event
            // Group events use the course context (there is no group context)
            if (!$event = $DB->get_record('event', array('id'=>(int)$eventid, 'courseid'=>$course->id))) {
                send_file_not_found();
            }

            // If its a group event require either membership of view all groups capability
            if ($event->eventtype === 'group') {
                if (!has_capability('moodle/site:accessallgroups', $context) && !groups_is_member($event->groupid, $USER->id)) {
                    send_file_not_found();
                }
            } else if ($event->eventtype === 'course' || $event->eventtype === 'site') {
                // Ok. Please note that the event type 'site' still uses a course context.
            } else {
                // Some other type.
                send_file_not_found();
            }

            // If we get this far we can serve the file
            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, $component, $filearea, $eventid, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);

        } else {
            send_file_not_found();
        }

    // ========================================================================================================================
    } else if ($component === 'user') {
        if ($filearea === 'icon' and $context->contextlevel == CONTEXT_USER) {
            if (count($args) == 1) {
                $themename = theme_config::DEFAULT_THEME;
                $filename = array_shift($args);
            } else {
                $themename = array_shift($args);
                $filename = array_shift($args);
            }

            // fix file name automatically
            if ($filename !== 'f1' and $filename !== 'f2' and $filename !== 'f3') {
                $filename = 'f1';
            }

            if ((!empty($CFG->forcelogin) and !isloggedin()) ||
                    (!empty($CFG->forceloginforprofileimage) && (!isloggedin() || isguestuser()))) {
                // protect images if login required and not logged in;
                // also if login is required for profile images and is not logged in or guest
                // do not use require_login() because it is expensive and not suitable here anyway
                $theme = theme_config::load($themename);
                redirect($theme->image_url('u/'.$filename, 'moodle')); // intentionally not cached
            }

            if (!$file = $fs->get_file($context->id, 'user', 'icon', 0, '/', $filename.'.png')) {
                if (!$file = $fs->get_file($context->id, 'user', 'icon', 0, '/', $filename.'.jpg')) {
                    if ($filename === 'f3') {
                        // f3 512x512px was introduced in 2.3, there might be only the smaller version.
                        if (!$file = $fs->get_file($context->id, 'user', 'icon', 0, '/', 'f1.png')) {
                            $file = $fs->get_file($context->id, 'user', 'icon', 0, '/', 'f1.jpg');
                        }
                    }
                }
            }
            if (!$file) {
                // bad reference - try to prevent future retries as hard as possible!
                if ($user = $DB->get_record('user', array('id'=>$context->instanceid), 'id, picture')) {
                    if ($user->picture > 0) {
                        $DB->set_field('user', 'picture', 0, array('id'=>$user->id));
                    }
                }
                // no redirect here because it is not cached
                $theme = theme_config::load($themename);
                $imagefile = $theme->resolve_image_location('u/'.$filename, 'moodle', null);
                send_file($imagefile, basename($imagefile), 60*60*24*14);
            }

            $options = $sendfileoptions;
            if (empty($CFG->forcelogin) && empty($CFG->forceloginforprofileimage)) {
                // Profile images should be cache-able by both browsers and proxies according
                // to $CFG->forcelogin and $CFG->forceloginforprofileimage.
                $options['cacheability'] = 'public';
            }
            send_stored_file($file, 60*60*24*365, 0, false, $options); // enable long caching, there are many images on each page

        } else if ($filearea === 'private' and $context->contextlevel == CONTEXT_USER) {
            require_login();

            if (isguestuser()) {
                send_file_not_found();
            }

            if ($USER->id !== $context->instanceid) {
                send_file_not_found();
            }

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, $component, $filearea, 0, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 0, 0, true, $sendfileoptions); // must force download - security!

        } else if ($filearea === 'profile' and $context->contextlevel == CONTEXT_USER) {

            if ($CFG->forcelogin) {
                require_login();
            }

            $userid = $context->instanceid;

            if (!empty($CFG->forceloginforprofiles)) {
                require_once("{$CFG->dirroot}/user/lib.php");

                require_login();

                // Verify the current user is able to view the profile of the supplied user anywhere.
                $user = core_user::get_user($userid);
                if (!user_can_view_profile($user, null, $context)) {
                    send_file_not_found();
                }
            }

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, $component, $filearea, 0, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 0, 0, true, $sendfileoptions); // must force download - security!

        } else if ($filearea === 'profile' and $context->contextlevel == CONTEXT_COURSE) {
            $userid = (int)array_shift($args);
            $usercontext = context_user::instance($userid);

            if ($CFG->forcelogin) {
                require_login();
            }

            if (!empty($CFG->forceloginforprofiles)) {
                require_once("{$CFG->dirroot}/user/lib.php");

                require_login();

                // Verify the current user is able to view the profile of the supplied user in current course.
                $user = core_user::get_user($userid);
                if (!user_can_view_profile($user, $course, $usercontext)) {
                    send_file_not_found();
                }
            }

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($usercontext->id, 'user', 'profile', 0, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 0, 0, true, $sendfileoptions); // must force download - security!

        } else if ($filearea === 'backup' and $context->contextlevel == CONTEXT_USER) {
            require_login();

            if (isguestuser()) {
                send_file_not_found();
            }
            $userid = $context->instanceid;

            if ($USER->id != $userid) {
                send_file_not_found();
            }

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, 'user', 'backup', 0, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 0, 0, true, $sendfileoptions); // must force download - security!

        } else {
            send_file_not_found();
        }

    // ========================================================================================================================
    } else if ($component === 'coursecat') {
        if ($context->contextlevel != CONTEXT_COURSECAT) {
            send_file_not_found();
        }

        if ($filearea === 'description') {
            if ($CFG->forcelogin) {
                // no login necessary - unless login forced everywhere
                require_login();
            }

            // Check if user can view this category.
            if (!core_course_category::get($context->instanceid, IGNORE_MISSING)) {
                send_file_not_found();
            }

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, 'coursecat', 'description', 0, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);
        } else {
            send_file_not_found();
        }

    // ========================================================================================================================
    } else if ($component === 'course') {
        if ($context->contextlevel != CONTEXT_COURSE) {
            send_file_not_found();
        }

        if ($filearea === 'summary' || $filearea === 'overviewfiles') {
            if ($CFG->forcelogin) {
                require_login();
            }

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, 'course', $filearea, 0, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);

        } else if ($filearea === 'section') {
            if ($CFG->forcelogin) {
                require_login($course);
            } else if ($course->id != SITEID) {
                require_login($course);
            }

            $sectionid = (int)array_shift($args);

            if (!$section = $DB->get_record('course_sections', array('id'=>$sectionid, 'course'=>$course->id))) {
                send_file_not_found();
            }

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, 'course', 'section', $sectionid, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);

        } else {
            send_file_not_found();
        }

    } else if ($component === 'cohort') {

        $cohortid = (int)array_shift($args);
        $cohort = $DB->get_record('cohort', array('id' => $cohortid), '*', MUST_EXIST);
        $cohortcontext = context::instance_by_id($cohort->contextid);

        // The context in the file URL must be either cohort context or context of the course underneath the cohort's context.
        if ($context->id != $cohort->contextid &&
            ($context->contextlevel != CONTEXT_COURSE || !in_array($cohort->contextid, $context->get_parent_context_ids()))) {
            send_file_not_found();
        }

        // User is able to access cohort if they have view cap on cohort level or
        // the cohort is visible and they have view cap on course level.
        $canview = has_capability('moodle/cohort:view', $cohortcontext) ||
                ($cohort->visible && has_capability('moodle/cohort:view', $context));

        if ($filearea === 'description' && $canview) {
            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (($file = $fs->get_file($cohortcontext->id, 'cohort', 'description', $cohort->id, $filepath, $filename))
                    && !$file->is_directory()) {
                \core\session\manager::write_close(); // Unlock session during file serving.
                send_stored_file($file, 60 * 60, 0, $forcedownload, $sendfileoptions);
            }
        }

        send_file_not_found();

    } else if ($component === 'group') {
        if ($context->contextlevel != CONTEXT_COURSE) {
            send_file_not_found();
        }

        require_course_login($course, true, null, false);

        $groupid = (int)array_shift($args);

        $group = $DB->get_record('groups', array('id'=>$groupid, 'courseid'=>$course->id), '*', MUST_EXIST);
        if (($course->groupmodeforce and $course->groupmode == SEPARATEGROUPS) and !has_capability('moodle/site:accessallgroups', $context) and !groups_is_member($group->id, $USER->id)) {
            // do not allow access to separate group info if not member or teacher
            send_file_not_found();
        }

        if ($filearea === 'description') {

            require_login($course);

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, 'group', 'description', $group->id, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);

        } else if ($filearea === 'icon') {
            $filename = array_pop($args);

            if ($filename !== 'f1' and $filename !== 'f2') {
                send_file_not_found();
            }
            if (!$file = $fs->get_file($context->id, 'group', 'icon', $group->id, '/', $filename.'.png')) {
                if (!$file = $fs->get_file($context->id, 'group', 'icon', $group->id, '/', $filename.'.jpg')) {
                    send_file_not_found();
                }
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, false, $sendfileoptions);

        } else {
            send_file_not_found();
        }

    } else if ($component === 'grouping') {
        if ($context->contextlevel != CONTEXT_COURSE) {
            send_file_not_found();
        }

        require_login($course);

        $groupingid = (int)array_shift($args);

        // note: everybody has access to grouping desc images for now
        if ($filearea === 'description') {

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, 'grouping', 'description', $groupingid, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);

        } else {
            send_file_not_found();
        }

    // ========================================================================================================================
    } else if ($component === 'backup') {
        if ($filearea === 'course' and $context->contextlevel == CONTEXT_COURSE) {
            require_login($course);
            require_capability('moodle/backup:downloadfile', $context);

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, 'backup', 'course', 0, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 0, 0, $forcedownload, $sendfileoptions);

        } else if ($filearea === 'section' and $context->contextlevel == CONTEXT_COURSE) {
            require_login($course);
            require_capability('moodle/backup:downloadfile', $context);

            $sectionid = (int)array_shift($args);

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, 'backup', 'section', $sectionid, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close();
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);

        } else if ($filearea === 'activity' and $context->contextlevel == CONTEXT_MODULE) {
            require_login($course, false, $cm);
            require_capability('moodle/backup:downloadfile', $context);

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, 'backup', 'activity', 0, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close();
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);

        } else if ($filearea === 'automated' and $context->contextlevel == CONTEXT_COURSE) {
            // Backup files that were generated by the automated backup systems.

            require_login($course);
            require_capability('moodle/backup:downloadfile', $context);
            require_capability('moodle/restore:userinfo', $context);

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, 'backup', 'automated', 0, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 0, 0, $forcedownload, $sendfileoptions);

        } else {
            send_file_not_found();
        }

    // ========================================================================================================================
    } else if ($component === 'question') {
        require_once($CFG->libdir . '/questionlib.php');
        question_pluginfile($course, $context, 'question', $filearea, $args, $forcedownload, $sendfileoptions);
        send_file_not_found();

    // ========================================================================================================================
    } else if ($component === 'grading') {
        if ($filearea === 'description') {
            // files embedded into the form definition description

            if ($context->contextlevel == CONTEXT_SYSTEM) {
                require_login();

            } else if ($context->contextlevel >= CONTEXT_COURSE) {
                require_login($course, false, $cm);

            } else {
                send_file_not_found();
            }

            $formid = (int)array_shift($args);

            $sql = "SELECT ga.id
                FROM {grading_areas} ga
                JOIN {grading_definitions} gd ON (gd.areaid = ga.id)
                WHERE gd.id = ? AND ga.contextid = ?";
            $areaid = $DB->get_field_sql($sql, array($formid, $context->id), IGNORE_MISSING);

            if (!$areaid) {
                send_file_not_found();
            }

            $fullpath = "/$context->id/$component/$filearea/$formid/".implode('/', $args);

            if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);
        }
    } else if ($component === 'contentbank') {
        if ($filearea != 'public' || isguestuser()) {
            send_file_not_found();
        }

        if ($context->contextlevel == CONTEXT_SYSTEM || $context->contextlevel == CONTEXT_COURSECAT) {
            require_login();
        } else if ($context->contextlevel == CONTEXT_COURSE) {
            require_login($course);
        } else {
            send_file_not_found();
        }

        $componentargs = fullclone($args);
        $itemid = (int)array_shift($args);
        $filename = array_pop($args);
        $filepath = $args ? '/'.implode('/', $args).'/' : '/';

        \core\session\manager::write_close(); // Unlock session during file serving.

        $contenttype = $DB->get_field('contentbank_content', 'contenttype', ['id' => $itemid]);
        if (component_class_callback("\\{$contenttype}\\contenttype", 'pluginfile',
                [$course, null, $context, $filearea, $componentargs, $forcedownload, $sendfileoptions], false) === false) {

            if (!$file = $fs->get_file($context->id, $component, $filearea, $itemid, $filepath, $filename) or

                $file->is_directory()) {
                send_file_not_found();

            } else {
                send_stored_file($file, 0, 0, true, $sendfileoptions); // Must force download - security!
            }
        }
    } else if (strpos($component, 'mod_') === 0) {
        $modname = substr($component, 4);
        if (!file_exists("$CFG->dirroot/mod/$modname/lib.php")) {
            send_file_not_found();
        }
        require_once("$CFG->dirroot/mod/$modname/lib.php");

        if ($context->contextlevel == CONTEXT_MODULE) {
            if ($cm->modname !== $modname) {
                // somebody tries to gain illegal access, cm type must match the component!
                send_file_not_found();
            }
        }

        if ($filearea === 'intro') {
            if (!plugin_supports('mod', $modname, FEATURE_MOD_INTRO, true)) {
                send_file_not_found();
            }

            // Require login to the course first (without login to the module).
            require_course_login($course, true);

            // Now check if module is available OR it is restricted but the intro is shown on the course page.
            $cminfo = cm_info::create($cm);
            if (!$cminfo->uservisible) {
                if (!$cm->showdescription || !$cminfo->is_visible_on_course_page()) {
                    // Module intro is not visible on the course page and module is not available, show access error.
                    require_course_login($course, true, $cminfo);
                }
            }

            // all users may access it
            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, 'mod_'.$modname, 'intro', 0, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            // finally send the file
            send_stored_file($file, null, 0, false, $sendfileoptions);
        }

        $filefunction = $component.'_pluginfile';
        $filefunctionold = $modname.'_pluginfile';
        if (function_exists($filefunction)) {
            // if the function exists, it must send the file and terminate. Whatever it returns leads to "not found"
            $filefunction($course, $cm, $context, $filearea, $args, $forcedownload, $sendfileoptions);
        } else if (function_exists($filefunctionold)) {
            // if the function exists, it must send the file and terminate. Whatever it returns leads to "not found"
            $filefunctionold($course, $cm, $context, $filearea, $args, $forcedownload, $sendfileoptions);
        }

        send_file_not_found();

    // ========================================================================================================================
    } else if (strpos($component, 'block_') === 0) {
        $blockname = substr($component, 6);
        // note: no more class methods in blocks please, that is ....
        if (!file_exists("$CFG->dirroot/blocks/$blockname/lib.php")) {
            send_file_not_found();
        }
        require_once("$CFG->dirroot/blocks/$blockname/lib.php");

        if ($context->contextlevel == CONTEXT_BLOCK) {
            $birecord = $DB->get_record('block_instances', array('id'=>$context->instanceid), '*',MUST_EXIST);
            if ($birecord->blockname !== $blockname) {
                // somebody tries to gain illegal access, cm type must match the component!
                send_file_not_found();
            }

            if ($context->get_course_context(false)) {
                // If block is in course context, then check if user has capability to access course.
                require_course_login($course);
            } else if ($CFG->forcelogin) {
                // If user is logged out, bp record will not be visible, even if the user would have access if logged in.
                require_login();
            }

            $bprecord = $DB->get_record('block_positions', array('contextid' => $context->id, 'blockinstanceid' => $context->instanceid));
            // User can't access file, if block is hidden or doesn't have block:view capability
            if (($bprecord && !$bprecord->visible) || !has_capability('moodle/block:view', $context)) {
                 send_file_not_found();
            }
        } else {
            $birecord = null;
        }

        $filefunction = $component.'_pluginfile';
        if (function_exists($filefunction)) {
            // if the function exists, it must send the file and terminate. Whatever it returns leads to "not found"
            $filefunction($course, $birecord, $context, $filearea, $args, $forcedownload, $sendfileoptions);
        }

        send_file_not_found();

    // ========================================================================================================================
    } else if (strpos($component, '_') === false) {
        // all core subsystems have to be specified above, no more guessing here!
        send_file_not_found();

    } else {
        // try to serve general plugin file in arbitrary context
        $dir = core_component::get_component_directory($component);
        if (!file_exists("$dir/lib.php")) {
            send_file_not_found();
        }
        include_once("$dir/lib.php");

        $filefunction = $component.'_pluginfile';
        if (function_exists($filefunction)) {
            // if the function exists, it must send the file and terminate. Whatever it returns leads to "not found"
            $filefunction($course, $cm, $context, $filearea, $args, $forcedownload, $sendfileoptions);
        }

        send_file_not_found();
    }

}
