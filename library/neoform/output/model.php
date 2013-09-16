<?php

    namespace neoform\output;

    use neoform\config;
    use neoform\http;
    use neoform\locale;
    use neoform\render;

    /**
     * Handle all output that goes to the browser, this includes headers
     *
     * Standard usage: output::instance()
     */
    class model {

        protected $http_response_code = 200;
        protected $headers = [];
        protected $body;

        const HTML = 'text/html';
        const JSON = 'application/json';
        const XML  = 'application/xml';

        protected $headers_sent = false;
        protected $output_type  = self::HTML;

        public function __construct() {
            $this->header('Core', 'v0.3');
            $this->header('cache-control', 'private, max-age=0');
        }

        /**
         * Set an HTTP header
         *
         * @param string      $type
         * @param string|null $val
         *
         * @return model
         */
        public function header($type, $val=null) {
            $header = $type . ($val ? ": {$val}" : '');
            if (! isset($this->headers[$header])) {
                $this->headers[$header] = $header;
            }
            return $this;
        }

        /**
         * Create a new cookie
         *
         * @param string       $key
         * @param string       $val
         * @param integer|null $ttl
         *
         * @return bool
         */
        public function cookie_set($key, $val, $ttl=null) {

            $config = config::instance();

            if ($ttl === null || ! is_numeric($ttl)) {
                $ttl = time() + $config['cookies']['ttl'];
            }

            return setcookie(
                $key,
                base64_encode($val),
                time() + intval($ttl),
                isset($config['cookies']['path']) ? $config['cookies']['path'] : http::instance()->server('subdir'),
                $config['http']['domain'],
                (bool) $config['cookies']['secure'],
                (bool) $config['cookies']['httponly']
            );
        }

        /**
         * Delete a cookie from browser
         *
         * @param string $key name of cookie
         *
         * @return bool
         */
        public static function cookie_delete($key) {
            $config = config::instance();
            return setcookie(
                $key,
                '',
                time() - 100000,
                isset($config['cookies']['path']) ? $config['cookies']['path'] : http::instance()->server('subdir'),
                $config['core']['domain']
            );
        }

        /**
         * Get an array containing all the HTTP headers
         *
         * @return array
         */
        public function get_headers() {
            return array_values($this->headers);
        }

        /**
         * Send all HTTP headers to browser
         *
         * @return model
         */
        public function send_headers() {
            if (! $this->headers_sent) {
                $this->headers_sent = true;
                http_response_code($this->http_response_code);
                foreach ($this->headers as $header) {
                    header($header);
                }
            }
            return $this;
        }

        /**
         * Set the output body, or get it
         *
         * @param string|null $str
         *
         * @return string|model
         */
        public function body($str=null) {
            if ($str === null) {
                return $this->body;
            } else {
                $this->body = (string) $str;
                return $this;
            }
        }

        /**
         * Set the content-type header
         *
         * @param string $type 'json', 'xml', defaults to 'html', if null passed the output type is returned
         *
         * @return model|string
         */
        public function output_type($type='') {
            if ($type !== null) {
                switch ((string) $type) {
                    case 'json':
                        $this->output_type = self::JSON;
                        $this->header('Content-type', self::JSON . '; charset="' . config::instance()['core']['encoding'] . '"');
                        break;

                    case 'xml':
                        $this->output_type = self::XML;
                        $this->header('Content-type', self::XML . '; charset="' . config::instance()['core']['encoding'] . '"');
                        break;

                    case 'html':
                    case '':
                        $this->output_type = self::HTML;
                        $this->header('Content-type', self::HTML . '; charset="' . config::instance()['core']['encoding'] . '"');
                        break;

                    default:
                        $this->output_type = $type;
                        $this->header('Content-type', $type . '; charset="' . config::instance()['core']['encoding'] . '"');
                        break;
                }
                return $this;
            } else {
                return $this->output_type;
            }
        }

        /**
         * Redirect the user to a different url on the site
         *
         * @param string $url
         * @param int    $http_code
         *
         * @return model
         */
        public function redirect($url='', $http_code=303) {
            $base_url = substr(http::instance()->server('url'), 0, -1);
            if (substr($url, 0, 1) !== '/') {
                $this->header('Location', $base_url . locale::instance()->route("/{$url}"), true, $http_code);
            } else {
                $this->header('Location', $base_url . locale::instance()->route($url), true, $http_code);
            }
            return $this;
        }

        /**
         * Display an error to the user
         *
         * @param string|null  $title
         * @param string|null  $message
         * @param integer|null $status_code
         */
        public function error($title=null, $message=null, $status_code=500) {

            $this->flush();

            // Reset the page
            $this->headers = [];
            $this->body    = null;

            if ($this->output_type === self::JSON) {

                $json = new render\json;
                $json->status = 'fault';

                if ($title && $message) {
                    $json->message = "{$title} - {$message}";
                } else if ($title) {
                    $json->message = $title;
                } else {
                    $json->message = 'There was a problem generating that page.';
                }

                $json->render();

            } else {

                try {;
                    http\controller::error($status_code, $title, $message);
                } catch (\exception $e) {
                    $this->body = $message;
                }
            }
        }

        /**
         * Destroy all output
         */
        public function flush() {
            try {
                //trash anything that was going to be outputted
                while (ob_get_status() && ob_end_clean()) {

                }
            } catch (\exception $e) {

            }

            $this->body = null;
        }

        /**
         * Get or set HTTP status code
         *
         * @param integer|null $code if passed, changes the current http status code, if not set, returns the current code
         *
         * @return int|model
         */
        public function http_status_code($code=null) {
            if ($code === null) {
                return $this->http_response_code;
            } else {
                $this->http_response_code = (int) $code;
                return $this;
            }
        }
    }