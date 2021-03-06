<?php

    namespace neoform;

    /**
     * Register the start time of the app
     */
    define('APP_START_TIME', microtime(1));

    /**
     * Turn on all error reporting
     */
    error_reporting(E_ALL);

    /**
     * Be literal with file permissions
     */
    umask(0);

    /**
    * Register autoloader
    */
    spl_autoload_register(
        function($name) {
            if (! include(str_replace(['\\', '_'], DIRECTORY_SEPARATOR, $name) . '.' . EXT)) {
                throw new \exception("Could not load file \"{$name}\"");
            }
        },
        true,
        true
    );

    /**
    * Core - first script loaded by main index.php file
    *        handles init of framework
    */
    class core {

        // Singletons
        protected static $instances = [];

        // Paths to directories used by framework
        protected static $paths = [];

        // What context was this application run (web/cli)
        protected static $environment;

        // Globals variables (use as sparingly as possible)
        protected static $globals = [];

        // Disable these methods
        final private function __clone() { }
        final private function __construct(array $args=null) {}

        /**
         * Applications path
         *
         * @param $type
         *
         * @return string|null
         * @throws \exception
         */
        public static function path($type) {
            if (isset(self::$paths[$type])) {
                return self::$paths[$type];
            }
            throw new \exception("Path {$type} not set");
        }

        /**
         * Return the name of the environment
         *
         * @return string
         */
        public static function environment() {
            return self::$environment;
        }

        /**
         * Application context
         *
         * @return string
         */
        public static function context() {
            return php_sapi_name() === 'cli' ? 'cli' : 'web';
        }

        /**
         * Checks if a singleton is loaded or not
         *
         * @param string $type
         * @param string|null $name (default: null)
         *
         * @return boolean
         */
        public static function is_loaded($type, $name=null) {
            return isset(self::$instances[$type][$name]);
        }

        /**
         * Distroys a singleton
         *
         * @param string $type
         * @param string|null $name (default: null)
         *
         * @return boolean
         */
        public static function kill($type, $name=null) {
            if (isset(self::$instances[$type][$name])) {
                self::$instances[$type][$name]->kill();
                self::$instances[$type][$name] = null;
                return true;
            } else {
                return false;
            }
        }

        /**
         * Get global variable
         *
         * @param string $k
         *
         * @return mixed
         */
        public static function get($k) {
            if (isset(self::$globals[$k])) {
                return self::$globals[$k];
            }
        }

        /**
         * Set global variable
         *
         * @param string $k
         * @param mixed $v
         *
         * @return null
         */
        public static function set($k, $v) {
            self::$globals[$k] = $v;
        }

        /**
         * Initialize the framework
         *   sets paths, error handlers, default timezone, and a few constants
         *
         * @param array $params
         *
         * @throws \ErrorException
         * @throws \exception
         */
        public static function init(array $params) {

            if (isset($params['extension'])) {
                define('EXT', $params['extension']);
            } else {
                die("Config Error: PHP file extension not set. core::init([\"extension\" => [...] ]).\n");
            }

            // This file is always found in the neoform dir
            self::$paths['library'] = realpath(__DIR__ . '/..');

            if (! isset($params['environment']) || ! self::$environment = $params['environment']) {
                die("Config Error: Environment name not set. core::init([\"environment\" => [...] [)\n");
            }

            if (! isset($params['application']) || ! self::$paths['application'] = realpath($params['application'])) {
                die("Config Error: PHP file extension not set or is invalid. core::init([\"application\" => [...] ])\n");
            }

            if (! isset($params['logs']) || ! self::$paths['logs'] = realpath($params['logs'])) {
                die("Config Error: Log dir path not set or is invalid. core::init([\"logs\" => [...] ])\n");
            }

            if (! isset($params['website']) || ! self::$paths['website'] = realpath($params['website'])) {
                die("Config Error: Web root path not set or is invalid. core::init([\"website\" => [...] ])\n");
            }

             //tell php where to find stuff
            set_include_path(
                self::$paths['application'] . // Application code/logic/views
                PATH_SEPARATOR .
                self::$paths['library'] // Library classes
            );
            define('WEB_ROOT', getcwd() . '/'); // Store the current dir as being the web root

            // Uncaught exception handler
            set_exception_handler(function(\exception $e) {

                error\lib::log($e);

                switch ((string) self::context()) {
                    case 'web':
                        http\controller::error(500, null, null, true);
                        echo output::instance()->send_headers()->body();
                        die;

                    default:
                        if ($e instanceof \ErrorException) {
                            die("Uncaught Exception: " . $e->getMessage() . " - " . $e->getFile() . ":" . $e->getLine() . "\n");
                        } else {
                            die("Uncaught Exception: " . $e->getMessage() . "\n");
                        }
                }
            });

            // PHP Error handler
            set_error_handler(function($err_number, $err_string, $err_file, $err_line) {

                // Error was suppressed with the @-operator
                if (! error_reporting()) {
                    return false;
                }

                throw new \ErrorException($err_string, $err_number, 0, $err_file, $err_line);
            });

            // Fatal error shutdown handler
            register_shutdown_function(function() {

                // Only grab error if there is one
                if (($error = error_get_last()) !== null) {
                    // This prevents obnoxious timezone warnings if the timezone has not been set
                    date_default_timezone_set(@date_default_timezone_get());

                    $message = isset($error['message']) ? $error['message'] : null;
                    $file    = isset($error['file']) ? $error['file'] : null;
                    $line    = isset($error['line']) ? $error['line'] : null;

                    error\lib::log(new \exception("{$message} - {$file}:{$line}"), 'fatal shutdown error');

                    switch ((string) core::context()) {
                        case 'web':
                            if (core::is_loaded('http')) {
                                try {
                                    http\controller::error(500, null, null, true);
                                } catch (\exception $e) {
                                    output::instance()->body('Unexpected Error - There was a problem loading that page');
                                }

                                echo output::instance()->send_headers()->body();
                            } else {
                                header('HTTP/1.1 500 Internal Server Error');
                                echo "An unexpected error occured\n";
                            }
                            die;

                        default:
                            die("FATAL ERROR [SHUTDOWN] - {$message} {$file} ({$line})\n");
                    }
                }
            });

            ini_set('log_errors', 1);
            ini_set('error_log', self::$paths['logs'] . '/errors.log');
            ini_set('log_errors_max_len', 0);
            ini_set('html_errors', 0);
            ini_set('display_errors', 'Off'); // do not display error(s) in browser - only affects non-fatal errors
            ini_set('display_startup_errors', 'Off');

            $config = config::instance()['core'];

            mb_internal_encoding($config['encoding']);
            date_default_timezone_set($config['timezone']);
        }

        /**
         * Save parameters to log file
         *
         * @param [mixed]
         *
         * @return null
         */
        public static function debug() {
            $args = func_get_args();
            self::log(count($args) === 1 ? current($args) : $args);
        }

        /**
         * Logs message to file, application dies if unable to log.
         *
         * @param mixed $msg
         * @param string $level (default: 'debug')
         * @param string $file  (default: 'errors')
         *
         * @return bool
         * @throws \Exception
         */
        public static function log($msg, $level='debug', $file='errors') {
            try {
                $log_path = self::$paths['logs'] . '/';

                if (file_exists($log_path) && is_writable($log_path)) {
                    $file_name = preg_replace('`[^A-Z0-9_\-\.]`isx', '', $file) . '.log';
                    $log_path .= $file_name;

                    if (! is_string($msg)) {
                        $msg = print_r($msg, 1);
                    }

                    $dt = new \datetime;

                    if (self::is_loaded('http')) {
                        $message = "\n" . $dt->format('Y-m-d H:i:s') . ' - ' . strtoupper($level) . "\n" . http::instance()->server('ip') . ' /' . http::instance()->server('query') . "\n{$msg}\n";
                    } else {
                        $message = "\n" . $dt->format('Y-m-d H:i:s') . ' - ' . strtoupper($level) . "\n\n{$msg}\n";
                    }

                    if (file_put_contents($log_path, $message, FILE_APPEND) === false) {
                        throw new \exception('Failed to write into a file...');
                    }

                    return true;
                }
            } catch (\exception $e) {
                // This only happens when we have an error within this function.
            }

            echo "Could not write to: {$log_path}";
            die;
        }
    }
