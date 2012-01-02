<?php
/*
Plugin Name: TentBlogger SEO Categories
Plugin URI: http://tentblogger.com/seo-categories/
Description: SEO Categories optimizes your <a href="http://tentblogger.com/wordpress-permalinks/" target="_blank">permalink</a> structure at the category level to make them as functional (and pretty) as possible for both your users and the search engines (<a href="http://google.com/" target="_blank">Google</a>, <a href="http://bing.com" target="_blank">Bing</a>, <a href="http://yahoo.com/" target="_blank">Yahoo</a>) that index your content! 
Version: 2.2
Author: TentBlogger
Author URI: http://tentblogger.com
License:

    Copyright 2011 - 2012 TentBlogger (info@tentblogger.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class TentBlogger_SEO_Categories {
	 
	/*--------------------------------------------*
	 * Constructors and Filters
	 *---------------------------------------------*/
	
	/**
	 * Registers the plugin, activates the plugin's filters, and flushes
	 * the current rewrite rules.
	 */
	function __construct() {
	
		if(function_exists('add_action')) {
		
			register_activation_hook(__FILE__, array($this, 'activate'));
			register_deactivation_hook(__FILE__, array($this, 'deactivate'));

			add_action('created_category', array($this, 'flush_rules'));
			add_action('edited_category', array($this, 'flush_rules'));
			add_action('delete_category', array($this, 'flush_rules'));
			add_action('admin_menu', array($this, 'admin'));
			
			add_filter('category_link', array($this, 'link'), 1000, 2);
			add_filter('category_rewrite_rules', array($this, 'rewrite_rules'));
			add_filter('query_vars', array($this, 'query_vars'));
			add_filter('request', array($this, 'base_request'));
			
		} // end if

	} // end constructor
	
	/*--------------------------------------------*
	 * Functions
	 *---------------------------------------------*/
	
	function admin() {
		if(function_exists('add_menu_page')) {
			$this->load_file('tentblogger-seo-categories-styles', '/tentblogger-seo-categories/css/tentblogger-seo-categories-admin.css');
		
      if(!$this->my_menu_exists('tentblogger-handle')) {
        add_menu_page('TentBlogger', 'TentBlogger', 'administrator', 'tentblogger-handle', array($this, 'display'));
      }
      add_submenu_page('tentblogger-handle', 'TentBlogger', 'SEO Categories', 'administrator', 'tentblogger-seo-cats-handle', array($this, 'display'));
		} // end if
	} // end admin_menu
	
	function display() {
		if(is_admin()) {
			include_once('tentblogger-seo-categories-dashboard.php');
		} // end if
	} // end display
	
	/**
	 * Fired during activation. Flushes the exisiting rules.
	 */
	function activate() {
		$this->flush_rules();
	} // end seo_category_activate
	
	/**
	 * Removes the plugins filters and then flushes reules created by the
	 * plugin.
	 */
	function deactivate() {
		remove_filter('category_link', array($this, 'link'), 1000, 2);
		remove_filter('category_rewrite_rules', array($this, 'rewrite_rules'));
		remove_filter('query_vars', array($this, 'query_vars'));
		remove_filter('request', array($this, 'base_request'));
		$this->flush_rules();
	} // end seo_category_deactivate
	
	/**
	 * Function for the Category Link filter that is responsible for modifying the category
	 * link text.
	 *
	 * @category_url	The URL of the category
	 * @category_id 	The ID of the category
	 */
	function link($category_url, $category_id) {
		
		$category = get_category($category_id);
		if(is_wp_error($category)) {
			$category_url = $category;
		} else {
		
			$category_name = $category->slug;
			
			if($category->parent == $category_id) {
				$category->parent = 0;
			} else if ($category->parent != 0) {
				$category_name = get_category_parents($category->parent, false, '/', true) . $category_name;
			} // end if
			
			$category_url = trailingslashit(get_option('home')) . user_trailingslashit($category_name, 'category');
			
		} // end if
		
		return $category_url;
		
	} // end link
	
	/**
	 * Rewrites the rules for category archives.
	 *
	 * @category_rewrite	The array used to manage the rules.
	 */
	function rewrite_rules($category_rewrite) {
		
		$category_rewrite = array();
		
		$categories = get_categories(
			array('hide_empty' => false)
		);
		
		foreach($categories as $category) {
			
			$category_name = $category->slug;
			if($category->parent == $category->cat_ID) {
				$category->parent = 0;
			} else if($category->parent != 0) {
				$category_name = get_category_parents($category->parent, false, '/', true) . $category_name;
			} // end if
			
			$category_rewrite['('.$category_name.')/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?category_name=$matches[1]&feed=$matches[2]';
			$category_rewrite['('.$category_name.')/page/?([0-9]{1,})/?$'] = 	'index.php?category_name=$matches[1]&paged=$matches[2]';
			$category_rewrite['('.$category_name.')/?$'] = 'index.php?category_name=$matches[1]';
			
		} // end for each
		
		$category_rewrite = $this->setup_redirection($category_rewrite);
		
		return $category_rewrite;
	
	} // end rewrite_rules
	
	/**
	 * Initializes the public query variables for rewriting categories
	 * 
	 * @public_query_vars	The array of query variables
	 */
	function query_vars($public_query_vars) {
		$public_query_vars[] = 'category_redirect';
		return $public_query_vars;
	} // end query_vars
	
	/**
	 * Prepares the base request for the original URL's for SEO
	 * purposes.
	 *
	 * @query_vars	The collection of query variables.
	 */
	function base_request($query_vars) {
	
		if(isset($query_vars['category_redirect'])) {
		
			$category_url = trailingslashit(get_option('home')) . user_trailingslashit($query_vars['category_redirect'], 'category');
			status_header(301);
			header('Location: ' . $category_url);
			exit();
			
		} // end if
		
		return $query_vars;
		
	} // end base_request
	
	/**
	 * Flushes the current set of WordPress' rewrite rules.
	 */
	public function flush_rules() {
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	} // end flush_rules
	
	/*--------------------------------------------*
	 * Private Functions
	 *---------------------------------------------*/
	 
	/**
	 * Sets up a 301 redirection for SEO purposes of renamed categories.
	 *
	 * @category_rewrite	The array of rewrite rules for categories.
	 */
	private function setup_redirection($category_rewrite) {
		
		global $wp_rewrite;
		
		$previous_category_base = $wp_rewrite->get_category_permastruct();
		$previous_category_base = str_replace('%category%', '(.+)', $previous_category_base);
		$previous_category_base = trim($previous_category_base, '/');
		$category_rewrite[$previous_category_base . '$'] = 'index.php?category_redirect=$matches[1]';
		
		return $category_rewrite;
		
	} // end setup_redirection
	
	/**
	 * Helper function for registering and loading scripts and styles.
	 *
	 * @name	The 	ID to register with WordPress
	 * @file_path		The path to the actual file
	 * @is_script		Optional argument for if the incoming file_path is a JavaScript source file.
	 */
	private function load_file($name, $file_path, $is_script = false) {
		$url = WP_PLUGIN_URL . $file_path;
		$file = WP_PLUGIN_DIR . $file_path;
		if(file_exists($file)) {
			if($is_script) {
				wp_register_script($name, $url);
				wp_enqueue_script($name);
			} else {
				wp_register_style($name, $url);
				wp_enqueue_style($name);
			} // end if
		} // end if
	} // end _load_file
	
  /**
   * http://wordpress.stackexchange.com/questions/6311/how-to-check-if-an-admin-submenu-already-exists
   */
  private function my_menu_exists( $handle, $sub = false){
    if( !is_admin() || (defined('DOING_AJAX') && DOING_AJAX) )
      return false;
    global $menu, $submenu;
    $check_menu = $sub ? $submenu : $menu;
    if( empty( $check_menu ) )
      return false;
    foreach( $check_menu as $k => $item ){
      if( $sub ){
        foreach( $item as $sm ){
          if($handle == $sm[2])
            return true;
        }
      } else {
        if( $handle == $item[2] )
          return true;
      }
    }
    return false;
  } // end my_menu_exists
} // TentBlogger_SEO_Categories
new TentBlogger_SEO_Categories();
?>