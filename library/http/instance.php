<?php

    class http_instance {

        use core_instance;

        /**
         * Local variables, generated by this class
         */
        protected $executed;
        protected $segments       = [];
        protected $parameter_vars = [];
        protected $server_vars    = [];
        protected $subdomain_validated;

        /**
         * Vars gathered from HTTP header info
         */
        protected $get;
        protected $post;
        protected $files;
        protected $server;
        protected $config;
        protected $cookies;

        /**
         * base64 decoded version of the cookies
         */
        protected $cookies_decoded = false;

        protected $ref_code_cache;
        protected $ref_secret_cache;

        /**
         * Assemble useful information based on $this->server vars and config
         *
         * @param string $router_path
         * @param array $config
         * @param array $get
         * @param array $post
         * @param array $files
         * @param array $server
         * @param array $cookies
         * @returns array
         * @throws http_exception
         */
        public function __construct($router_path, array $config, array $get, array $post, array $files, array $server, array $cookies) {
            if ($router_path === false) {
                throw new http_exception('Please set the routing path');
            }

            $this->get     = $get;
            $this->post    = $post;
            $this->files   = $files;
            $this->server  = $server;
            $this->config  = $config;
            $this->cookies = $cookies;

            // set the default locale
            core::locale()->set($config['locale']['default']);

            // Make sure require variables are set
            $subdomains = isset($config['subdomains']) && is_array($config['subdomains']) ? $config['subdomains'] : [];

            //strip off any GET elements
            if (strpos($router_path, '?') !== false) {
                if (preg_match('`([^\?]*)\?(.*)`', $router_path, $matche)) {
                    $router_path = $matche[1];
                }
            }

            $this->segments    = explode('/', $router_path);
            $this->segments[0] = '/';

            $segment_count = count($this->segments);
            $unsetted      = false;

            foreach ($this->segments as $key => $val) {
                //if this is the last segment and it's empty, remove it (trailing slashes cause the last segment to be empty)
                if ($key == $segment_count - 1 && ! strlen($val)) {
                    unset($this->segments[$key]);
                    $unsetted = true;
                    break;
                }

                // if locale is the first param of the url, override the default locale
                if ($key === 1 && in_array($this->segments[$key], $config['locale']['allowed'], true)) {
                    core::locale()->set($this->segments[$key]);
                    unset($this->segments[$key]);
                    $unsetted = true;
                } else {

                    //check for variables in the segments
                    $location = strpos($val, ':');

                    if ($location !== false) {
                        $k = substr($val, 0, $location);
                        $v = substr($val, $location + 1);

                        if (strpos($v, '|') !== false) {
                            $this->parameter_vars[$k] = explode('|', $v);
                        } else {
                            $this->parameter_vars[$k] = $v;
                        }

                        unset($this->segments[$key]);
                        $unsetted = true;
                    }
                }
            }

            if ($unsetted) {
                $this->segments = array_values($this->segments);
            }

            // Site's current subdirectory (if set)
            $subdir = isset($this->server['SCRIPT_NAME']) ? dirname($this->server['SCRIPT_NAME']) : '';
            $subdir = strlen($subdir) > 1 ? $subdir.'/' : '/';

            // Domain/subdomain
            $domain    = isset($this->server['SERVER_NAME']) ? strtolower($this->server['SERVER_NAME']) : '';
            $subdomain = '';

            // The period count in the site's domain
            $real_domain_segment_count = substr_count($this->config['domain'], '.') + 1; // +1 because the period count is 1 less than the segment count

            // Since we're not sure what the domain is, pull everything after the second dot (right to left) and make that the current subdomain
            if (strpos($domain, '.') !== false) {
                $domain_segments       = explode('.', $domain);
                $domain_segments_count = count($domain_segments);

                if ($domain_segments_count > $real_domain_segment_count) {
                    // sub2.sub1.domain.com
                    $subdomain = implode('.', array_splice($domain_segments, 0, $domain_segments_count - 2));
                    $domain    = implode('.', $domain_segments);
                }
            }

            $https = isset($this->server['HTTPS']) && $this->server['HTTPS'] == 'on';

            // Check if subdomain is valid (in the config)
            if ($subdomain && count($subdomains)) {
                foreach ($subdomains as $subdomain_pair) {
                    if ($subdomain_pair['regular'] == $subdomain || $subdomain_pair['secure'] == $subdomain) {
                        $subdomain_regular = $subdomain_pair['regular'];
                        $subdomain_secure  = $subdomain_pair['secure'];

                        //assemble the SURL (secure url) and RURL (regular url)
                        $rurl = ($config['https']['regular'] ? 'https' : 'http') . '://' .
                                ($subdomain_pair['regular'] ? $subdomain_pair['regular'] . '.' : '' ) .
                                $config['domain'] . $subdir;

                        $surl = ($config['https']['secure']  ? 'https' : 'http') . '://' .
                                ($subdomain_pair['secure'] ? $subdomain_pair['secure'] . '.' : '') .
                                $config['domain'] . $subdir;

                        $this->subdomain_validated = true;

                        break;
                    }
                }
            }

            //default urls
            //assemble the SURL (secure url) and RURL (regular url)
            $drurl = ($config['https']['regular'] ? 'https' : 'http') . '://' .
                    ($config['subdomain_default']['regular'] ? $config['subdomain_default']['regular'] . '.' : '') .
                    $config['domain'] . $subdir;

            $dsurl = ($config['https']['secure'] ? 'https' : 'http') . '://' .
                    ($config['subdomain_default']['secure'] ? $config['subdomain_default']['secure'] . '.' : '') .
                    $config['domain'] . $subdir;


            if (! $this->subdomain_validated) {
                $subdomain_regular = $config['subdomain_default']['regular'];
                $subdomain_secure  = $config['subdomain_default']['secure'];

                $rurl = $drurl;
                $surl = $dsurl;
            }

            // Query
            $query = isset($this->server['REQUEST_URI']) ? $this->server['REQUEST_URI'] : '';

            if ($query) {
                if (substr($query, 0, strlen($subdir)) == strtolower($subdir)) {
                    $query = substr($query, strlen($subdir));
                } else {
                    $query = substr($query, 1);
                }
            }

            $this->server_vars = [
                'https'             => $https,
                'domain'            => $domain,
                'subdomain'         => $subdomain,
                'subdomain_regular' => $subdomain_regular,
                'subdomain_secure'  => $subdomain_secure,
                'query'             => $query,
                'agent'             => isset($this->server['HTTP_USER_AGENT']) ? $this->server['HTTP_USER_AGENT'] : null,
                'ip'                => isset($this->server['REMOTE_ADDR']) ? $this->server['REMOTE_ADDR'] : null,
                'method'            => isset($this->server['REQUEST_METHOD']) ? $this->server['REQUEST_METHOD'] : null,
                'url'               => $rurl,  // URL
                'rurl'              => $rurl,  // Regular URL
                'surl'              => $surl,  // Secure URL
                'durl'              => $drurl, // Default URL
                'drurl'             => $drurl, // Defualt regular URL
                'dsurl'             => $dsurl, // Defualt secure URL
                'subdir'            => $subdir,
                'referer'           => isset($this->server['HTTP_REFERER']) ? $this->server['HTTP_REFERER'] : null,
            ];
        }

        /**
         * http://www.example.com/segment1/segment2/segment3/etc..
         *
         * @param integer $number
         *
         * @return string|null
         */
        public function segment($number) {
            if (isset($this->segments[$number])) {
                return $this->segments[$number];
            }
        }

        /**
         * http://www.example.com/segment1/segment2/segment3/etc..
         *
         * @return array of url segments
         */
        public function segments() {
            return $this->segments;
        }

        /**
         * URL vars. http://example.com/var1:value1/var2:varlue2/
         *
         * @param string $key
         *
         * @return string|array|null
         */
        public function parameter($key)  {
            if (isset($this->parameter_vars[$key])) {
                return $this->parameter_vars[$key];
            }
        }

        /**
         * URL vars. http://example.com/var1:value1/var2:varlue2/
         *
         * @return array of url vars
         */
        public function parameters() {
            return $this->parameter_vars;
        }

        /**
         * Vars assigned during `http` instantiation
         *
         * @param string $key
         *
         * @return string
         */
        public function server($key) {
            if (isset($this->server_vars[$key])) {
                return $this->server_vars[$key];
            } else if (isset($this->server[$key])) {
                return $this->server[$key];
            }
        }

        /**
         * Vars assigned during `http` instantiation
         *
         * @return array
         */
        public function server_vars() {
            return $this->server_vars;
        }

        /**
         * GET var
         *
         * @param string $key
         *
         * @return string|array|null
         */
        public function get($key) {
            if (isset($this->get[$key])) {
                return $this->get[$key];
            }
        }

        /**
         * GET vars
         *
         * @return array
         */
        public function gets() {
            return $this->get;
        }

        /**
         * Checks if a GET var is set
         *
         * @param string $key
         *
         * @return bool
         */
        public function get_isset($key) {
            return isset($this->get[$key]);
        }

        /**
         * POST var
         *
         * @param string $key
         *
         * @return string|array|null
         */
        public function post($key) {
            if (isset($this->post[$key])) {
                return $this->post[$key];
            }
        }

        /**
         * POST vars
         *
         * @return array
         */
        public function posts() {
            return $this->post;
        }

        /**
         * Checks if a POST var is set
         *
         * @param string $key
         *
         * @return bool
         */
        public function post_isset($key) {
            return isset($this->post[$key]);
        }

        /**
         * FILE var
         *
         * @param string $key
         *
         * @return array|null
         */
        public function file($key) {
            if (isset($this->files[$key])) {
                return $this->files[$key];
            }
        }

        /**
         * FILE vars
         *
         * @return array
         */
        public function files() {
            return $this->files;
        }

        /**
         * Raw input from the HTTP request
         *
         * @return string
         */
        public function input() {
            return file_get_contents('php://input');
        }

        /**
         * Cookie value
         *
         * @param string $key
         *
         * @return string|array|null
         */
        public function cookie($key) {
            if (isset($this->cookies[$key])) {
                $this->decode_cookies();
                return $this->cookies[$key];
            }
        }

        /**
         * Cookie values
         *
         * @return array
         */
        public function cookies() {
            $this->decode_cookies();
            return $this->cookies;
        }

        /**
         * Decode cookies (base64)
         */
        protected function decode_cookies() {
            if (! $this->cookies_decoded) {
                foreach ($this->cookies as & $cookie) {
                    $cookie = base64_decode($cookie);
                }
                $this->cookies_decoded = true;
            }
        }

        /**
         * Execute the http request and load the correct controller based on user permissions
         *
         * @throws redirect_login_exception
         */
        public function execute() {
            // Only run this once
            if ($this->executed) {
                return;
            }

            $this->executed = true;

            $info = http_dao::get(core::locale()->get());
            core::locale()->set_routes($info['routes']);

            $controllers       = $info['controllers'];
            $controller_path   = false;
            $controller_secure = false;

            //
            // Router
            //
            foreach ($this->segments as $struct) {

                // Add up the segments as we go along "/first/second/third" etc..
                if ($struct === '/') {
                    $controller = $controllers;
                } else if (isset($controller[$struct])) {
                    $controller = & $controller[$struct];
                } else {
                    break;
                }

                // If permissions set, make sure user has matching permissions
                if ($controller['resource_ids']) {
                    // If user is logged in
                    if (core::auth()->user_id) {
                        // And does not have permission - access denied
                        if (! core::auth()->user()->has_access($controller['resource_ids'])) {
                            core::output()->redirect('error/access_denied');
                            return;
                        }

                    // If user is not logged in
                    } else {
                        // Ask them to log in
                        throw new redirect_login_exception($this->server_vars['query'], 'You must be logged in to continue');
                    }
                }

                $controller_path = $controller['controller_path'];

                if ($controller['secure']) {
                    $controller_secure = true;
                }

                if (! $controller['children']) {
                    break;
                }

                $controller = & $controller['children'];
            }

            // Remove it from the global namespace
            unset($controllers_map);

            //
            // Domain/SSL validation
            //
            $redirect_needed = false;

            // Requested structure is 'secure'
            if ($controller_secure) {
                // Set the url
                $this->server_vars['url'] = $this->server_vars['surl'];

                if ( ($this->config['https']['secure'] && ! $this->server_vars['https']) || (! $this->config['https']['secure'] && $this->server_vars['https']) ) {
                    $redirect_needed = true;
                }

                if ($this->server_vars['subdomain_secure'] != $this->server_vars['subdomain']) {
                    $redirect_needed = true;
                }

            // Requested controller is 'regular'
            } else {
                //set the url
                $this->server_vars['url'] = $this->server_vars['rurl'];

                if ( ($this->config['https']['regular'] && ! $this->server_vars['https']) || (! $this->config['https']['regular'] && $this->server_vars['https']) ) {
                    $redirect_needed = true;
                }

                if ($this->server_vars['subdomain_regular'] != $this->server_vars['subdomain']) {
                    $redirect_needed = true;
                }
            }

            // If the domain name requested does not match the settings, redirect.
            if ($this->config['domain'] != $this->server_vars['domain']) {
                $redirect_needed = true;
            }

            // If a redirect is needed because incorrect protocol or subdomain is being used..
            if ($redirect_needed) {
                core::output()->redirect($this->server_vars['query']);
                return;
            } else {

                // Clean the path of naughtiness
                if (strpos($controller_path, '..') !== false) {
                    $controller_path = str_replace('..', '', $controller_path);
                }

                // Load the controller
                if (file_exists(core::path('application') . '/controllers' . $controller_path . '.' . EXT)) {
                    $controller_path = '/controllers' . $controller_path . '.' . EXT;

                // If page does not exist, 404 erorr page
                } else {
                    core::log('Controller missing: ' . $controller_path . '.' . EXT, 'fatal');
                    core::output()->redirect('error/not_found', 301);
                    return;
                }

                $this->server_vars['controller'] = $controller_path;

                //load the controller
                require_once(core::path('application') . $controller_path);
            }
        }

        /**
         * Check if the page being accessed was from an internal source and not from a 3rd party website
         * Good for blocking XSRF attacks.
         *
         * @param bool $output_error
         * @param string $rc if is not set, $rc is pulled from GET var
         *
         * @return bool
         * @throws error_exception
         */
        public function ref($output_error=true, $rc=null) {

            $good = true;

            $cookied_code = $this->cookie(core::config()->auth['cookie']);

            if (! $cookied_code) {
                $cookied_code = auth_lib::create_hash_cookie();
            }

            $timeout = (int) core::config()->session['ref_timeout'];
            if ($rc === null) {
                $httphash = isset($this->get['rc']) ? base64_decode($this->get['rc']) : false;
            } else {
                $httphash = base64_decode($rc);
            }
            $timestamp    = substr($httphash, -10);
            $cookiehash   = $this->ref_hash($cookied_code, $timestamp);
            $time         = time();

            // Make sure the code matches the user's cookie
            if (
                ! $httphash
                || ! $cookiehash
                || strcmp($cookiehash, $httphash) !== 0
            ) {
                $good = false;
            }

            try {
                // Make sure the referal domain matches this site
                $referer = trim($this->server('referer'));
                if ($referer) {
                    $url    = parse_url($referer);
                    $domain = isset($url['host']) ? $url['host'] : false;

                    if (! preg_match('`(^' . quotemeta($this->server('domain')) . '$)|(\.' . quotemeta($this->server('domain')) . '$)`i', $domain, $matches)) {
                        $good = false;
                    }
                } else {
                    $good = false;
                }
            } catch (Exception $e) {
                $good = false;
            }

            // Ref code expires if not used for too long..
            if ($good && ($timestamp > $time + $timeout || $timestamp < $time - $timeout)) {
                $good = false;
                if ($output_error) {
                    throw new error_exception("Your session has timed out, please go back and reload the page you were just on.");
                }
            } else if ($output_error && ! $good) {
                throw new error_exception("There was a problem verifying that your browser was referred here properly.");
            }

            return $good;
        }

        /**
         * Returns URL variable to be used to prevent XSRF attacks
         *
         * @return string
         */
        public function get_ref() {

            if (! $this->ref_code_cache) {

                $cookied_code = $this->cookie(core::config()->auth['cookie']);

                if (! $cookied_code) {
                    $cookied_code = auth_lib::create_hash_cookie();
                }

                // Append a timestamp so we can have the ref code expire
                $time = time();

                $this->ref_code_cache = rawurlencode(base64_encode($this->ref_hash($cookied_code, $time)));
            }

            return $this->ref_code_cache;
        }

        /**
         * Generates referral hash
         *
         * @param string $code
         * @param integer $timestamp
         *
         * @return string
         */
        protected function ref_hash($code, $timestamp) {

            if (! $this->ref_secret_cache) {
                $this->ref_secret_cache = core::config()->session['ref_secret'];
            }

            return hash('whirlpool', $code . $timestamp . $this->ref_secret_cache, 1) . $timestamp;
        }

        /**
         * Returns true if this page has been hotlinked, or the name of the domain doing the hotlinking
         *
         * @param bool $return_domain
         *
         * @return bool|string
         */
        public function hotlinked($return_domain=false) {
            static $hotlinked      = null;
            static $refered_domain = null;

            if ($hotlinked === null) {
                $hotlinked = true;
                try {
                    if ($this->server('referer')) {
                        $ref = parse_url(strtolower($this->server('referer')));
                        $refered_domain = join(
                            '.',
                            array_slice(
                                explode('.', isset($ref['host']) ? $ref['host'] : ''),
                                -2
                            )
                        );
                        $hotlinked = $refered_domain !== $this->server('domain');
                    }
                } catch (exception $e) {
                    //bo
                }
            }

            if ($return_domain) {
                return $refered_domain;
            } else {
                return $hotlinked;
            }
        }
    }