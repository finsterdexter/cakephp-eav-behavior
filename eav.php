<?php
/*
This behavior assumes we have a model (the entity table) and an model_attribute table (the attribute table) and a join table between the two (an EAV table).

for example: widgets, widget_attributes, and widgets_widget_attributes

This behavior also requires that the Widget model has a HABTM association with WidgetAttribute (the name of the associated table can be controlled with the 'with' setting in the $actsAs array).

requires the name of the HABTM association to match the with

*/
class EavBehavior extends ModelBehavior
{
	
	function setup(&$Model, $settings = array()) {
		if (!isset($this->settings[$Model->alias])) {
			$this->settings[$Model->alias]['with'] = $Model->alias . 'Attribute';
			$this->settings[$Model->alias]['join_alias'] = Inflector::singularize(Inflector::camelize($Model->hasAndBelongsToMany[$this->settings[$Model->alias]['with']]['joinTable']));
		}
		$this->settings[$Model->alias] = array_merge(
			$this->settings[$Model->alias], (array)$settings);
			
		if (!isset($Model->hasAndBelongsToMany[$this->settings[$Model->alias]['with']]))
		{
			// bind the $Model to $ModelAttribute with bind model?
		}
	}
	
	
	function afterFind(&$Model, $results, $primary)
	{
		// $attributes = $Model->{$this->settings[$Model->alias]['with']}->find('all');
		extract($this->settings[$Model->alias]);
		if (!Set::matches('/'.$with, $results)) {
			// no attributes found, I think?
			return;
		}
		foreach ($results as $i => $item) {
			foreach ($item[$with] as $j => $field) {
				$results[$i][$Model->alias][$field['key']] = $field[$join_alias]['val'];
			}
		}
		return $results;
		
	}
	
	
	function beforeSave(&$Model)
	{
		// theroretically, we could do some attribute validation processing here... 
		// maybe from a 'validation_method' field saved in the attribute table
		return true;
	}
	
	
	function afterSave(&$Model, $created)
	{
		extract($this->settings[$Model->alias]);

		// get a $Model id
		if (isset($Model->data[$Model->alias]['id']) && !empty($Model->data[$Model->alias]['id']))
		{
			$user_id = $Model->data[$Model->alias]['id'];
		}
		else if ($created)
		{
			$user_id = $Model->getLastInsertId();
		}
		
		// get all attributes and parse those out from incoming data and save them separately
		$attributes = $Model->{$with}->find('all');
		foreach ($attributes as $attribute)
		{
			if (isset($Model->data[$Model->alias][$attribute[$with]['key']]) && !empty($Model->data[$Model->alias][$attribute[$with]['key']]))
			{
				// clear value for this attribute and re-add
				$Model->{$join_alias}->deleteAll(array('user_id' => $user_id, 'user_attribute_id' => $attribute[$with]['id']), false, false);
				$attribute_data = array(
					"$join_alias" => array(
						'user_id' => $user_id,
						'user_attribute_id' => $attribute[$with]['id'],
						'val' => $Model->data[$Model->alias][$attribute[$with]['key']]
					)
				);
				$Model->{$join_alias}->create();
				$Model->{$join_alias}->save($attribute_data);
			}
		}
	}
	
	// I don't think we need to call delete callbacks since cascade works properly
	
	// function beforeDelete(&$Model, $cascade)
	// {
	// 	
	// }
	// 
	// 
	// function afterDelete(&$Model)
	// {
	// 	
	// }


	// not sure what to do with onError yet

	// function onError(&$Model)
	// {
	// 	
	// }
	
	
	function addField(&$Model, $field_name)
	{
		extract($this->settings[$Model->alias]);
		
		$field_exists = $Model->{$with}->find('count', array('conditions' => array('key' => $field_name)));
		if ($field_exists)
		{
			// field already exists
			return false;
		}
		else
		{
			$Model->{$with}->create();
			$field_data = array($with => array('key' => $field_name));
			$Model->{$with}->save($field_data);
			return true;
		}
	}
	
	
	function deleteField(&$Model, $attribute_id)
	{
		extract($this->settings[$Model->alias]);
		
		if (!is_numeric($attribute_id))
		{
			// I suppose attribute_id could be a field name... why not
			$attribute = $Model->{$with}->find('first', array('conditions' => array('key' => $attribute_id)));
			$attribute_id = $attribute[$with]['id'];
		}
		
		return $Model->{$with}->delete($attribute);
	}
	
	
	
}