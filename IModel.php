<?php

interface IModel
{
  public static function all(): array;
  public static function find(...$args);
  public static function where(...$args);
  public static function store(array $data): int;
  public static function count(): int;
  public static function exists(): bool;
  public static function empty(): int;
  public function get();
  public function set(...$args): int;
  public function destroy(): int;
  public function and(...$args);
  public function or(...$args);
  public function update();
  public function with();
  public function skip(int $offset);
  public function take(int $limit);
  public function first();
  public function orderBy(...$args);
  public function groupBy(...$args);
  public function hasMany(string $class);
  public function hasOne(string $class);
  public function belongsTo(string $class);
  public function belongsToMany(string $class);
}