<?php
/*
 * Name: frio
 * Description: Bootstrap V3 theme. The theme is currently under construction, so it is far from finished. For further information have a look at the <a href="https://github.com/rabuzarus/frio/blob/master/README.md">ReadMe</a> and <a href="https://github.com/rabuzarus/frio">GitHub</a>.
 * Version: V.0.1 Alpha
 * Author: Rabuzarus <https://friendica.kommune4.de/profile/rabuzarus>
 * 
 */

$frio = "view/theme/frio";

global $frio;

function frio_init(&$a) {
	set_template_engine($a, 'smarty3');

	$baseurl = $a->get_baseurl();

	$style = get_pconfig(local_user(), 'frio', 'style');

	$frio = "view/theme/frio";

	global $frio;
	
	


	if ($style == "")
		$style = get_config('frio', 'style');
}

function frio_install() {
	register_hook('prepare_body_final', 'view/theme/frio/theme.php', 'frio_item_photo_links');
	register_hook('item_photo_menu', 'view/theme/frio/theme.php', 'frio_item_photo_menu');
	register_hook('nav_info', 'view/theme/frio/theme.php', 'frio_remote_nav');

	logger("installed theme frio");
}

function frio_uninstall() {
	unregister_hook('prepare_body_final', 'view/theme/frio/theme.php', 'frio_item_photo_links');
	unregister_hook('item_photo_menu', 'view/theme/frio/theme.php', 'frio_item_photo_menu');

	logger("uninstalled theme frio");
}
/**
 * @brief Replace friendica photo links
 * 
 *  This function does replace the links to photos
 *  of other friendica users. Original the photos are
 *  linked to the photo page. Now they will linked directly
 *  to the photo file. This function is nessesary to use colorbox
 *  in the network stream
 * 
 * @param App $a
 * @param array $body_info The item and its html output
 */
function frio_item_photo_links(&$a, &$body_info) {
	require_once('include/Photo.php');

	$phototypes = Photo::supportedTypes();
	$occurence = 1;
	$p = bb_find_open_close($body_info['html'], "<a", ">");

	while($p !== false && ($occurence++ < 500)) {
		$link = substr($body_info['html'], $p['start'], $p['end'] - $p['start']);
		$matches = array();

		preg_match("/\/photos\/[\w]+\/image\/([\w]+)/", $link, $matches);
		if($matches) {
			// Replace the link for the photo's page with a direct link to the photo itself
			$newlink = str_replace($matches[0], "/photo/{$matches[1]}", $link);

			// Add a "quiet" parameter to any redir links to prevent the "XX welcomes YY" info boxes
			$newlink = preg_replace("/href=\"([^\"]+)\/redir\/([^\"]+)&url=([^\"]+)\"/", 'href="$1/redir/$2&quiet=1&url=$3"', $newlink);

			 // Having any arguments to the link for Colorbox causes it to fetch base64 code instead of the image
			$newlink = preg_replace("/\/[?&]zrl=([^&\"]+)/", '', $newlink);

			$body_info['html'] = str_replace($link, $newlink, $body_info['html']);
		}

		$p = bb_find_open_close($body_info['html'], "<a", ">", $occurence);
	}
}

/**
 * @brief Replace links of the item_photo_menu
 * 
 *  This function replaces the original poke and the message links
 *  to call the addToModal javascript function so this pages can
 *  be loaded in a bootstrap modal
 * 
 * @param app $a The app data
 * @param array $arr Contains item data and the original photo_menu
 */
function frio_item_photo_menu($a, &$arr){

	foreach($arr["menu"] as $k =>$v) {
		if(strpos($v,'poke/?f=&c=') === 0 || strpos($v,'message/new/') === 0) {
			$v = "javascript:addToModal('" . $v . "'); return false;";
			$arr["menu"][$k] = $v;
			$testvariable = $testvariable+1;
		}
	}
	$args = array('item' => $item, 'menu' => $menu);
}

/**
 * @brief Construct remote nav menu
 * 
 *  It creates a remote baseurl form $_SESSION for remote users and friendica
 *  visitors. This url will be added to some of the nav links. With this behaviour 
 *  the user will come back to her/his own pages on his/her friendica server.
 *  Not all possible links are available (notifications, administrator, manage,
 *  notes aren't available because we have no way the check remote permissions)..
 *  Some links will point to the local pages because the user would expect
 *  local page (these pages are: search, community, help, apps, directory).
 * 
 * @param app $a The App class
 * @param array $nav The original nav menu
 */
