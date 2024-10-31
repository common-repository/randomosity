<?php
/*
Plugin Name: Randomosity
Plugin URI: http://www.trevorburnham.com/randomosity
Description: Displays a random excerpt from one of your posts. To use, insert <code>&lt;?php randomize(true) ?&gt;</code> in your template wherever you want a random excerpt to appear. Go to <a href="options-general.php?page=Randomosity/randomosity.php">Options &rarr; Randomosity</a> to tweak the plugin's settings to your needs.
Version: 0.3
Author: Trevor Burnham
Author URI: http://www.trevorburnham.com/
*/

/*  Copyright 2008 Trevor Burnham (e-mail: trevor*AT*trevorburnham.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    To read the GPL, visit http://www.gnu.org/copyleft/gpl.html
*/

define('RANDOMOSITY_VERSION', '0.2');

// Define default options
$randomosity_default_options = array('format'=>'<h2>Random %type%</h2><p>%excerpt%
<a title="%title%" href="%link%">Read more...</a></p>',
                                     'exclude_displayed'=>true,
                                     'minimum_posts_ago'=>0,
                                     'types'=>'post, entry, quote, aphorism, tidbit, snippet, words of wisdom',
                                     'no_results_message'=>'<b>Randomosity could not find any valid posts.</b>',
                                     'version'=>RANDOMOSITY_VERSION);

