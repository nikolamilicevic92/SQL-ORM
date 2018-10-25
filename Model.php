<?php

require __DIR__ . '/DB.php';
require __DIR__ . '/IModel.php';
require __DIR__ . '/QueryBuilder.php';

/**
 * The base model class with elegant API for working with SQL database.
 */
class Model implements IModel
{
  /**
   * Here you can override default generated table name of your model.
   * 
   * @var string
   */
  protected $table;

  /**
   * When using filter methods with one argument, like User::find(3), it will be
   * assumed that the argument is value of the default filter bellow.
   * 
   * @var string
   */
  protected $defaultFilter = 'id';

  /**
   * If your table's primary key is not named 'id' you should specify it here.
   * 
   * @var string
   */
  protected $primaryKey = 'id';

  /**
   * Used to signal the save method to perform UPDATE instead of inserting after
   * calling the save method on already fetched result.
   * 
   * @var bool 
   */
  private $exists = false;

  /**
   * Used to signal the get method to return a bootstraped model instance instead
   * of raw results when it is called in context of expecting a single value.
   * 
   * @var bool
   */
  private $shouldReturnOneResult = false;

  /**
   * The object used for building sql queries.
   * 
   * @var QueryBuilder
   */
  private $queryBuilder;

  /**
   * Once a single result is fetched its primary key value will be saved here for
   * easier access in case it is needed later on.
   * 
   * @var int
   */
  private $id;

  /**
   * Database data of a single result will be stored here.
   * 
   * @var object|array
   */
  private $data = [];

  /**
   * When trying to set values on model instance the input will be stored here.
   * Since it will be passed directly to execute method, its keys will be 
   * prefixed with ':' in order to replace corresponding placeholders.
   * 
   * @var array
   */
  private $inputData = [];

  /**
   * When adding conditions to filter the results the input will be stored here.
   * Once query gets executed its placeholders will be replaced with this data.
   * 
   * @var array
   */
  private $filtersData = [];


  /**
   * Assigning new QueryBuilder instance to the model instance and optionally 
   * generating the table name if no default name is set on table property.
   * 
   * @constructor
   */
  public function __construct()
  {
    $this->queryBuilder = new QueryBuilder();

    if(!$this->table) {
      $this->table = static::getTableName(static::class);
    }
  }


  /**
   * When column is assigned new value it will be stored within input data
   * for use in the save() method.
   * 
   * @param string $key
   * @param mixed  $value
   * 
   * @return void
   */
  public function __set(string $key, $value): void
  {
    $this->inputData[":$key"] = $value;
  }


  /**
   * If column does not exist on fetched result and relationship method matching
   * the key name is defined its return value will be returned instead.
   * 
   * @param string $key
   * 
   * @return mixed
   */
  public function __get(string $key)
  {
    if(isset($this->data->$key)) {

      return $this->data->$key;

    } else if(method_exists($this, $key)) {

      return $this->$key()->get();

    }
    return null;
  }


  /**
   * Parsing the given class name into a table name by separating class words
   * at each capital letter (after the first one) with underscore, as well
   * as making it lowercase and plural version by appending the letter s.
   * 
   * @param string $class
   * 
   * @return string $table
   */
  private static function getTableName($class): string
  {
    //In case the class is namespaced we'll split it by \ and take the last part
    $partials = explode('\\', $class);
    $class = $partials[count($partials) - 1];
    $withUnderscores = preg_replace('/([a-z])([A-Z])/', '$1_$2', $class);
    return strtolower($withUnderscores) . 's';
  }

  /**
   * Defining a one to many relationship. If User can have many posts and posts
   * table has a column referencing user id then this method should be used.
   * 
   * @param string $ModelClass  | fully qualified class name or related model
   * @param mixed  $foreignKey  | column name in a related table of current model 
   * 
   * @return Model $relatedModel
   */
  public function hasMany(string $ModelClass, $foreignKey = null)
  {
    $relatedModel = new $ModelClass();

    $foreignKey = $foreignKey ?? substr($this->table, 0, -1) .'_id';

    $relatedModel->queryBuilder->addCondition($foreignKey, $this->id);

    return $relatedModel;
  }