function frio_remote_nav($a,&$nav) {
	// get the homelink from $_XSESSION
	$homelink = get_my_url();
	if(! $homelink)
		$homelink = ((x($_SESSION,'visitor_home')) ? $_SESSION['visitor_home'] : '');

	// split up the url in it's parts (protocol,domain/directory, /profile/, nickname
	// I'm not familiar with regex, so someone might find a better solutionen
	// 
	// E.g $homelink = 'https://friendica.domain.com/profile/mickey' should result in an array
	// with 0 => 'https://friendica.domain.com/profile/mickey' 1 => 'https://',
	// 2 => 'friendica.domain.com' 3 => '/profile/' 4 => 'mickey'
	// 
	//$server_url = preg_match('/^(https?:\/\/.*?)\/profile\//2', $homelink);
	preg_match('/^(https?:\/\/)?(.*?)(\/profile\/)(.*)/', $homelink, $url_parts);

	// Construct the server url of the visitor. So we could link back to his/her own menu.
	// And construct a webbie (e.g. mickey@friendica.domain.com for the search in gcontact
	// We use the webbie for search in gcontact because we don't know if gcontact table stores
	// the right value if its http or https protocol
	if(count($url_parts)) {
		$server_url = $url_parts[1] . $url_parts[2];
		$webbie = $url_parts[4] . '@' . $url_parts[2];
	}

	// since $userinfo isn't available for the hook we write it to the nav array
	// this isn't optimal because the contact query will be done now twice
	if(local_user()) {
		// empty the server url for local user because we won't need it
		$server_url = '';
		// user info
		$r = q("SELECT `micro` FROM `contact` WHERE `uid` = %d AND `self` = 1", intval($a->user['uid']));
		
		$r[0]['photo'] = (count($r) ? $a->remove_baseurl($r[0]['micro']) : "images/person-48.jpg");
		$r[0]['name'] = $a->user['username'];

	} elseif(!local_user() && remote_user()) {
		$r = q("SELECT `name`, `nick`, `micro` AS `photo` FROM `contact` WHERE `id` = %d", intval(remote_user()));
		$nav['remote'] = 1;

	} elseif(get_my_url ()) {
		$r = q("SELECT `name`, `nick`, `photo` FROM `gcontact`
				WHERE `addr` = '%s' AND `network` = 'dfrn'",
			dbesc($webbie));
		$nav['remote'] = 1;
	}

	if(count($r)){
			$nav['userinfo'] = array(
				'icon' => (count($r) ? $r[0]['photo'] : "images/person-48.jpg"),
				'name' => $r[0]['name'],
			);
		}

	if(!local_user() && !empty($server_url)) {
		$nav['logout'] = Array($server_url . '/logout',t('Logout'), "", t('End this session'));

		// user menu
		$nav['usermenu'][] = Array($server_url . '/profile/' . $a->user['nickname'], t('Status'), "", t('Your posts and conversations'));
		$nav['usermenu'][] = Array($server_url . '/profile/' . $a->user['nickname']. '?tab=profile', t('Profile'), "", t('Your profile page'));
		$nav['usermenu'][] = Array($server_url . '/photos/' . $a->user['nickname'], t('Photos'), "", t('Your photos'));
		$nav['usermenu'][] = Array($server_url . '/videos/' . $a->user['nickname'], t('Videos'), "", t('Your videos'));
		$nav['usermenu'][] = Array($server_url . '/events/', t('Events'), "", t('Your events'));

		// navbar links
		$nav['network'] = array($server_url . '/network', t('Network'), "", t('Conversations from your friends'));
		$nav['events'] = Array($server_url . '/events', t('Events'), "", t('Events and Calendar'));
		$nav['messages'] = array($server_url . '/message', t('Messages'), "", t('Private mail'));
		$nav['settings'] = array($server_url . '/settings', t('Settings'),"", t('Account settings'));
		$nav['contacts'] = array($server_url . '/contacts', t('Contacts'),"", t('Manage/edit friends and contacts'));
	}
}
