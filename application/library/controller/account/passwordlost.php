<?php

    class controller_account_passwordlost extends controller_index {

        public function default_action() {
            if (core::auth()->logged_in()) {
                core::output()->redirect();
            } else {
                $view = new render_view();
                $view->render('account/password_lost');
            }
        }
    }