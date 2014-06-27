<?php

/**
*
* @package Quick Style
* @copyright (c) 2014 PayBas
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
* Based on the original Prime Quick Style by Ken F. Innes IV (primehalo)
*
*/

namespace paybas\quickstyle\event;

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
    exit;
}

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver */
	protected $db;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;
	
	/** @var string phpBB root path */
	protected $root_path;
	
	/** @var string PHP extension */
	protected $phpEx;

	protected $enabled;
	protected $default_loc;
	protected $allow_guests;

	public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver $db, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, $root_path, $phpEx)
	{
		$this->config = $config;
		$this->db = $db;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->root_path = $root_path;
		$this->phpEx = $phpEx;

		// Setup the common settings
		$this->enabled = ($config['override_user_style'] == true) ? false : true;
		$this->default_loc = isset($config['quickstyle_default_loc']) ? (int)$config['quickstyle_default_loc'] : true;
		$this->allow_guests = isset($config['quickstyle_allow_guests']) ? (int)$config['quickstyle_allow_guests'] : true;
	}

	static public function getSubscribedEvents()
	{
		return array(
			'core.page_header_after'			=> 'select_style_form',
			'core.ucp_display_module_before'	=> 'switch_style',
			'core.user_setup'					=> 'set_guest_style', 
		);
	}

	/**
	* The UCP event is only triggered when the user is logged in, so we have to set the guest cookie using some other event
	*/
	public function switch_style()
	{
		if ($this->enabled && $style = $this->request->variable('quick_style', 0))
		{
			$redirect_url = $this->request->variable('redirect', append_sid("{$this->root_path}index.$this->phpEx"));
			$style = ($this->config['override_user_style']) ? $this->config['default_style'] : $style;

			$sql = 'UPDATE ' . USERS_TABLE . ' SET user_style = ' . intval($style) . ' WHERE user_id = ' . $this->user->data['user_id'];
			$this->db->sql_query($sql);

			redirect($redirect_url);
		}
	}

	/**
	* Assing the style switcher form variables
	*/
	public function select_style_form()
	{
		if ($this->enabled && ($this->allow_guests || $user->data['is_registered']))
		{
			$current_style = ($this->user->data['is_registered']) ? $this->user->data['user_style'] : $this->request_cookie('style', $this->user->data['user_style']);
			$style_options = style_select($this->request->variable('style', (int)$current_style));
			if (substr_count($style_options, '<option') > 1)
			{
				$this->user->add_lang_ext('paybas/quickstyle', 'quickstyle');

				$redirect = '&amp;redirect=' . urlencode(str_replace('&amp;', '&', build_url(array('_f_', 'style'))));
				$this->template->assign_var('S_QUICK_STYLE_ACTION', append_sid("{$this->root_path}ucp.$this->phpEx", 'i=prefs&amp;mode=personal' .  $redirect));
				$this->template->assign_var('S_QUICK_STYLE_OPTIONS', ($this->config['override_user_style']) ? '' : $style_options);
				$this->template->assign_var('S_QUICK_STYLE_DEFAULT_LOC', $this->default_loc);
			}
		}
	}

	/**
	* Handle style switching by guests (not logged in visitors)
	*/
	public function set_guest_style($event)
	{
		if ($this->enabled && $this->allow_guests && $this->user->data['user_id'] == ANONYMOUS)
		{
			$style = $event['style_id'];

			// Set the style to display
			$event['style_id'] = ($style) ? $style : $this->request_cookie('style', intval($this->user->data['user_style']));

			// Set the cookie (and redirect) when the style is switched
			if ($style = $this->request->variable('quick_style', 0))
			{
				$redirect_url = $this->request->variable('redirect', append_sid("{$this->root_path}index.$this->phpEx"));
				$style = ($this->config['override_user_style']) ? $this->config['default_style'] : $style;

				$this->user->set_cookie('style', $style, 0);

				redirect($redirect_url);
			}
		}
	}

	/**
	*/
	public function request_cookie($name, $default = null)
	{
		$name = $this->config['cookie_name'] . '_' . $name;
		return $this->request->variable($name, $default, false, 3);
	}
}