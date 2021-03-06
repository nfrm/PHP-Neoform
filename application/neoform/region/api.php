<?php

    namespace neoform\region;

    use neoform\input;
    use neoform\entity;

    class api {

        /**
         * Creates a Region model with $info
         *
         * @param array $info
         *
         * @return model
         * @throws input\exception
         */
        public static function insert(array $info) {

            $input = new input\collection($info);

            self::_validate_insert($input);

            if ($input->is_valid()) {
                return entity::dao('region')->insert([
                    'country_id'      => $input->country_id->val(),
                    'name'            => $input->name->val(),
                    'name_normalized' => $input->name_normalized->val(),
                    'name_soundex'    => $input->name_soundex->val(),
                    'iso2'            => $input->iso2->val(),
                    'longitude'       => $input->longitude->val(),
                    'latitude'        => $input->latitude->val(),
                ]);
            }
            throw $input->exception();
        }

        /**
         * Update a Region model with $info
         *
         * @param model $region
         * @param array $info
         * @param bool  $crush
         *
         * @return model
         * @throws input\exception
         */
        public static function update(model $region, array $info, $crush=false) {

            $input = new input\collection($info);

            self::_validate_update($region, $input);

            if ($input->is_valid()) {
                return entity::dao('region')->update(
                    $region,
                    $input->vals(
                        [
                            'country_id',
                            'name',
                            'name_normalized',
                            'name_soundex',
                            'iso2',
                            'longitude',
                            'latitude',
                        ],
                        $crush
                    )
                );
            }
            throw $input->exception();
        }

        /**
         * Delete a Region
         *
         * @param model $region
         *
         * @return bool
         */
        public static function delete(model $region) {
            return entity::dao('region')->delete($region);
        }

        /**
         * Validates info to for insert
         *
         * @param input\collection $input
         */
        public static function _validate_insert(input\collection $input) {

            // country_id
            $input->country_id->cast('int')->digit(0, 255)->callback(function($country_id) {
                try {
                    $country_id->data('model', new \neoform\country\model($country_id->val()));
                } catch (\neoform\country\exception $e) {
                    $country_id->errors($e->getMessage());
                }
            });

            // name
            $input->name->cast('string')->length(1, 255);

            // name_normalized
            $input->name_normalized->cast('string')->length(1, 255);

            // name_soundex
            $input->name_soundex->cast('string')->length(1, 255);

            // iso2
            $input->iso2->cast('string')->length(1, 2)->callback(function($iso2) {
                if (entity::dao('region')->by_iso2($iso2->val())) {
                    $iso2->errors('already in use');
                }
            });

            // longitude
            $input->longitude->cast('float');

            // latitude
            $input->latitude->cast('float');
        }

        /**
         * Validates info to update a Region model
         *
         * @param model $region
         * @param input\collection $input
         */
        public static function _validate_update(model $region, input\collection $input) {

            // country_id
            $input->country_id->cast('int')->optional()->digit(0, 255)->callback(function($country_id) {
                try {
                    $country_id->data('model', new \neoform\country\model($country_id->val()));
                } catch (\neoform\country\exception $e) {
                    $country_id->errors($e->getMessage());
                }
            });

            // name
            $input->name->cast('string')->optional()->length(1, 255);

            // name_normalized
            $input->name_normalized->cast('string')->optional()->length(1, 255);

            // name_soundex
            $input->name_soundex->cast('string')->optional()->length(1, 255);

            // iso2
            $input->iso2->cast('string')->optional()->length(1, 2)->callback(function($iso2) use ($region) {
                $id_arr = entity::dao('region')->by_iso2($iso2->val());
                if (is_array($id_arr) && $id_arr && (int) current($id_arr) !== $region->id) {
                    $iso2->errors('already in use');
                }
            });

            // longitude
            $input->longitude->cast('float')->optional();

            // latitude
            $input->latitude->cast('float')->optional();
        }
    }
