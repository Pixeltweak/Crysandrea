<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class No_Result_Exception extends Exception {
	public function __construct() {
		parent::__construct('No results');
	}
}

/**
 * CRYS framework model which acts as a factory for generating object instances
 * of the specified data model.
 *
 * @package CRYS\Core\Models
 * @author Gio Carlo Cielo <gio@crysandrea.com>
 */
class CRYS_Model extends CI_Model {
	protected static $TABLE;
	protected static $PRIMARY_KEY;
	protected static $FIELDS;

	protected static $INNER_MODEL;

	public function __construct() {
		parent::__construct();
		if (empty(static::$INNER_MODEL))
			return;
		$reflector = new ReflectionClass(static::$INNER_MODEL);
		$properties = $reflector->getProperties(ReflectionProperty::IS_PUBLIC);
		foreach ($properties as $property)
			self::$FIELDS[] = $property->getName();
	}

	public function prefixField($field) {
		assert(in_array($field, static::$FIELDS));
		return static::$TABLE.'.'.$field;
	}

	public function joinCond($model) {
		$key = $model::$PRIMARY_KEY;
		return $this->prefixField($key).'='.$model->prefixField($key);
	}

	public function getTable() {
		return static::$TABLE;
	}

	public function getPK() {
		return static::$PRIMARY_KEY;
	}

	public function getFields() {
		return static::$FIELDS;
	}

	public function find($id=0, $select_fields='*', $constraints=array(), $limit=1) {
		$conditions = $constraints;
		if ( ! empty($id)) $conditions[static::$PRIMARY_KEY] = $id;

		$result = $this->db->select($select_fields)
						   ->limit($limit)
						   ->get_where(static::$TABLE, $conditions);

		return $this->_process_result($result, $limit);
	}

	public function remove($conditions=array(), $limit=1) {
		if (empty($conditions))
			show_error('Conditions cannot be empty on remove');
		$this->db->limit($limit);
		$this->db->delete(static::$TABLE, $conditions);
	}

	public static function create($attributes=array()) {
		return new static::$INNER_MODEL($attributes);
	}

	public function update($obj, $limit=1) {
		assert(get_class($obj) === static::$INNER_MODEL);
		if (empty($obj->{static::$PRIMARY_KEY}))
			show_error('Cannot update without primary key');
		$obj = $obj->updateCompile();
		$this->db->where(static::$PRIMARY_KEY, $obj->{static::$PRIMARY_KEY});
		$this->db->limit($limit);
		$this->db->update(static::$TABLE, $obj);
	}

	public function save($obj) {
		assert(get_class($obj) === static::$INNER_MODEL);
		if (!empty($obj->{static::$PRIMARY_KEY}))
			return $this->update($obj, 1);
		$obj = $obj->saveCompile();
		$this->db->insert(static::$TABLE, $obj);
	}

	protected function _process_result(&$result, $limit=1) {
		if (!$result->result_array())
			throw new No_Result_Exception();
		if ($limit === 1)
			return $result->row(0, static::$INNER_MODEL);
		else
			return $result->result(static::$INNER_MODEL);
	}
}

/**
 * Data model generated by CRYS factories.
 *
 * @package CRYS\Core\Models
 * @author Gio Carlo Cielo <gio@crysandrea.com>
 */
class CRYS_Data_Model {
	protected static $REQUIRED = array();
	protected static $DEFAULTS = array();

	public function __construct($attributes=array()) {
		if (empty($attributes))
			return;
		if (empty(static::$DEFAULTS))
			show_error('Model DEFAULTS cannot be empty');
		foreach ($attributes as $key => $val)
			$this->{$key} = $val;
	}

	public function updateCompile() {
		return (object) array_filter((array) $this, function($elem) {
			return !empty($elem) || $elem === 0;
		});
	}

	public function saveCompile() {
		$obj = (object) array_filter((array) $this);
		foreach (static::$DEFAULTS as $key => $val)
			if (!property_exists($obj, $key))
				$obj->{$key} = $val;
		return $obj;
	}
}

