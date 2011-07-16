<?php
/**
 * Ionize
 *
 * @package		Ionize
 * @author		Ionize Dev Team
 * @license		http://ionizecms.com/doc-license
 * @link		http://ionizecms.com
 * @since		Version 0.92
 *
 */

/**
 * Ionize Tagmanager Class
 *
 * Gives a controller Ionize basic FTL tags 
 *
 * @package		Ionize
 * @subpackage	Libraries
 * @category	TagManager Libraries
 *
 */
require_once APPPATH.'libraries/ftl/parser.php';
require_once APPPATH.'libraries/ftl/arraycontext.php';

class TagManager
{
	protected static $_inited = FALSE;

	protected static $tags = array();
	
	protected static $module_folders = array();
	
	protected $trigger_else = 0;
	
	static $ci;

//	protected static $_cache = array();

	/*
	 * Extended fields prefix. Needs to be the same as the one defined in /models/base_model
	 *
	 */
	protected static $extend_field_prefix = 'ion_';
	
	public static $context;
	
	public static $tag_prefix = 'ion';

	public static $view = '';

	/**
	 * The tags with their corresponding methods that this class provides (selector => methodname).
	 * 
	 * Add extra in subclasses to provide additional tags.
	 * 
	 * @var array
	 */
	public static $tag_definitions = array
	(
		'debug' =>				'tag_debug',
		'field' =>				'tag_field',
		'list' =>				'tag_list',
		'config' => 			'tag_config',
		'base_url' =>			'tag_base_url',
		'partial' => 			'tag_partial',
		'widget' =>				'tag_widget',
		'translation' => 		'tag_translation',
		'name' => 				'tag_name',
		'site_title' => 		'tag_site_title',
		'meta_keywords' => 		'tag_meta_keywords',
		'meta_description' => 	'tag_meta_description',
		'setting' => 			'tag_setting',
		'time' =>				'tag_time',
		'if' =>					'tag_if',
		'else' =>				'tag_else',
		'set' =>				'tag_set',
		'get' =>				'tag_get',
		'php' =>				'tag_php',
		'jslang' =>				'tag_jslang',
		
		'global:get' =>			'tag_global_get'
	);


	// ------------------------------------------------------------------------


	/**
	 * Initializes the FTL Manager.
	 * 
	 * @return void
	 */
	public static function init()
	{
		if(self::$_inited)
		{
			return;
		}
		self::$_inited = TRUE;
		
		self::$ci =& get_instance(); 
		
		self::$context = new FTL_ArrayContext();

		// Inlude array of module definition. This file is generated by module installation in Ionize.
		// This file contains definition for installed modules only.
		include APPPATH.'config/modules.php';
		
		
		// Put modules arrays keys to lowercase
		if (!empty($modules))
			self::$module_folders = array_combine(array_map('strtolower', array_values($modules)), array_values($modules));
		
		
		// Loads automatically all installed modules tags
		foreach (self::$module_folders as $module)
		{
			self::autoload_module_tags($module.'_Tags');
		}
		
		// Load automatically all TagManagers defined in /libraries/Tagmanager
		$tagmanagers = glob(APPPATH.'libraries/Tagmanager/*'.EXT);
		
		foreach ($tagmanagers as $tagmanager)
		{
			self::autoload(array_pop(explode('/', $tagmanager)));
		}
		
		self::add_globals('TagManager');
		self::add_tags();
		self::add_module_tags();
	}


	// ------------------------------------------------------------------------
	
	
	/**
	 * Autoloads tags from core TagManagers
	 * located in /libraries/Tagmanager
	 *
	 */
	public static function autoload($file_name)
	{
		$class = 'tagmanager_' . strtolower(str_replace(EXT, '', $file_name));

		require_once APPPATH.'libraries/Tagmanager/'.$file_name;

		// Get public vars
		$vars = get_class_vars($class);

		$tag_definitions = $vars['tag_definitions'];

		// Use of module name as namespace for the module to avoid modules tags collision
		foreach ($tag_definitions as $tag => $method)
		{
			// Regular tag declaration					
			self::$tags[$tag] = $class.'::'.$method;
		}
	}
	

	// ------------------------------------------------------------------------
	

