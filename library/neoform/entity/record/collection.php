<?php

    namespace neoform\entity\record;

    use neoform\entity\exception;
    use neoform\entity;

    /**
     * Record Collection
     */
    class collection extends \arrayobject {

        /**
         * @var array locally cached data
         */
        protected $_vars = []; //caching

        /**
         * Construct
         *
         * @param array       $pks       Primary keys
         * @param array|model $infos     Preloaded data array
         * @param string|null $map_field Assign collection keys based on this field (taken from the models)
         */
        public function __construct(array $pks=null, array $infos=null, $map_field=null) {

            if ($pks !== null) {
                $infos = entity::dao(static::ENTITY_NAME)->records($pks);
            }

            if ($infos !== null && $infos) {
                $model = '\\neoform\\' . static::ENTITY_NAME . '\\model';
                foreach ($infos as $key => $info) {
                    try {
                        if (is_array($info)) {
                            $this[$map_field !== null ? $info[$map_field] : $key] = new $model(null, $info);
                        } else if ($info instanceof $model) {
                            $this[$map_field !== null ? $info->$map_field : $key] = $info;
                        }
                    } catch (exception $e) {

                    }
                }
            }
        }

        /**
         * Get a collection by a given field or fields
         * folder_collection::by_parent(5) will return a folder collection.
         * this is just a shortcut for new folder_collection(entity::dao('folder')->by_parent(5));
         *
         * @param string $name
         * @param array  $args
         *
         * @return collection
         */
        public static function __callstatic($name, array $args) {
            $collection = '\\neoform\\' . static::ENTITY_NAME . '\\collection';
            if ($name === 'all' || $name === 'records') {
                return new $collection(null, call_user_func_array([entity::dao(static::ENTITY_NAME), $name], $args));
            } else {
                return new $collection(call_user_func_array([entity::dao(static::ENTITY_NAME), $name], $args));
            }
        }

        /**
         * Add a model to the collection
         *
         * @param array|model $info
         * @param string|null $map_field Assign collection keys based on this field (taken from the models)
         *
         * @return collection $this
         */
        public function add($info, $map_field=null) {
            $model = '\\neoform\\' . static::ENTITY_NAME . '\\model';
            if ($info instanceof $model) {
                $v = $info;
            } else if (is_array($info)) {
                $v = new $model(null, $info);
            } else {
                $v = new $model($info);
            }

            if ($map_field !== null) {
                $this[$v->$map_field] = $v;
            } else {
                $this[] = $v;
            }

            //reset
            $this->_vars = [];

            return $this;
        }

        /**
         * Remove a model from the collection
         *
         * @param mixed $k Key
         *
         * @return collection $this
         */
        public function del($k) {
            unset($this[$k]);

            //reset
            $this->_vars = [];

            return $this;
        }

        /**
         * Remap the collection according to a certain field - this makes the key of the collection be that field
         *
         * @param string $field
         * @param bool   $ignore_null
         *
         * @return collection $this
         */
        public function remap($field, $ignore_null=false) {
            $new = [];
            if ($ignore_null) {
                foreach ($this as $record) {
                    if ($record->$field !== null) {
                        $new[$record->$field] = $record;
                    }
                }
            } else {
                foreach ($this as $record) {
                    $new[$record->$field] = $record;
                }
            }
            $this->exchangeArray($new);
            return $this;
        }

        /**
         * Get an array with the values of the models in the collection
         *
         * @param string      $field
         * @param string|null $key
         *
         * @return array
         */
        public function field($field, $key=null) {
            if (! array_key_exists($field, $this->_vars)) {
                $arr = [];
                foreach ($this as $k => $record) {
                    $arr[$key ? $record->$key : $k] = $record->$field;
                }
                $this->_vars[$field] = $arr;
            }
            return $this->_vars[$field];
        }

        /**
         * Exports an array with all the data from the models, or select fields
         *
         * @param array|null $fields to export
         *
         * @return array
         */
        public function export(array $fields=null) {
            $return = [];
            foreach ($this as $k => $v) {
                $return[$k] = $v->export($fields);
            }
            return $return;
        }

        /**
         * Sort the collection based on $f (function) or $f field name
         *
         * @param callable|string $f
         * @param string          $order
         *
         * @return collection
         */
        public function sort($f, $order='asc') {
            if (is_callable($f)) {
                $this->uasort(function ($a, $b) use ($f, $order) {
                    $a = $f($a);
                    $b = $f($b);

                    if ($a === $b) {
                        return 0;
                    }

                    if ($order === 'asc') {
                        return ($a < $b) ? -1 : 1;
                    } else {
                        return ($a > $b) ? -1 : 1;
                    }
                });
            } else {
                $this->uasort(function ($a, $b) use ($f, $order) {
                    $a_field = $a->$f;
                    $b_field = $b->$f;

                    if ($a_field === $b_field) {
                        return 0;
                    }

                    if ($order === 'asc') {
                        return ($a_field < $b_field) ? -1 : 1;
                    } else {
                        return ($a_field > $b_field) ? -1 : 1;
                    }
                });
            }
            return $this;
        }

        /**
         * Get many groups of records all in one shot. This greatly reduces the number of requests to the cache service,
         * which can greatly speed up an application.
         *
         * @param string       $_var_key    Key (in $model::$_var) that stores preloaded model data
         * @param string       $entity      eg, user
         * @param string       $by_function eg, by_comments
         * @param array|null   $order_by    array of field names (as the key) and sort direction (entity_record_dao::SORT_ASC, entity_record_dao::SORT_DESC)
         * @param integer|null $offset      get PKs starting at this offset
         * @param integer|null $limit       max number of PKs to return
         *
         * @return collection
         */
        protected function _preload_one_to_many($_var_key, $entity, $by_function, array $order_by=null, $offset=null,
                                                $limit=null) {

            $collection_name  = "\\neoform\\{$entity}\\collection";
            $model_name       = "\\neoform\\{$entity}\\model";
            $dao              = entity::dao($entity);
            $by_function     .= '_multi';

            // Get the ids for those
            $pks_groups = $dao->$by_function($this, $order_by, $offset, $limit);
            $pks        = [];

            // make a flat array of all keys, removing dupes along the way.
            foreach ($pks_groups as $pks_group) {
                foreach ($pks_group as $pk) {
                    $pks[$pk] = $pk;
                }
            }

            // get all the records all in one shot
            $collection = new $collection_name(null, $dao->records($pks));

            // sort flat array back into grouped data again
            foreach ($pks_groups as & $pks_group) {
                foreach ($pks_group as $k => $pk) {
                    if (isset($collection[$pk])) {
                        $pks_group[$k] = $collection[$pk];
                    } else {
                        // this shouldn't actually happen... if it did, something went wrong in the DAO
                        unset($pks_group[$k]);
                    }
                }
            }

            // load the child models into the $_var of each model in this collection
            foreach ($this as $key => $model) {
                if (isset($pks_groups[$key])) {
                    $model->_set_var(
                        $model_name::_limit_var_key(
                            $_var_key,
                            $order_by,
                            $offset,
                            $limit
                        ),
                        new $collection_name(null, $pks_groups[$key])
                    );
                }
            }
            return $collection;
        }

        /**
         * Get many groups of records all in one shot. This greatly reduces the number of requests to the cache service,
         * which can greatly speed up an application.
         *
         * @param string       $_var_key       Key (in $model::$_var) that stores preloaded model data
         * @param string       $entity         eg, user_permission
         * @param string       $by_function    eg, by_user
         * @param string       $foreign_type   eg, permission
         * @param array|null   $order_by       array of field names (as the key) and sort direction (entity_record_dao::SORT_ASC, entity_record_dao::SORT_DESC)
         * @param integer|null $offset         get PKs starting at this offset
         * @param integer|null $limit          max number of PKs to return
         *
         * @return collection
         */
        protected function _preload_many_to_many($_var_key, $entity, $by_function, $foreign_type,
                                                 array $order_by=null, $offset=null, $limit=null) {

            $by_function             .= '_multi';
            $foreign_collection_name  = "\\neoform\\{$foreign_type}\\collection";

            // Get the ids for those
            $pks_groups = entity::dao($entity)->$by_function($this, $order_by, $offset, $limit);
            $pks        = [];

            // make a flat array of all keys, removing dupes along the way.
            foreach ($pks_groups as $pks_group) {
                foreach ($pks_group as $pk) {
                    $pks[$pk] = $pk;
                }
            }

            // get all the records all in one shot
            $collection = new $foreign_collection_name(null, entity::dao($foreign_type)->records($pks));

            // sort flat array back into grouped data again
            foreach ($pks_groups as & $pks_group) {
                foreach ($pks_group as $k => $pk) {
                    if (isset($collection[$pk])) {
                        $pks_group[$k] = $collection[$pk];
                    } else {
                        // this shouldn't actually happen... if it did, something went wrong in the DAO
                        unset($pks_group[$k]);
                    }
                }
            }

            foreach ($this as $key => $model) {
                if (isset($pks_groups[$key])) {
                    $model->_set_var(
                        $_var_key,
                        new $foreign_collection_name(null, $pks_groups[$key])
                    );
                }
            }
            return $collection;
        }

        /**
         * Get many groups of records all in one shot. This greatly reduces the number of requests to the cache service,
         * which can greatly speed up an application.
         *
         * @param string $_var_key Key (in $model::$_var) that stores preloaded model data
         * @param string $entity   Name of the entity
         * @param string $field    Corresponding field for that entity
         *
         * @return collection
         */
        protected function _preload_one_to_one($_var_key, $entity, $field) {

            $model_name      = "\\neoform\\{$entity}\\model";
            $collection_name = "\\neoform\\{$entity}\\collection";

            $pks = [];
            // we don't want to look up duplicates, just unique values
            foreach ($this as $model) {
                $pks[$model->$field] = $model->$field;
            }
            $infos  = entity::dao($entity)->records($pks);
            $models = [];

            foreach ($this as $key => $model) {
                $k = $model->$field;
                if (isset($infos[$k])) {
                    $model->_set_var(
                        $_var_key,
                        $models[$key] = new $model_name(null, $infos[$k])
                    );
                }
            }

            return new $collection_name(null, $models);
        }

        /**
         * Preload record/link counts based on fields
         *
         * @param string $_var_key
         * @param string $entity
         * @param string $foreign_field_name field name used to filter the dao count
         *
         * @return collection
         */
        protected function _preload_counts($_var_key, $entity, $foreign_field_name) {

            $pk = static::PRIMARY_KEY;

            // Build list of keyvals to filter the counts
            $fieldvals = [];
            foreach ($this as $k => $model) {
                $fieldvals[$k] = [ $foreign_field_name => $model->$pk, ];
            }

            // Get the counts (as a multi get)
            $counts = entity::dao($entity)->count_multi($fieldvals);

            // Insert the counts into each model's $_var cache
            foreach ($this as $k => $model) {
                $model->_set_var(
                    $model::_count_var_key(
                        $_var_key,
                        $fieldvals[$k]
                    ),
                    $counts[$k]
                );
            }

            // Return the counts
            return $counts;
        }
    }


