<?php

$_LW->REGISTERED_APPS['navigations_monitor'] = array(
	'title' => 'Navigations Monitor',
	'handlers' => array(
      'onLoad',
      'onManagerSubmit'
    )
);

class LiveWhaleApplicationNavigationsMonitor {

  protected $test_ip = '';
  protected $user = array();
  protected $recipients = array();
  protected $error_email = '';
  protected $from_email = '';
  protected $indent = "&nbsp; &nbsp; &nbsp;";
  protected $linebreak = "\n";
  protected $blank = "[blank]";
  protected $use_log = FALSE;
  protected $logfile_path = '';
  protected $logfile = NULL;
  protected $page_key = 'pages';
  protected $expected_nav_post_keys = array('id', 'link', 'title', 'page_title', 'url', 'depth', 'pgid', 'status');

  protected $prior_navigation_items = array();
  protected $current_navigation_items = array();

  function logger ( $message ) {
    if ( is_resource($this->logfile) ) {
      @fwrite($this->logfile, date(DATE_RFC822) . "\t{$_SERVER['REMOTE_ADDR']}\t{$message}\n");
    } else if ( !empty($this->error_email) ) {
      @mail($this->error_email, 'Navigations Monitor Log/Error', $message);
    }
  }

  function is_being_tested () {
    if ( !empty($this->test_ip) && $_SERVER['REMOTE_ADDR'] == $this->test_ip ) return TRUE;
    return FALSE; 
  }

  function is_post_in_expected_format () {
    global $_LW;
    if ( array_keys($_LW->_POST) === $this->expected_nav_post_keys ) return TRUE;
    return FALSE;
  }

  function is_editing_navigations () {
    global $_LW;
    if ( $_LW->page == $this->page_key && $this->is_post_in_expected_format() ) return TRUE;
    return FALSE;
  }

  function get_navigation_items ( $id = NULL ) {
    global $_LW;
    $items = array();
    if ( empty($id) && !empty($_LW->_POST['id']) ) $id = (int) $_LW->_POST['id'];
    $navigation_items = $_LW->query("SELECT `livewhale_pages_navs_items`.* FROM `livewhale_pages_navs_items` WHERE `livewhale_pages_navs_items`.`pid` = {$id} ORDER BY `livewhale_pages_navs_items`.`position`;");
    if ( $navigation_items && $navigation_items->num_rows ) while ( $item = $navigation_items->fetch_assoc() ) $items[] = $item;
    return $items;
  }

  function save_navigation_items ( $id = NULL ) {
    $this->prior_navigation_items = $this->get_navigation_items($id);
  }

  function get_row ( $table = '', $id = NULL ) {
    global $_LW;
    if ( !empty($id) ) $id = abs((int) $id);
    if ( empty($id) ) return NULL;
    if ( !empty($table) ) $table = preg_replace('~[^a-z_2]+~', '', $table);
    if ( empty($table) ) return NULL;
    $row = $_LW->query("SELECT `{$table}`.* FROM `{$table}` WHERE `{$table}`.`id` = {$id};");
    if ( $row && $row->num_rows === 1 ) return $row->fetch_assoc();
    return NULL;
  }

  function get_navigation ( $id = NULL ) {
    return $this->get_row('livewhale_pages_navs', $id);
  }

  function get_group ( $id = NULL ) {
    return $this->get_row('livewhale_groups', $id);
  }

  function assign_page_urls_for ( &$items ) {
    global $_LW;
    $pgids = array();
    foreach ( $items as $item ) if ( !empty($item['pgid']) ) $pgids["{$item['pgid']}"] = $item['pgid'];
    if ( empty($pgids) ) return NULL;
    ksort($pgids);
    $pages = $_LW->query("SELECT `livewhale_pages`.`id`, `livewhale_pages`.`host`, `livewhale_pages`.`path` FROM `livewhale_pages` WHERE `livewhale_pages`.`id` IN (" . implode(', ', array_keys($pgids)) . ") ORDER BY `livewhale_pages`.`id`;");
    if ( $pages && $pages->num_rows ) while ( $page = $pages->fetch_assoc() ) $pgids["{$page['id']}"] = $page;
    foreach ( $items as $index => $item ) {
      if ( !empty($pgids["{$item['pgid']}"]) && empty($items[$index]['url']) ) $items[$index]['url'] = "http://" . $pgids["{$item['pgid']}"]['host'] . str_replace('/index.php', '/', $pgids["{$item['pgid']}"]['path']);
      if ( empty($items[$index]['url']) ) $items[$index]['url'] = $this->blank;
    }
    return NULL;
  }

