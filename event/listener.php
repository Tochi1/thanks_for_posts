<?php
/**
 *
 * @package ThanksForPosts
 * @copyright (c) 2014 Sergeiy Varzaev (Палыч)  phpbbguru.net varzaev@mail.ru
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace gfksx\ThanksForPosts\event;

/**
* Event listener
*/
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\auth\auth $auth, \phpbb\template\template $template, \phpbb\user $user, \phpbb\cache\driver\driver_interface $cache, $phpbb_root_path, $php_ext, $helper)
	{
		$this->config = $config;
		$this->db = $db;
		$this->auth = $auth;
		$this->template = $template;
		$this->user = $user;
		$this->cache = $cache;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
		$this->helper = $helper;
	}

	static public function getSubscribedEvents()
	{
		return array(
			'core.index_modify_page_title'			=> 'get_thanks_list',
			'core.memberlist_view_profile'			=> 'memberlist_viewprofile',
			'core.delete_posts_in_transaction'		=> 'delete_post_thanks',
			'core.viewforum_modify_topicrow'		=> 'viewforum_output_topics_reput',
			'core.viewtopic_get_post_data'			=> 'viewtopic_handle_thanks',
			'core.viewtopic_cache_user_data'		=> 'viewtopic_alter_user_cache_data',
			'core.viewtopic_modify_post_row'		=> 'viewtopic_modify_postrow',
			'core.display_forums_modify_forum_rows'	=> 'forumlist_display_rating',
			'core.display_forums_add_template_data'	=> 'forumlist_modify_template_vars',
			'core.ucp_prefs_personal_data'			=> 'ucp_add_personal_prefs',
			'core.ucp_prefs_personal_update_data'	=> 'ucp_update_personal_prefs',
			'core.acp_users_prefs_modify_data'		=> 'acp_modify_users_prefs_data',
			'core.acp_users_prefs_modify_sql'		=> 'acp_users_prefs_modify_sql',
			'core.acp_users_prefs_modify_template_data'	=> 'acp_users_prefs_modify_template_data',
			'core.ucp_prefs_modify_common'			=> 'add_ucp_prefs_template_vars',
		);
	}

	public function get_thanks_list($event)
	{
		$this->user->add_lang_ext('gfksx/ThanksForPosts', 'thanks_mod');

		// Generate thankslist if required ...
		$thanks_list = '';
		$ex_fid_ary = array_keys($this->auth->acl_getf('!f_read', true));
		$ex_fid_ary = (sizeof($ex_fid_ary)) ? $ex_fid_ary : 0;
		if (isset($this->config['thanks_top_number']) && $this->config['thanks_top_number'])
		{
			$thanks_list = $this->helper->get_toplist_index($ex_fid_ary);
		}
		$this->template->assign_vars(array(
			'THANKS_LIST'		=> ($thanks_list != '') ? $thanks_list : false,
			'S_THANKS_LIST'		=> (isset($this->config['thanks_top_number']) && $thanks_list != '') ? true : false,
			'U_THANKS_LIST'		=> append_sid("{$this->phpbb_root_path}thankslist"),
			'L_TOP_THANKS_LIST'	=> isset($this->config['thanks_top_number']) ? sprintf($this->user->lang['REPUT_TOPLIST'], (int) $this->config['thanks_top_number']) : false,
		));
	}

	public function memberlist_viewprofile($event)
	{
		$member = $event['member'];
		$user_id = (int) $member['user_id'];

		$ex_fid_ary = array_keys($this->auth->acl_getf('!f_read', true));
		$ex_fid_ary = (sizeof($ex_fid_ary)) ? $ex_fid_ary : false;

		$this->user->add_lang_ext('gfksx/ThanksForPosts', 'thanks_mod');
		if (isset($_REQUEST['list_thanks']))
		{
			$this->helper->clear_list_thanks($user_id, request_var('list_thanks', ''));
		}
		if (isset($this->config['thanks_for_posts_version']))
		{
			$this->helper->output_thanks_memberlist($user_id, $ex_fid_ary);
		}
	}

	public function delete_post_thanks($event)
	{
		$post_ids = $event['post_ids'];
		$this->helper->delete_post_thanks($post_ids);
	}

	public function viewforum_output_topics_reput($event)
	{
		$topic_list = array();
		$topic_thanks = array();
		$max_topic_thanks = 0;
		$topic_id = $event['topic_row']['TOPIC_ID'];
		if ($max_topic_thanks = $this->helper->get_max_topic_thanks() && !empty($topic_list))
		{
			$topic_thanks = $this->helper->get_thanks_topic_number($topic_list);
		}
		if (!empty($topic_thanks))
		{
			$this->helper->get_thanks_topic_reput($topic_id, $max_topic_thanks, $topic_thanks);
		}
	}

	public function viewtopic_handle_thanks($event)
	{
		$post_list = $event['post_list'];
		$forum_id = (int) $event['forum_id'];
		$this->helper->array_all_thanks($post_list);

		if (isset($_REQUEST['thanks']) && !isset($_REQUEST['rthanks']))
		{
			$this->helper->insert_thanks(request_var('thanks', 0), $this->user->data['user_id'], $forum_id);
		}

		if (isset($_REQUEST['rthanks']) && !isset($_REQUEST['thanks']))
		{
			$this->helper->delete_thanks(request_var('rthanks', 0), $this->user->data['user_id'], $forum_id);
		}

		if (isset($_REQUEST['list_thanks']))
		{
			$this->helper->clear_list_thanks(request_var('p', 0), request_var('list_thanks', ''));
		}
	}

	public function viewtopic_alter_user_cache_data($event)
	{
		$user_cache_data = $event['user_cache_data'];
		$row = $event['row'];
		$user_cache_data = array_merge($user_cache_data, array(
				'allow_thanks_pm' => isset($row['user_allow_thanks_pm']) ? : false,
				'allow_thanks_email' => isset($row['user_allow_thanks_email']) ? : false,)
		);
		$event['user_cache_data'] = $user_cache_data;
	}

	public function viewtopic_modify_postrow($event)
	{
		$row = $event['row'];
		$postrow = $event['post_row'];
		$topic_data = $event['topic_data'];
		$forum_id = (int) $row['forum_id'];
		$poster_id = (int) $row['user_id'];

		$postrow = array_merge($postrow, array(
			'S_FORUM_THANKS'	=> ($this->auth->acl_get('f_thanks', $forum_id)) ? true : false,
		));

		$this->helper->output_thanks($poster_id, $postrow, $row, $topic_data, $forum_id);

		$event['post_row'] = $postrow;
	}

	public function forumlist_display_rating($event)
	{
		$forum_rows = $event['forum_rows'];
		$this->helper->get_max_forum_thanks();
		$forum_thanks_rating = array();
		foreach ($forum_rows as $row)
		{
			$forum_thanks_rating[] = $row['forum_id'];
		}

		$this->cache->put('_forum_thanks_rating', $forum_thanks_rating);
		$this->helper->get_thanks_forum_number();
		$this->cache->destroy('_forum_thanks_rating');
	}

	public function forumlist_modify_template_vars($event)
	{
		$forum_row = $event['forum_row'];
		$row = $event['row'];
		$forum_row = array_merge($forum_row, array(
			'S_THANKS_FORUM_REPUT_VIEW_COLUMN' => isset($this->config['thanks_forum_reput_view']) ? $this->config['thanks_forum_reput_view_column'] : false,
			'THANKS_REPUT_GRAPHIC_WIDTH'=> isset($this->config['thanks_reput_level'])? (isset($this->config['thanks_reput_height']) ? sprintf('%dpx', $this->config['thanks_reput_level']*$this->config['thanks_reput_height']) : false) : false,
		));
		if (isset($this->config['thanks_forum_reput_view']) && $this->config['thanks_forum_reput_view'])
		{
			$this->helper->get_thanks_forum_reput($row['forum_id']);
		}
		$event['forum_row'] = $forum_row;
	}

	public function ucp_add_personal_prefs($event)
	{
		$data = $event['data'];
		$data = array_merge($data, array(
			'allowthankspm'	=> request_var('allowthankspm', (bool) (isset($this->user->data['user_allow_thanks_pm']) ? $this->user->data['user_allow_thanks_pm'] : false)),
			'allowthanksemail'	=> request_var('allowthanksemail', (bool) (isset($this->user->data['user_allow_thanks_email']) ? $this->user->data['user_allow_thanks_email'] : false)),
		));
		$event['data'] = $data;
	}

	public function ucp_update_personal_prefs($event)
	{
		$sql_ary = $event['sql_ary'];
		$data = $event['data'];
		if (isset($this->user->data['user_allow_thanks_pm']) && isset($this->user->data['user_allow_thanks_email']))
		{
			$sql_ary = array_merge($sql_ary, array(
				'user_allow_thanks_pm'	=> $data['allowthankspm'],
				'user_allow_thanks_email'=> $data['allowthanksemail'],
			));
		}
		$event['sql_ary'] = $sql_ary;
	}

	public function acp_modify_users_prefs_data($event)
	{
		$data = $event['data'];
		$user_row = $event['user_row'];
		$data = array_merge($data, array(
			'allowthankspm'		=> request_var('allowthankspm', $user_row['user_allow_thanks_pm']),
			'allowthanksemail'	=> request_var('allowthanksemail', $user_row['user_allow_thanks_email']),
		));
		$event['data'] = $data;
	}

	public function acp_users_prefs_modify_sql($event)
	{
		$sql_ary = $event['sql_ary'];
		$data = $event['data'];
		$sql_ary = array_merge($sql_ary, array(
			'user_allow_thanks_pm'	=> $data['allowthankspm'],
			'user_allow_thanks_email'	=> $data['allowthanksemail'],
		));
		$event['sql_ary'] = $sql_ary;
	}

	public function acp_users_prefs_modify_template_data($event)
	{
		$user_prefs_data = $event['user_prefs_data'];
		$data = $event['data'];
		$user_prefs_data = array_merge($user_prefs_data, array(
			'ALLOW_THANKS_PM'	=> $data['allowthankspm'],
			'ALLOW_THANKS_EMAIL' => $data['allowthanksemail'],
		));
		$event['user_prefs_data'] = $user_prefs_data;
	}

	public function add_ucp_prefs_template_vars($event)
	{
		$mode = $event['mode'];
		$data = $event['data'];
		if ($mode = 'personal')
		{
			$this->template->assign_vars(array(
				'S_ALLOW_THANKS_PM'	=> $data['allowthankspm'],
				'S_ALLOW_THANKS_EMAIL'=> $data['allowthanksemail'],
				'S_THANKS_NOTICE_ON'=> isset($this->config['thanks_notice_on']) ? $this->config['thanks_notice_on'] : false,
			));
		}
	}
}
