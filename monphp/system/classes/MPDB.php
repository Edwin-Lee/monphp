<?php

class MPDB
{
    // {{{ properties
    private $conn;
    private $db;

    static const ASC = MongoCollection::ASCENDING;
    static const DESC = MongoCollection::DESCENDING;
    // }}}
    // {{{ function __construct($set = 'default', $db_file = '')
    function __construct($set = 'default', $db_file = '')
    {
        include $db_file === ''
            ? DIR_SYS.'/config.database.php'
            : $db_file;
        $this->conn = new Mongo($_db_conn[$set]['server'], $_db_conn[$set]['options']);
        $this->db = $this->conn->selectDB($_db_conn[$set]['options']['db']);
    }
    // }}}
    // {{{ public function __call($name, $arguments)
    public function __call($name, $arguments)
    {
        if (method_exists($this->db, $name))
        {
            return call_user_func_array(array($this->db, $name), $arguments);
        }
        throw new MPDBException('That method does not exist');
    }
    // }}}
    // {{{ public function __get($name)
    public function __get($name)
    {
        return $this->db->$name;
    }
    // }}}
    // {{{ public static function __callStatic($name, $arguments)
    public static function __callStatic($name, $arguments)
    {
        $mondb = new MPDB();
        $db = $mondb->db;
        if (method_exists($db, $name))
        {
            return call_user_func_array(array($db, $name), $arguments);
        }
        throw new MPDBException('That method does not exist');
    }
    // }}}
    // {{{ public static function is_success($response)
    public static function is_success($response)
    {
        if (ake('err', $response) && !is_null($response['err']))
        {
            return false;
        }
        return true;
    }
    // }}}
}

class MPDBException extends Exception
{
}