  function changed ( $old_item, $new_item ) {
    $result = array_diff_assoc((array) $new_item, (array) $old_item);
    unset($result['id']); // ignore changed id
    return $result;
  }

  function has_no_changes ( $changed ) {
    if ( count($changed) === 0 ) return TRUE;
    return FALSE;
  }

  function is_an_exact_match ( $old_item, $new_item ) {
    return $this->has_no_changes($this->changed($old_item, $new_item));
  }

  function only_has_display_changes ( $changed ) {
    $changed_keys = array_keys($changed);
    if ( $changed_keys === array('status', 'title') || $changed_keys === array('status') || $changed_keys === array('title') ) return TRUE;
    return FALSE;
  }

  function is_a_display_change ( $old_item, $new_item ) {
    return $this->only_has_display_changes($this->changed($old_item, $new_item));
  }

  function only_has_link_changes ( $changed ) {
    if ( count($changed) === 1 && (key($changed) === 'pgid' || key($changed) === 'url') ) return TRUE;
    return FALSE;
  }

  function is_a_link_change ( $old_item, $new_item ) {
    return $this->only_has_link_changes($this->changed($old_item, $new_item));
  }

  function only_has_position_changes ( $changed ) {
    $changed_keys = array_keys($changed);
    if ( $changed_keys === array('depth', 'position') || $changed_keys === array('depth') || $changed_keys === array('position') ) return TRUE;
    return FALSE;
  }

  function is_a_position_change ( $old_item, $new_item ) {
    return $this->only_has_position_changes($this->changed($old_item, $new_item));
  }

  function change_type ( $changed, &$assigned ) {
    if ( !is_bool($assigned) ) $assigned = FALSE;
    if ( $this->has_no_changes($changed) ) {
      $assigned = TRUE;
      return 'has_no_changes';
    } else if ( $this->only_has_display_changes($changed) ) {
      $assigned = TRUE;
      return 'only_has_display_changes';
    } else if ( $this->only_has_link_changes($changed) ) {
      $assigned = TRUE;
      return 'only_has_link_changes';
    } else if ( $this->only_has_position_changes($changed) ) {
      $assigned = TRUE;
      return 'only_has_position_changes';
    }
    return NULL;
  }

  function assess_changes ( &$old, &$new ) {
    foreach ( $old as $old_index => $old_item ) {
      $assigned = FALSE;
      $change_type = $this->change_type($this->changed($old_item, $new[$old_index]), $assigned);
      if ( $change_type ) {
        $new[$old_index][$change_type] = $old_item;
      } else {
        foreach ( $new as $new_index => $new_item ) {
          $change_type = $this->change_type($this->changed($old_item, $new_item), $assigned);
          if ( $change_type ) $new[$new_index][$change_type] = $old_item;
        }
      }
      if ( !$assigned ) $old[$old_index]['dropped'] = TRUE;
    }
    foreach ( $new as $new_index => $new_item ) {
      if ( empty($new_item['has_no_changes']) && empty($new_item['only_has_display_changes']) && empty($new_item['only_has_link_changes']) && empty($new_item['only_has_position_changes'])) $new[$new_index]['is_new_item'] = TRUE;
    }
    return NULL;
  }

  function summarize_item ( $item, &$single_push_has_occurred ) {
    if ( !is_bool($single_push_has_occurred) ) $single_push_has_occurred = FALSE;
    $summary = array($item['title'], $item['url']);
    if ( !empty($item['has_no_changes']) ) {
      $summary[] = "[no changes]";
    } else if ( !empty($item['only_has_display_changes']) ) {
      if ( $item['only_has_display_changes']['title'] != $item['title'] ) $summary[] = "[the title changed from {$item['only_has_display_changes']['title']} to {$item['title']}]";
      if ( $item['only_has_display_changes']['status'] != $item['status'] ) $summary[] = "[the link is now " . (($item['status'] == 1) ? 'visible' : 'hidden') . "]";
    } else if ( !empty($item['only_has_link_changes']) ) {
      $summary[] = "[the url changed to {$item['url']}" . ((!empty($item['pgid'])) ? ", for page #{$item['pgid']}" : "") . "]";
    } else if ( !empty($item['only_has_position_changes']) ) {
      if ( $single_push_has_occurred && $item['only_has_position_changes']['depth'] == $item['depth'] && $item['only_has_position_changes']['position'] == (int) $item['position'] - 1 ) { // skip if only a push
        if ( empty($item['only_has_display_changes']) && empty($item['only_has_link_changes']) && empty($item['is_new_item']) ) $summary[] = "[no changes]";
      } else {
        if ( $item['only_has_position_changes']['position'] != $item['position'] ) {
          $summary[] = "[the item moved here from position #" . ((int) $item['only_has_position_changes']['position'] + 1) . "]";
          if ( !$single_push_has_occurred ) $single_push_has_occurred = TRUE;
        }
        if ( $item['only_has_position_changes']['depth'] != $item['depth'] ) $summary[] = "[the item is now nested " . (($item['only_has_position_changes']['depth'] < $item['depth']) ? "less deep" : "deeper") . "]";
      }
    } else if ( !empty($item['is_new_item']) ) {
      $summary[] = "[this is most-likely a new item]";
      if ( !$single_push_has_occurred ) $single_push_has_occurred = TRUE;
    } else if ( !empty($item['dropped']) ) {
      $summary[] = "[this item was premanently removed]";
    }
    $depth = str_repeat($this->indent, (int) $item['depth']);
    return $depth . implode(('<br />' . $this->linebreak . $depth), $summary);
  }

