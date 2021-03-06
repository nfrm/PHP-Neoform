<?php

    namespace neoform\sql\parser;

    use neoform\sql;

    /**
     * An object representation of a field in an SQL table
     *
     * @var string  $name
     * @var string  $name_idless
     * @var table   $table
     * @var string  $type
     * @var integer $size
     * @var field   $referenced_field
     * @var field[] $referencing_fields
     * @var string  $casting
     * @var string  $casting_extended
     * @var string  $bool_true_value
     * @var array   $info
     */
    class field {

        protected $info;
        protected $table;
        protected $referenced_field;
        protected $referencing_fields = [];

        /**
         * Expects array passed to it to contain:
         *  string  name
         *  table   table
         *  string  type
         *  integer size
         *  field   referenced_field
         *  array   referencing_fields
         *
         * @param array $info
         */
        public function __construct(array $info) {
            $this->info = $info;
        }

        /**
         * Set the parent table
         *
         * @param table $table
         */
        public function _set_table(table $table) {
            $this->table = $table;
        }

        /**
         * Set the field this field references (if there is a FK on it)
         *
         * @param field $field
         */
        public function _set_referenced_field(field $field) {
            $this->referenced_field = $field;
            $field->_add_referencing_field($this);
        }

        /**
         * Add to an array of fields that reference this one
         * This gets called by _set_referenced_field() implicitly.
         *
         * @param field $field
         */
        public function _add_referencing_field(field $field) {
            $this->referencing_fields[] = $field;
        }

        public function __get($k) {
            switch ((string) $k) {
                case 'name':
                    return $this->info['name'];

                // Get the name of the field without a "_id" suffix if it exists
                case 'name_idless':
                    if (substr($this->info['name'], -3) === '_id') {
                        return substr($this->info['name'], 0, -3);
                    } else if (substr($this->info['name'], -2) === 'Id') { // covers camelCase DBs (yuck)
                        return substr($this->info['name'], 0, -2);
                    } else {
                        return $this->info['name'];
                    }

                case 'table':
                    return $this->table;

                // The datatype of this field
                case 'type':
                    return $this->info['type'];

                // The size (in bytes) of this field
                case 'size':
                    return $this->info['size'];

                // ENUM values, or decimal length, or varchar length
                case 'var_info':
                    return $this->info['size'];

                // The field that this field references
                case 'referenced_field':
                    return $this->referenced_field;

                // All fields that reference this field
                case 'referencing_fields':
                    return $this->referencing_fields;

                // PHP type (eg, int, string)
                case 'casting':
                    if ($this->info['casting'] === 'decimal') {
                        return 'float'; // php no support decimal type
                    } else {
                        return $this->info['casting'];
                    }

                // PHP type (eg, int, string, date, datetime, bool)
                case 'casting_extended':
                    return $this->info['casting_extended'];

                // If this is a boolean value (because it's an ENUM and has an on/off type value)
                // what is the value that corresponds with "true"
                case 'bool_true_value':
                    return $this->info['bool_true'];

                case 'info':
                    return $this->info;

                case 'pdo_casting':
                    if ($this->is_binary()) {
                        return 'binary';
                    } else {
                        return $this->info['casting'];
                    }

                default:
                    throw new \exception("Unknown field `{$k}`");

            }
        }

        /**
         * Is this field unsigned
         *
         * @return bool
         */
        public function is_unsigned() {
            return (bool) $this->info['unsigned'];
        }

        /**
         * Does this field auto increment
         *
         * @return bool
         */
        public function is_auto_increment() {
            return (bool) $this->info['autoincrement'];
        }

        /**
         * Does this field auto increment
         *
         * @return bool
         */
        public function is_autogenerated_on_insert() {
            return (bool) $this->info['autogenerated_insert'];
        }

        /**
         * Does this field allow null
         *
         * @return bool
         */
        public function allows_null() {
            return (bool) $this->info['allow_null'];
        }

        /**
         * Is this field part of a primary key
         *
         * @return bool
         */
        public function is_primary_key() {
            foreach ($this->table->primary_keys as $key) {
                if ($key->name === $this->info['name']) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Is this field part of a unique key
         *
         * @return bool
         */
        public function is_unique_key() {
            foreach ($this->table->unique_keys as $uk) {
                foreach ($uk as $key) {
                    if ($key->name === $this->info['name']) {
                        return true;
                    }
                }
            }

            return false;
        }

        /**
         * Checks if this field is unique. Which means it's a single key unique, not a composite.
         *
         * @return bool
         */
        public function is_unique() {
            if (count($this->table->primary_keys) === 1) {
                foreach ($this->table->primary_keys as $key) {
                    if ($key->name === $this->info['name']) {
                        return true;
                    }
                }
            }

            foreach ($this->table->unique_keys as $uk) {
                if (count($uk) === 1) {
                    foreach ($uk as $key) {
                        if ($key->name === $this->info['name']) {
                            return true;
                        }
                    }
                }
            }

            return false;
        }

        /**
         * Another table references this field
         *
         * @return bool
         */
        public function is_referenced() {
            return (bool) count($this->referencing_fields);
        }

        /**
         * Does this field reference another table
         *
         * @return bool
         */
        public function is_reference() {
            return (bool) $this->referenced_field;
        }

        /**
         * Does this field allow binary data
         *
         * @return bool
         */
        public function is_binary() {
            return (bool) $this->info['binary'];
        }

        /**
         * Can this field be useful for an equality lookup? (datetimes are an example of a field that is not useful)
         *
         * @return bool
         */
        public function is_field_lookupable() {
            return sql\parser::is_field_lookupable($this);
        }

        /**
         * Is this field part of an index
         *
         * @return bool
         */
        public function is_indexed() {
            if ($this->is_primary_key()) {
                return true;
            }

            foreach ($this->table->indexes as $index) {
                foreach ($index as $key) {
                    if ($key->name === $this->info['name']) {
                        return true;
                    }
                }
            }

            return false;
        }

        /**
         * Is this field indexed by itself
         *
         * @return bool
         */
        public function is_single_key_index() {
            if (count($this->table->primary_keys) === 1) {
                if (current($pk)->name === $this->info['name']) {
                    return true;
                }
            }

            foreach ($this->table->indexes as $index) {
                if (count($index) === 1) {
                    if (current($index)->name === $this->info['name']) {
                        return true;
                    }
                }
            }

            return false;
        }

        /**
         * Is this field part of a link (2-key index where both keys reference other tables) index?
         *
         * @return bool
         */
        public function is_link_index() {
            foreach ($this->table->all_unique_indexes as $uk) {
                if (count($uk) === 2) {
                    $found = false;
                    foreach ($uk as $field) {
                        if ($field->name === $field->info['name']) {
                            $found = true;
                        }
                    }

                    if ($found) {
                        // check if they both link to another table
                        $uk = array_values($uk);
                        if ($uk[0]->referenced_field && $uk[1]->referenced_field) {
                            return true;
                        }
                    }
                }
            }

            return false;
        }

        /**
         * If this field part of a link (2-key index where both keys reference other tables) index, return the other field
         * in the link.
         *
         * @return field|null
         */
        public function get_other_link_index_field() {
            foreach ($this->table->all_unique_indexes as $uk) {
                if (count($uk) === 2) {
                    $found = false;
                    foreach ($uk as $field) {
                        if ($field->name === $field->info['name']) {
                            $found = true;
                        }
                    }

                    if ($found) {
                        // check if they both link to another table
                        $uk = array_values($uk);
                        if ($uk[0]->referenced_field && $uk[1]->referenced_field) {
                            if ($uk[0] === $this) {
                                return $uk[1];
                            } else {
                                return $uk[0];
                            }
                        }
                    }
                }
            }
        }
    }