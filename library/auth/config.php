<?php

    class auth_config extends config_defaults {

        protected function defaults() {
            return [
                // the normal auth session lifespan [3 months in seconds] [required]
                'normal_auth_lifetime' => '3M',

                // the long auth session lifespan  [1 year in seconds] [required]
                'long_auth_lifetime' => '1Y',

                // how long can the password salt be
                'max_salt_length' => 40,

                // default password hash method
                'default_hash_method_id' => 1,

                // default user account status
                'default_user_account_status_id' => 1,

                // default user account status
                'default_hash_method_cost' => 10,

                // authentication cookie name [required]
                'cookie' => 'cc',

                // Which site does this user account belong to
                'site' => 'main',
            ];
        }
    }