	/**
	 * Autoloads tag carrying classes from modules.
	 * 
	 * @param  string	<module_name>_<tag_definition_file_name>
	 * @return bool
	 */
	public static function autoload_module_tags($class)
	{
		$class = strtolower($class);

		if(FALSE !== $p = strpos($class, '_'))
		{
			// Module name
			$module = substr($class, 0, $p);
			
			// Class file name (usually 'tags')
			$file_name = substr($class, $p + 1);
		}
		else
		{
			return FALSE;
		}

		/* If modules are installed : Get the modules tags definition
		 * Modules tags definition must be stored in : /modules/your_module/libraires/tags.php
		 * 
		 */
		if(isset(self::$module_folders[$module]))
		{
			// Only load the tags definition class if the file exists.
			if(file_exists(MODPATH.self::$module_folders[$module].'/libraries/'.$file_name.EXT))
			{
				require_once MODPATH.self::$module_folders[$module].'/libraries/'.$file_name.EXT;

				// Get tag definition class name
				$methods = get_class_methods($class);
				
				// Get public vars
				$vars = get_class_vars($class);
				
				// Store tags definitions into self::$tags
				// add module enclosing tag
				self::$tags[$module] = $class.'::index';

				
				// Use of module name as namespace for the module to avoid modules tags collision
				foreach ($methods as $method)
				{																
					// Allow to extend core tags using "tag_extension_map" static array
					
					if (isset($vars["tag_extension_map"]) && isset($vars["tag_extension_map"][$method]))
					{
						self::$tags[$vars["tag_extension_map"][$method]] = $class.'::'.$method;
					}
					
					// Regular tag declaration					
					else
					{
						self::$tags[$module.':'.$method] = $class.'::'.$method;
					}
				}

				return TRUE;
			}
			else
			{
				log_message('warning', 'Cannot find tag definitions for module "'.self::$module_folders[$module].'".');
			}
		}
	}
	
	
	// ------------------------------------------------------------------------
	
	
	/**
	 * Adds tags from modules.
	 * 
	 * @param  FTL_Context
	 * @return void
	 */
	public static function add_module_tags()
	{
		foreach(self::$tags as $selector => $callback)
		{
			self::$context->define_tag($selector, $callback);
		}
	}
	
	
	// ------------------------------------------------------------------------
	
	
	/**
	 * Adds the tags for the current class and loaded classes
	 * 
	 * @param  FTL_Context
	 * @return void
	 */
	public final function add_tags()
	{
		foreach(self::$tag_definitions as $t => $m)
		{
			self::$context->define_tag($t, array(__CLASS__, $m));
		}
	}
	
	
	// ------------------------------------------------------------------------


	/**
	 * Adds global tags to the context.
	 * 
	 * @param  FTL_Context
	 * @return void
	 */
	public function add_globals()
	{
		// Add all basic settings to the globals
		/*
		$settings = Settings::get_settings();	

		foreach($settings as $k=>$v)
		{
			// Do not add the languages array
			if ( ! is_array($v))
				$con->globals->$k = $v;	
		}
		*/

		// Stores vars
		self::$context->globals->vars = array();
		
		// Global settings
		self::$context->globals->site_title = Settings::get('site_title');
		self::$context->globals->google_analytics = Settings::get('google_analytics');
		
		// Theme
		self::$context->globals->theme = Theme::get_theme();
		self::$context->globals->theme_url = base_url() . Theme::get_theme_path();
		
		// Current Lang code
		self::$context->globals->current_lang = Settings::get_lang();
		
		// Menus
		self::$context->globals->menus = Settings::get('menus');
	}

	
	
	public static function parse($string)
	{
		$p = new FTL_Parser(self::$context, array('tag_prefix' => self::$tag_prefix));

		return $p->parse($string);
	}



	public static function render($view = FALSE, $return = false)
	{
		$ci =& get_instance();

		// Loads the view to parse
		$view = ($view != FALSE) ? $view : self::$view;
		$parsed = Theme::load($view);

		// We can now check if the file is a PHP one or a FTL one
		if (substr($parsed, 0, 5) == '<?php')
		{
			$parsed = $ci->load->view($view, array(), true);					
		}
		else
		{
			$parsed = self::parse($parsed, self::$context);

			if (Connect()->is('editors') && Settings::get('display_connected_label') == '1' )
			{
				$injected_html = $ci->load->view('core/logged_as_editor', array(), true);	
				
				$parsed = str_replace('</body>', $injected_html, $parsed);
			}
		}
		
		// Full page cache ?
		if (isset($context->globals->page['_cached']))
		{
			/*
			 * Write the full page cache file
			 *
			 */
		}
		
		
		// Returns the result or output it directly
		if ($return)
			return $parsed;
		else
			$ci->output->set_output($parsed);
	}


	/**
	 * Adds a var to the global vars array
	 * Useful to send a variable to a tag.
	 * 
	 */
	public static function set_global($name, $value)
	{
		self::$context->globals->vars[$name] = $value;
	}

	// ------------------------------------------------------------------------


	public static function has_cache($tag)
	{
	} 		


	// ------------------------------------------------------------------------