  /**
   * Defining a one to one relationship. If User can have one Phone and phones
   * table has a column referencing user id then this method should be used.
   * 
   * @param string $ModelClass | fully qualified class name or related model
   * @param mixed  $foreignKey | column name in a related table of current model
   * 
   * @return Model $relatedModel
   */
  public function hasOne(string $ModelClass, $foreignKey = null)
  {
    $relatedModel = $this->hasMany($ModelClass, $foreignKey);

    $relatedModel->shouldReturnOneResult = true;

    return $relatedModel;
  }


  /**
   * Defining a belongs to relationship. If User can have many Posts and posts
   * table has a column referencing user id then this method can be used to
   * retrieve the user owning that post.
   * 
   * @param string $OwnerClass    | class name of model that current model belongs to
   * @param mixed  $foreignKey    | column that references the id of an owner
   * @param string $referencedKey | column in owner's table that is being referenced
   * 
   * @return Model $owner
   */
  public function belongsTo(string $OwnerClass, $foreignKey = null, $referencedKey = 'id')
  {
    $foreignKey = $foreignKey ?? substr(static::getTableName($OwnerClass), 0, -1) .'_id';

    $owner = new $OwnerClass();
    
    //$this->$foreignKey contains value of owner_id
    $owner->queryBuilder->addCondition($referencedKey, $this->$foreignKey);

    $owner->shouldReturnOneResult = true;

    return $owner;
  }


  /**
   * Defining a belongs to many relationship. If User can have many Roles and
   * Roles can have many Users and there is a pivot table users_roles then
   * this method should be used to retrieve roles attached to the user.
   * 
   * @param string $class         | class name of associated model
   * @param mixed  $table         | optional name of the pivot table
   * @param mixed  $selfColumn    | optional column name of current model in pivot table
   * @param mixed  $relatedColumn | optional column name of related model in pivot table
   * 
   * @return Model $relatedRows
   */
  public function belongsToMany(string $class, $table = null, $selfColumn = null, $relatedColumn = null)
  {
    $relatedTable = static::getTableName($class);

    if(!$selfColumn) {
      $selfColumn = substr(static::getTableName(static::class), 0, -1) .'_id';
    }
    if(!$relatedColumn) {
      $relatedColumn = substr(static::getTableName($class), 0, -1) .'_id';
    }
    
    $relatedRows = new static();

    if(!$table) {
      //If no table was provided then pivot table name will be constructed by
      //merging tables with underscore, in the alphabetic order
      if(strcmp($this->table, $relatedTable) < 0) {
        $pivotTable = $this->table .'_'. $relatedTable;
      } else {
        $pivotTable = $relatedTable .'_'. $this->table;
      }
    }

    $relatedRows->table = $pivotTable;
    //select * from users_roles join roles on roles.id=role_id where user_id=$this->id
    $relatedRows->queryBuilder
      ->addCondition($selfColumn, $this->id)
      ->addJoin("JOIN {$relatedTable} on {$relatedTable}.id={$relatedColumn}");

    return $relatedRows;
  }


  /**
   * Returning number of rows that match provided condition.
   * 
   * @param mixed $args  | filter: (3), ('name', 'Jhon'), ('age', '>', 25)
   * 
   * @return int $numRows
   */
  public static function count(...$args): int
  {
    $model = count($args) ? (static::where(...$args)) : new static();

    $model->queryBuilder
      ->setOperation('SELECT')
      ->setTable($model->table)
      ->setColumns(['count(*) as numRows']);

    $result = DB::select($model->queryBuilder->getQuery(), $model->data);

    return $result[0]->numRows;
  }


  /**
   * Testing whether row with provided condition exists.
   * 
   * @param array $args | filter: (3), ('name', 'Jhon'), ('age', '>', 25)
   * 
   * @return bool
   */
  public static function exists(...$args): bool
  {
    return static::count(...$args);
  }


  /**
   * Fetching all rows from the model table.
   * 
   * @return array $result
   */
  public static function all(): array
  {
    $model = new static();

    $model->queryBuilder->setOperation('SELECT')->setTable($model->table);

    return DB::select($model->queryBuilder->getQuery());
  }


  /**
   * Adding the first filter for the where clause. Aditional filters can be
   * chained with 'and()' or 'or()' methods.
   * 
   * @param array $args | filter: (3), ('name', 'Jhon'), ('age', '>', 25)
   * 
   * @return Model $instance
   */
  public static function where(...$args)
  {
    $model = new static();

    $model->addFilter($args);

    return $model;
  }


