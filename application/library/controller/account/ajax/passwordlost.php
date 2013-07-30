<?php

    class controller_account_ajax_passwordlost extends controller_account_ajax {

        public function default_action() {
            if (core::auth()->logged_in()) {
                throw new error_exception('You are already logged in');
            } else {
                $json = new render_json;

                try {
                    user_lostpassword_api::lost(
                        new site_model(core::config()['core']['site_id']),
                        core::http()->posts()
                    );
                    $json->status = 'good';
                } catch (input_exception $e) {
                    $json->message = $e->message();
                    $json->errors  = $e->errors();
                }
                $json->render();
            }
        }
    }