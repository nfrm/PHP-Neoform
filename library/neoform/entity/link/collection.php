<?php

    namespace neoform\entity\link;

    use neoform\entity\exception;

    /**
     * Link Collection
     */
    class collection extends \arrayobject {

        protected $_vars = []; //caching
        protected $type;

        /**
         * @param array|null  $infos
         * @param string|null $map_field
         */
        public function __construct(array $infos=null, $map_field=null) {

            if (count($infos)) {
                $model = '\\neoform\\' . static::ENTITY_NAME . '\\model';
                $current = current($infos);
                if (is_array($current)) {
                    $this->type = 'model';
                } else if ($current instanceof $model) {
                    $this->type = 'object';
                } else {
                    $this->type = 'value';
                }

                foreach ($infos as $key => $info) {
                    if ($this->type === 'model') {
                        try {
                            $v = new $model($info);
                        } catch (exception $e) {
                        }
                    } else if ($this->type === 'object') {
                        $v = $info;
                    } else {
                        $v = $info === null ? null : (int) $info;
                    }

                    if ($map_field !== null) {
                        $this[$info[$map_field]] = $v;
                    } else {
                        $this[(int) $key] = $v;
                    }
                }
            }
        }

        /**
         * @param array       $info
         * @param string|null $map_field
         *
         * @return collection
         */
        public function add(array $info, $map_field=null) {
            if ($this->type === 'model') {
                $model = '\\neoform\\' . static::ENTITY_NAME . '\\model';
                $v = new $model($info);
            } else if ($this->type === 'object') {
                $v = $info;
            } else {
                $v = $info === null ? null : $info;
            }

            if ($map_field !== null) {
                $this[$info[$map_field]] = $v;
            } else {
                $this[] = $v;
            }

            //reset
            $this->_vars = [];

            return $this;
        }

        /**
         * Delete row
         *
         * @param $k Key
         *
         * @return collection
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
         *
         * @return collection
         * @throws exception
         */
        public function remap($field) {
            if ($this->type !== 'model' || $this->type !== 'object') {
                throw new exception('You cannot remap a value based collection');
            }

            $new = [];
            foreach ($this as $record) {
                $new[$record->$field] = $record;
            }
            $this->exchangeArray($new);
            return $this;
        }

        /**
         * Get an array containing the values of all rows, populated by the specified field
         *
         * @param string $field
         *
         * @return array
         * @throws exception
         */
        public function field($field) {
            if ($this->type !== 'model' || $this->type !== 'object') {
                throw new exception('You cannot get a field from a value based collection');
            }

            if (! array_key_exists($field, $this->_vars)) {
                $this->_vars[$field] = [];
                foreach ($this as $record) {
                    $this->_vars[$field][] = $record->$field;
                }
            }
            return $this->_vars[$field];
        }

        /**
         * Sort the collection
         *
         * @param callable|string $f
         * @param string $order
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
        }
    }