<?php

    namespace neoform\acl\group\user;

    use neoform\input;
    use neoform\entity;

    class api {

        /**
         * Creates a Acl Group User model with $info
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
                return entity::dao('acl\group\user')->insert([
                    'acl_group_id' => $input->acl_group_id->val(),
                    'user_id'      => $input->user_id->val(),
                ]);
            }
            throw $input->exception();
        }

        /**
         * Deletes links
         *
         * @param \neoform\acl\group\model $acl_group
         * @param \neoform\user\collection $user_collection
         *
         * @return bool
         */
        public static function delete_by_acl_group(\neoform\acl\group\model $acl_group, \neoform\user\collection $user_collection) {
            $keys = [];
            foreach ($user_collection as $user) {
                $keys[] = [
                    'acl_group_id' => (int) $acl_group->id,
                    'user_id'      => (int) $user->id,
                ];
            }
            return entity::dao('acl\group\user')->delete_multi($keys);
        }

        /**
         * Deletes links
         *
         * @param \neoform\user\model $user
         * @param \neoform\acl\group\collection $acl_group_collection
         *
         * @return bool
         */
        public static function delete_by_user(\neoform\user\model $user, \neoform\acl\group\collection $acl_group_collection) {
            $keys = [];
            foreach ($acl_group_collection as $acl_group) {
                $keys[] = [
                    'user_id'      => (int) $user->id,
                    'acl_group_id' => (int) $acl_group->id,
                ];
            }
            return entity::dao('acl\group\user')->delete_multi($keys);
        }

        /**
         * Validates info to for insert
         *
         * @param input\collection $input
         */
        public static function _validate_insert(input\collection $input) {

            // acl_group_id
            $input->acl_group_id->cast('int')->digit(0, 4294967295)->callback(function($acl_group_id) {
                try {
                    $acl_group_id->data('model', new \neoform\acl\group\model($acl_group_id->val()));
                } catch (\neoform\acl\group\exception $e) {
                    $acl_group_id->errors($e->getMessage());
                }
            });

            // user_id
            $input->user_id->cast('int')->digit(0, 4294967295)->callback(function($user_id) {
                try {
                    $user_id->data('model', new \neoform\user\model($user_id->val()));
                } catch (\neoform\user\exception $e) {
                    $user_id->errors($e->getMessage());
                }
            });
        }
    }
