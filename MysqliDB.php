<?php

/**
 * Class MysqliDB
 *
 * Thanks: https://gist.github.com/johnmorris/8135167#file-gistfile1-php
 */
class MysqliDB
{
    /**
     * Mysqli Connection
     *
     * @var mysqli
     */
    protected $connection;

    /**
     * The host of mysql server
     *
     * @var string
     */
    protected $host;

    /**
     * The username of mysql
     *
     * @var string
     */
    protected $username;

    /**
     * The password of mysql
     *
     * @var string
     */
    protected $password;

    /**
     * The database name
     *
     * @var string
     */
    protected $dbName;

    /**
     * The mysql port number
     *
     * @var int
     */
    protected $port;

    /**
     * The charset of database
     *
     * @var string
     */
    protected $charset;

    /**
     * The query
     *
     * @var
     */
    protected $query;


    public function __construct(
        $host = null,
        $username = null,
        $password = null,
        $dbName = null,
        $port = 3306,
        $charset = 'utf8'
    ){
        // check if parameters passed as an array
        if (is_array($host)) {
            foreach ($host as $key => $value) {
                $$key = $value;
            }
        // check if mysqli object passed
        }

        if ($host instanceof mysqli) {
            $this->connection = $host;
        } else {
            $this->host = $host;
        }

        $this->username = $username;
        $this->password = $password;
        $this->dbName   = $dbName;
        $this->port     = $port;
        $this->charset  = $charset;

        if (!$host instanceof mysqli) {
            $this->initMysqli();
        }
    }

    /**
     * Destructor for closing connection
     */
    public function __destruct()
    {
        if ($this->connection) {
            $this->connection->close();
        }
    }

    /**
     * Initialize Mysqli connection
     *
     * @throws Exception
     */
    public function initMysqli()
    {
        $this->connection = new mysqli(
            $this->host,
            $this->username,
            $this->password,
            $this->dbName,
            $this->port
        );

        if ($this->connection->connect_error) {
            throw new Exception("Mysqli Connection Error: " .
                $this->connection->connect_errno .
                ": " . $this->connection->connect_error
            );
        }

        if (!empty($this->charset)) {
            $this->connection->set_charset($this->charset);
        }
    }


    /**
     * Insert data into database
     *
     * @param string $tablename The table name
     * @param array  $data      The data that needs to be inserted int database
     *
     * @return bool
     * @throws Exception
     */
    public function insert($tablename, $data)
    {
        if (empty($tablename) || empty($data)) {
            return false;
        }

        // make sure data and format are arrays
        $data = (array) $data;
        $format = '';

        foreach ($data as $key => $value) {
            $format .= $this->detectValueType($value);
        }

        list($fields, $placeholders, $values) = $this->preQuery($data);

        array_unshift($values, $format);

        // prepare query for parameters binding
        $prepareQuery = "INSERT INTO `{$tablename}` ({$fields}) VALUES ({$placeholders})";
        if (!$stmt = $this->connection->prepare($prepareQuery)) {
            throw new Exception('problem happened while preparing statement: '.$this->connection->error);
        }

        call_user_func_array(array($stmt, 'bind_param'), $this->refValues($values));

        $stmt->execute();

        if ($stmt->affected_rows) {
            return true;
        }

        return false;

    }

    /**
     * Prepare query and return fields, placeholders and values based on query
     *
     * @param array    $query
     * @param string   $type
     *
     * @return array
     */
    private function preQuery($query, $type = 'insert')
    {
        $fields = '';
        $placeholders = '';
        $values = array();

        foreach ( $query as $field => $value ) {
            $fields .= "`{$field}`,";
            $values[] = $value;

            if ( $type == 'update') {
                $placeholders .= $field . '=?,';
            } else {
                $placeholders .= '?,';
            }

        }

        $fields = substr($fields, 0, -1);
        $placeholders = substr($placeholders, 0, -1);

        return array($fields, $placeholders, $values);
    }


    /**
     * Value reference
     *
     * @param array $values The array of values
     *
     * @return array
     */
    private function refValues($values)
    {
        // reference required for php 5.3+
        if (strnatcmp(phpversion(), '5.3') >= 0) {
            $refs = array();
            foreach ($values as $key => $value) {
                $refs[$key] = &$values[$key];
            }

            return $refs;
        }
        return $values;
    }

    /**
     * Detect the type of value and return specific data type of the field
     * like 's', 'i' etc.
     *
     * @param mixed $value The value that needs to be detected
     *
     * @return string
     */
    protected function detectValueType($value)
    {
        switch (gettype($value)) {
            case 'NULL':
            case 'string':
                return 's';
                break;
            case 'boolean':
            case 'integer':
                return 'i';
                break;
            case 'blob':
                return 'b';
                break;
            case 'double':
                return 'd';
                break;
            default:
                return '';
        }
    }

    /**
     * Make magic method __clone empty to prevent duplicate connection
     */
    private function __clone(){}

}