  /**
   * Appending aditional filter on where clause. The value of the filter is
   * placed inside filtersData while only :placeholder is placed inside the
   * query. Once the prepared statement is executed placeholder will
   * be replaced with corresponding value from the filters data.
   * 
   * @param array $args | filter: [3], ['name', 'Jhon'], ['age', '>', 25]
   * 
   * @return Model $this
   */
  private function addFilter(array $args)
  {
    $argsLength = count($args);

    if($argsLength === 1) {
      $this->filtersData[":{$this->defaultFilter}"] = $args[0];
      $this->queryBuilder->addCondition($this->defaultFilter, ":{$this->defaultFilter}");
    } else if($argsLength === 2) {
      $this->filtersData[":{$args[0]}"] = $args[1];
      $this->queryBuilder->addCondition($args[0], ":{$args[0]}");
    } else {
      $this->filtersData[":{$args[0]}"] = $args[2];
      $this->queryBuilder->addCondition($args[0], $args[1], ":{$args[0]}");
    }

    return $this;
  }


  /**
   * Appending another filter with AND chain.
   * 
   * @param array $args | filter: [3], ['name', 'Jhon'], ['age', '>', 25]
   * 
   * @return Model $this
   */
  public function and(...$args)
  {
    $this->queryBuilder->setChain('AND');
   
    return $this->addFilter($args);
  }


  /**
   * Appending another filter with OR chain.
   * 
   * @param array $args | filter: [3], ['name', 'Jhon'], ['age', '>', 25]
   * 
   * @return Model $instance
   */
  public function or(...$args)
  {
    $this->queryBuilder->setChain('OR');

    return $this->addFilter($args);
  }


  /**
   * Setting the offset for the LIMIT part.
   * 
   * @param int $offset | number of rows to skip
   * 
   * @return Model $this
   */
  public function skip(int $offset)
  {
    $this->queryBuilder->setOffset($offset);
    
    return $this;
  }


  /**
   * Setting the limit for the LIMIT part.
   * 
   * @param int $limit  | number of rows to take
   * 
   * @return Model $this
   */
  public function take(int $limit)
  {
    $this->queryBuilder->setLimit($limit);

    return $this;
  }


  /**
   * Setting the ORDER BY query part.
   * 
   * @param array $args  | ex. ('id'), ('id desc'), ('id', 'age')
   * 
   * @return Model $this
   */
  public function orderBy(...$args)
  {
    $this->queryBuilder->setOrderBy($args);

    return $this;
  }


  /**
   * Setting the GROUP BY query part.
   * 
   * @param array $args  | ex. ('address'), ('state', 'city')
   * 
   * @return Model $this
   */
  public function groupBy(...$args)
  {
    $this->queryBuilder->setGroupBy($args);

    return $this;
  }


  /**
   * Fetching all rows that match previously set conditions. If no columns
   * are specified then all columns will be included. The id will always
   * be included as it may be needed in incoming relationships method.
   * 
   * @return array $result
   */
  public function get(...$columns)
  {
    //Making sure primary key is included in the result
    $columns = $this->includePrimaryKey($columns);

    $this->queryBuilder
      ->setOperation('SELECT')->setColumns($columns)->setTable($this->table);
    
    //Executing the prepared statement and replacing all :placeholders with
    //their corresponding values stored inside filtersData property
    $result = DB::select($this->queryBuilder->getQuery(), $this->filtersData);
    
    //If get() method is called in the context of expecting a single record
    //like after the belongsTo() or hasOne() method, we will return the model
    //instance instead of the raw result
    if($this->shouldReturnOneResult) {
      $this->shouldReturnOneResult = false;
      return count($result) >= 1 ? $this->bootstrapSelf($result[0]) : null;
    } else {
      return $result;
    }
  }


  /**
   * If primary key is not present within provided columns it will be inserted.
   * 
   * @param array $columns
   * 
   * @return array $columnsWithId
   */
  private function includePrimaryKey(array $columns): array
  {
    if(!in_array($this->primaryKey, $columns)) {
      array_push($columns, $this->primaryKey);
      if(count($columns) === 1) {
        $columns = ['*'];
      }
    }
  }


  /**
   * Inserting a new record in the table.
   * 
   * @param array $data | [column => value, ...]
   * 
   * @return int $insertId
   */
  public static function store(array $data): int
  {
    return (new static())->setInputData($data)->save();
  }


