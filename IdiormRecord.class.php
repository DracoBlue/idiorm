<?php
/**
 *
 * IdiormRecord
 *
 * A single record/row in a database table, retrieved by IdiormQuery.
 *
 * @license <pre>
 * BSD Licensed.
 *
 * Copyright (c) 2010-2011, Jamie Matthews
 * Copyright (c) 2011, DracoBlue
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * * Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.</pre>
 *
 */
class IdiormRecord {

    // The name of the table the current ORM instance is associated with
    protected $_table_name;

    protected $_connection;
    
    // Alias for the table to be used in SELECT queries
    protected $_table_alias = null;

    // Values to be bound to the query
    protected $_values = array();

    // The data for a hydrated instance of the class
    protected $_data = array();

    // Fields that have been modified during the
    // lifetime of the object
    protected $_dirty_fields = array();

    // Is this a new object (has create() been called)?
    protected $_is_new = false;

    /**
     * "Private" constructor; shouldn't be called directly.
     * Use the ORM::for_table factory method instead.
     */
    public function __construct(IdiormConnection $connection, $table_name) {
        $this->_connection = $connection;
        $this->_table_name = $table_name;
    }
    
    public static function createNew(IdiormConnection $connection, $table_name, $data = null) {
        $class_name = 'IdiormRecord';
        
        if (class_exists($table_name) && is_callable($table_name . '::getTableName'))
        {
            $class_name = $table_name;
            $table_name = call_user_func_array($table_name . '::getTableName', array());    
        }
        
        $record = self::createFromData($connection, $table_name, $class_name, array());
        $record->_is_new = true;
        if ($data !== null)
        {
            $record->hydrate($data);
        }
        $record->force_all_dirty();
        return $record;
    }

    public static function createFromData(IdiormConnection $connection, $table_name, $class_name, array $data) {
        $record = new $class_name($connection, $table_name);
        $record->hydrate($data);
        return $record;
    }
    
    /**
     * This method can be called to hydrate (populate) this
     * instance of the class from an associative array of data.
     * This will usually be called only from inside the class,
     * but it's public in case you need to call it directly.
     */
    public function hydrate($data=array()) {
        $this->_data = $data;
        return $this;
    }

    /**
     * Force the ORM to flag all the fields in the $data array
     * as "dirty" and therefore update them when save() is called.
     */
    public function force_all_dirty() {
        $this->_dirty_fields = $this->_data;
        return $this;
    }

    /**
     * Quote a string that is used as an identifier
     * (table names, column names etc). This method can
     * also deal with dot-separated identifiers eg table.column
     */
    protected function _quote_identifier($identifier) {
        return $this->_connection->quote_identifier($identifier);
    }
    
    /**
     * Return a string containing the given number of question marks,
     * separated by commas. Eg "?, ?, ?"
     */
    protected function _create_placeholders($number_of_placeholders) {
        return join(", ", array_fill(0, $number_of_placeholders, "?"));
    }

    /**
     * Return the raw data wrapped by this ORM
     * instance as an associative array. Column
     * names may optionally be supplied as arguments,
     * if so, only those keys will be returned.
     */
    public function as_array() {
        if (func_num_args() === 0) {
            return $this->_data;
        }
        $args = func_get_args();
        return array_intersect_key($this->_data, array_flip($args));
    }

    /**
     * Return the value of a property of this object (database row)
     * or null if not present.
     */
    public function get($key) {
        return isset($this->_data[$key]) ? $this->_data[$key] : null;
    }

    /**
     * Set a property to a particular value on this object.
     * Flags that property as 'dirty' so it will be saved to the
     * database when save() is called.
     */
    public function set($key, $value) {
        $this->_data[$key] = $value;
        $this->_dirty_fields[$key] = $value;
    }

    /**
     * Check whether the given field has been changed since this
     * object was saved.
     */
    public function is_dirty($key) {
        return isset($this->_dirty_fields[$key]);
    }

    /**
     * Save any fields which have been modified on this object
     * to the database.
     */
    public function save() {
        $query = array();
        $values = array_values($this->_dirty_fields);

        if (!$this->_is_new) { // UPDATE
            // If there are no dirty values, do nothing
            if (count($values) == 0) {
                return true;
            }
            $query = $this->_build_update();
            $values[] = $this->id();
        } else { // INSERT
            $query = $this->_build_insert();
        }

        $statement = $this->_connection->get_db()->prepare($query);
        $success = $statement->execute($values);

        $this->_dirty_fields = array();
        
        // If we've just inserted a new record, set the ID of this object
        if ($this->_is_new) {
            $this->_is_new = false;
            return $this->_connection->get_db()->lastInsertId();
        }
        
        return $success;
    }

    /**
     * Build an UPDATE query
     */
    protected function _build_update() {
        $query = array();
        $query[] = "UPDATE {$this->_quote_identifier($this->_table_name)} SET";

        $field_list = array();
        foreach ($this->_dirty_fields as $key => $value) {
            $field_list[] = "{$this->_quote_identifier($key)} = ?";
        }
        $query[] = join(", ", $field_list);
        $query[] = "WHERE";
        $query[] = $this->_quote_identifier($this->_get_id_column_name());
        $query[] = "= ?";
        return join(" ", $query);
    }

    /**
     * Build an INSERT query
     */
    protected function _build_insert() {
        $query[] = "INSERT INTO";
        $query[] = $this->_quote_identifier($this->_table_name);
        $field_list = array_map(array($this, '_quote_identifier'), array_keys($this->_dirty_fields));
        $query[] = "(" . join(", ", $field_list) . ")";
        $query[] = "VALUES";

        $placeholders = $this->_create_placeholders(count($this->_dirty_fields));
        $query[] = "({$placeholders})";
        return join(" ", $query);
    }

    /**
     * Delete this record from the database
     */
    public function delete() {
        $query = join(" ", array(
                "DELETE FROM",
        $this->_quote_identifier($this->_table_name),
                "WHERE",
        $this->_quote_identifier($this->_get_id_column_name()),
                "= ?",
        ));
        $params = array($this->id());
        $statement = $this->_connection->get_db()->prepare($query);
        return $statement->execute($params);
    }

    // --------------------- //
    // --- MAGIC METHODS --- //
    // --------------------- //
    public function __get($key) {
        return $this->get($key);
    }

    public function __set($key, $value) {
        $this->set($key, $value);
    }

    public function __isset($key) {
        return isset($this->_data[$key]);
    }
}