	/**
	 * Cache or returns one tag cache
	 *
	 *
	 */
	public static function get_cache($tag)
	{
		$id = self::get_tag_cache_id($tag);

		return Cache()->get($id);
	} 		


	// ------------------------------------------------------------------------

		
	/**
	 * Cache or returns one tag cache
	 *
	 *
	 */
	public static function set_cache($tag, $output)
	{
		if (isset($tag->attr['nocache'])) return FALSE;
		
		$id = self::get_tag_cache_id($tag);
		
		Cache()->store($id, $output);
	} 		


	// ------------------------------------------------------------------------

	
	/**
	 * Memory request micro cache
	 * Stores one tag result in the local $_cache var
	 * Avoid calling 2 times one same process.
	 * 
	public static function set_micro_cache($tag)
	{
		$key = self::get_tag_cache_id($tag);
		
		self::$_cache[$key] = $value;
	}
	
	 */

	// ------------------------------------------------------------------------

	
	/**
	 * Returns one tag's micro cache
	 *
	public static function get_micro_cache($tag)
	{
		$key = self::get_tag_cache_id($tag);
	
		if ( ! empty(self::$_cache[$key]))
			return self::$_cache[$key];
		
		return FALSE;
	}
	 */


	// ------------------------------------------------------------------------

	
	/**
	 * Returns one tag unique ID, regarding the tag attributes
	 *
	 *
	 */
	public static function get_tag_cache_id($tag)
	{
		if (isset($tag->attr['nocache'])) return FALSE;		
	
		$ci =& get_instance();
		
		$uri =	config_item('base_url').				// replaced $ci->config->item(....
				Settings::get_lang('current').
				config_item('index_page').
				$ci->uri->uri_string();
		
		asort($tag->attr);
		
		$uri .= serialize($tag->attr);

		return $tag->name . $uri;
	}
	


	// ------------------------------------------------------------------------


	/** 
	 * Get the current page data.
	 * 
	 * @param	FTL_Context		FTL_ArrayContext array object
	 * @param	string			Page name
	 * @return	array			Array of the page data. Can be empty.
	 */
	protected static function get_current_page($page_name)
	{
		// Ignore the page named 'page' and get the home page
		if ($page_name == 'page')
		{
			return self::get_home_page();
		}
		else
		{
			return self::get_page($page_name);
		}
	}


	// ------------------------------------------------------------------------


	/**
	 * Get the website Home page
	 * The Home page is the first page from the main menu (ID : 1)
	 * 
	 * @param	FTL_Context		FTL_ArrayContext array object
	 * @return	Array			Home page data array or an empty array if no home page is found
	 */
	protected static function get_home_page()
	{
		if( ! empty(self::$context->globals->pages))
		{
			foreach(self::$context->globals->pages as $page)
			{
				if ($page['home'] == 1)
				{
					return $page;
				}
			}
			
			// No Home page found : Return the first page of the menu 1
			foreach(self::$context->globals->pages as $p)
			{
				if ($p['id_menu'] == 1)
					return $p;
			}
		}

		return array();
	}


	// ------------------------------------------------------------------------


	/**
	 * Get one page regarding to its name
	 * 
	 * @param	string	Page name
	 * @return	array	Page data array
	 */
	protected static function get_page($page_name)
	{
		foreach(self::$context->globals->pages as $p)
		{
			if ($p['url'] == $page_name)
				return $p;
		}
	
		return array();	
	}


	// ------------------------------------------------------------------------


	/**
	 * Returns a dynamic attribute value
	 * Used with attributes which can get data from a database field.
	 *
	 * @param	FTL_Binding object		The current tag object
	 * @param	String					Attributes name
	 *
	 * @return	Mixed	The attribute value of false if nothing is found
	 *
	 */
	protected static function get_attribute($tag, $attr, $return=FALSE)
	{
		// Try to get the couple array:field
		// "array" is the data array. For example "page" or "article"
		// $ar[0] : the data array name
		// $ar[1] : the field to get
		if ( ! empty($tag->attr[$attr]))
		{
			$ar = explode(':', $tag->attr[$attr]);

			// If no explode result, simply return the attribute value
			// In this case, the tag doesn't ask for a dynamic value, but just gives a value
			// (no ":" separator)
			if (!isset($ar[1]))
			{
				return $tag->attr[$attr];
			}
	
			// Here, there is a field to get
			if (isset($tag->locals->$ar[0]))
			{
				// Element can be page, article, etc.
				$element = $tag->locals->$ar[0];
			
				// First : try to get the field in the standard fields
				// exemple : $tag->locals->page[field]
				if ( ! isset($element[$ar[1]]))
				{
					// Second : Try to get the field in the extend fields
					// exemple : $tag->locals->page[ion_field]
					if ( ! isset($element[self::$extend_field_prefix.$ar[1]]))
					{
						return false;
					}
					else
					{
						// Try to get the value
						if ( ! empty($element[self::$extend_field_prefix.$ar[1]]))
						{
							return $element[self::$extend_field_prefix.$ar[1]];
						}
						return false;
					}
				}
				else
				{
					// Try to get the value.
					// Else return false
					if ( ! empty($element[$ar[1]]))
					{
						return $element[$ar[1]];
					}
					else
					{
						return false;
					}
				}
			}
		}

		return $return;
	}