  /**
   * Populating the insertData array in a format that will be used to replace
   * placeholders when the prepared statement is executed.
   * 
   * @param mixed $input
   * 
   * @return void
   */
  private function setInputData(array $input): void
  {
    foreach($input as $key => $value) {
      $this->inputData[":$key"] = $value;
    }
  }


  /**
   * Initializing the columns that will be updated. Columns can be comma 
   * separated strings in method call or an array of strings.
   * 
   * @param mixed $columns | (column1, ...), ([column1, ...])
   * 
   * @return Model $instance
   */
  public function update(...$columns)
  {
    if(count($columns) === 1 && gettype($columns[0]) === 'array') {
      $columns = $columns[0];
    }
    $this->queryBuilder->setColumns($columns);

    return $this;
  }


  /**
   * Updating the previously set columns. Values can be comma separated values
   * in method call or an array of values.
   * 
   * @param mixed $values | (value1, ...) or ([value1, ...])
   * 
   * @return int $affectedRows
   */
  public function with(...$values): int
  {
    if(count($values) === 1 && gettype($values[0]) === 'array') {
      $values = $values[0];
    }
    //Creating column => value associative array for use in set() method
    $updateData = array_combine($this->queryBuilder->getColumns(), $values);
    //Delegating the hard work to the set method
    return $this->set($updateData);
  }


  /**
   * Deleting rows that match previously set conditions.
   * 
   * @return int $affectedRows
   */
  public function destroy(): int
  {
    $this->queryBuilder
      ->setOperation('DELETE')->setTable($this->table);
    
    return DB::delete($this->queryBuilder->getQuery());
  }


  /**
   * Deleting all rows and truncating the table. Use with caution.
   * 
   * @return int $affectedRows
   */
  public static function empty(): int
  {
    $model = new static();

    $affectedRows = $model->destroy();

    DB::update("TRUNCATE {$model->table}");

    return $affectedRows;
  }


  /**
   * Fetching the first row that matches previously set filters.
   * 
   * @return mixed $result
   */
  public function first()
  {
    $this->queryBuilder->setLimit(1);

    $result = $this->get();
  
    return count($result) >= 1 ? $this->bootstrapSelf($result[0]) : null;
  }


  /**
   * Fetching one row that matches the filter. If more than one row is found or
   * no rows were found returning null.
   * 
   * @param mixed $args   | ex. (3), ('name', 'Jhon'), ('age', '>', 25)
   * 
   * @return mixed $result
   */
  public static function find(...$args)
  {
    $model = new static();

    $model->queryBuilder
      ->setOperation('SELECT')->setTable($model->table);

    $model->addFilter($args);

    $result = $model->get();

    return count($result) >= 1 ? $model->bootstrapSelf($result[0]) : null;
  }


  /**
   * Initializing model instance by filling it with fetched result.
   *
   * @param object $result
   * 
   * @return Model $this
   */
  private function bootstrapSelf($result)
  {
    //Storing database data
    $this->data = $result;
    //Marking instance as existing in database in order to use update query on save
    $this->exists = true;
    //Storing the value of primary key for easier access later in relationship methods
    $primaryKey = $this->primaryKey;
    $this->id = $result->$primaryKey;

    return $this;
  }


  /**
   * Updating rows that match previously set filters. 
   * 
   * @param array $args | ('name', 'Jhon') or (['name' => 'Jhon', ...])
   * 
   * @return int  $affectedRows
   */
  public function set(...$args): int
  {
    $input = count($args) === 2 ? [$args[0] => $args[1]] : $args[0];

    foreach($input as $column => $value) {
      $this->$column = $value;
    }
    //Signaling the save method to perform the update instead of inserting
    $this->exists = true;

    return $this->save();
  }


  /**
   * Inserting new record or updating existing if instance was fetched earlier.
   * 
   * @return int $insertId|$affectedRows
   */
  public function save(): int
  {
    $placeholders = array_keys($this->inputData);

    $this->queryBuilder
      ->setOperation($this->exists ? 'UPDATE' : 'INSERT')->setTable($this->table)
      ->setColumns(array_map(function($column) {
          return substr($column, 1);
        }, $placeholders))
      ->setValues($placeholders);
    
    if($this->exists) {
      $this->queryBuilder->addCondition($this->primaryKey, $this->id);
      return DB::update($this->queryBuilder->getQuery(), $this->inputData);
    } else {
      return DB::insert($this->queryBuilder->getQuery(), $this->inputData);
    }
  }

}