/**
 * Composite model for managing factories with composite, foreign keys. This is
 * frequently a relationship between two other models as a many-to-many
 * relationship.
 *
 * @package CRYS\Core\Models
 * @author Gio Carlo Cielo <gio@crysandrea.com
 */
class CRYS_Composite_Model extends CRYS_Model {
	protected static $MODEL_FROM;
	protected static $MODEL_TO;

	protected static $FK_FROM;
	protected static $FK_TO;

	public function __construct() {
		parent::__construct();
	}

	public function findFrom($fk_from=0, $select_fields='*', $constraints=array(), $limit=1) {
		$conditions = $constraints;
		if (!empty($fk_from))
			$conditions[static::$FK_FROM] = $fk_from;
		$this->db->limit($limit);
		$result = $this->db->select($select_fields)
			->get_where(static::$TABLE, $conditions);
		return $this->_process_result($result, $limit);
	}

	public function findTo($fk_to=0, $select_fields='*', $constraints=array(), $limit=1) {
		$conditions = $constraints;
		if (!empty($fk_to))
			$conditions[static::$FK_TO] = $fk_to;
		$this->db->limit($limit);
		$result = $this->db->select($select_fields)
			->get_where(static::$TABLE, $conditions);
		return $this->_process_result($result, $limit);
	}

	public function findRelation($fk_from=0, $fk_to=0, $select_fields='*', $constraints=array(), $limit=1) {
		if (empty($fk_from) || empty($fk_to))
			show_error('Foreign keys cannot be empty on composite model');
		$conditions = array_merge(array(
			static::$FK_FROM => $fk_from,
			static::$FK_TO => $fk_to),
			$constraints);
		$this->db->limit($limit);
		$result = $this->db->select($select_fields)
							->order_by(static::$PRIMARY_KEY, 'DESC')
							->get_where(static::$TABLE, $conditions);

		return $this->_process_result($result, $limit);
	}

	public function removeRelation($fk_from=0, $fk_to=0, $constraints=array(), $limit=1) {
		if (empty($fk_from) || empty($fk_to))
			show_error('Foreign keys cannot be empty on composite model');

		$conditions = array_merge(array(static::$FK_FROM => $fk_from, static::$FK_TO => $fk_to), $constraints);

		$this->db->where($conditions);
		$this->db->limit($limit);
		$this->db->delete(static::$TABLE);
	}

	public static function createRelation($fk_from, $fk_to, $attributes=array()) {
		if (empty($fk_from) || empty($fk_to))
			show_error('Foreign keys cannot be empty on composite model');
		$attributes = array_merge(
			array(
				static::$FK_FROM => $fk_from,
				static::$FK_TO => $fk_to),
			$attributes);
		return new static::$INNER_MODEL($attributes);
	}

	public function updateRelation($relation_obj, $constraints=array(), $limit=1) {
		assert(get_class($relation_obj) === static::$INNER_MODEL);
		if (empty($relation_obj->{static::$FK_FROM}) || empty($relation_obj->{static::$FK_FROM}))
			show_error('Foreign keys cannot be empty on composite model');

		$relation_obj = $relation_obj->updateCompile();

		$conditions = array_merge(array(
			static::$FK_FROM => $relation_obj->{static::$FK_FROM},
			static::$FK_TO => $relation_obj->{static::$FK_TO},
			static::$PRIMARY_KEY => $relation_obj->{static::$PRIMARY_KEY}),
		$constraints);

		unset($relation_obj->{static::$PRIMARY_KEY});

		$this->db->where($conditions);
		$this->db->limit($limit);
		$this->db->order_by(static::$PRIMARY_KEY, 'DESC');
		$this->db->update(static::$TABLE, $relation_obj);
	}

	public function saveRelation($relation_obj) {
		assert(get_class($relation_obj) === static::$INNER_MODEL);
		$relation_obj = $relation_obj->saveCompile();
		$this->db->insert(static::$TABLE, $relation_obj);
	}
}