	// ------------------------------------------------------------------------
	// Tags definition stars here
	// ------------------------------------------------------------------------


	/**
	 * Returns a trace of one $tag->object
	 * ONLY TO BE USED IN DEV !!!
	 *
	 */
	public static function tag_debug($tag)
	{
		// local var name
		$name = (isset($tag->attr['name']) ) ? $tag->attr['name'] : false;

		$obj = isset($tag->locals->{$name}) ? $tag->locals->{$name} : null;

		if ( ! is_null($obj) && $name != false)
		{
			trace($tag->locals->{$name});
		}	
	
		return '';
	}



	public static function tag_if($tag)
	{
		$field = ( ! empty($tag->attr['field'])) ? $tag->attr['field'] : FALSE;
		$condition = ( ! empty($tag->attr['condition'])) ? $tag->attr['condition'] : FALSE;
		$result = FALSE;

		if ($field && $condition)
		{
			$obj_name = self::get_parent_tag($tag);

			if ( ! empty($tag->locals->{$obj_name}[$field] ))
			{
				$value = $tag->locals->{$obj_name}[$field];
				eval("\$result = ('".$value."'".$condition.") ? TRUE : FALSE;");
				
				if ($result)
					return $tag->expand();
			}
		}
	}


	// ------------------------------------------------------------------------


	/**
	 * Stores a var
	 * @usage	<ion:set var="foo" value="bar" scope="<local|global>" />
	 *
	 */
	public static function tag_set($tag)
	{
		$var = ( !empty ($tag->attr['var'])) ? $tag->attr['var'] : null;
		$scope = ( !empty ($tag->attr['scope'])) ? $tag->attr['scope'] : 'locals';
		$value = ( !empty ($tag->attr['value'])) ? $tag->attr['value'] : null;

		if ( ! is_null($var))
		{
			$tag->{$scope}->{$var} = $value;
		}
		
		return $value;
	}

	
	// ------------------------------------------------------------------------
	
	
	/**
	 * Gets a stored var
	 * @usage	<ion:get var="foo" scope="<local|global>" />
	 *
	 */
	public static function tag_get($tag)
	{
		$var = ( !empty ($tag->attr['var'])) ? $tag->attr['var'] : null;
		$scope = ( !empty ($tag->attr['scope'])) ? $tag->attr['scope'] : 'locals';

		if ( ! is_null($var) && !empty($tag->{$scope}->vars[$var]))
		{
			return $tag->{$scope}->vars[$var];
		}
		
		return '';
	}

	
	// ------------------------------------------------------------------------


