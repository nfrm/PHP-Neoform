<?php

    namespace neoform\locale\key\message;

    /**
     * Locale Key Message DAO
     */
    class dao extends \neoform\entity\record\dao implements definition {

        const BY_LOCALE     = 'by_locale';
        const BY_LOCALE_KEY = 'by_locale_key';
        const BY_BODY       = 'by_body';
        const BY_KEY        = 'by_key';

        /**
         * $var array $field_bindings list of fields and their corresponding bindings
         *
         * @return array
         */
        protected $field_bindings = [
            'id'     => self::TYPE_INTEGER,
            'key_id' => self::TYPE_INTEGER,
            'body'   => self::TYPE_STRING,
            'locale' => self::TYPE_STRING,
        ];

        /**
         * $var array $referenced_entities list of fields (in this entity) and their related foreign entity
         *
         * @return array
         */
        protected $referenced_entities = [
            'key_id' => 'locale\key',
            'locale' => 'locale',
        ];

        // READS

        /**
         * Get Locale Key Message ids by body
         *
         * @param string $body
         * @param array $order_by array of field names (as the key) and sort direction (parent::SORT_ASC, parent::SORT_DESC)
         * @param integer|null $offset get PKs starting at this offset
         * @param integer|null $limit max number of PKs to return
         *
         * @return array of Locale Key Message ids
         */
        public function by_body($body, array $order_by=null, $offset=null, $limit=null) {
            return parent::_by_fields(
                self::BY_BODY,
                [
                    'body' => (string) $body,
                ],
                $order_by,
                $offset,
                $limit
            );
        }

        /**
         * Get Locale Key Message ids by key
         *
         * @param int $key_id
         * @param array $order_by array of field names (as the key) and sort direction (parent::SORT_ASC, parent::SORT_DESC)
         * @param integer|null $offset get PKs starting at this offset
         * @param integer|null $limit max number of PKs to return
         *
         * @return array of Locale Key Message ids
         */
        public function by_key($key_id, array $order_by=null, $offset=null, $limit=null) {
            return parent::_by_fields(
                self::BY_KEY,
                [
                    'key_id' => (int) $key_id,
                ],
                $order_by,
                $offset,
                $limit
            );
        }

        /**
         * Get Locale Key Message ids by locale
         *
         * @param string $locale
         * @param array $order_by array of field names (as the key) and sort direction (parent::SORT_ASC, parent::SORT_DESC)
         * @param integer|null $offset get PKs starting at this offset
         * @param integer|null $limit max number of PKs to return
         *
         * @return array of Locale Key Message ids
         */
        public function by_locale($locale, array $order_by=null, $offset=null, $limit=null) {
            return parent::_by_fields(
                self::BY_LOCALE,
                [
                    'locale' => (string) $locale,
                ],
                $order_by,
                $offset,
                $limit
            );
        }

        /**
         * Get Locale Key Message ids by locale and key
         *
         * @param string $locale
         * @param int $key_id
         *
         * @return array of Locale Key Message ids
         */
        public function by_locale_key($locale, $key_id) {
            return parent::_by_fields(
                self::BY_LOCALE_KEY,
                [
                    'locale' => (string) $locale,
                    'key_id' => (int) $key_id,
                ]
            );
        }

        /**
         * Get multiple sets of Locale Key Message ids by locale_key
         *
         * @param \neoform\locale\key\collection|array $locale_key_list
         * @param array $order_by array of field names (as the key) and sort direction (parent::SORT_ASC, parent::SORT_DESC)
         * @param integer|null $offset get PKs starting at this offset
         * @param integer|null $limit max number of PKs to return
         *
         * @return array of arrays containing Locale Key Message ids
         */
        public function by_key_multi($locale_key_list, array $order_by=null, $offset=null, $limit=null) {
            $keys = [];
            if ($locale_key_list instanceof \neoform\locale\key\collection) {
                foreach ($locale_key_list as $k => $locale_key) {
                    $keys[$k] = [
                        'key_id' => (int) $locale_key->id,
                    ];
                }
            } else {
                foreach ($locale_key_list as $k => $locale_key) {
                    $keys[$k] = [
                        'key_id' => (int) $locale_key,
                    ];
                }
            }
            return parent::_by_fields_multi(
                self::BY_KEY,
                $keys,
                $order_by,
                $offset,
                $limit
            );
        }

        /**
         * Get multiple sets of Locale Key Message ids by locale
         *
         * @param \neoform\locale\collection|array $locale_list
         * @param array $order_by array of field names (as the key) and sort direction (parent::SORT_ASC, parent::SORT_DESC)
         * @param integer|null $offset get PKs starting at this offset
         * @param integer|null $limit max number of PKs to return
         *
         * @return array of arrays containing Locale Key Message ids
         */
        public function by_locale_multi($locale_list, array $order_by=null, $offset=null, $limit=null) {
            $keys = [];
            if ($locale_list instanceof \neoform\locale\collection) {
                foreach ($locale_list as $k => $locale) {
                    $keys[$k] = [
                        'locale' => (string) $locale->iso2,
                    ];
                }
            } else {
                foreach ($locale_list as $k => $locale) {
                    $keys[$k] = [
                        'locale' => (string) $locale,
                    ];
                }
            }
            return parent::_by_fields_multi(
                self::BY_LOCALE,
                $keys,
                $order_by,
                $offset,
                $limit
            );
        }

        /**
         * Get Locale Key Message id_arr by an array of bodys
         *
         * @param array $body_arr an array containing bodys
         * @param array $order_by array of field names (as the key) and sort direction (parent::SORT_ASC, parent::SORT_DESC)
         * @param integer|null $offset get PKs starting at this offset
         * @param integer|null $limit max number of PKs to return
         *
         * @return array of arrays of Locale Key Message ids
         */
        public function by_body_multi(array $body_arr, array $order_by=null, $offset=null, $limit=null) {
            $keys_arr = [];
            foreach ($body_arr as $k => $body) {
                $keys_arr[$k] = [ 'body' => (string) $body, ];
            }
            return parent::_by_fields_multi(
                self::BY_BODY,
                $keys_arr,
                $order_by,
                $offset,
                $limit
            );
        }

        /**
         * Get Locale Key Message id_arr by an array of locale and keys
         *
         * @param array $locale_key_arr an array of arrays containing locales and key_ids
         *
         * @return array of arrays of Locale Key Message ids
         */
        public function by_locale_key_multi(array $locale_key_arr) {
            $keys_arr = [];
            foreach ($locale_key_arr as $k => $locale_key) {
                $keys_arr[$k] = [
                    'locale' => (string) $locale_key['locale'],
                    'key_id' => (int) $locale_key['key_id'],
                ];
            }
            return parent::_by_fields_multi(
                self::BY_LOCALE_KEY,
                $keys_arr
            );
        }

        // WRITES

        /**
         * Insert Locale Key Message record, created from an array of $info
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
         * Insert multiple Locale Key Message records, created from an array of arrays of $info
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
         * Updates a Locale Key Message record with new data
         *   only fields that are specified in the $info array will be written
         *
         * @param model $locale_key_message record to be updated
         * @param array $info data to write to the record
         *
         * @return model updated model
         */
        public function update(model $locale_key_message, array $info) {

            // Update record
            return parent::_update($locale_key_message, $info);
        }

        /**
         * Delete a Locale Key Message record
         *
         * @param model $locale_key_message record to be deleted
         *
         * @return bool
         */
        public function delete(model $locale_key_message) {

            // Delete record
            return parent::_delete($locale_key_message);
        }

        /**
         * Delete multiple Locale Key Message records
         *
         * @param collection $locale_key_message_collection records to be deleted
         *
         * @return bool
         */
        public function delete_multi(collection $locale_key_message_collection) {

            // Delete records
            return parent::_delete_multi($locale_key_message_collection);
        }
    }