// The randomosity function itself - where the action is!
function randomosity($use_formatting) {
	// global Wordpress variables
	global $posts;
    global $wpdb;
    
    // array of posts already displayed by Randomosity on this page
    static $randomosity_posts;
    if (! isset($randomosity_posts)) {
    	$randomosity_posts = array();
    }
    
    $randomosity_current_settings = get_option('randomosity_options');
    $types = array_map('trim', explode(',', $randomosity_current_settings['types']));
    
    if($use_formatting) {
        $str = $randomosity_current_settings['format'];
        
    } else {
        $str = '%excerpt%';
    }

    $querystr = "
    SELECT *
    FROM $wpdb->posts
    WHERE post_status = 'publish'
    AND post_type = 'post'
    ORDER BY ID DESC LIMIT 1
    ";
    
    $results = $wpdb->get_results($querystr, OBJECT);
    $latest_post_id =  $results[0]->ID;

    $querystr = "
    SELECT *
    FROM $wpdb->posts wposts, $wpdb->postmeta wpostmeta
    WHERE wposts.post_status = 'publish' 
    AND wposts.post_type = 'post'
    AND wposts.ID <= " . ($latest_post_id - $randomosity_current_settings['minimum_posts_ago']) . "
    AND ( TRIM(wposts.post_excerpt) != '' ";
    foreach($types as $type)
        $querystr .= "OR wpostmeta.meta_key = '" . $type . "' ";
    $querystr .= ")";
    
    foreach($randomosity_posts as $post) { // exclude posts already displayed by Randomosity on this page
    	$querystr .= "
AND wposts.ID != " . $post->ID;
    }
    
    if($randomosity_current_settings['exclude_displayed']) {
    	foreach($posts as $post) {
    		$querystr .= "
AND wposts.ID != " . $post->ID;
    	}
    }
    
    $querystr .= "
    ORDER BY RAND() LIMIT 1
    ";
    
    $results = $wpdb->get_results($querystr, OBJECT);
    $selection = $results[0];
    if($selection == null) {
        echo $randomosity_current_settings['no_results_message'];
        return;
    }
    
    $excerpt = "";
    foreach($types as $type) {
        $type_data = get_post_meta($selection->ID, $type, true);
        if($type_data) {
            $excerpt = $type_data;
            break;
        }
    }
    if (!$excerpt) {
        $excerpt = $selection->post_excerpt;
        $type = 'excerpt';
    }
    
    $title = $selection->post_title;
    $link = $selection->guid;

    $str = str_replace("%type%", $type, $str);
    $str = str_replace("%title%", $title, $str);
    $str = str_replace("%link%", $link, $str);
    $str = str_replace("%excerpt%", $excerpt, $str);
    echo $str;
    
    array_push($randomosity_posts, $selection);
}

// Get the current settings, or set defaults if needed
if (!$randomosity_current_settings = get_option('randomosity_options')){
    randomosity_set_default_options($randomosity_default_options);
} else { 
    // Set any unset options
    if ($randomosity_current_settings['version'] != RANDOOMOSITYVERSION) {
        foreach ($randomosity_default_options as $randomosity_key => $randomosity_value) {
            $randomosity_change = false;
            if (!isset($randomosity_current_settings[$randomosity_key])) {
                $randomosity_change = true;
                $randomosity_current_settings[$randomosity_key] = $randomosity_value;
            }
            if ($randomosity_change) update_option('randomosity_options', $randomosity_current_settings);
        }
    }   
}

if (!empty($_POST['randomosity_save_options'])){
    randomosity_save_options();
}elseif(!empty($_POST['randomosity_reset_options'])){
    randomosity_set_default_options($randomosity_default_options);
}

// Set all options to their defaults
function randomosity_set_default_options ($options){
    update_option('randomosity_options', '');
    update_option('randomosity_options', $options);
}

// Hook for adding admin menus
add_action('admin_menu', 'mt_add_pages');

// Action function for above hook
function mt_add_pages() {
    // Add a new submenu under Options:
    add_options_page('Randomosity Options', 'Randomosity', 8, __FILE__, 'randomosity_options_page');
}

// Randomosity options submenu page content
function randomosity_options_page() {

    $randomosity_current_settings = get_option('randomosity_options');

if (!empty($_POST['randomosity_save_options'])): ?>
<div class="updated"><p><strong>Options saved.</strong></p></div>
<?php elseif (!empty($_POST['randomosity_reset_options'])): ?>
<div class="updated"><p><strong>Options reset.</strong></p></div>
<?php endif; ?>

<div class="wrap">
        
    <h2>Randomosity Options</h2>
    <form method="post" action="<?php echo $_SERVER['PHP_SELF']."?".$_SERVER['QUERY_STRING']; ?>">
        <fieldset class="options">
            <label for="format">Output format:<br />
            <small>Use <code>%type%</code> to display the excerpt type (e.g. <strong>quote</strong>),
            <code>%excerpt%</code> to display it, <code>%title%</code> to give the post's title, and <code>%link%</code>
            for its URL.</small>
            </label><br />
            <textarea name="format" rows="3" cols="60"><?php echo $randomosity_current_settings['format']; ?></textarea><br /><br />
			<label for="exclude_displayed"><input type="checkbox" name="exclude_displayed" <?php if($randomosity_current_settings['exclude_displayed'] == true) echo 'checked'; ?> /> Exclude displayed posts</label><br />
			<small>Use this option to exclude excerpts from posts that are displayed on the same page.</small> <br /><br />
            <label for="minimum_posts_ago">The minimum number of posts old an excerpt must be:<br />
            <small>Use 0 to include all posts, 1 to include all but the most recent one, etc. Generally, this should be
            set to the number of posts on your front page.</small></label><br />
            <input size="3" type="text" name="minimum_posts_ago"
                   value="<?php echo $randomosity_current_settings['minimum_posts_ago']; ?>" /> (Default: 0)<br /><br />
            <label for="types">Valid custom field types:<br />
            <small>Separate with commas. If any of these is a custom field key, that field takes priority over the post's excerpt.</small></label><br />
            <textarea rows="1" cols="60" name="types"><?php echo $randomosity_current_settings['types']; ?></textarea><br /><br />
            <label for="no_results_message">Message if no results can be found:<br />
            <small>The <code>randomosity(true or false)</code> function will display this message if no posts with
            valid fields can be found, or if all of those posts are on the current page and "Exclude displayed posts"
            is enabled.</small></label><br />
            <textarea rows="1" cols="60" name="no_results_message"><?php echo $randomosity_current_settings['no_results_message']; ?></textarea><br /><br />
        </fieldset>
        <p class="submit"><input type="submit" name="randomosity_save_options" value="Update Options &raquo;" /></p>
        <p class="submit"><input type="submit" name="randomosity_reset_options" value="Reset Options to Defaults &raquo;" /></p>
    </form>
</div>
<div class="wrap">
    <h2>Licensing Info</h2>
    <p>Randomosity version <?php echo $randomosity_current_settings['version']; ?>, copyright &copy; 2007 Trevor Burnham</p>
    <p>Randomosity is licensed under the <a href="http://www.gnu.org/licenses/gpl.html">GNU GPL</a>. Randomosity comes with ABSOLUTELY NO WARRANTY. This is free software, and you are welcome to redistribute it under certain conditions. See the <a href="http://www.gnu.org/licenses/gpl.html">license</a> for details.</p>
</div>

    <?php
} // options menu

// Save changes to the plugin options
function randomosity_save_options() {
    if (get_magic_quotes_gpc()) $_POST = stripslashes_array($_POST);
    $randomosity_options['format'] = $_POST['format'];
	$randomosity_options['exclude_displayed'] = (array_key_exists('exclude_displayed', $_POST)) ? true : false;
    $randomosity_options['minimum_posts_ago'] = $_POST['minimum_posts_ago'];
    $randomosity_options['types'] = $_POST['types'];
    $randomosity_options['no_results_message'] = $_POST['no_results_message'];
    
    update_option('randomosity_options', $randomosity_options);
}

function stripslashes_array($data) {
	if (is_array($data)) {
		foreach ($data as $key => $value) {
			$data[$key] = stripslashes_array($value);
		}
		return $data;
	} else {
		return stripslashes($data);
	}
}

add_option("aiosp_home_description", null, 'Randomosity ', 'yes');

?>