	/**
	 * Returns the base URL of the website, with or without lang code in the URL
	 *
	 */
	public static function tag_base_url($tag) 
	{
		// don't display the lang URL (by default)
		$lang_url = false;

		// The lang code in the URL is forced by the tag
		$force_lang = (isset($tag->attr['force_lang'])) ? true : false;


		// Set all languages online if connected as editor or more
		if( Connect()->is('editors', true))
		{
			Settings::set_all_languages_online();
		}

		if (isset($tag->attr['lang']) && strtolower($tag->attr['lang']) == 'true' OR $force_lang === true)
		{
			if (count(Settings::get_online_languages()) > 1 )
			{
				// forces the lang code to be in the URL, for each language
//				if ($force_lang === true)
//				{
				return base_url() . Settings::get_lang() .'/';
//				}
				// More intelligent : Detects if the current lang is the default one and don't return the lang code this lang code
/*
				else
				{
					if (Settings::get_lang() != Settings::get_lang('default'))
					{
						return base_url() . Settings::get_lang() .'/';
					}
				}
*/
			}
		}

		return base_url();
	}

	
	// ------------------------------------------------------------------------
	
	
	public static function tag_list($tag)
	{
		$objects = (isset($tag->attr['objects']) ) ? $tag->attr['objects'] : FALSE;
		$from = (isset($tag->attr['from']) ) ? $tag->attr['from'] : FALSE;
		$field = (isset($tag->attr['field']) ) ? $tag->attr['field'] : FALSE;
		$separator = (isset($tag->attr['separator']) ) ? $tag->attr['separator'] : ',';
		$filter = (isset($tag->attr['filter']) ) ? $tag->attr['filter'] : FALSE;
		$prefix = (isset($tag->attr['prefix']) ) ? $tag->attr['prefix'] : '';
		$filters = NULL;

		if ($objects != FALSE)
		{
			if ($from == FALSE)
			{
				$from = self::get_parent_tag($tag);
			}
			if ($from == FALSE)
			{
				$from = 'page';
			}
			
			$obj = isset($tag->locals->{$from}) ? $tag->locals->{$from} : NULL;

			if ( ! is_null($obj) && $field != FALSE)
			{
				if ( ! empty($obj[$objects]))
				{

// trace($obj[$objects]);

					// Set the prefix
					$prefix = (function_exists($prefix)) ? call_user_func($prefix) : $prefix;

					// Prepare filtering
					if ($filter)
					{
						$filters = array();
						$operators = array ('!=', '=');
						
						$filter_list = explode(',', str_replace(' ', '', $filter));
						
						foreach ($operators as $op)
						{
							foreach($filter_list as $key => $fl)
							{
								$fr = explode($op, $fl);
								if ( $fr[0] !== $fl )
								{
									$filters[] = array($fr[0], $op, $fr[1]);
									unset($filter_list[$key]);
								}
							}
						}
					}
					
					$fields = array();
					foreach($filters as $filter)
					{
						// $fields += array_filter($obj[$objects], create_function('$row', 'return $row["'.$filter[0].'"]'.$filter[1].'="'.$filter[2].'";'));
						foreach($obj[$objects] as $ob)
						{
							// TODO : Rewrite
							// Because the operator isn't takken in account
							//
							// trace($ob[$filter[0]].$filter[1].'='.$filter[2]);
							eval("\$result = '" . $ob[$filter[0]]."'".$filter[1]."='".$filter[2]."';");

							if ($result)
							{
								$fields[] = $ob;
							}
						}
					}
						

					$return = array();
					foreach($fields as $key => $row)
					{
						if ( ! empty($row[$field]))
						{
							$return[] = $prefix.$row[$field];
						}
					}
					// Safe about prefix
					unset($tag->attr['prefix']);
					return self::wrap($tag, implode($separator, $return));
				}
			}
		}
		
		return '';
	}


	// ------------------------------------------------------------------------


	/**
	 * Get one field from a data array
	 * Used to get extended fields values
	 * First, this tag tries to get and extended field value.
	 * If nothing is found, he tries to get a core field value
	 * It is possible to force the core value by setting the "core" attribute to true
	 *
	 * @usage : <ion:field name="<field_name>" from="<table_name>" <core="true"> />
	 *
	 * @return String	The field value
	 *
	 */
	public static function tag_field($tag)
	{
		// Object type : page, article, media
		$from = (isset($tag->attr['from']) ) ? $tag->attr['from'] : FALSE;
		
		// Name of the field to get
		$name = (isset($tag->attr['name']) ) ? $tag->attr['name'] : FALSE;
		
		// Format of the returned field (useful for dates)
		$format = (isset($tag->attr['format']) ) ? $tag->attr['format'] : FALSE;
		
		// Force to get the field name from core. To be used when the field has the same name as one core field
		$force_core = (isset($tag->attr['core']) && $tag->attr['core'] == TRUE ) ? TRUE : FALSE;

		// Current tag parent tag
		if ($from == FALSE && $force_core == FALSE )
		{
			$from = self::get_parent_tag($tag);
		}

		$obj = isset($tag->locals->{$from}) ? $tag->locals->{$from} : NULL;

		if ( ! is_null($obj) && $name != FALSE)
		{
			$value = '';

			// If force core field value, return it.
			if ($force_core === TRUE && !empty($obj[$name]))
			{
				return self::wrap($tag, $obj[$name]);
			}
			
			// Try to get the extend field value			
			if ( ! empty($obj[self::$extend_field_prefix.$name]))
			{
				// If "format" attribute is defined, suppose the field is a date ...
				if ($format && $obj[self::$extend_field_prefix.$name] != '')
				{
					return self::wrap($tag, (self::format_date($tag, $obj[self::$extend_field_prefix.$name])));
				}

				return self::wrap($tag, $obj[self::$extend_field_prefix.$name]);
			}
			// Else, get the core field value
			else if (!empty($obj[$name]))
			{
				return self::wrap($tag, $obj[$name]);
			}
		}
		
		// Error
		return '';
		// return self::show_tag_error($tag->name, '<b>The "from" attribute is mandatory</b>');
	}
	
	
	// ------------------------------------------------------------------------


