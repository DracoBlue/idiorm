<?php

error_reporting(E_ALL | E_STRICT);

/*
 * IdiormConnection + IdiormQuery + IdiormRecord  Demo
 * 
 * Note: This is just about the simplest database-driven webapp it's possible to create
 * and is designed only for the purpose of demonstrating how Idiorm works.
 * 
 * The ContactOrm is an example how a special IdiormRecord might look like.
 * 
 * In case it's not obvious: this is not the correct way to build web applications!
 */

require_once("./IdiormConnection.class.php");
require_once("./IdiormQuery.class.php");
require_once("./IdiormRecord.class.php");
require_once("./ContactOrm.class.php");

$connection = new IdiormConnection();
$connection->configure('sqlite:./dbs/demo.sqlite');

echo "<pre>";

// This grabs the raw database connection from the ORM
// class and creates the table if it doesn't already exist.
// Wouldn't normally be needed if the table is already there.
$db = $connection->get_db();
$db->exec("
    CREATE TABLE IF NOT EXISTS contact (
        id INTEGER PRIMARY KEY, 
        name TEXT, 
        email TEXT 
    );"
);

$record = $connection->createRecord('contact');
$record->name = 'Testi';
$record->save();

$record = $connection->createRecord('ContactOrm');
$record->setName('Tester2');
$record->setEmail('Tester2@example.org');
$record->save();

/*
 * Find ContactOrms
 */
$query = $connection->createQuery('ContactOrm');
$results = $query->where_gt('id', 1)->find_many();

foreach ($results as $result)
{
    echo (get_class($result));
    print_r($result->as_array());
}


/*
 * Find contacts as IdormRecord
 */
$query = $connection->createQuery('contact');
$results = $query->find_many();

foreach ($results as $result)
{
    echo (get_class($result));
    print_r($result->as_array());
}


echo "done!";
