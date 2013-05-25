<?php

    core::locale()->set_namespace('main');

    $page = (int) core::http()->parameter('page');
    $per_page = 10;

    if ($page < 1) {
        $page = 1;
    }

    $view = new render_view();

    $view->meta_title = 'Users';

    //$users = new user_collection(user_dao::limit(20, 'id', 'asc', null));
    $users = new user_collection(user_dao::pagination('id', 'asc', ($page - 1) * $per_page, $per_page));
    $users->user_date_collection(); // preload user_dates

    $view->users    = $users;

    $view->page     = $page;
    $view->total    = user_dao::count();
    $view->per_page = $per_page;

    $view->render('admin/user');