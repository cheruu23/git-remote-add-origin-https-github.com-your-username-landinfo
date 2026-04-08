<?php
if (!function_exists('getDBConnection')) {
    function getDBConnection() {
        $host = 'localhost';
        $dbname = 'landinfo_new';
        $username = 'root';
        $password = '';
        $conn = new mysqli($host, $username, $password, $dbname);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        return $conn;
    }
}

if (!function_exists('query')) {
    function query($sql) {
        $conn = getDBConnection();
        $result = $conn->query($sql);
        return $result;
    }
}
?>