<?php

    namespace neoform\cache\driver;

    use neoform;

    class redis implements neoform\cache\driver {

        /**
         * Activate a pipelined (batch) query
         *
         * @param string $pool
         */
        public static function pipeline_start($pool) {
            neoform\redis::instance($pool)->multi();
        }

        /**
         * Execute pipelined (batch) queries and return result
         *
         * @param string $pool
         *
         * @return array result of batch operation
         */
        public static function pipeline_execute($pool) {
            return neoform\redis::instance($pool)->exec();
        }

        /**
         * Increment the value of a cached entry (only works if the value is an int)
         *
         * @param string  $pool
         * @param string  $key
         * @param integer $offset
         */
        public static function increment($pool, $key, $offset=1) {
            neoform\redis::instance($pool)->incrBy($key, $offset);
        }

        /**
         * Decrement the value of a cached entry (only works if the value is an int)
         *
         * @param string  $pool
         * @param string  $key
         * @param integer $offset
         */
        public static function decrement($pool, $key, $offset=1) {
            neoform\redis::instance($pool)->incrBy($key, -$offset);
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
            return (bool) neoform\redis::instance($pool)->exists($key);
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

            // Batch execute since phpredis returns false if the key doesn't exist on a GET command, which might actually
            // be the stored value... which is not helpful.
            $result = neoform\redis::instance($pool)
                ->multi()
                ->exists($key)
                ->get($key)
                ->exec();

            return $result[0] === true ? [ $result[1] ] : null;
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
            // Due to a bug that was introduced in phpredis 2.2.4 (fixed in 2.2.5), the 3rd param is not included
            // when not used
            if ($ttl) {
                return neoform\redis::instance($pool)->set($key, $data, $ttl);
            } else {
                return neoform\redis::instance($pool)->set($key, $data);
            }
        }

        /**
         * Fetch multiple rows from redis
         *
         * @param string $pool
         * @param array  $keys
         *
         * @return array
         */
        public static function get_multi($pool, array $keys) {
            $redis = neoform\redis::instance($pool)->multi();

            // Redis returns the results in order - if the key doesn't exist, false is returned - this problematic
            // since false might be an actual value being stored... therefore we check if the key exists if false is
            // returned

            foreach ($keys as $key) {
                $redis->exists($key);
                $redis->get($key);
            }

            $results       = [];
            $redis_results = $redis->exec();
            $i             = 0;
            foreach ($keys as $k => $key) {
                if ($redis_results[$i]) {
                    $results[$k] = $redis_results[$i + 1];
                }

                $i += 2;
            }

            return $results;
        }

        /**
         * Set multiple records at the same time
         *
         * It is recommended that this function be wrapped in pipeline_start() and pipeline_execute();
         *
         * @param string       $pool
         * @param array        $rows
         * @param integer|null $ttl
         *
         * @return mixed
         */
        public static function set_multi($pool, array $rows, $ttl=null) {
            if ($ttl) {
                $redis = neoform\redis::instance($pool);
                $redis->multi();
                foreach ($rows as $k => $v) {
                    $redis->set($k, $v, $ttl);
                }
                $redis->exec();
            } else {
                return neoform\redis::instance($pool)->mset($rows);
            }
        }

        /**
         * Delete a single record
         *
         * @param string $pool
         * @param string $key
         *
         * @return integer the number of keys deleted
         */
        public static function delete($pool, $key) {
            return neoform\redis::instance($pool)->delete($key);
        }

        /**
         * Delete multiple entries from cache
         *
         * @param string $pool
         * @param array  $keys
         *
         * @return integer the number of keys deleted
         */
        public static function delete_multi($pool, array $keys) {
            if ($keys) {
                return neoform\redis::instance($pool)->delete($keys);
            }
        }

        /**
         * Expire a single record
         *
         * @param string  $pool
         * @param string  $key
         * @param integer $ttl how many seconds left for this key to live - if not set, it will expire now
         *
         * @return integer the number of keys deleted
         */
        public static function expire($pool, $key, $ttl=0) {
            if ($ttl) {
                return neoform\redis::instance($pool)->expire($key, $ttl);
            } else {
                return neoform\redis::instance($pool)->delete($key);
            }
        }

        /**
         * Expire multiple entries
         *
         * @param string  $pool
         * @param array   $keys
         * @param integer $ttl how many seconds left for this key to live - if not set, it will expire now
         *
         * @return integer the number of keys deleted
         */
        public static function expire_multi($pool, array $keys, $ttl=0) {
            if ($ttl) {
                $redis = neoform\redis::instance($pool)->multi();
                foreach ($keys as $key) {
                    $redis->expire($key, $ttl);
                }
                $redis->exec();
            } else {
                neoform\redis::instance($pool)->delete($keys, $ttl);
            }
        }
    }