  function summarize ( $old = array(), $new = array() ) {
  
  	/*	This function translates changes into somewhat real text. */

    if ( empty($old) || !is_array($old) ) $old = $this->prior_navigation_items;
    if ( empty($new) || !is_array($new) ) $new = $this->get_navigation_items();

    $this->logger(var_export($old, TRUE));

    $this->assess_changes($old, $new);
    $this->assign_page_urls_for($new);
    $this->current_navigation_items = $new;

    $summary = array();
    foreach ( $new as $new_item ) $summary[] = $this->summarize_item($new_item, $pushed);
    foreach ( $old as $old_item ) if ( !empty($old_item['dropped']) ) array_push($summary, "", $this->summarize_item($old_item, $pushed));

    return implode(('<br />' . $this->linebreak . '<br />' . $this->linebreak), $summary);
  }

  function message () {
    global $_LW;

    $message = array();
    $message[] = $this->summarize();

    $pid = ((!empty($this->current_navigation_items[0]['pid'])) ? $this->current_navigation_items[0]['pid'] : ((!empty($this->prior_navigation_items[0]['pid'])) ? $this->prior_navigation_items[0]['pid'] : NULL));
    if ( empty($pid) ) return NULL;
    $navigation = $this->get_navigation($pid);
    if ( !empty($navigation) ) $group = $this->get_group($navigation['gid']);
    if ( empty($group) ) return NULL;

    array_unshift($message, "<p>{$this->user['firstname']} {$this->user['lastname']} has just updated the navigation &ldquo;{$navigation['title']}&rdquo; within group {$group['fullname']}. The navigation now appears as follows:</p>", "");
    array_push($message, "<p><a href=\"http://{$_SERVER['SERVER_NAME']}/livewhale/?pages&id={$navigation['id']}\">Visit this navigation now.</a></p>", nl2br($_LW->CONFIG['EMAIL_FOOTER']));

    return implode($this->linebreak, $message);
  }

  function headers () {
    $headers = array(
      "MIME-Version: 1.0",
      "Reply-to: {$this->user['firstname']} {$this->user['lastname']} <{$this->user['email']}>",
      "Content-type: text/html;charset=utf-8"
      );
    if ( !empty($this->from_email) ) $headers[] = "From: {$this->from_email}";
    if ( !empty($this->error_email) ) $headers[] = "Errors-to: {$this->error_email}";
  	return implode($this->linebreak, $headers);
  }

  function onLoad () {
    global $_LW;
    if ( $this->is_editing_navigations() ) {
      @include("{$_LW->INCLUDES_DIR_PATH}/client/modules/navigations_monitor/application.config.php");
      if ( !empty($config) ) foreach ( $config as $property => $value ) if ( isset($this->$property) || property_exists($this, $property) ) $this->$property = $value;
      if ( $this->use_log === TRUE && !empty($this->logfile_path) ) $this->logfile = @fopen($this->logfile_path, 'a+');
      $this->save_navigation_items(); // save pre-existing state
      $this->user = $_SESSION['livewhale']['manage'];
    }
  }

  function onManagerSubmit ($type, $page) {
    global $_LW;
    if ( $this->is_editing_navigations() ) {
      $message = $this->message();
      if ( $message && !empty($this->recipients) ) @mail(implode(', ', (array) $this->recipients), 'LiveWhale Navigation Update', $message, $this->headers());
      if ( is_resource($this->logfile) ) @fclose($this->logfile);
    }
  }

}

?>