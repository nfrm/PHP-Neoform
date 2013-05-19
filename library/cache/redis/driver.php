<?php

    class cache_redis_driver implements cache_driver {

        /**
         * Increment the value of a cached entry (only works if the value is an int)
         *
         * @param string  $key
         * @param string  $pool
         * @param integer $offset
         */
        public static function increment($key, $pool, $offset=1) {
            core::cache_redis($pool)->incrBy($key, $offset);
        }

        /**
         * Decrement the value of a cached entry (only works if the value is an int)
         *
         * @param string  $key
         * @param string  $pool
         * @param integer $offset
         */
        public static function decrement($key, $pool, $offset=1) {
            core::cache_redis($pool)->incrBy($key, -$offset);
        }

        /**
         * Checks if cached record exists.
         *
         * @param string $key
         * @param string $pool
         *
         * @return boolean
         */
        public static function exists($key, $pool) {
            return (bool) core::cache_redis($pool)->exists($key);
        }

        /**
         * Create a list and/or Add a value to a list
         *
         * @param string $key
         * @param string $pool
         * @param mixed  $value
         *
         * @return bool
         */
        public static function list_add($key, $pool, $value) {
            if ($return = core::cache_redis($pool)->sAdd($key, $value)) {
                return $return;
            } else {
                // if for some reason this key is holding a non-list delete it and create a list (this should never happen)
                // this is here just for fault tolerance
                $redis = core::cache_redis($pool);
                $redis->delete($key);
                return $redis->sAdd($key, $value);
            }
        }

        /**
         * Get all members of a list or get matching members of a list
         *
         * @param string $key
         * @param string $pool
         * @param array  $filter list of keys, an intersection is done
         *
         * @return mixed
         */
        public static function list_get($key, $pool, array $filter = null) {
            if ($filter) {
                return array_values(array_intersect(core::cache_redis($pool)->sMembers($key), $filter));
            } else {
                return core::cache_redis($pool)->sMembers($key);
            }
        }

        /**
         * Remove values from a list
         *
         * @param string $key
         * @param string $pool
         * @param array  $remove_keys
         */
        public static function list_remove($key, $pool, array $remove_keys) {
            $redis = core::cache_redis($pool);
            // Batch execute the deletes
            $redis->multi();
            foreach ($remove_keys as $remove_key) {
                $redis->sRemove($key, $remove_key);
            }
            $redis->exec();

            // bug in the documentation makes it seem like you can delete multiple keys at the same time. Nope!
            //call_user_func_array([core::cache_redis($pool), 'sRemove'], array_merge([ $key, ], $remove_keys))
        }

        /**
         * Gets cached data.
         *  if record does exist, an array with a single element, containing the data.
         *  returns null if record does not exist
         *
         * @param string $key
         * @param string $pool
         *
         * @return array|null returns null if record does not exist.
         */
        public static function get($key, $pool) {
            $redis = core::cache_redis($pool);

            // Batch execute since phpredis returns false if the key doesn't exist on a GET command, which might actually
            // be the stored value... which is not helpful.
            $redis->multi();
            $redis->exists($key);
            $redis->get($key);
            $result = $redis->exec();

            return $result[0] === true ? [ $result[1] ] : null;
        }

        /**
         * @param string       $key
         * @param string       $pool
         * @param mixed        $data
         * @param integer|null $ttl
         *
         * @return mixed
         */
        public static function set($key, $pool, $data, $ttl=null) {
            return core::cache_redis($pool)->set($key, $data, $ttl);
        }

        /**
         * Fetch multiple rows from redis
         *
         * @param array  $keys
         * @param string $pool
         *
         * @return array
         */
        public static function get_multi(array $keys, $pool) {
            $redis   = core::cache_redis($pool);
            $results = $redis->getMultiple($keys);

            // Redis returns the results in order - if the key doesn't exist, false is returned - this problematic
            // since false might be an actual value being stored... therefore we check if the key exists if false is
            // returned
            $redis->multi();
            $exists_lookup = [];
            foreach (array_keys($keys) as $i => $k) {
                if ($results[$i] === false) {
                    $redis->exists($k);
                    $exists_lookup[] = $i;
                }
            }

            // Remove any records that don't exist from the array
            if ($exists_lookup) {
                foreach ($redis->exec() as $i => $record_exists) {
                    if (! $record_exists) {
                        unset($results[$exists_lookup[$i]]);
                    }
                }
            }

            return $results;
        }

        /**
         * Set multiple records at the same time
         *
         * @param array        $rows
         * @param string       $pool
         * @param integer|null $ttl
         *
         * @return mixed
         */
        public static function set_multi(array $rows, $pool, $ttl=null) {
            if ($ttl) {
                $redis = core::cache_redis($pool);
                $redis->multi();
                foreach ($rows as $k => $v) {
                    $redis->set($k, $v, $ttl);
                }
                $redis->exec();
            } else {
                return core::cache_redis($pool)->mset($rows);
            }
        }

        /**
         * Delete a single record
         *
         * @param string $key
         * @param string $pool
         *
         * @return integer the number of keys deleted
         */
        public static function delete($key, $pool) {
            return core::cache_redis($pool)->delete($key);
        }

        /**
         * Delete multiple entries from cache
         *
         * @param array  $keys
         * @param string $pool
         *
         * @return integer the number of keys deleted
         */
        public static function delete_multi(array $keys, $pool) {
            if (count($keys)) {
                reset($keys);
                return core::cache_redis($pool)->delete($keys);
            }
        }
    }