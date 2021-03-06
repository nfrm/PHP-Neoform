<?php

    namespace neoform\user\hashmethod;

    use neoform\entity;

    /**
     * User Hashmethod Model
     *
     * @var int $id
     * @var string $name
     */
    class model extends entity\record\model implements definition {

        public function __get($k) {

            if (isset($this->vars[$k])) {
                switch ($k) {
                    // integers
                    case 'id':
                        return (int) $this->vars[$k];

                    // strings
                    case 'name':
                        return (string) $this->vars[$k];

                    default:
                        return $this->vars[$k];
                }
            }
        }

        /**
         * User Collection
         *
         * @param array|null   $order_by array of field names (as the key) and sort direction (entity_record_dao::SORT_ASC, entity_record_dao::SORT_DESC)
         * @param integer|null $offset get PKs starting at this offset
         * @param integer|null $limit max number of PKs to return
         *
         * @return \neoform\user\collection
         */
        public function user_collection(array $order_by=null, $offset=null, $limit=null) {
            $key = self::_limit_var_key('user_collection', $order_by, $offset, $limit);
            if (! array_key_exists($key, $this->_vars)) {
                $this->_vars[$key] = new \neoform\user\collection(
                    entity::dao('user')->by_password_hashmethod($this->vars['id'], $order_by, $offset, $limit)
                );
            }
            return $this->_vars[$key];
        }

        /**
         * User Count
         *
         * @return integer
         */
        public function user_count() {
            $fieldvals = [
                'password_hashmethod' => (int) $this->vars['id'],
            ];

            $key = parent::_count_var_key('user_count', $fieldvals);
            if (! array_key_exists($key, $this->_vars)) {
                $this->_vars[$key] = entity::dao('user')->count($fieldvals);
            }
            return $this->_vars[$key];
        }

        /**
         * Hashes a password, with salt given a certain cost value
         *
         * @param string        $password
         * @param binary|string $salt
         * @param integer       $cost
         *
         * @return binary|string
         * @throws \neoform\user\exception
         */
        public function hash($password, $salt, $cost) {
            if (($cost = (int) $cost) < 1) {
                throw new \neoform\user\exception('Password hash cost must be at least 1');
            }

            // Seems pointless to make an object here, except PHP doesn't allow abstract static functions, weak
            $hashmethod = "\\neoform\\user\\hashmethod\\driver\\{$this->name}";
            $hashmethod = new $hashmethod;
            return $hashmethod->hash($password, $salt, $cost);
        }
    }
