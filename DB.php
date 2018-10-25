<?php

/**
 * This class is a singleton providing access to single PDO object as well as
 * providing static methods for basic CRUD queries.
 */
class DB
{
  /**
   * @var DB
   */
  private static $instance = null;

  /**
   * @var PDOObject
   */
  private $connection;

  /**
   * @credentials
   */
  const HOSTNAME = '127.0.0.1';
  const DATABASE = 'video_klub';
  const USERNAME = 'root';
  const PASSWORD = '';


  /**
   * @constructor
   */
  private function __construct() 
  {
    $this->connection = new PDO(
      'mysql:host='. self::HOSTNAME .';dbname='. self::DATABASE. ';charset=utf8', 
       self::USERNAME, self::PASSWORD
    );
    $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }


  /**
   * Providing the connection to database.
   */
  public static function getConnection()
  {
    if(!self::$instance) {
      self::$instance = new self();
    }
    return self::$instance->connection;
  }


  /**
   * Executing the insert query as a prepared statement.
   * 
   * @param string $query
   * @param mixed  $params
   * 
   * @return void
   */
  public static function insert(string $query, $params = []): int
  {
    $conn = self::getConnection();

    $stmt = $conn->prepare($query);

    $stmt->execute($params);

    return $conn->lastInsertId();
  }


  /**
   * Executing the update query as a prepared statement.
   * 
   * @param string $query
   * @param mixed  $params
   * 
   * @return void
   */
  public static function update(string $query, $params = []): int
  {
    $stmt = self::getConnection()->prepare($query);

    $stmt->execute($params);

    return $stmt->rowCount();
  }


  /**
   * Executing the delete query as a prepared statement.
   * 
   * @param string $query
   * @param mixed  $params
   * 
   * @return void
   */
  public static function delete(string $query, $params = []): int
  {
    $stmt = self::getConnection()->prepare($query);

    $stmt->execute($params);

    return $stmt->rowCount();
  }


  /**
   * Executing the select query as a prepared statement.
   * 
   * @param string $query
   * @param mixed  $params
   * 
   * @return void
   */
  public static function select(string $query, $params = [])
  {
    $stmt = self::getConnection()->prepare($query);
    
    $stmt->execute($params);

    $result = [];

    while($row = $stmt->fetchObject()) {
      $result[] = $row;
    }

    return $result;
  }


  /**
   * Calling the provided closure inside a transaction. If errors occur queries
   * will be rolled back, otherwise commited.
   */
  public static function transaction($closure)
  {
    $conn = self::getConnection();
    $conn->beginTransaction();
    try {
      $closure($conn);
    } catch(Exception $e) {
      var_dump($e->getMessage());
      $conn->rollBack();
      return false;
    }
    $conn->commit();
    return true;
  }

}