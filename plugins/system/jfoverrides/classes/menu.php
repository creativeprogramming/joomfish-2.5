<?php
/**
 * @copyright	Copyright (C) 2005 - 2012 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access.
defined('_JEXEC') or die;
jimport('joomfish.manager');
JLoader::register('JFModelRoute', JPATH_ADMINISTRATOR  . '/components/com_joomfish/models/JFRoute.php' );
jimport('joomla.application.component.model');
/**
 * JMenu class
 *
 * @package		Joomla.Site
 * @subpackage	Application
 * @since		1.5
 */
class JMenuSite extends JMenu
{	
	public static $_menuitems = array();
	
	/**
	 * Loads the entire menu table into memory.
	 *
	 * @return array
	 */
	public function load()
	{	
		// @Todo cache this
		$jlanguage = JFactory::getConfig()->getValue('language');
		
		if(isset(self::$_menuitems[$jlanguage])) {
			$this->_items = self::$_menuitems[$jlanguage];
			return;
		}
		
		// Initialise variables.
		$db		= JFactory::getDbo();
		$app	= JApplication::getInstance('site');
		$query	= $db->getQuery(true);

		$query->select('m.id, m.menutype, m.title, m.alias, m.note, m.path AS route, m.link, m.type, m.level, m.language');
		$query->select('m.browserNav, m.access, m.params, m.home, m.img, m.template_style_id, m.component_id, m.parent_id');
		$query->select('e.element as component');
		$query->from('#__menu AS m');
		$query->leftJoin('#__extensions AS e ON m.component_id = e.extension_id');
		$query->where('m.published = 1');
		$query->where('m.parent_id > 0');
		$query->where('m.client_id = 0');
		$query->order('m.lft');

		// Set the query
		$db->setQuery($query);
		if (!($this->_items = $db->loadObjectList('id'))) {
			JError::raiseWarning(500, JText::sprintf('JERROR_LOADING_MENUS', $db->getErrorMsg()));
			return false;
		}

		foreach($this->_items as &$item) {
			// Get parent information.
			$parent_tree = array();
			if (isset($this->_items[$item->parent_id])) {
				$parent_tree  = $this->_items[$item->parent_id]->tree;
			}

			// Create tree.
			$parent_tree[] = $item->id;
			$item->tree = $parent_tree;

			// Create the query array.
			$url = str_replace('index.php?', '', $item->link);
			$url = str_replace('&amp;', '&', $url);

			parse_str($url, $item->query);
		}
		
		
		
		// @Todo move this to plugin
		JModel::getInstance('JFModelRoute')->fixMenuItemRoutes($this->_items, null);
		
		// throw away everything that is not * or default as the rest is there only for translations
		$deflang = JoomFishManager::getInstance()->getDefaultLanguage();
		
		foreach($this->_items as &$item) {
			if ($item->language != '*' && $item->language != $deflang ) {
				unset($this->_items[(string)$item->id]);
			} else if ($item->language == $deflang) {
				$item->language = '*'; // either this or override JRouterSite line 222 that has * hardcoded
			}
		}
		
		self::$_menuitems[$jlanguage] = $this->_items;

	}

	/**
	 * Gets menu items by attribute
	 *
	 * @param	string	$attributes	The field name
	 * @param	string	$values		The value of the field
	 * @param	boolean	$firstonly	If true, only returns the first item found
	 *
	 * @return	array
	 */
	public function getItems($attributes, $values, $firstonly = false)
	{
		$attributes = (array) $attributes;
		$values 	= (array) $values;
		$app		= JApplication::getInstance('site');

		if ($app->isSite())
		{
			// Filter by language if not set
			if (($key = array_search('language', $attributes)) === false)
			{
				if ($app->getLanguageFilter())
				{
					$attributes[] 	= 'language';
					$values[] 		= array(JFactory::getLanguage()->getTag(), JoomFishManager::getDefaultLanguage(), '*');
				}
			}
			elseif ($values[$key] === null)
			{
				unset($attributes[$key]);
				unset($values[$key]);
			}

			// Filter by access level if not set
			if (($key = array_search('access', $attributes)) === false)
			{
				$attributes[] = 'access';
				$values[] = JFactory::getUser()->getAuthorisedViewLevels();
			}
			elseif ($values[$key] === null)
			{
				unset($attributes[$key]);
				unset($values[$key]);
			}
		}

		return parent::getItems($attributes, $values, $firstonly);
	}

	/**
	 * Get menu item by id
	 *
	 * @param	string	$language	The language code.
	 *
	 * @return	object	The item object
	 * @since	1.5
	 */
	public function getDefault($language = '*')
	{
		if (array_key_exists($language, $this->_default) && JApplication::getInstance('site')->getLanguageFilter()) {
			return $this->_items[$this->_default[$language]];
		}
		elseif (array_key_exists('*', $this->_default)) {
			return $this->_items[$this->_default['*']];
		}
		else {
			return 0;
		}
	}
	
	/**
	 * Returns a JMenu object
	 *
	 * @param   string  $client   The name of the client
	 * @param   array   $options  An associative array of options
	 *
	 * @return  JMenu  A menu object.
	 *
	 * @since   11.1
	 */
	public static function getInstance($client, $options = array())
	{
		if (empty(parent::$instances[$client]))
		{
			//Load the router object
			$info = JApplicationHelper::getClientInfo($client, true);

	
				// Create a JPathway object
				$classname = 'JMenu' . ucfirst($client);
				$instance = new $classname($options);

	
			parent::$instances[$client] = & $instance;
		}
	
		return parent::$instances[$client];
	}

}
