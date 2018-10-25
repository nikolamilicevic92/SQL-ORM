<?php

/**
 * This class is used for building sql queries.
 */
class QueryBuilder
{
  const MAX_LIMIT = 1000000000;
  private $operation;
  private $table;
  public $columns = ['*'];
  private $join = '';
  private $conditions = '';
  private $groupBy = '';
  private $orderBy = '';
  private $offset = 0;
  private $limit = self::MAX_LIMIT;
  private $values = '';
  private $chain = '';

  public function setOperation(string $operation): QueryBuilder
  {
    $this->operation = strtolower($operation);

    return $this;
  }

  public function addJoin(string $join): QueryBuilder
  {
    $this->join .= " $join";
    
    return $this;
  }

  public function setChain(string $chain): QueryBuilder
  {
    $this->chain = $chain;

    return $this;
  }

  public function setGroupBy(array $columns): QueryBuilder
  {
    $this->groupBy = 'GROUP BY '. implode(',', $columns);

    return $this;
  }

  public function setOrderBy(array $args): QueryBuilder
  {
    $this->orderBy = 'ORDER BY '. implode(',', $args);

    return $this;
  }

  public function setLimit(int $limit): QueryBuilder
  {
    $this->limit = $limit;

    return $this;
  }

  public function setOffset(int $offset): QueryBuilder
  {
    $this->offset = $offset;

    return $this;
  }

  public function addCondition(string $arg1, $arg2, $arg3 = null): QueryBuilder
  {
    $column = $arg1;
    $operator = $arg3 ? $arg2 : '=';
    $value = $arg3 ? $arg3 : $arg2;

    if($this->chain === '') {
      $this->conditions = "{$column}{$operator}{$value}";
    } else {
      $this->conditions .= " $this->chain {$column}{$operator}{$value}";
      $this->chain = '';
    }

    return $this;
  }

  public function setTable(string $table): QueryBuilder
  {
    $this->table = $table;

    return $this;
  }

  public function setColumns(array $columns): QueryBuilder
  {
    $this->columns = $columns;

    return $this;
  }

  public function getColumns(): array
  {
    return $this->columns;
  }

  public function setValues(array $values): QueryBuilder
  {
    $this->values = $values;

    return $this;
  }

  public function getQuery(): string
  {
    if($this->operation === 'select') {
      $query = $this->makeSelectQuery();
    } else if($this->operation === 'insert') {
      $query = $this->makeInsertQuery();
    } else if($this->operation === 'update') {
      $query = $this->makeUpdateQuery();
    } else if($this->operation === 'delete') {
      $query = $this->makeDeleteQuery();
    } else {
      throw new Exception(
        "Unknown operation: [$this->operation]. Operation can be select, insert, update or delete."
      );
    }

    $this->reset();

    var_dump($query);
    
    return $query;
  }

  private function reset(): void
  {
    $this->columns = ['*'];
    $this->join = '';
    $this->conditions = '';
    $this->groupBy = '';
    $this->orderBy = '';
    $this->limit = '';
    $this->values = '';
    $this->chain = '';
  }

  private function makeSelectQuery(): string
  {
    $where = '';
    $having = '';
    $limit = '';

    if($this->conditions !== '') {
      if($this->groupBy !== '') {
        $having = ' HAVING ' . $this->conditions;      
      } else {
        $where = ' WHERE ' . $this->conditions;        
      }
    }

    if($this->offset !== 0 || $this->limit !== self::MAX_LIMIT) {
      $limit = " LIMIT $this->limit OFFSET $this->offset";
    }
    
    $query  = 'SELECT '. implode(',', $this->columns) .' FROM '. $this->table .' ';
    $query .= $this->join . $where .' '. $this->groupBy . $having .' ';
    $query .= $this->orderBy . $limit;
    
    return $query;
  }

  private function makeInsertQuery(): string
  {
    $query  = 'INSERT INTO '. $this->table .' ';
    $query .= '('. implode(',', $this->columns) .') values ';
    $query .= '('. implode(',', $this->values) . ')';

    return $query;
  }

  private function makeDeleteQuery(): string
  {
    $where = $this->conditions !== '' ? ' WHERE '. $this->conditions : '';

    $query = 'DELETE FROM '. $this->table . $where;
    
    return $query;
  }

  private function makeUpdateQuery(): string
  {
    $query = 'UPDATE '. $this->table .' SET ';

    $columnsLength = count($this->columns);

    for($i = 0; $i < $columnsLength; $i++) {
      $query .= $this->columns[$i] .'='. $this->values[$i] . ',';
    }

    $where = $this->conditions !== '' ? ' WHERE '. $this->conditions : '';

    //Trimming the last comma
    return substr($query, 0, -1) . $where;
  }

}