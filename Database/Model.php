<?php

namespace Framework\Database;

use Framework\Database\Connection\MysqlConnection;
use Framework\Database\Connection\ConnectionString;

abstract class Model {

	protected MysqlConnection $connection;
	protected string $table;
	protected array $attributes;
	protected array $dirty = [];

	public function setConnection(MysqlConnection $connection): static {
		$this->connection = $connection;
		return $this;
	}

	public function getConnection(): MysqlConnection {
		if (!isset($this->connection)) {
			return new MysqlConnection(ConnectionString::class);
		}
	}

	public function setTable(string $table): static {
		$this->table = $table;
		return $this;
	}

	public function getTable(): string {
		if (!isset($this->table)) {
			$reflector = new ReflectorClass(static::class);
			foreach($reflector->getAttributes() as $attribute) {
				if ($attribute->getName() == TableName::class) {
					return $attribute->getArguments()[0];
				}
			}
			throw new Exception("$table is not set and getTable is not defined");
		}
		return $this->table;
	}

	public static function with(array $attributes = []): static {
		$model = new static();
		$model->attributes = $attributes;
		return $model;
	}


	public static function query() {
		$model = new static();
		$query = $model->getConnection()->from($model->getTable());
		return (new ModelCollector($query, static::class))->from($model->getTable());
	}

	public static function __callStatic(string $method, array $parameters = []): mixed {
		return static::query()->$method($parameters);
	}

	public function __get(string $property): mixed {
		$getter = 'get' . ucfirst($property) . 'Attribute';
		$value = null;
		if (method_exists($this, $property)) {
			$relationship = $this->$property();
			$method = $relationship->method;
			$value = $relationship->$method();
		}
		if (method_exists($this, $getter)) {
			return $this->getter($this->attributes[$property] ?? null);
		}
		if (isset($this->attributes[$property])) {
			return $this->attributes[$property];
		}
		return null;
	}
	public function __set(string $property, $value) {
		$setter = 'set' . ucfirst($property) . 'Attribute';
		array_push($this->dirty, $property);
		if (method_exists($this,$setter)) {
			$this->attributes[$property] = $this->$setter($value);
		}
		$this->attributes[$property] = $value;
	}

	public function save(): static {
		$values = [];
		foreach($this->dirty as $dirty) {
			$values[$dirty] = $this->attributes[$dirty];
		}
		$data = [array_keys($values), $values];
		$query = static::query();
		if (isset($this->attributes['id'])) {
			$query->where(['id',$this->attributes['id']])->update(...$data);
			return $this;
		}
		$query->insert(...$data);
		$this->attributes['id'] = $query->getLastInsertedId();
		return $this;
	}

	public function hasOne(string $class, string $foreignKey, string $primaryKey = 'id'): mixed {
		$model = new $class;
		$query = $class::query()->from($model->getTable())->where([$foreignKey, $this->attributes['id']]);
		return $query->first();
	}

	public function hasMany(string $class, string $foreignKey, string $primaryKey = 'id'): mixed {
		$model = new $class;
		$query = $class::query()->from($model->getTable())->where([$foreignKey,$this->attributes['id']]);
		return $query->all();
	}

	public function belongsTo(string $class, string $foreignKey, string $primaryKey = 'id'): mixed {
		$model = new $class;
		$query = $class::query()->from($model->getTable())->where([$primaryKey,$this->attributes[$foreignKey]]);
		return $query->first();
	}

}
