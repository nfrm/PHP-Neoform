<?php

    namespace neoform\acl\role\resource;

    use neoform\input;
    use neoform\entity;

    class api {

        /**
         * Creates a Acl Role Resource model with $info
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
                return entity::dao('acl\role\resource')->insert([
                    'acl_role_id'     => $input->acl_role_id->val(),
                    'acl_resource_id' => $input->acl_resource_id->val(),
                ]);
            }
            throw $input->exception();
        }

        /**
         * Deletes links
         *
         * @param \neoform\acl\role\model $acl_role
         * @param \neoform\acl\resource\collection $acl_resource_collection
         *
         * @return bool
         */
        public static function delete_by_acl_role(\neoform\acl\role\model $acl_role, \neoform\acl\resource\collection $acl_resource_collection) {
            $keys = [];
            foreach ($acl_resource_collection as $acl_resource) {
                $keys[] = [
                    'acl_role_id'     => (int) $acl_role->id,
                    'acl_resource_id' => (int) $acl_resource->id,
                ];
            }
            return entity::dao('acl\role\resource')->delete_multi($keys);
        }

        /**
         * Deletes links
         *
         * @param \neoform\acl\resource\model $acl_resource
         * @param \neoform\acl\role\collection $acl_role_collection
         *
         * @return bool
         */
        public static function delete_by_acl_resource(\neoform\acl\resource\model $acl_resource, \neoform\acl\role\collection $acl_role_collection) {
            $keys = [];
            foreach ($acl_role_collection as $acl_role) {
                $keys[] = [
                    'acl_resource_id' => (int) $acl_resource->id,
                    'acl_role_id'     => (int) $acl_role->id,
                ];
            }
            return entity::dao('acl\role\resource')->delete_multi($keys);
        }

        /**
         * Validates info to for insert
         *
         * @param input\collection $input
         */
        public static function _validate_insert(input\collection $input) {

            // acl_role_id
            $input->acl_role_id->cast('int')->digit(0, 4294967295)->callback(function($acl_role_id) {
                try {
                    $acl_role_id->data('model', new \neoform\acl\role\model($acl_role_id->val()));
                } catch (\neoform\acl\role\exception $e) {
                    $acl_role_id->errors($e->getMessage());
                }
            });

            // acl_resource_id
            $input->acl_resource_id->cast('int')->digit(0, 4294967295)->callback(function($acl_resource_id) {
                try {
                    $acl_resource_id->data('model', new \neoform\acl\resource\model($acl_resource_id->val()));
                } catch (\neoform\acl\resource\exception $e) {
                    $acl_resource_id->errors($e->getMessage());
                }
            });
        }
    }
