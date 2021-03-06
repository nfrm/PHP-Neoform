<?php

    namespace neoform;

    use PDO;

    class sql extends core\singleton {

        public static function init($name) {

            //get the class that instatiated this singleton
            $config = config::instance()['sql'];

            if (! isset($config['pools'][$name])) {
                if ($name !== $config['default_pool_write'] && ! $name && $config['default_pool_write']) {
                    //try fallback connection
                    return sql::instance($config['default_pool_write']);
                } else {
                    throw new error\exception("The database connection \"{$name}\" configuration could not be found.");
                }
            }

            //select a random connection:
            $connection = $config['pools'][$name][\array_rand($config['pools'][$name])];

            $dsn  = isset($connection['dsn']) ? $connection['dsn'] : null;
            $user = isset($connection['user']) ? $connection['user'] : null;

            if (! $dsn || ! $user) {
                throw new error\exception("The database connection \"{$name}\" has not been configured properly.");
            }

            try {
                $options = [
                    //PDO::ATTR_CASE                 => PDO::CASE_LOWER, // force lower case for all field names
                    PDO::ATTR_ERRMODE                => PDO::ERRMODE_EXCEPTION, // all errors should be exceptions
                    PDO::ATTR_DEFAULT_FETCH_MODE     => PDO::FETCH_ASSOC,
                    //PDO::ATTR_PERSISTENT           => true,
                ];

                if (isset($config['encoding'])) {
                    $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES {$config['encoding']}";
                }

                //return new sql_debug(
                return new sql\pdo(
                    $dsn,
                    $user,
                    isset($connection['password']) ? $connection['password'] : '',
                    $options
                );
            } catch (\exception $e) {
                core::log("Could not connect to database configuration \"{$name}\" -- " . $e->getMessage(), 'CRITICAL');
                throw new error\exception('We are experiencing a brief interruption of service', 'Please try again in a few moments...');
            }
        }
    }