<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles many to many relationships
 *
 * @package Jelly
 * @author Jonathan Geiger
 */
abstract class Jelly_Field_ManyToMany 
extends Jelly_Field_Relationship 
implements Jelly_Behavior_Field_Saveable, Jelly_Behavior_Field_Haveable, Jelly_Behavior_Field_Changeable
{	
	/**
	 * This is expected to contain an assoc. array containing the key 
	 * 'model', and the key 'columns'
	 * 
	 * If they do not exist, they will be filled in with sensible defaults 
	 * derived from the field's name and from the values in 'foreign'
	 * 
	 * 'model' => 'a model or table to use as the join table'
	 * 
	 * If 'model' is empty it is set to the pluralized names of the 
	 * two model's names combined alphabetically with an underscore.
	 * 
	 * 'columns' => array('column for this model', 'column for foreign model')
	 * 
	 * 'columns' must be set in the order they appear above.
	 *
	 * @var array
	 */
	public $through = array();
	
	/**
	 * This is expected to contain an assoc. array containing the key 
	 * 'model', and the key 'column'
	 * 
	 * If they do not exist, they will be filled in with sensible defaults 
	 * derived from the field's name .
	 * 
	 * 'model' => 'a model to use as the foreign association'
	 * 
	 * If 'model' is empty it is set to the singularized name of the field.
	 * 
	 * 'column' => 'the column (or alias) that is the foreign model's primary key'
	 * 
	 * If 'column' is empty, it is set to 'id'
	 *
	 * @var array
	 */
	public $foreign = array();
		
	/**
	 * Overrides the initialize to automatically provide the column name
	 *
	 * @param string $model 
	 * @param string $column 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function initialize($model, $column)
	{
		if (empty($this->foreign['model']))
		{
			$this->foreign['model'] = inflector::singular($column);
		}
		
		if (empty($this->foreign['column']))
		{
			$this->foreign['column'] = 'id';
		}
		
		if (empty($this->through['columns'][0]))
		{
			$this->through['columns'][0] = inflector::singular($model).'_id';
		}	
		
		if (empty($this->through['columns'][1]))
		{
			$this->through['columns'][1] = inflector::singular($this->foreign['model']).'_id';
		}
		
		if (empty($this->through['model']))
		{
			// Find the join table based on the two model names pluralized, 
			// sorted alphabetically and with an underscore separating them
			$this->through['model'] = array(
				inflector::plural($this->foreign['model']), 
				inflector::plural($model)
			);
			
			// Sort
			sort($this->through['model']);
			
			// Bring them back together
			$this->through['model'] = implode('_', $this->through['model']);
		}
		
		parent::initialize($model, $column);
	}
	
	/**
	 * Converts a Database_Result, Jelly, array of ids, or id to an array of ids
	 *
	 * @param  mixed $value 
	 * @return array
	 * @author Jonathan Geiger
	 */
	public function set($value)
	{
		// Can be set in only one go
		$return = array();
		
		// Handle Database Results
		if ($value instanceof Iterator || is_array($value))
		{
			foreach($value as $row)
			{
				if (is_object($row))
				{
					$return[] = $row->id();
				}
				else
				{
					$return[] = $row;
				}
			}
		}
		// And individual models
		else if (is_object($value))
		{
			$return = array($value->id());
		}
		// And everything else
		else
		{
			$return = (array)$value;
		}
		
		return $return;
	}
	
	/**
	 * Returns a pre-built Jelly model ready to be loaded
	 *
	 * @param  Jelly  $model 
	 * @param  mixed $value 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function get($model, $value)
	{
		return Jelly::select($this->foreign['model'])
				->where($this->foreign['column'], 'IN', $this->_in($model));
	}
	
	/**
	 * Implementation for Jelly_Behavior_Field_Saveable.
	 *
	 * @param  Jelly $model 
	 * @param  mixed $value 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function save($model, $value)
	{
		// Find all current records so that we can calculate what's changed
		$in = $this->_in($model, TRUE);
		
		// Find old relationships that must be deleted
		if ($old = array_diff($in, $value))
		{
			Jelly::delete($this->through['model'])
				->where($this->through['columns'][0], '=', $model->id())
				->where($this->through['columns'][1], 'IN', $old)
				->execute(Jelly_Meta::get($model)->db());
		}

		// Find new relationships that must be inserted
		if ($new = array_diff($value, $in))
		{
			$query = Jelly::insert($this->through['model'])
						 ->columns($this->through['columns']);
				
			foreach ($new as $new_id)
			{
				$query->values(array($model->id(), $new_id));
			}
			
			$query->execute();
		}
	}
	
	/**
	 * Implementation of Jelly_Behavior_Field_Haveable
	 *
	 * @param  Jelly   $model 
	 * @param  array   $ids
	 * @return boolean
	 * @author Jonathan Geiger
	 */
	public function has($model, $ids)
	{
		$in = $this->_in($model, TRUE);
		
		foreach ($ids as $id)
		{
			if (!in_array($id, $in))
			{
				return FALSE;
			}
		}
		
		return TRUE;
	}
		
	/**
	 * Returns either an array or unexecuted query to find 
	 * which columns the model is "in" in the join table
	 *
	 * @param  Jelly   $model 
	 * @param  boolean $as_array 
	 * @return mixed
	 * @author Jonathan Geiger
	 */
	protected function _in($model, $as_array = FALSE)
	{
		$result = Jelly::select($this->through['model'])
				->select($this->through['columns'][1])
				->where($this->through['columns'][0], '=', $model->id());
				
		if ($as_array)
		{
			$result = $result
						->execute(Jelly_Meta::get($model)->db())
						->as_array(NULL, $this->through['columns'][1]);
		}
		
		return $result;
	}
	
	/**
	 * Adds the "ids" variable to the view data
	 *
	 * @param  string $prefix
	 * @param  array  $data
	 * @return View
	 * @author Jonathan Geiger
	 */
	public function input($prefix = 'jelly/field', $data = array())
	{
		$data['ids'] = array();
		
		foreach ($data['value'] as $model)
		{
			$data['ids'][] = $model->id();
		}
		
		return parent::input($prefix, $data);
	}
}