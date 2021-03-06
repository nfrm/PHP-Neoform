<?php

    namespace neoform\cache\driver;

    use neoform;

    class memcache implements neoform\cache\driver {

        /**
         * Activate a pipelined (batch) query - this doesn't exist in memcache, so ignore
         *
         * @param string $pool
         */
        public static function pipeline_start($pool) {

        }

        /**
         * Execute pipelined (batch) queries and return result - this doesn't exist in memcache, so ignore
         *
         * @param string $pool
         */
        public static function pipeline_execute($pool) {

        }

        /**
         * Increment the value of a cached entry (only works if the value is an int)
         *
         * @param string  $pool
         * @param string  $key
         * @param integer $offset
         */
        public static function increment($pool, $key, $offset=1) {
            neoform\memcache::instance($pool)->increment($key, $offset);
        }

        /**
         * Decrement the value of a cached entry (only works if the value is an int)
         *
         * @param string  $pool
         * @param string  $key
         * @param integer $offset
         */
        public static function decrement($pool, $key, $offset=1) {
            neoform\memcache::instance($pool)->decrement($key, $offset);
        }

        /**
         * Checks if cached record exists.
         *
         * @param string $pool
         * @param string $key
         *
         * @return boolean
         */
        public static function exists($pool, $key) {
            $memcache = neoform\memcache::instance($pool);
            $memcache->get($key);
            return (bool) $memcache->row_found();
        }

        /**
         * Gets cached data.
         *  if record does exist, an array with a single element, containing the data.
         *  returns null if record does not exist
         *
         * @param string $pool
         * @param string $key
         *
         * @return array|null returns null if record does not exist.
         */
        public static function get($pool, $key) {
            $memcache = neoform\memcache::instance($pool);
            $data = $memcache->get($key);
            if ($memcache->row_found()) {
                return [
                    $data,
                ];
            }
        }

        /**
         * @param string       $pool
         * @param string       $key
         * @param mixed        $data
         * @param integer|null $ttl
         *
         * @return mixed
         */
        public static function set($pool, $key, $data, $ttl=null) {
            return neoform\memcache::instance($pool)->set(
                $key,
                $data,
                $ttl
            );
        }

        /**
         * Fetch multiple rows from memcached
         *
         * @param string $pool
         * @param array  $keys
         *
         * @return array
         */
        public static function get_multi($pool, array $keys) {
            $mc_results = neoform\memcache::instance($pool)->getMulti($keys);
            $results = [];
            foreach ($keys as $k => $key) {
                if (array_key_exists($key, $mc_results)) {
                    $results[$k] = $mc_results[$key];
                }
            }
            return $results;
        }

        /**
         * Set multiple records at the same time
         *
         * @param string       $pool
         * @param array        $rows
         * @param integer|null $ttl
         *
         * @return mixed
         */
        public static function set_multi($pool, array $rows, $ttl=null) {
            return neoform\memcache::instance($pool)->setMulti($rows, $ttl);
        }

        /**
         * Delete a single record
         *
         * @param string $pool
         * @param string $key
         */
        public static function delete($pool, $key) {
            neoform\memcache::instance($pool)->delete($key);
        }

        /**
         * Delete multiple entries from cache
         *
         * @param string $pool
         * @param array  $keys
         */
        public static function delete_multi($pool, array $keys) {
            if ($keys) {
                $mc = neoform\memcache::instance($pool);
                foreach ($keys as $key) {
                    $mc->delete($key);
                }
            }
        }

        /**
         * Delete a single record
         *
         * @param string  $pool
         * @param string  $key
         * @param integer $ttl how many seconds left for this key to live - if not set, it will expire now
         */
        public static function expire($pool, $key, $ttl=0) {
            if ($ttl === 0) {
                neoform\memcache::instance($pool)->delete($key);
            } else {
                neoform\memcache::instance($pool)->touch($key, $ttl);
            }
        }

        /**
         * Delete multiple entries from cache
         *
         * @param string  $pool
         * @param array   $keys
         * @param integer $ttl how many seconds left for this key to live - if not set, it will expire now
         */
        public static function expire_multi($pool, array $keys, $ttl=0) {
            if ($keys) {
                $mc = neoform\memcache::instance($pool);
                if ($ttl === 0) {
                    foreach ($keys as $key) {
                        $mc->delete($key);
                    }
                } else {
                    foreach ($keys as $key) {
                        $mc->touch($key, $ttl);
                    }
                }
            }
        }
    }