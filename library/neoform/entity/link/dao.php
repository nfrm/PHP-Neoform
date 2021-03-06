<?php

    namespace neoform\entity\link;

    use neoform\entity;
    use neoform\cache;

    /**
     * Link DAO Standard database access, for accessing tables that do not have a single primary key but instead a
     * composite key (2 column PK) that link two other tables together.
     *
     * It is strongly discouraged to include any other fields in this record type, as it breaks the convention of a
     * linking table. If you must have a linking record with additional fields, use a record entity instead.
     */
    abstract class dao extends entity\dao {

        /**
         * Build a cache key used by the cache\lib by combining the dao class name, the cache key and the variables found in the $params
         *
         * @param string       $cache_key_name word used to identify this cache entry, it should be unique to the dao class its found in
         * @param string       $select_field
         * @param array        $order_by       optional - array of order bys
         * @param integer      $offset         what starting position to get records from
         * @param integer|null $limit          how many records to select
         * @param array        $fieldvals      optional - array of table keys and their values being looked up in the table
         *
         * @return string a cache key that is unqiue to the application
         */
        final protected static function _build_key_limit($cache_key_name, $select_field, array $order_by, $offset=null,
                                                         $limit=null, array $fieldvals=[]) {
            ksort($order_by);

            // each key is namespaced with the name of the class, then the name of the function ($cache_key_name)
            $param_count = count($fieldvals);
            if ($param_count === 1) {
                return static::CACHE_KEY . ":{$cache_key_name}:{$select_field}:{$offset},{$limit}:" .
                       md5(json_encode($order_by)) . ':' . md5(reset($fieldvals));
            } else if ($param_count === 0) {
                return static::CACHE_KEY . ":{$cache_key_name}:{$select_field}:{$offset},{$limit}:" .
                       md5(json_encode($order_by)) . ':';
            } else {
                ksort($fieldvals);
                foreach ($fieldvals as & $val) {
                    $val = base64_encode($val);
                }
                // Use only the array_values() and not the named array, since each $cache_key_name is unique per function
                return static::CACHE_KEY . ":{$cache_key_name}:{$select_field}:{$offset},{$limit}:" .
                       md5(json_encode($order_by)) . ':' . md5(json_encode(array_values($fieldvals)));
            }
        }

        /**
         * Gets fields that match the $keys, this gets the columns specified by $select_fields
         *
         * @param string $cache_key_name word used to identify this cache entry, it should be unique to the dao class its found in
         * @param array  $select_fields  array of table fields (table columns) to be selected
         * @param array  $fieldvals      array of table keys and their values being looked up in the table
         * @param array  $order_by
         * @param null   $offset
         * @param null   $limit
         *
         * @return array  array of records from cache
         * @throws entity\exception
         */
        final protected function _by_fields($cache_key_name, array $select_fields, array $fieldvals, array $order_by=null,
                                           $offset=null, $limit=null) {

            if ($order_by) {
                $select_field = reset($select_fields);
                $limit        = $limit === null ? null : (int) $limit;
                $offset       = $offset === null ? null : (int) $offset;

                if (! isset($this->referenced_entities[$select_field])) {
                    throw new entity\exception("Unknown foreign key field \"{$select_field}\" in " . $this::ENTITY_NAME . '.');
                }

                $foreign_dao = entity::dao($this->referenced_entities[$select_field]);

                $cache_key = self::_build_key_limit(
                    $cache_key_name,
                    $select_field,
                    $order_by,
                    $offset,
                    $limit,
                    $fieldvals
                );

                return cache\lib::single(
                    $this->cache_engine,
                    $this->cache_engine_pool_read,
                    $this->cache_engine_pool_write,
                    $cache_key,
                    function() use ($select_field, $fieldvals, $foreign_dao, $order_by, $offset, $limit) {
                        $source_driver = "\\neoform\\entity\\link\\driver\\{$this->source_engine}";
                        return $source_driver::by_fields_limit(
                            $this,
                            $this->source_engine_pool_read,
                            $select_field,
                            $foreign_dao,
                            $fieldvals,
                            $order_by,
                            $offset,
                            $limit
                        );
                    },
                    function($cache_key, $results) use ($select_field, $fieldvals, $order_by, $foreign_dao) {

                        // The PKs found in this result set must also be put in meta cache to handle record deletion/updates
                        if (array_key_exists($select_field, $fieldvals)) {
                            $fieldvals[$select_field] = array_unique(array_merge(
                                is_array($fieldvals[$select_field]) ? $fieldvals[$select_field] : [ $fieldvals[$select_field] ],
                                $results
                            ));
                        } else {
                            $fieldvals[$select_field] = $results;
                        }

                        // Local DAO
                        $this->_set_meta_cache($cache_key, $fieldvals, [ $select_field ]);

                        // Foreign DAO
                        $order_by[$foreign_dao::PRIMARY_KEY] = true; // add primary key to the list of fields
                        $foreign_dao->_set_meta_cache($cache_key, null, array_keys($order_by));
                    }
                );
            } else {
                return cache\lib::single(
                    $this->cache_engine,
                    $this->cache_engine_pool_read,
                    $this->cache_engine_pool_write,
                    parent::_build_key($cache_key_name, $fieldvals),
                    function() use ($select_fields, $fieldvals) {
                        $source_driver = "\\neoform\\entity\\link\\driver\\{$this->source_engine}";
                        return $source_driver::by_fields(
                            $this,
                            $this->source_engine_pool_read,
                            $select_fields,
                            $fieldvals
                        );
                    },
                    function($cache_key, $results) use ($select_fields, $fieldvals) {

                        /**
                         * In order to be more efficient, we do not want just clear all cache associated with $select_fields
                         * but instead, the data that has been returned from source. This means less cache busting.
                         */
                        foreach ($select_fields as $select_field) {
                            if (array_key_exists($select_field, $fieldvals)) {
                                $fieldvals[$select_field] = array_unique(array_merge(
                                    is_array($fieldvals[$select_field]) ? $fieldvals[$select_field] : [ $fieldvals[$select_field] ],
                                    array_column($results, $select_field)
                                ));
                            } else {
                                $fieldvals[$select_field] = array_column($results, $select_field);
                            }
                        }

                        $this->_set_meta_cache($cache_key, $fieldvals);
                    }
                );
            }
        }

        /**
         * Gets the ids of more than one set of key values
         *
         * @param string       $cache_key_name word used to identify this cache entry, it should be unique to the dao class its found in
         * @param array        $select_fields  array of table fields (table columns) to be selected
         * @param array        $keys_arr       array of arrays of table keys and their values being looked up in the table - each sub-array must have matching keys
         * @param array        $order_by       fields in the foreign table - key = field, val = order direction
         * @param integer|null $offset
         * @param integer|null $limit
         *
         * @return array  ids of records from cache
         * @throws entity\exception
         */
        final protected function _by_fields_multi($cache_key_name, array $select_fields, array $keys_arr, array $order_by=null,
                                                  $offset=null, $limit=null) {
            if ($order_by) {
                // Limit ranges only work on single keys
                $select_field = reset($select_fields);
                $limit        = $limit === null ? null : (int) $limit;
                $offset       = $offset === null ? null : (int) $offset;

                if (! isset($this->referenced_entities[$select_field])) {
                    throw new entity\exception("Unknown foreign key field \"{$select_field}\" in " . $this::ENTITY_NAME . '.');
                }

                $foreign_dao = entity::dao($this->referenced_entities[$select_field]);

                return cache\lib::multi(
                    $this->cache_engine,
                    $this->cache_engine_pool_read,
                    $this->cache_engine_pool_write,
                    $keys_arr,
                    function($fieldvals) use ($cache_key_name, $select_field, $order_by, $limit, $offset) {
                        return $this::_build_key_limit(
                            $cache_key_name,
                            $select_field,
                            $order_by,
                            $offset,
                            $limit,
                            $fieldvals
                        );
                    },
                    function($fieldvals_arr) use ($select_field, $foreign_dao, $order_by, $offset, $limit) {
                        $source_driver = "\\neoform\\entity\\link\\driver\\{$this->source_engine}";
                        return $source_driver::by_fields_limit_multi(
                            $this,
                            $this->source_engine_pool_read,
                            $select_field,
                            $foreign_dao,
                            $fieldvals_arr,
                            $order_by,
                            $offset,
                            $limit
                        );
                    },
                    function(array $cache_keys, array $fieldvals_arr, array $results_arr) use ($select_field, $order_by, $foreign_dao) {

                        $cache_keys_fieldvals = [];
                        foreach ($cache_keys as $k => $cache_key) {

                            // The PKs found in this result set must also be put in meta cache to handle record deletion/updates
                            $fieldvals = & $fieldvals_arr[$k];

                            if (array_key_exists($select_field, $fieldvals)) {
                                $fieldvals[$select_field] = array_unique(array_merge(
                                    is_array($fieldvals[$select_field]) ? $fieldvals[$select_field] : [ $fieldvals[$select_field] ],
                                    array_column($results_arr[$k], $select_field)
                                ));
                            } else {
                                $fieldvals[$select_field] = array_column($results_arr[$k], $select_field);
                            }

                            $cache_keys_fieldvals[$cache_key] = $fieldvals;
                        }

                        // Local DAO
                        $this->_set_meta_cache_multi($cache_keys_fieldvals, [ $select_field ]);

                        // Foreign DAO
                        $order_by[$foreign_dao::PRIMARY_KEY] = true; // add primary key to the list of fields
                        $foreign_dao->_set_meta_cache_multi(array_flip($cache_keys), array_keys($order_by));
                    }
                );
            } else {
                return cache\lib::multi(
                    $this->cache_engine,
                    $this->cache_engine_pool_read,
                    $this->cache_engine_pool_write,
                    $keys_arr,
                    function($fieldvals) use ($cache_key_name) {
                        return $this::_build_key($cache_key_name, $fieldvals);
                    },
                    function($fieldvals_arr) use ($select_fields) {
                        $source_driver = "\\neoform\\entity\\link\\driver\\{$this->source_engine}";
                        return $source_driver::by_fields_multi($this, $this->source_engine_pool_read, $select_fields, $fieldvals_arr);
                    },
                    function(array $cache_keys, array $fieldvals_arr, array $results_arr) use ($select_fields) {

                        $cache_keys_fieldvals = [];
                        foreach ($cache_keys as $k => $cache_key) {

                            /**
                             * In order to be more efficient, we do not want just clear all cache associated with $select_fields
                             * but instead, the data that has been returned from source. This means less cache busting.
                             */
                            $fieldvals = & $fieldvals_arr[$k];

                            foreach ($select_fields as $select_field) {
                                if (array_key_exists($select_field, $fieldvals)) {
                                    $fieldvals[$select_field] = array_unique(array_merge(
                                        is_array($fieldvals[$select_field]) ? $fieldvals[$select_field] : [ $fieldvals[$select_field] ],
                                        array_column($results_arr[$k], $select_field)
                                    ));
                                } else {
                                    $fieldvals[$select_field] = array_column($results_arr[$k], $select_field);
                                }
                            }

                            $cache_keys_fieldvals[$cache_key] = $fieldvals;
                        }

                        $this->_set_meta_cache_multi($cache_keys_fieldvals, $select_fields);
                    }
                );
            }
        }

        /**
         * Get a record count
         *
         * @param array|null $fieldvals
         *
         * @return integer
         */
        public function count(array $fieldvals=null) {

            if ($fieldvals) {
                $this->bind_fields($fieldvals);
            }

            return cache\lib::single(
                $this->cache_engine,
                $this->cache_engine_pool_read,
                $this->cache_engine_pool_write,
                parent::_build_key(parent::COUNT, $fieldvals ?: []),
                function() use ($fieldvals) {
                    $source_driver = "\\neoform\\entity\\link\\driver\\{$this->source_engine}";
                    return $source_driver::count($this, $this->source_engine_pool_read, $fieldvals);
                },
                function($cache_key) use ($fieldvals) {
                    $this->_set_meta_cache($cache_key, $fieldvals);
                }
            );
        }

        /**
         * Get multiple record count
         *
         * @param array $fieldvals_arr
         *
         * @return array
         */
        public function count_multi(array $fieldvals_arr) {

            foreach ($fieldvals_arr as $fieldvals) {
                $this->bind_fields($fieldvals);
            }

            return cache\lib::multi(
                $this->cache_engine,
                $this->cache_engine_pool_read,
                $this->cache_engine_pool_write,
                $fieldvals_arr,
                function($fieldvals) {
                    return $this::_build_key($this::COUNT, $fieldvals ?: []);
                },
                function(array $fieldvals_arr) {
                    $source_driver = "\\neoform\\entity\\link\\driver\\{$this->source_engine}";
                    return $source_driver::count_multi(
                        $this,
                        $this->source_engine_pool_read,
                        $fieldvals_arr
                    );
                },
                function(array $cache_keys, array $fieldvals_arr) {
                    // Can't use array_combine since the keys might not be in the same order (possibly)
                    $cache_keys_fieldvals = [];
                    foreach ($cache_keys as $k => $cache_key) {
                        $cache_keys_fieldvals[$cache_key] = $fieldvals_arr[$k];
                    }
                    $this->_set_meta_cache_multi($cache_keys_fieldvals);
                }
            );
        }

        /**
         * Inserts a linking record into the database
         *
         * @param array   $info    an associative array of into to be put info the database
         * @param boolean $replace optional - user REPLACE INTO instead of INSERT INTO
         *
         * @return boolean
         * @throws entity\exception
         */
        protected function _insert(array $info, $replace=false) {
            $source_driver = "\\neoform\\entity\\link\\driver\\{$this->source_engine}";
            try {
                $info = $source_driver::insert(
                    $this,
                    $this->source_engine_pool_write,
                    $info,
                    $replace
                );
            } catch (entity\exception $e) {
                return false;
            }

            self::_delete_meta_cache($info);

            return true;
        }

        /**
         * Inserts more than one linking record into the database at a time
         *
         * @access protected
         * @param array   $infos   an array of associative array of info to be put into the database
         * @param boolean $replace optional - user REPLACE INTO instead of INSERT INTO
         *
         * @return boolean
         * @throws entity\exception
         */
        protected function _insert_multi(array $infos, $replace=false) {
            if (! $infos) {
                return;
            }

            $source_driver = "\\neoform\\entity\\link\\driver\\{$this->source_engine}";
            try {
                $infos = $source_driver::insert_multi($this, $this->source_engine_pool_write, $infos, $replace);
            } catch (entity\exception $e) {
                return false;
            }

            self::_delete_meta_cache_multi($infos);

            return true;
        }

        /**
         * Updates linking records in the database
         *
         * @param array $new_info the new info to be put into the model
         * @param array $where    return a model of the new record
         *
         * @return boolean
         * @throws entity\exception
         */
        protected function _update(array $new_info, array $where) {
            if ($new_info) {
                $source_driver = "\\neoform\\entity\\link\\driver\\{$this->source_engine}";
                try {
                    $source_driver::update($this, $this->source_engine_pool_write, $new_info, $where);
                } catch (entity\exception $e) {
                    return false;
                }

                // Delete any cache relating to the $new_info or the $where
                self::_delete_meta_cache(array_merge_recursive($new_info, $where));

                return true;
            }

            return false;
        }

        /**
         * Delete linking records from the database
         *
         * @param array $keys the where of the query
         *
         * @return boolean
         * @throws entity\exception
         */
        protected function _delete(array $keys) {
            $source_driver = "\\neoform\\entity\\link\\driver\\{$this->source_engine}";
            try {
                $source_driver::delete($this, $this->source_engine_pool_write, $keys);
            } catch (entity\exception $e) {
                return false;
            }

            parent::_delete_meta_cache($keys);

            return true;
        }

        /**
         * Delete linking records from the database
         *
         * @param array $keys_arr arrays matching the PKs of the link
         *
         * @return boolean returns true on success
         * @throws entity\exception
         */
        protected function _delete_multi(array $keys_arr) {
            $source_driver = "\\neoform\\entity\\link\\driver\\{$this->source_engine}";
            try {
                $source_driver::delete_multi($this, $this->source_engine_pool_write, $keys_arr);
            } catch (entity\exception $e) {
                return false;
            }

            parent::_delete_meta_cache_multi($keys_arr);

            return true;
        }
    }