	/**
	 * Loads a partial view from a FTL tag
	 * Callback function linked to the tag <ion:partial />
	 * 
	 * @param	FTL_Binding		The binded tag to parse
	 *
	 */
	public static function tag_partial($tag)
	{
		// Compatibility reason
		$view = ( ! empty($tag->attr['view'])) ?$tag->attr['view'] : NULL;
		
		if (is_null($view))
		{
			$view = ( ! empty($tag->attr['path'])) ?$tag->attr['path'] : NULL;
		}
		
		if ( ! is_null($view))
		{
			if(isset($tag->attr['php']) && $tag->attr['php'] == 'true')
			{
				$data = ( ! empty($tag->attr['data'])) ? $tag->attr['data'] : array();
				return self::$ci->load->view($view, $data, true);
			}
			else
			{
				$file = Theme::load($view);
				return $tag->parse_as_nested($file);
			}
		}
		else
		{
			show_error('TagManager : Please use the attribute <b>"view"</b> when using the tag <b>partial</b>');
		}
	}


	// ------------------------------------------------------------------------


	/**
	 * Loads a widget
	 * Callback function linked to the tag <ion:widget />
	 * 
	 * @param	FTL_Binding		The binded tag to parse
	 *
	 */
	public static function tag_widget($tag)
	{
		$name = $tag->attr['name'];
		
		return Widget::run($name, array_slice(array_values($tag->attr), 1)); 
	}


	// ------------------------------------------------------------------------


	/**
	 * Gets a tranlation value from a key
	 * Callback function linked to the tag <ion:translation />
	 * 
	 * @param	FTL_Binding		The binded tag to parse
	 *
	 */
	public static function tag_translation($tag)
	{
		// Kind of article : Get only the article linked to the given view
		$term = (isset($tag->attr['item'] )) ? $tag->attr['item'] : FALSE ;
		
		if ($term === FALSE)
			$term = (isset($tag->attr['term'] )) ? $tag->attr['term'] : FALSE ;
		
		if ($term !== FALSE)
		{
			// Return the auto-linked translation value
			if (array_key_exists($term, self::$ci->lang->language) && self::$ci->lang->language[$term] != '') 
			{
				return auto_link(self::$ci->lang->language[$term], 'both', true);
			}
			// Return the term index prefixed by "#" if no translation is found
			else
			{
				return '#'.$term;
			}
		}
		return;
	}
	
	
	// ------------------------------------------------------------------------


	/**
	 * Return a JSON object of all translation items and one "Lang" object which gives you access
	 * to the translations through "set" and "get" functions. 
	 * 
	 * @usage	Put this tag in the header / footer of your view : 
	 *			<ion:jslang [framework="jQuery"] />
	 *
	 *			Mootools example :
	 *
	 *			<div id="my_div"></div>
	 *
	 *			<script>
	 *				var my_text = Lang.get('my_translation_item');
	 *
	 *				$('my_div').set('text', my_text);
	 *			</script>
	 *
	 *
	 */
	public static function tag_jslang($tag)
	{
		// Returned Object name
		$object = ( ! empty($tag->attr['object'] )) ? $tag->attr['object'] : 'Lang' ;

		// Files from where load the langs
		$files = ( ! empty($tag->attr['files'] )) ? explode(',', $tag->attr['files']) : array(Theme::get_theme());
		
		// JS framework
		$fm = ( ! empty($tag->attr['framework'] )) ? $tag->attr['framework'] : 'jQuery' ;
		
		// Returned language array
		$translations = array();
		
		// If $files doesn't contains the current theme lang name, add it !
		if ( ! in_array(Theme::get_theme(), $files) )
		{
			$files[] = Theme::get_theme();
		}
		
		if ((Settings::get_lang() != '') && !empty($files))
		{
			foreach ($files as $file)
			{
				$paths = array(
					APPPATH.'language/'.Settings::get_lang().'/'.$file.'_lang'.EXT,
					Theme::get_theme_path().'language/'.Settings::get_lang().'/'.$file.'_lang'.EXT
				);
				
				foreach ($paths as $path)
				{
					if (is_file($path) && '.'.end(explode('.', $path)) == EXT)
					{
						include $path;
						if ( ! empty($lang))
						{
							$translations = array_merge($translations, $lang);
							unset($lang);
						}
					}
				}
			}
		}
		$json = json_encode($translations);
		
		$js = "var $object = $json;";
		
		/*
		$.extend(Lang, {
			get: function(key) { return this[key]; },
			set: function(key, value) { this[key] = value;}
		});
		*/
		switch($fm)
		{
			case 'jQuery':
				$js .= "
					Lang.get = function (key) { return this[key]; };
					Lang.set = function(key, value) { this[key] = value;};
				";
				break;
			
			case 'mootools':
				$js .= "
					Lang.get = function (key) { return this[key]; };
					Lang.set = function(key, value) { this[key] = value;};
				";
				break;
		}
		
		return '<script type="text/javascript">'.$js.'</script>';
		
	}
	
	
	// ------------------------------------------------------------------------


