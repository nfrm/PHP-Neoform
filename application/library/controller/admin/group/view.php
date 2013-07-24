<?php

    class controller_admin_group_view extends controller_admin {

        public function default_action() {

            $group = new acl_group_model((int) core::http()->parameter('id'));

            $view = new render_view;
            $view->meta_title = 'Group';
            $view->group      = $group;

            $view->roles = new acl_role_collection(null, acl_role_dao::all(), 'id');

            $view->render('admin/group/view');
        }
    }