<?php

/**
 *
 * IdiormConnection
 *
 * A single connection Idorm connection.
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
class IdiormConnection {

    // Class configuration
    protected $_config = array(
        'connection_string' => 'sqlite::memory:',
        'id_column' => 'id',
        'id_column_overrides' => array(),
        'error_mode' => PDO::ERRMODE_EXCEPTION,
        'username' => null,
        'password' => null,
        'driver_options' => null,
        'identifier_quote_character' => null, // if this is null, will be autodetected
        'logging' => false,
        'caching' => false,
    );

    // Database connection, instance of the PDO class
    protected $_db;

    /**
     * Pass configuration settings to the class in the form of
     * key/value pairs. As a shortcut, if the second argument
     * is omitted, the setting is assumed to be the DSN string
     * used by PDO to connect to the database. Often, this
     * will be the only configuration required to use Idiorm.
     */
    public function configure($key, $value=null) {
        // Shortcut: If only one argument is passed,
        // assume it's a connection string
        if (is_null($value)) {
            $value = $key;
            $key = 'connection_string';
        }
        $this->_config[$key] = $value;
    }

    public function createQuery($table_name = null) {
        $this->_setup_db();
        return new IdiormQuery($this, $table_name);
    }
    
    public function createRecord($table_name, $data = null) {
        return IdiormRecord::createNew($this, $table_name, $data);
    }

    /**
     * Set up the database connection used by the class.
     */
    protected function _setup_db() {
        if (!$this->_db) {
            $connection_string = $this->_config['connection_string'];
            $username = $this->_config['username'];
            $password = $this->_config['password'];
            $driver_options = $this->_config['driver_options'];
            $db = new PDO($connection_string, $username, $password, $driver_options);
            $db->setAttribute(PDO::ATTR_ERRMODE, $this->_config['error_mode']);
            $this->set_db($db);
        }
    }

    /**
     * Set the PDO object used by Idiorm to communicate with the database.
     * This is public in case the ORM should use a ready-instantiated
     * PDO object as its database connection.
     */
    public function set_db($db) {
        $this->_db = $db;
        $this->_setup_identifier_quote_character();
    }

    /**
     * Detect and initialise the character used to quote identifiers
     * (table names, column names etc). If this has been specified
     * manually using ORM::configure('identifier_quote_character', 'some-char'),
     * this will do nothing.
     */
    protected function _setup_identifier_quote_character() {
        if (is_null($this->_config['identifier_quote_character'])) {
            $this->_config['identifier_quote_character'] = $this->_detect_identifier_quote_character();
        }
    }

    /**
     * Return the correct character used to quote identifiers (table
     * names, column names etc) by looking at the driver being used by PDO.
     */
    protected function _detect_identifier_quote_character() {
        switch($this->_db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            case 'pgsql':
            case 'sqlsrv':
            case 'dblib':
            case 'mssql':
            case 'sybase':
                return '"';
            case 'mysql':
            case 'sqlite':
            case 'sqlite2':
            default:
                return '`';
        }
    }

    /**
     * Returns the PDO instance used by the the ORM to communicate with
     * the database. This can be called if any low-level DB access is
     * required outside the class.
     */
    public function get_db() {
        $this->_setup_db(); // required in case this is called before Idiorm is instantiated
        return $this->_db;
    }

    /**
     * Quote a string that is used as an identifier
     * (table names, column names etc). This method can
     * also deal with dot-separated identifiers eg table.column
     */
    public function quote_identifier($identifier) {
        $parts = explode('.', $identifier);
        $parts = array_map(array($this, '_quote_identifier_part'), $parts);
        return join('.', $parts);
    }

    /**
     * This method performs the actual quoting of a single
     * part of an identifier, using the identifier quote
     * character specified in the config (or autodetected).
     */
    protected function _quote_identifier_part($part) {
        if ($part === '*') {
            return $part;
        }
        $quote_character = $this->_config['identifier_quote_character'];
        return $quote_character . $part . $quote_character;
    }
}