	/**
	 * Gets a config value from the CI config file
	 * Callback function linked to the tag <ion:config />
	 * 
	 * @param	FTL_Binding		The binded tag to parse
	 *
	 * @usage	<ion:config item="<the_config_item>" />
	 *
	 */
	public static function tag_config($tag)
	{
		// Config item asked
		$item = (isset($tag->attr['item'] )) ? $tag->attr['item'] : FALSE ;
		$is_like = (isset($tag->attr['is_like'] )) ? $tag->attr['is_like'] : FALSE ;
	
		if ($item !== false)
		{
			if ($is_like !== FALSE && config_item($item) == $is_like)
				return $tag->expand();
			
			return config_item($item);
		}
		return;
	}
	

	// ------------------------------------------------------------------------


	/**
	 * Gets a setting value
	 * Callback function linked to the tag <ion:setting />
	 * 
	 * @param	FTL_Binding		The binded tag to parse
	 *
	 */
	public static function tag_setting($tag)
	{
		// Setting item asked
		$item = (isset($tag->attr['item'] )) ? $tag->attr['item'] : false ;
	
		if ($item !== false)
		{
			return Settings::get($item);
		}
		return;
	}
	

	// ------------------------------------------------------------------------


	/**
	 * Shared tags callback functions
	 * 
	 * @return 
	 */
	public static function tag_name($tag) 
	{
		$use_global = isset($tag->attr['use_global']) ? true : false;
		
		if ($use_global == true)
		{
			return $tag->globals->page['name'];
		}
		else
		{
			return $tag->locals->page['name'];
		}
	}


	// ------------------------------------------------------------------------


	/**
	 * Returns the local meta keywords if found, otherwise the global ones.
	 * 
	 * @param  FTL_Binding
	 * @return string
	 */
	public static function tag_site_title($tag)
	{
		return self::wrap($tag, Settings::get('site_title'));
	}


	// ------------------------------------------------------------------------


	/**
	 * Returns the local meta keywords if found, otherwise the global ones.
	 * 
	 * @param  FTL_Binding
	 * @return string
	 */
	public static function tag_meta_keywords($tag)
	{
		if( ! empty($tag->locals->page['meta_keywords']))
		{
			return $tag->locals->page['meta_keywords'];
		}
		return Settings::get('meta_keywords');
	}


	// ------------------------------------------------------------------------


	/**
	 * Returns the local meta keywords if found, otherwise the global ones.
	 * 
	 * @param  FTL_Binding
	 * @return string
	 */
	public static function tag_meta_description($tag)
	{
		if( ! empty($tag->locals->page['meta_description']))
		{
			return $tag->locals->page['meta_description'];
		}
		return Settings::get('meta_description');
	}
	

	// ------------------------------------------------------------------------


	/**
	 * Returns the local meta keywords if found, otherwise the global ones.
	 * 
	 * @param  FTL_Binding
	 * @return string
	 */
	public static function tag_time($tag)
	{
		return md5(time());
	}	


	// ------------------------------------------------------------------------


	/**
	 * Wraps a tag value depending on the given HTML tag
	 *
	 * @example : <ion:page:title tag="<h1>" class="class" id="id" 
	 *
	 */
	protected static function wrap($tag, $value)
	{
		$open_tag = $close_tag = '';
		
		$html_tag = self::get_attribute($tag, 'tag');
		$class = self::get_attribute($tag, 'class');
		$id = self::get_attribute($tag, 'id');
		$prefix = self::get_attribute($tag, 'prefix', '');
		
		if ( ! empty($class)) $class = ' class="'.$class.'"';
		if ( ! empty($id)) $id = ' id="'.$id.'"';
		
		// helper
		$helper = (isset($tag->attr['helper']) ) ? $tag->attr['helper'] : FALSE;
		
		// php func ?
		// $php_func = ( ! empty($tag->attr['function'])) ? $tag->attr['function'] : FALSE;
		

		// Process the value through the passed in function name.
		if ( ! empty($tag->attr['function'])) $value = self::php_process($value, $tag->attr['function'] );

		if ($helper !== FALSE)
		{
			$value = self::helper_process($value, $helper);
		}

		if ($html_tag !== false)
		{
			$open_tag = '<' . $html_tag . $id . $class . '>';
			$close_tag = '</' . $html_tag .'>';
		}
		
		if ( ! empty ($value) )
			return $open_tag . $prefix . $value . $close_tag;
		else
			return '';
	}
	

