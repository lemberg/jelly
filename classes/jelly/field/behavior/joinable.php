<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Declares a field that can be joined using Jelly_Model's with()
 * 
 * The with() method is expected to complete the join() clause.
 *
 * @package Jelly
 */
interface Jelly_Field_Behavior_Joinable
{	
	/**
	 * This method should add a join() and on() clause 
	 * to the model to finish the query.
	 * 
	 * For examples, check out belongsTo and hasOne's implementations.
	 * 
	 * @param Jelly  $model       The model to apply the join to
	 * @param string $relation    The name of the model that you are joining
	 * @param string $target_path The target's alias in the join clause
	 * @param string $parent_path The parent's alias in the join clause
	 * @return void
	 */
	public function with($model);
}
