<?php

    namespace neoform\entity\record\driver;

    use neoform\entity\record;
    use neoform\entity;
    use neoform\sql;

    use PDO;

    /**
     * Postgres record\dao driver
     */
    class pgsql implements record\driver {

        protected static $binding_conversions = [
            record\dao::TYPE_STRING  => PDO::PARAM_STR,
            record\dao::TYPE_INTEGER => PDO::PARAM_INT,
            record\dao::TYPE_BINARY  => PDO::PARAM_LOB,
            record\dao::TYPE_FLOAT   => PDO::PARAM_STR,
            record\dao::TYPE_DECIMAL => PDO::PARAM_STR,
            record\dao::TYPE_BOOL    => PDO::PARAM_BOOL,
        ];

        /**
         * Parse the table name into a properly escaped table string
         *
         * @param string $table
         *
         * @return string
         */
        protected static function table($table) {
            if (strpos($table, '.') !== false) {
                $table = explode('.', $table);
                return "{$table[0]}\".\"{$table[1]}";
            } else {
                return $table;
            }
        }

        /**
         * Get full record by primary key
         *
         * @param record\dao $dao
         * @param string            $pool which source engine pool to use
         * @param int|string|null   $pk
         *
         * @return mixed
         */
        public static function record(record\dao $dao, $pool, $pk) {

            $info = sql::instance($pool)->prepare("
                SELECT *
                FROM \"" . self::table($dao::TABLE) . "\"
                WHERE \"" . $dao::PRIMARY_KEY . "\" = ?
            ");

            $info->bindValue(1, $pk, self::$binding_conversions[$dao->field_binding($dao::PRIMARY_KEY)]);
            $info->execute();

            if ($info = $info->fetch()) {
                sql\pdo::unbinary($info);
                return $info;
            }
        }

        /**
         * Get full records by primary key
         *
         * @param record\dao $dao
         * @param string     $pool which source engine pool to use
         * @param array      $pks
         *
         * @return array
         */
        public static function records(record\dao $dao, $pool, array $pks) {

            $infos_rs = sql::instance($pool)->prepare("
                SELECT *
                FROM \"" . self::table($dao::TABLE) . "\"
                WHERE \"" . $dao::PRIMARY_KEY . "\" IN (" . join(',', \array_fill(0, count($pks), '?')) . ")
            ");

            $pdo_binding = self::$binding_conversions[$dao->field_binding($dao::PRIMARY_KEY)];
            foreach (array_values($pks) as $i => $pk) {
                $infos_rs->bindValue($i + 1, $pk, $pdo_binding);
            }
            $infos_rs->execute();

            $infos = [];
            foreach ($infos_rs->fetchAll() as $info) {
                $k = array_search($info[$dao::PRIMARY_KEY], $pks);
                if ($k !== false) {
                    $infos[$k] = $info;
                }
            }

            sql\pdo::unbinary($infos);

            return $infos;
        }

        /**
         * Get a count based on key inputs
         *
         * @param record\dao $dao
         * @param string     $pool
         * @param array      $fieldvals
         *
         * @return integer
         */
        public static function count(record\dao $dao, $pool, array $fieldvals=null) {
            $where = [];
            $vals  = [];

            if ($fieldvals) {
                foreach ($fieldvals as $k => $v) {
                    if ($v === null) {
                        $where[] = "\"{$k}\" IS NULL";
                    } else {
                        $vals[]  = $v;
                        $where[] = "\"{$k}\" = ?";
                    }
                }
            }

            $rs = sql::instance($pool)->prepare("
                SELECT COUNT(*) \"num\"
                FROM \"" . self::table($dao::TABLE) . "\"
                " . ($where ? " WHERE " . join(" AND ", $where) : '') . "
            ");
            $rs->execute($vals);
            return (int) $rs->fetch()['num'];
        }

        /**
         * Get multiple counts
         *
         * @param record\dao $self
         * @param string     $pool
         * @param array      $fieldvals_arr
         *
         * @return array
         */
        public static function count_multi(record\dao $self, $pool, array $fieldvals_arr) {
            $queries = [];
            $vals    = [];
            $counts  = [];

            foreach ($fieldvals_arr as $k => $fieldvals) {
                $where      = [];
                $counts[$k] = [];
                $vals[]     = $k;

                foreach ($fieldvals as $field => $val) {
                    if ($val === null) {
                        $where[] = "\"{$field}\" IS NULL";
                    } else {
                        $vals[]  = $val;
                        $where[] = "\"{$field}\" = ?";
                    }
                }

                $queries[] = "(
                    SELECT COUNT(*) \"num\", ? k
                    FROM \"" . self::table($self::TABLE) . "\"
                    " . ($where ? " WHERE " . join(" AND ", $where) : '') . "
                )";
            }

            $rs = sql::instance($pool)->prepare(join(' UNION ALL ', $queries));
            $rs->execute($vals);

            foreach ($rs->fetchAll() as $row) {
                $counts[$row['k']] = (int) $row['num'];
            }

            return $counts;
        }

        /**
         * Get all records in the table
         *
         * @param record\dao $dao
         * @param string     $pool which source engine pool to use
         * @param int|string $pk
         * @param array      $fieldvals
         *
         * @return array
         */
        public static function all(record\dao $dao, $pool, $pk, array $fieldvals=null) {
            $where = [];
            $vals  = [];

            if ($fieldvals) {
                foreach ($fieldvals as $field => $val) {
                    if ($val === null) {
                        $where[] = "\"{$field}\" IS NULL";
                    } else {
                        $vals[$field] = $val;
                        $where[]      = "\"{$field}\" = ?";
                    }
                }
            }

            $info = sql::instance($pool)->prepare("
                SELECT *
                FROM \"" . self::table($dao::TABLE) . "\"
                " . ($where ? " WHERE " . join(" AND ", $where) : "") . "
                ORDER BY \"{$pk}\" ASC
            ");

            $bindings = $dao->field_bindings();

            // do NOT remove this reference, it will break the bindParam() function
            foreach ($vals as $k => &$v) {
                $info->bindParam($k, $v, self::$binding_conversions[$bindings[$k]]);
            }

            $info->execute();

            $infos = array_column($info->fetchAll(), null, $pk);
            sql\pdo::unbinary($infos);

            return $infos;
        }

        /**
         * Get record primary key by fields
         *
         * @param record\dao $dao
         * @param string     $pool which source engine pool to use
         * @param array      $fieldvals
         * @param int|string $pk
         *
         * @return array
         */
        public static function by_fields(record\dao $dao, $pool, array $fieldvals, $pk) {
            $where = [];
            $vals  = [];

            if ($fieldvals) {
                foreach ($fieldvals as $field => $val) {
                    if ($val === null) {
                        $where[] = "\"{$field}\" IS NULL";
                    } else {
                        $vals[$field] = $val;
                        $where[]      = "\"{$field}\" = ?";
                    }
                }
            }

            $rs = sql::instance($pool)->prepare("
                SELECT \"{$pk}\"
                FROM \"" . self::table($dao::TABLE) . "\"
                " . ($where ? " WHERE " . join(" AND ", $where) : "") . "
            ");

            $bindings = $dao->field_bindings();

            // do NOT remove this reference, it will break the bindParam() function
            foreach ($vals as $k => &$v) {
                $rs->bindParam($k, $v, self::$binding_conversions[$bindings[$k]]);
            }

            $rs->execute();

            $rs = $rs->fetchAll();

            $pks = $rs->fetchAll(PDO::FETCH_COLUMN, 0);
            sql\pdo::unbinary($pks);

            return $pks;
        }

        /**
         * Get multiple record primary keys by fields
         *
         * @param record\dao $dao
         * @param string     $pool which source engine pool to use
         * @param array      $fieldvals_arr
         * @param int|string $pk
         *
         * @return array
         */
        public static function by_fields_multi(record\dao $dao, $pool, array $fieldvals_arr, $pk) {
            $return  = [];
            $vals    = [];
            $queries = [];

            $query = "
                SELECT \"{$pk}\", ? \"__k__\"
                FROM \"" . self::table($dao::TABLE) . "\"
            ";
            foreach ($fieldvals_arr as $k => $fieldvals) {
                $where      = [];
                $return[$k] = [];
                $vals       = $k;

                foreach ($fieldvals as $field => $val) {
                    if ($val === null) {
                        $where[] = "\"{$field}\" IS NULL";
                    } else {
                        $vals[]  = $val;
                        $where[] = "\"{$field}\" = ?";
                    }
                }
                $queries[] = "(
                    {$query}
                    WHERE " . join(" AND ", $where) . "
                )";
            }

            $rs = sql::instance($pool)->prepare(
                join(' UNION ALL ', $queries)
            );

            $rs->execute($vals);

            foreach ($rs->fetchAll() as $row) {
                $return[$row['__k__']][] = $row[$pk];
            }

            sql\pdo::unbinary($return);

            return $return;
        }

        /**
         * Get a set of PKs based on params, in a given order and offset/limit
         *
         * @param record\dao   $dao
         * @param string       $pool
         * @param array        $fieldvals
         * @param mixed        $pk
         * @param array        $order_by
         * @param integer|null $offset
         * @param integer      $limit
         *
         * @return mixed
         */
        public static function by_fields_offset(record\dao $dao, $pool, array $fieldvals, $pk, array $order_by, $offset, $limit) {
            $where = [];
            $vals  = [];

            if ($fieldvals) {
                foreach ($fieldvals as $field => $val) {
                    if ($val === null) {
                        $where[] = "\"{$field}\" IS NULL";
                    } else {
                        $vals[]  = $val;
                        $where[] = "\"{$field}\" = ?";
                    }
                }
            }

            // LIMIT
            if ($limit) {
                $limit = "LIMIT {$limit}";
            } else {
                $limit = '';
            }

            // OFFSET
            if ($offset !== null) {
                $offset = "OFFSET {$offset}";
            } else {
                $offset = '';
            }

            $order = [];
            foreach ($order_by as $field => $sort_direction) {
                $order[] = "\"{$field}\" " . (entity\dao::SORT_DESC === $sort_direction ? 'DESC' : 'ASC');
            }
            $order_by = join(', ', $order);

            $rs = sql::instance($pool)->prepare("
                SELECT \"{$pk}\"
                FROM \"" . self::table($dao::TABLE) . "\"
                " . ($where ? " WHERE " . join(" AND ", $where) : '') . "
                ORDER BY {$order_by}
                {$limit} {$offset}
            ");
            $rs->execute($vals);

            return $rs->fetchAll(PDO::FETCH_COLUMN, 0);
        }

        /**
         * Get multiple sets of PKs based on params, in a given order and offset/limit
         *
         * @param record\dao   $dao
         * @param string       $pool
         * @param array        $fieldvals_arr
         * @param mixed        $pk
         * @param array        $order_by
         * @param integer|null $offset
         * @param integer      $limit
         *
         * @return array
         */
        public static function by_fields_offset_multi(record\dao $dao, $pool, array $fieldvals_arr, $pk, array $order_by, $offset, $limit) {
            $return  = [];
            $vals    = [];
            $queries = [];

            // LIMIT
            $limit = $limit ? "LIMIT {$limit}" : '';

            // OFFSET
            $offset = $offset !== null ? "OFFSET {$offset}" : '';

            $order = [];
            foreach ($order_by as $field => $sort_direction) {
                $order[] = "\"{$field}\" " . (entity\dao::SORT_DESC === $sort_direction ? 'DESC' : 'ASC');
            }
            $order_by = join(', ', $order);

            $query = "
                SELECT \"{$pk}\", ? \"__k__\"
                FROM \"" . self::table($dao::TABLE) . "\"
            ";

            foreach ($fieldvals_arr as $k => $fieldvals) {
                $where      = [];
                $return[$k] = [];
                $vals[]     = $k;

                foreach ($fieldvals as $field => $val) {
                    if ($val === null) {
                        $where[] = "\"{$field}\" IS NULL";
                    } else {
                        $vals[]  = $val;
                        $where[] = "\"{$field}\" = ?";
                    }
                }

                $queries[] = "(
                    {$query}
                    WHERE " . join(" AND ", $where) . "
                    ORDER BY {$order_by}
                    {$limit} {$offset}
                )";
            }

            $rs = sql::instance($pool)->prepare(
                join(' UNION ALL ', $queries)
            );

            $rs->execute($vals);

            foreach ($rs->fetchAll() as $row) {
                $return[$row['__k__']][] = $row[$pk];
            }

            return $return;
        }

        /**
         * Insert record
         *
         * @param record\dao $dao
         * @param string     $pool which source engine pool to use
         * @param array      $info
         * @param bool       $autoincrement
         * @param bool       $replace
         *
         * @return array
         * @throws entity\exception
         */
        public static function insert(record\dao $dao, $pool, array $info, $autoincrement, $replace) {

            if ($replace) {
                throw new entity\exception('PostgreSQL does not support REPLACE INTO.');
            }

            $insert_fields = [];
            foreach (array_keys($info) as $key) {
                $insert_fields[] = "\"{$key}\"";
            }

            $insert = sql::instance($pool)->prepare("
                INSERT INTO \"" . self::table($dao::TABLE) . "\"
                ( " . join(', ', $insert_fields) . " )
                VALUES
                ( " . join(',', \array_fill(0, count($insert_fields), '?')) . " )
                " . ($autoincrement ? "RETURNING \"". $dao::PRIMARY_KEY . "\"" : '') . "
            ");

            $bindings = $dao->field_bindings();

            // bindParam() expects a reference, not a value, do not remove the &
            $i = 1;
            foreach ($info as $k => &$v) {
                $insert->bindParam($i++, $v, self::$binding_conversions[$bindings[$k]]);
            }

            if (! $insert->execute()) {
                $error = sql::instance($pool)->errorInfo();
                throw new entity\exception("Insert failed - {$error[0]}: {$error[2]}");
            }

            if ($autoincrement) {
                $info[$dao::PRIMARY_KEY] = $insert->fetch()[$dao::PRIMARY_KEY];
            }

            return $info;
        }

        /**
         * Insert multiple records
         *
         * @param record\dao $dao
         * @param string     $pool which source engine pool to use
         * @param array      $infos
         * @param bool       $keys_match
         * @param bool       $autoincrement
         * @param bool       $replace
         *
         * @return array
         * @throws entity\exception
         */
        public static function insert_multi(record\dao $dao, $pool, array $infos, $keys_match, $autoincrement, $replace) {

            if ($replace) {
                throw new entity\exception('PostgreSQL does not support REPLACE INTO.');
            }

            if ($keys_match) {
                $insert_fields = [];

                foreach (array_keys(reset($infos)) as $k) {
                    $insert_fields[] = "\"{$k}\"";
                }

                // If the table is auto increment, we cannot lump all inserts into one query
                // since we need the returned IDs for cache-busting and to return a model
                if ($autoincrement) {
                    $sql = sql::instance($pool);
                    $sql->beginTransaction();
                    $pk = $dao::PRIMARY_KEY;

                    $insert = $sql->prepare("
                        INSERT INTO \"" . self::table($dao::TABLE) . "\"
                        ( " . join(', ', $insert_fields) . " )
                        VALUES
                        ( " . join(',', \array_fill(0, count($insert_fields), '?')) . " )
                        RETURNING \"{$pk}\"
                    ");

                    $bindings = $dao->field_bindings();

                    foreach ($infos as $info) {

                        // bindParam() expects a reference, not a value, do not remove the &
                        $i = 1;
                        foreach ($info as $k => &$v) {
                            $insert->bindParam($i++, $v, self::$binding_conversions[$bindings[$k]]);
                        }

                        if (! $insert->execute()) {
                            $error = $sql->errorInfo();
                            $sql->rollback();
                            throw new entity\exception("Inserts failed - {$error[0]}: {$error[2]}");
                        }

                        if ($autoincrement) {
                            $info[$dao::PRIMARY_KEY] = $insert->fetch()[$pk];
                        }
                    }

                    if (! $sql->commit()) {
                        $error = $sql->errorInfo();
                        throw new entity\exception("Inserts failed - {$error[0]}: {$error[2]}");
                    }
                } else {
                    // this might explode if $keys_match was a lie
                    $insert_vals = new \splFixedArray(count($insert_fields) * count($infos));
                    foreach ($infos as $info) {
                        foreach ($info as $v) {
                            $insert_vals[] = $v;
                        }
                    }

                    $inserts = sql::instance($pool)->prepare("
                        INSERT INTO \"" . self::table($dao::TABLE) . "\"
                        ( " . \implode(', ', $insert_fields) . " )
                        VALUES
                        " . join(', ', \array_fill(0, count($infos), '( ' . join(',', \array_fill(0, count($insert_fields), '?')) . ')')) . "
                    ");

                    $bindings = $dao->field_bindings();

                    // bindParam() expects a reference, not a value, do not remove the &
                    $i = 1;
                    foreach ($insert_vals as $k => &$v) {
                        $inserts->bindParam($i++, $v, self::$binding_conversions[$bindings[$k]]);
                    }

                    if (! $inserts->execute()) {
                        $error = sql::instance($pool)->errorInfo();
                        throw new entity\exception("Inserts failed - {$error[0]}: {$error[2]}");
                    }
                }
            } else {
                $sql   = sql::instance($pool);
                $table = self::table($dao::TABLE);

                $sql->beginTransaction();

                $bindings = $dao->field_bindings();

                foreach ($infos as $info) {
                    $insert_fields = [];

                    foreach (array_keys($info) as $key) {
                        $insert_fields[] = "\"$key\"";
                    }

                    $insert = $sql->prepare("
                        INSERT INTO \"{$table}\"
                        ( " . join(', ', $insert_fields) . " )
                        VALUES
                        ( " . join(',', \array_fill(0, count($info), '?')) . " )
                        " . ($autoincrement ? "RETURNING \"". $dao::PRIMARY_KEY . "\"" : '') . "
                    ");

                    // bindParam() expects a reference, not a value, do not remove the &
                    $i = 1;
                    foreach ($info as $k => &$v) {
                        $insert->bindParam($i++, $v, self::$binding_conversions[$bindings[$k]]);
                    }

                    if (! $insert->execute()) {
                        $error = $sql->errorInfo();
                        $sql->rollback();
                        throw new entity\exception("Inserts failed - {$error[0]}: {$error[2]}");
                    }

                    if ($autoincrement) {
                        $info[$dao::PRIMARY_KEY] = $insert->fetch()[$dao::PRIMARY_KEY];
                    }
                }

                if (! $sql->commit()) {
                    $error = $sql->errorInfo();
                    throw new entity\exception("Inserts failed - {$error[0]}: {$error[2]}");
                }
            }

            return $infos;
        }

        /**
         * Update a record
         *
         * @param record\dao   $dao
         * @param string       $pool which source engine pool to use
         * @param int|string   $pk
         * @param record\model $model
         * @param array        $info
         *
         * @throws entity\exception
         */
        public static function update(record\dao $dao, $pool, $pk, record\model $model, array $info) {
            $update_fields = [];
            foreach (array_keys($info) as $key) {
                $update_fields[] = "\"{$key}\" = :{$key}";
            }
            $update = sql::instance($pool)->prepare("
                UPDATE \"" . self::table($dao::TABLE) . "\"
                SET " . \implode(", \n", $update_fields) . "
                WHERE \"{$pk}\" = :{$pk}
            ");

            $info[$pk] = $model->$pk;

            $bindings = $dao->field_bindings();

            // bindParam() expects a reference, not a value, do not remove the &
            foreach ($info as $k => &$v) {
                $update->bindParam($k, $v, self::$binding_conversions[$bindings[$k]]);
            }

            if (! $update->execute()) {
                $error = sql::instance($pool)->errorInfo();
                throw new entity\exception("Update failed - {$error[0]}: {$error[2]}");
            }
        }

        /**
         * Delete a record
         *
         * @param record\dao   $dao
         * @param string       $pool which source engine pool to use
         * @param int|string   $pk
         * @param record\model $model
         *
         * @throws entity\exception
         */
        public static function delete(record\dao $dao, $pool, $pk, record\model $model) {
            $delete = sql::instance($pool)->prepare("
                DELETE FROM \"" . self::table($dao::TABLE) . "\"
                WHERE \"{$pk}\" = ?
            ");
            $delete->bindValue(1, $model->$pk, self::$binding_conversions[$dao->field_binding($dao::PRIMARY_KEY)]);
            if (! $delete->execute()) {
                $error = sql::instance($pool)->errorInfo();
                throw new entity\exception("Delete failed - {$error[0]}: {$error[2]}");
            }
        }

        /**
         * Delete multiple records
         *
         * @param record\dao        $dao
         * @param string            $pool which source engine pool to use
         * @param int|string        $pk
         * @param record\collection $collection
         *
         * @throws entity\exception
         */
        public static function delete_multi(record\dao $dao, $pool, $pk, record\collection $collection) {
            $pks = $collection->field($pk);
            $delete = sql::instance($pool)->prepare("
                DELETE FROM \"" . self::table($dao::TABLE) . "\"
                WHERE \"{$pk}\" IN (" . join(',', \array_fill(0, count($collection), '?')) . ")
            ");

            $pdo_binding = self::$binding_conversions[$dao->field_binding($dao::PRIMARY_KEY)];
            $i = 1;
            foreach ($pks as $pk) {
                $delete->bindValue($i++, $pk, $pdo_binding);
            }
            if (! $delete->execute()) {
                $error = sql::instance($pool)->errorInfo();
                throw new entity\exception("Deletes failed - {$error[0]}: {$error[2]}");
            }
        }
    }