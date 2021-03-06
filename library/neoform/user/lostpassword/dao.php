<?php

    namespace neoform\user\lostpassword;

    /**
     * User Lostpassword DAO
     */
    class dao extends \neoform\entity\record\dao implements definition {

        const BY_USER = 'by_user';

        /**
         * $var array $field_bindings list of fields and their corresponding bindings
         *
         * @return array
         */
        protected $field_bindings = [
            'hash'      => self::TYPE_STRING,
            'user_id'   => self::TYPE_INTEGER,
            'posted_on' => self::TYPE_STRING,
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
         * Get User Lostpassword hashs by user
         *
         * @param int $user_id
         *
         * @return array of User Lostpassword hashs
         */
        public function by_user($user_id) {
            return parent::_by_fields(
                self::BY_USER,
                [
                    'user_id' => (int) $user_id,
                ]
            );
        }

        /**
         * Get multiple sets of User Lostpassword hashs by user
         *
         * @param \neoform\user\collection|array $user_list
         * @param array $order_by array of field names (as the key) and sort direction (parent::SORT_ASC, parent::SORT_DESC)
         * @param integer|null $offset get PKs starting at this offset
         * @param integer|null $limit max number of PKs to return
         *
         * @return array of arrays containing User Lostpassword hashs
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
         * Insert User Lostpassword record, created from an array of $info
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
         * Insert multiple User Lostpassword records, created from an array of arrays of $info
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
         * Updates a User Lostpassword record with new data
         *   only fields that are specified in the $info array will be written
         *
         * @param model $user_lostpassword record to be updated
         * @param array $info data to write to the record
         *
         * @return model updated model
         */
        public function update(model $user_lostpassword, array $info) {

            // Update record
            return parent::_update($user_lostpassword, $info);
        }

        /**
         * Delete a User Lostpassword record
         *
         * @param model $user_lostpassword record to be deleted
         *
         * @return bool
         */
        public function delete(model $user_lostpassword) {

            // Delete record
            return parent::_delete($user_lostpassword);
        }

        /**
         * Delete multiple User Lostpassword records
         *
         * @param collection $user_lostpassword_collection records to be deleted
         *
         * @return bool
         */
        public function delete_multi(collection $user_lostpassword_collection) {

            // Delete records
            return parent::_delete_multi($user_lostpassword_collection);
        }
    }
