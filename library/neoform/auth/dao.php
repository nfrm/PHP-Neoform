<?php

    namespace neoform\auth;

    /**
     * Auth DAO
     */
    class dao extends \neoform\entity\record\dao implements definition {

        const BY_USER = 'by_user';

        /**
         * $var array $field_bindings list of fields and their corresponding bindings
         *
         * @return array
         */
        protected $field_bindings = [
            'hash'       => self::TYPE_BINARY,
            'user_id'    => self::TYPE_INTEGER,
            'expires_on' => self::TYPE_STRING,
        ];

        /**
         * $var array $referenced_entities list of fields (in this entity) and their related foreign entity
         *
         * @return array
         */
        protected $referenced_entities = [
            'user_id' => 'user',
        ];

        // READS

        /**
         * Get Auth hashs by user
         *
         * @param int $user_id
         * @param array $order_by array of field names (as the key) and sort direction (parent::SORT_ASC, parent::SORT_DESC)
         * @param integer|null $offset get PKs starting at this offset
         * @param integer|null $limit max number of PKs to return
         *
         * @return array of Auth hashs
         */
        public function by_user($user_id, array $order_by=null, $offset=null, $limit=null) {
            return parent::_by_fields(
                self::BY_USER,
                [
                    'user_id' => (int) $user_id,
                ],
                $order_by,
                $offset,
                $limit
            );
        }

        /**
         * Get multiple sets of Auth hashs by user
         *
         * @param \neoform\user\collection|array $user_list
         * @param array $order_by array of field names (as the key) and sort direction (parent::SORT_ASC, parent::SORT_DESC)
         * @param integer|null $offset get PKs starting at this offset
         * @param integer|null $limit max number of PKs to return
         *
         * @return array of arrays containing Auth hashs
         */
        public function by_user_multi($user_list, array $order_by=null, $offset=null, $limit=null) {
            $keys = [];
            if ($user_list instanceof \neoform\user\collection) {
                foreach ($user_list as $k => $user) {
                    $keys[$k] = [
                        'user_id' => (int) $user->id,
                    ];
                }
            } else {
                foreach ($user_list as $k => $user) {
                    $keys[$k] = [
                        'user_id' => (int) $user,
                    ];
                }
            }
            return parent::_by_fields_multi(
                self::BY_USER,
                $keys,
                $order_by,
                $offset,
                $limit
            );
        }

        // WRITES

        /**
         * Insert Auth record, created from an array of $info
         *
         * @param array $info associative array, keys matching columns in database for this entity
         *
         * @return model
         */
        public function insert(array $info) {

            // Insert record
            return parent::_insert($info);
        }

        /**
         * Insert multiple Auth records, created from an array of arrays of $info
         *
         * @param array $infos array of associative arrays, keys matching columns in database for this entity
         *
         * @return collection
         */
        public function insert_multi(array $infos) {

            // Insert record
            return parent::_insert_multi($infos);
        }

        /**
         * Updates a Auth record with new data
         *   only fields that are specified in the $info array will be written
         *
         * @param model $auth record to be updated
         * @param array $info data to write to the record
         *
         * @return model updated model
         */
        public function update(model $auth, array $info) {

            // Update record
            return parent::_update($auth, $info);
        }

        /**
         * Delete a Auth record
         *
         * @param model $auth record to be deleted
         *
         * @return bool
         */
        public function delete(model $auth) {

            // Delete record
            return parent::_delete($auth);
        }

        /**
         * Delete multiple Auth records
         *
         * @param collection $auth_collection records to be deleted
         *
         * @return bool
         */
        public function delete_multi(collection $auth_collection) {

            // Delete records
            return parent::_delete_multi($auth_collection);
        }
    }
