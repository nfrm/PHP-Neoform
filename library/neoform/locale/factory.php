<?php

    namespace neoform;

    class locale_factory implements core_factory {

        public static function init(array $args) {
            return new locale_instance(core::config()['locale']);
        }
    }