	// ------------------------------------------------------------------------


	/**
	 * Format the given date and return the expanded tag
	 *
	 */
	protected static function format_date($tag, $date)
	{
		$date = strtotime($date);
		
		if ($date)
		{
			$format = ( !empty($tag->attr['format'])) ? $tag->attr['format'] : 'Y-m-d H:i:s';		
			$str = '';

			if ($format != 'Y-m-d H:i:s' && lang('dateformat_'.$format) != '#dateformat_'.$format )
			{
				$format = lang('dateformat_'.$format);
				$segments = explode(' ', $format);

				foreach($segments as $key => $segment)
				{
					$tmp = (String) date($segment, $date);

					if (preg_match('/D|l|F|M/', $segment))
						$tmp = lang(strtolower($tmp));

					$segments[$key] = $tmp;
				}
				$str = implode(' ', $segments);
			}
			else
			{
				// Get date in the wished format
				$str = (String) date($format, $date);
	
				/*
				 * Get translation, if mandatory
				 * Date translations are located in the files : /themes/your_theme/language/xx/date_lang.php
				 *
				 */
				if (preg_match('/D|l|F|M/', $format) && strlen($format) == 1)
					$str = lang(strtolower($str));		
			}

			return $str;
		}
		return $tag->expand();
	}


	// ------------------------------------------------------------------------
	
	
	
	/**
	 * Return the parent tag name or 'page' if not found
	 *
	 */
	protected static function get_parent_tag($tag)
	{
		$tag_name = 'page';
		
		// Get the tag path
		$tag_path = explode(':', $tag->nesting());

		// Remove the current tag from the path
		array_pop($tag_path);

		// If no parent, the default parent is 'page'
		$obj_tag = (count($tag_path) > 0) ? array_pop($tag_path) : $tag_name;
		
		if ($obj_tag == 'partial') $obj_tag = array_pop($tag_path);
		
		// Parent name. Removes plural from parent tag name if any.
		if (substr($obj_tag, -1) == 's')
			$tag_name = substr($obj_tag, 0, -1);
		else
			$tag_name = $obj_tag;
		
		return $tag_name;
	}


	// ------------------------------------------------------------------------


	/**
	 * Process the input through the called functions and return the result
	 * 
	 * @param	Mixed				The value to process
	 * @param	String / Array		String or array of PHP functions
	 *
	 * @return	Mixed				The processed result
	 */
	protected static function php_process($value, $functions)
	{
		if ( ! is_array($functions))
			$functions = explode(',', $functions);
		
		foreach($functions as $func)
		{
			if (function_exists($func))
				$value = $func($value);
		}
		
		return $value;
	}


	// ------------------------------------------------------------------------


	/**
	 * Process the input through the called functions and return the result
	 * 
	 * @param	Mixed				The value to process
	 * @param	String / Array		String or array of PHP functions
	 *
	 * @return	Mixed				The processed result
	 */
	protected static function helper_process($value, $helper)
	{
		$helper = explode(':', $helper);

		$helper_name = ( ! empty($helper[0])) ? $helper[0] : FALSE;
		$helper_func = ( ! empty($helper[1])) ? $helper[1] : FALSE;
		
		$helper_args = ( ! empty($helper[2])) ? explode(",", $helper[2]) : array();
		
		if($helper_name !== FALSE && $helper_func !== FALSE)
		{
			self::$ci->load->helper($helper_name);
			
			array_unshift($helper_args, $value);

			if (function_exists($helper_func))
				$value = call_user_func_array($helper_func, $helper_args);
			else
				return self::show_tag_error($tag->name, 'Error when calling <b>'.$helper_name.'->'.$helper_func.'</b>. This helper function doesn\'t exist');
		}
		
		return $value;	
	}
	
	
	// ------------------------------------------------------------------------


	/**
	 * Displays an error concerning one tag use
	 * 
	 * @param	String		Tag name
	 * @param	String		Message
	 * @param	String		Error template
	 *
	 * @return	String		Error message
	 *
	 */
	protected static function show_tag_error($tag_name, $message, $template = 'error_tag')
	{
		$message = '<p>'.implode('</p><p>', ( ! is_array($message)) ? array($message) : $message).'</p>';

		ob_start();
		include(APPPATH.'errors/'.$template.EXT);
		$buffer = ob_get_contents();

		ob_end_clean();
		return $buffer;
	}
}


TagManager::init();


/* End of file Tagmanager.php */
/* Location: /application/libraries/Tagmanager.php */