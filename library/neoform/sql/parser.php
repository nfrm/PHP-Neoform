<?php

    namespace neoform\sql;

    use PDO;
    use neoform;
    use exception;

    class parser {

        protected static function driver() {
            return neoform\sql::instance()->getAttribute(PDO::ATTR_DRIVER_NAME);
        }

        public static function get_table($name) {
            switch (self::driver()) {

                case 'pgsql':
                    $parser = new parser\driver\pgsql;
                    break;

                case 'mysql':
                    $parser = new parser\driver\mysql;
                    break;

                default:
                    throw new exception('No parsing driver exists for "' . self::driver() . '"');
            }

            $tables = $parser->tables();

            if (isset($tables[$name])) {
                return $tables[$name];
            } else {
                throw new exception('That table could not be found');
            }
        }

        /**
         * Identify driver specific validation for this field
         *
         * @param parser\field $field
         *
         * @return string
         * @throws exception
         */
        public static function driver_specific_api_validation(parser\field $field) {
            switch (self::driver()) {

                case 'pgsql':
                    return parser\driver\pgsql::api_type_validation($field);

                case 'mysql':
                    return parser\driver\mysql::api_type_validation($field);

                default:
                    throw new exception('No parsing driver exists for "' . self::driver() . '"');
            }
        }

        /**
         * Does this table have a primary key that allows only a small number of rows?
         *
         * @param parser\table $table
         *
         * @return bool
         * @throws exception
         */
        public static function is_table_tiny(parser\table $table) {
            switch (self::driver()) {

                case 'pgsql':
                    return parser\driver\pgsql::is_table_tiny($table);

                case 'mysql':
                    return parser\driver\mysql::is_table_tiny($table);

                default:
                    throw new exception('No parsing driver exists for "' . self::driver() . '"');
            }
        }

        /**
         * Can this field be useful for an equality lookup? (datetimes are an example of a field that is not useful)
         *
         * @param parser\field $field
         *
         * @return bool
         * @throws exception
         */
        public static function is_field_lookupable(parser\field $field) {
            switch (self::driver()) {

                case 'pgsql':
                    return parser\driver\pgsql::is_field_lookupable($field);

                case 'mysql':
                    return parser\driver\mysql::is_field_lookupable($field);

                default:
                    throw new exception('No parsing driver exists for "' . self::driver() . '"');
            }
        }
    }