<?php
/**
 * Fuel Menu package
 *
 * @package    Fuel
 * @subpackage Menu
 * @version    1.0
 * @author     Márk Ság-Kazár
 * @license    MIT License
 * @link       https://github.com/sagikazarmark/fuel-menu
 */

namespace Menu;

class Menu
{

	/**
	 * loaded instance
	 */
	protected static $_instance = null;

	/**
	 * array of loaded instances
	 */
	protected static $_instances = array();

	/**
	 * Driver config defaults.
	 */
	protected static $_defaults = array(
		'menu' => "<ul>{menu}</ul>",
		'item' => "<li class=\"{class}\">{item}\n{submenu}</li>\n",
		'item_inner' => '<a href="{link}" title="{title}">{text}</a>',
	);

	/**
	 * Menu driver forge.
	 *
	 * @param	string|array	$template		template key for array defined in menu.template config or config array
	 * @param	array			$config		extra config array
	 * @return  Menu    instance with the 
	 */
	public static function forge($template = null, array $config = array())
	{
		$instance_name = is_string($template) ? $template : 'default';

		empty($template) and $template = \Config::get('menu.template.default', static::$_defaults);
		is_string($template) and $template = \Config::get('menu.template.'.$template, static::$_defaults);

		$template = \Arr::merge(static::$_defaults, $template);
		$config = \Arr::merge($template, $config);

		static::$_instances[$instance_name] = new static($config);

		return static::$_instances[$instance_name];
	}

	/**
	 * Return a specific instance, or the default instance (is created if necessary)
	 *
	 * @param   string  driver id
	 * @return  Menu
	 */
	public static function instance($instance = null)
	{
		if ($instance !== null)
		{
			if ( ! array_key_exists($instance, static::$_instances))
			{
				return false;
			}

			return static::$_instances[$instance];
		}

		if (static::$_instance === null)
		{
			static::$_instance = static::forge();
		}

		return static::$_instance;
	}

	protected function __construct(array $config = array())
	{
		$this->template = $config;
	}

	public function build($name)
	{
		$html = '';
		$menus = Model_Menu::query()->where('menu', $name)->where('parent', 0)->order_by('position')->get();
		$menus and $html = $this->build_menu($menus);
		return $html;
	}

	private function build_menu(array $menus, $sub = false)
	{
		$html = '';
		$prefix = $sub ? 'sub_' : '';

		foreach ($menus as $menu) {
			$submenu = null;
			$menu->children and $submenu = $this->build_menu($menu->children, true);
			$suffix = $submenu ? '_sub' : '';

			$inner = str_replace(array('{link}', '{text}', '{title}'), array($menu->link, $menu->text, $menu->title), \Arr::get($this->template, $prefix.'item_inner'.$suffix, $this->template['item_inner']));

			$item = str_replace('{item}', $inner, \Arr::get($this->template, $prefix.'item'.$suffix, $this->template['item']));

			if (preg_match_all('#\{(.*?)\}#', $item, $match))
			{
				foreach ($match[1] as $key => $value)
				{
					try
					{
						$match[1][$key] = $menu->$value;
					}
					catch (\OutOfBoundsException $e)
					{
						$match[1][$key] = null;
					}

					if ($value == 'submenu')
					{
						unset($match[0][$key]);
						unset($match[1][$key]);
					}
				}
			}

			$item = str_replace($match[0], $match[1], $item);
			$item = str_replace('{submenu}', $submenu, $item);

			$html .= $item;
		}
		
		$html = str_replace('{menu}', $html, \Arr::get($this->template, $prefix.'menu', $this->template['menu']));
		
		return $html;
	}

	public static function build_static($name)
	{
		return static::instance()->build($name);
	}

}
