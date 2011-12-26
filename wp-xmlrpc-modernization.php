<?php

/*
 * Plugin Name: wp-xmlrpc-modernization
 * Description: This plugin extends the basic XML-RPC API exposed by WordPress. Derived from GSoC '11 project.
 * Version: 1.0
 * Author: Max Cutler
 * Author URI: http://www.maxcutler.com
 *
*/

include_once(ABSPATH . WPINC . '/class-IXR.php');
include_once(ABSPATH . WPINC . '/class-wp-xmlrpc-server.php');

add_filter( 'wp_xmlrpc_server_class', 'replace_xmlrpc_server_class' );

function replace_xmlrpc_server_class( $class_name ) {
	// only replace the default XML-RPC class if another plug-in hasn't already changed it
	if ( $class_name === 'wp_xmlrpc_server' )
		return 'wp_xmlrpc_server_ext';
	else
		return $class_name;
}

class wp_xmlrpc_server_ext extends wp_xmlrpc_server {

	function __construct() {
		// hook filter to add the new methods after the existing ones are added in the parent constructor
		add_filter( 'xmlrpc_methods' , array( &$this, 'xmlrpc_methods' ) );

		parent::__construct();
	}

	function xmlrpc_methods ( $methods ) {

		// user management
		$methods['wp.newUser']          = array( &$this, 'wp_newUser' );
		$methods['wp.editUser']         = array( &$this, 'wp_editUser' );
		$methods['wp.deleteUser']       = array( &$this, 'wp_deleteUser' );
		$methods['wp.getUser']          = array( &$this, 'wp_getUser' );
		$methods['wp.getUsers']         = array( &$this, 'wp_getUsers' );

		// custom post type management
		$methods['wp.newPost']          = array( &$this, 'wp_newPost' );
		$methods['wp.editPost']         = array( &$this, 'wp_editPost' );
		$methods['wp.deletePost']       = array( &$this, 'wp_deletePost' );
		$methods['wp.getPost']          = array( &$this, 'wp_getPost' );
		$methods['wp.getPosts']         = array( &$this, 'wp_getPosts' );
		$methods['wp.getPostTerms']     = array( &$this, 'wp_getPostTerms' );
		$methods['wp.setPostTerms']     = array( &$this, 'wp_setPostTerms' );
		$methods['wp.getPostType']      = array( &$this, 'wp_getPostType' );
		$methods['wp.getPostTypes']     = array( &$this, 'wp_getPostTypes' );

		// custom taxonomy management
		$methods['wp.newTerm']          = array( &$this, 'wp_newTerm' );
		$methods['wp.editTerm']         = array( &$this, 'wp_editTerm' );
		$methods['wp.deleteTerm']       = array( &$this, 'wp_deleteTerm' );
		$methods['wp.getTerm']          = array( &$this, 'wp_getTerm' );
		$methods['wp.getTerms']         = array( &$this, 'wp_getTerms' );
		$methods['wp.getTaxonomy']      = array( &$this, 'wp_getTaxonomy' );
		$methods['wp.getTaxonomies']    = array( &$this, 'wp_getTaxonomies' );

		return $methods;

	}

	/**
	 * Prepares user data for return in an XML-RPC object
	 *
	 * @param obj $user The unprepared WP_User object
	 * @return array The prepared user data
	 */
	function prepare_user( $user ) {
		$contact_methods = _wp_get_user_contactmethods();

		$user_contacts = array();
		foreach( $contact_methods as $key => $value ) {
			$user_contacts[ $key ] = $user->$key;
		}

		$_user = array(
			'user_id'           => $user->ID,
			'username'          => $user->user_login,
			'first_name'        => $user->user_firstname,
			'last_name'         => $user->user_lastname,
			'registered'        => new IXR_Date( mysql2date('Ymd\TH:i:s', $user->user_registered, false) ),
			'bio'               => $user->user_description,
			'email'             => $user->user_email,
			'nickname'          => $user->nickname,
			'nicename'          => $user->user_nicename,
			'url'               => $user->user_url,
			'display_name'      => $user->display_name,
			'capabilities'      => $user->wp_capabilities,
			'user_level'        => $user->wp_user_level,
			'user_contacts'     => $user_contacts
		);

		return apply_filters( 'xmlrpc_prepare_user', $_user, $user );
	}

	/**
	 * Prepares post data for return in an XML-RPC object
	 *
	 * @param array $post The unprepared post data
	 * @param array $fields The subset of post fields to return
	 * @return array The prepared post data
	 */
	function prepare_post( $post, $fields ) {
		// pre-calculate conceptual group in_array searches
		$all_post_fields = in_array( 'post', $fields );
		$all_taxonomy_fields = in_array( 'taxonomies', $fields );

		// holds the data for this post. built up based on $fields
		$_post = array( 'postid' => $post['ID'] );

		if ( $all_post_fields || in_array( 'title', $fields ) )
			$_post['title'] = $post['post_title'];

		if ( $all_post_fields || in_array( 'post_date', $fields ) )
			$_post['post_date'] = new IXR_Date(mysql2date( 'Ymd\TH:i:s', $post['post_date'], false ));

		if ( $all_post_fields || in_array( 'post_date_gmt', $fields ) )
			$_post['post_date_gmt'] = new IXR_Date(mysql2date( 'Ymd\TH:i:s', $post['post_date_gmt'], false ));

		if ( $all_post_fields || in_array( 'post_modified', $fields ) )
			$_post['post_modified'] = new IXR_Date(mysql2date( 'Ymd\TH:i:s', $post['post_modified'], false ));

		if ( $all_post_fields || in_array( 'post_modified_gmt', $fields ) )
			$_post['post_modified_gmt'] = new IXR_Date(mysql2date( 'Ymd\TH:i:s', $post['post_modified_gmt'], false ));

		if ( $all_post_fields || in_array( 'post_status', $fields ) ) {
			// Consider future posts as published
			if ( $post['post_status'] === 'future' )
				$_post['post_status'] = 'publish';
			else
				$_post['post_status'] = $post['post_status'];
		}

		if ( $all_post_fields || in_array( 'post_type', $fields ) )
			$_post['post_type'] = $post['post_type'];

		if ( $all_post_fields || in_array( 'post_format', $fields ) ) {
			$post_format = get_post_format( $post['ID'] );
			if ( empty( $post_format ) )
				$post_format = 'standard';
			$_post['post_format'] = $post_format;
		}

		if ( $all_post_fields || in_array( 'wp_slug', $fields ) )
			$_post['wp_slug'] = $post['post_name'];

		if ( $all_post_fields || in_array( 'link', $fields ) )
			$_post['link'] = post_permalink( $post['ID'] );

		if ( $all_post_fields || in_array( 'permaLink', $fields ) )
			$_post['permaLink'] = post_permalink( $post['ID'] );

		if ( $all_post_fields || in_array( 'userid', $fields ) )
			$_post['userid'] = $post['post_author'];

		if ( $all_post_fields || in_array( 'wp_author_id', $fields ) )
			$_post['wp_author_id'] = $post['post_author'];

		if ( $all_post_fields || in_array( 'mt_allow_comments', $fields ) )
			$_post['mt_allow_comments'] = $post['comment_status'];

		if ( $all_post_fields || in_array( 'mt_allow_pings', $fields ) )
			$_post['mt_allow_pings'] = $post['ping_status'];

		if ( $all_post_fields || in_array( 'sticky', $fields ) ) {
			$sticky = null;
			if( $post['post_type'] == 'post' ) {
				$sticky = false;
				if ( is_sticky( $post['ID'] ) )
					$sticky = true;
			}
			$_post['sticky'] = $sticky;
		}

		if ( $all_post_fields || in_array( 'wp_password', $fields ) )
			$_post['wp_password'] = $post['post_password'];

		if ( $all_post_fields || in_array( 'mt_excerpt', $fields ) )
			$_post['mt_excerpt'] = $post['post_excerpt'];

		if ( $all_post_fields || in_array( 'description', $fields ) ) {
			$post_content = get_extended( $post['post_content'] );
			$_post['description'] = $post_content['main'];
			$_post['mt_text_more'] = $post_content['extended'];
		}

		if ( $all_taxonomy_fields || in_array( 'terms', $fields ) ) {
			$post_type_taxonomies = get_object_taxonomies( $post['post_type'] , 'names');
			$_post['terms'] = wp_get_object_terms( $post['ID'], $post_type_taxonomies );;
		}

		// backward compatiblity
		if ( $all_taxonomy_fields || in_array( 'mt_keywords', $fields ) ) {
			$tagnames = array();
			$tags = wp_get_post_tags( $post['ID'] );
			if ( !empty( $tags ) ) {
				foreach ( $tags as $tag )
					$tagnames[] = $tag->name;
				$tagnames = implode( ', ', $tagnames );
			} else {
				$tagnames = '';
			}
			$_post['mt_keywords'] = $tagnames;
		}

		// backward compatiblity
		if ( $all_taxonomy_fields || in_array( 'categories', $fields ) ) {
			$categories = array();
			$catids = wp_get_post_categories( $post['ID'] );
			foreach($catids as $catid) {
				$categories[] = get_cat_name($catid);
			}
			$_post['categories'] = $categories;
		}

		if ( in_array( 'custom_fields', $fields ) )
			$_post['custom_fields'] = $this->get_custom_fields( $post['ID'] );

		if ( in_array( 'enclosure', $fields ) ) {
			$enclosure = array();
			foreach ( (array) get_post_custom( $post['ID'] ) as $key => $val) {
				if ($key == 'enclosure') {
					foreach ( (array) $val as $enc ) {
						$encdata = split("\n", $enc);
						$enclosure['url'] = trim(htmlspecialchars($encdata[0]));
						$enclosure['length'] = (int) trim($encdata[1]);
						$enclosure['type'] = trim($encdata[2]);
						break 2;
					}
				}
			}
			$_post['enclosure'] = $enclosure;
		}

		return apply_filters( 'xmlrpc_prepare_post', $_post, $post, $fields );
	}

	/**
	 * Prepares taxonomy data for return in an XML-RPC object
	 *
	 * @param array|object $taxonomy The unprepared taxonomy data
	 * @return array The prepared taxonomy data
	 */
	function prepare_taxonomy( $taxonomy ) {
		$_taxonomy = (array) $taxonomy;

		unset(
			$_taxonomy['update_count_callback']
		);

		return apply_filters( 'xmlrpc_prepare_taxonomy', $_taxonomy, $taxonomy );
	}

	/**
	 * Prepares term data for return in an XML-RPC object
	 *
	 * @param array $term The unprepared term data
	 * @return array The prepared term data
	 */
	function prepare_term( $term ) {
		$_term = (array) $term;

		return apply_filters( 'xmlrpc_prepare_term', $_term, $term );
	}

	/**
	 * Prepares post type data for return in an XML-RPC object
	 *
	 * @param array|object $post_type The unprepared post type data
	 * @return array The prepared post type data
	 */
	function prepare_post_type( $post_type ) {
		$_post_type = (array) $post_type;

		$_post_type['taxonomies'] = get_object_taxonomies( $_post_type['name'] );

		return apply_filters( 'xmlrpc_prepare_post_type', $_post_type, $post_type );
	}

	/**
	 * Create a new user
	 *
	 * @uses wp_insert_user()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - array     $content_struct.
	 *      The $content_struct must contain:
	 *      - 'username'
	 *      - 'password'
	 *      - 'email'
	 *      Also, it can optionally contain:
	 *      - 'role'
	 *      - 'first_name'
	 *      - 'last_name'
	 *      - 'website'
	 *  - boolean $send_mail optional. Defaults to false
	 * @return string user_id
	 */
	function wp_newUser( $args ) {

		global $wp_roles;
		$this->escape($args);

		$blog_id        = (int) $args[0];
		$username       = $args[1];
		$password       = $args[2];
		$content_struct = $args[3];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		if ( ! current_user_can( 'create_users' ) )
			return new IXR_Error( 401, __( 'You are not allowed to create users.' ) );

		// this hold all the user data
		$user_data = array();

		$user_data['user_login'] = '';
		if( isset ( $content_struct['username'] ) ) {

			$user_data['user_login'] = sanitize_user( $content_struct['username'] );

			//Remove any non-printable chars from the login string to see if we have ended up with an empty username
			$user_data['user_login'] = trim( $user_data['user_login'] );

		}

		if( empty ( $user_data['user_login'] ) )
			return new IXR_Error( 403, __( 'Cannot create a user with an empty login name.' ) );
		if( username_exists ( $user_data['user_login'] ) )
			return new IXR_Error( 403, __( 'This username is already registered.' ) );

		//password cannot be empty
		if( empty ( $content_struct['password'] ) )
			return new IXR_Error( 403, __( 'Password cannot be empty.' ) );

		$user_data['user_pass'] = $content_struct['password'];

		// check whether email address is valid
		if( ! is_email( $content_struct['email'] ) )
			return new IXR_Error( 403, __( 'This email address is not valid' ) );

		// check whether it is already registered
		if( email_exists( $content_struct['email'] ) )
			return new IXR_Error( 403, __( 'This email address is already registered' ) );

		$user_data['user_email'] = $content_struct['email'];

		// If no role is specified default role is used
		$user_data['role'] = get_option('default_role');
		if( isset ( $content_struct['role'] ) ) {

			if( ! isset ( $wp_roles ) )
				$wp_roles = new WP_Roles ();

			if( ! array_key_exists( $content_struct['role'], $wp_roles->get_names() ) )
				return new IXR_Error( 403, __( 'The role specified is not valid' ) );

			$user_data['role'] = $content_struct['role'];

		}

		$user_data['first_name'] = '';
		if( isset ( $content_struct['first_name'] ) )
			$user_data['first_name'] = $content_struct['first_name'];

		$user_data['last_name'] = '';
		if( isset ( $content_struct['last_name'] ) )
			$user_data['last_name'] = $content_struct['last_name'];

		$user_data['user_url'] = '';
		if( isset ( $content_struct['url'] ) )
			$user_data['user_url'] = $content_struct['url'];

		$user_id = wp_insert_user( $user_data );

		if ( is_wp_error( $user_id ) )
			return new IXR_Error( 500, $user_id->get_error_message() );

		if ( ! $user_id )
			return new IXR_Error( 500, __( 'Sorry, the new user failed.' ) );

		return $user_id;
	}

	/**
	 * Edit a new user
	 *
	 * @uses wp_update_user()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - int     $user_id
	 *  - string  $username
	 *  - string  $password
	 *  - array     $content_struct.
	 *      It can optionally contain:
	 *      - 'email'
	 *      - 'first_name'
	 *      - 'last_name'
	 *      - 'website'
	 *      - 'role'
	 *      - 'nickname'
	 *      - 'usernicename'
	 *      - 'bio'
	 *      - 'usercontacts'
	 *      - 'password'
	 *  - boolean $send_mail optional. Defaults to false
	 * @return string user_id
	 */
	function wp_editUser( $args ) {

		global $wp_roles;
		$this->escape( $args );

		$blog_id        = (int) $args[0];
		$username       = $args[1];
		$password       = $args[2];
		$user_ID        = (int) $args[3];
		$content_struct = $args[4];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		$user_info = get_userdata( $user_ID );

		if( ! $user_info )
			return new IXR_Error(404, __('Invalid user ID.'));

		if( ! ( $user_ID == $user->ID || current_user_can( 'edit_users' ) ) )
			return new IXR_Error(401, __('Sorry, you cannot edit this user.'));

		// holds data of the user
		$user_data = array();
		$user_data['ID'] = $user_ID;

		if ( isset( $content_struct['username'] ) && $content_struct['username'] !== $user_info->user_login )
			return new IXR_Error(401, __('Username cannot be changed.'));

		if ( isset( $content_struct['email'] ) ) {

			if( ! is_email( $content_struct['email'] ) )
				return new IXR_Error( 403, __( 'This email address is not valid.' ) );

			// check whether it is already registered
			if( $content_struct['email'] !== $user_info->user_email && email_exists( $content_struct['email'] ) )
				return new IXR_Error( 403, __( 'This email address is already registered.' ) );

			$user_data['user_email'] = $content_struct['email'];

		}

		if( isset ( $content_struct['role'] ) ) {

			if ( ! current_user_can( 'edit_users' ) )
				return new IXR_Error( 401, __( 'You are not allowed to change roles for this user' ) );

			if( ! isset ( $wp_roles ) )
				$wp_roles = new WP_Roles ();

			if( !array_key_exists( $content_struct['role'], $wp_roles->get_names() ) )
				return new IXR_Error( 403, __( 'The role specified is not valid' ) );

			$user_data['role'] = $content_struct['role'];

		}

		// only set the user details if it was given
		if ( isset( $content_struct['first_name'] ) )
			$user_data['first_name'] = $content_struct['first_name'];

		if ( isset( $content_struct['last_name'] ) )
			$user_data['last_name'] = $content_struct['last_name'];

		if ( isset( $content_struct['website'] ) )
			$user_data['user_url'] = $content_struct['url'];

		if ( isset( $content_struct['nickname'] ) )
			$user_data['nickname'] = $content_struct['nickname'];

		if ( isset( $content_struct['usernicename'] ) )
			$user_data['user_nicename'] = $content_struct['nicename'];

		if ( isset( $content_struct['bio'] ) )
			$user_data['description'] = $content_struct['bio'];

		if( isset ( $content_struct['user_contacts'] ) ) {

			$user_contacts = _wp_get_user_contactmethods( $user_data );
			foreach( $content_struct['user_contacts'] as $key => $value ) {

				if( ! array_key_exists( $key, $user_contacts ) )
					return new IXR_Error( 401, __( 'One of the contact method specified is not valid' ) );

				$user_data[ $key ] = $value;
			}
		}

		if( isset ( $content_struct['password'] ) )
			$user_data['user_pass'] = $content_struct['password'];

		$result = wp_update_user( $user_data );

		if ( is_wp_error( $result ) )
			return new IXR_Error( 500, $result->get_error_message() );

		if ( ! $result )
			return new IXR_Error( 500, __( 'Sorry, the user cannot be updated. Something wrong happened.' ) );

		return $result;
	}

	/**
	 * Delete a  post
	 *
	 * @uses wp_delete_user()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - array   $user_ids
	 * @return array user_ids
	 */
	function wp_deleteUser( $args ) {
		$this->escape( $args );

		$blog_id    = (int) $args[0];
		$username   = $args[1];
		$password   = $args[2];
		$user_IDs   = $args[3]; // can be an array of user ID's

		if( ! $user = $this->login( $username, $password ) )
			return $this->error;

		if( ! current_user_can( 'delete_users' ) )
			return new IXR_Error( 401, __( 'You are not allowed to delete users.' ) );

		// if only a single ID is given convert it to an array
		if( ! is_array( $user_IDs ) )
			$user_IDs = array( (int)$user_IDs );

		foreach( $user_IDs as $user_ID ) {

			$user_ID = (int) $user_ID;

			if( ! get_userdata( $user_ID ) )
			return new IXR_Error(404, __('Sorry, one of the given user does not exist.'));

			if( $user->ID == $user_ID )
			return new IXR_Error( 401, __( 'You cannot delete yourself.' ) );

		}

		// this holds all the id of deleted users and return it
		$deleted_users = array();

		foreach( $user_IDs as $user_ID ) {

			$result = wp_delete_user( $user_ID );
			if ( $result )
				$deleted_users[] = $user_ID;

		}

		return $deleted_users;
	}

	/**
	 * Retrieve  user
	 *
	 * @uses get_userdata()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - int     $user_id
	 * @return array contains:
	 *  - 'user_login'
	 *  - 'user_firstname'
	 *  - 'user_lastname'
	 *  - 'user_registered'
	 *  - 'user_description'
	 *  - 'user_email'
	 *  - 'nickname'
	 *  - 'user_nicename'
	 *  - 'user_url'
	 *  - 'display_name'
	 *  - 'usercontacts'
	 */
	function wp_getUser( $args ) {
		$this->escape( $args );

		$blog_id    = (int) $args[0];
		$username   = $args[1];
		$password   = $args[2];
		$user_id    = (int) $args[3];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		$user_data = get_userdata( $user_id );

		if( ! $user_data )
			return new IXR_Error(404, __('Invalid user ID'));

		if( ! ( $user_id == $user->ID || current_user_can( 'edit_users' ) ))
			return new IXR_Error( 401, __( 'Sorry, you cannot edit users.' ) );

		$user = $this->prepare_user( $user_data );

		return $user;
	}

	/**
	 * Retrieve  users
	 *
	 * @uses get_users()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - array   $filter optional
	 * @return array contatins:
	 *  - 'ID'
	 *  - 'user_login'
	 *  - 'user_registered'
	 *  - 'user_email'
	 *  - 'user_url'
	 *  - 'display_name'
	 *  - 'user_nicename'
	 */
	function wp_getUsers( $args ) {
		$this->escape( $args );

		$blog_id    = (int) $args[0];
		$username   = $args[1];
		$password   = $args[2];
		$filter     = isset( $args[3] ) ? $args[3] : array();

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		if( ! current_user_can( 'edit_users' ))
			return new IXR_Error( 401, __( 'Sorry, you cannot edit users.' ) );

		$query = array();

		// only retrieve IDs since wp_getUser will ignore anything else
		$query['fields'] = array( 'ID' );

		$query['number'] = ( isset( $filter['number'] ) ) ? absint( $filter['number'] ) : 50;
		$query['offset'] = ( isset( $filter['offset'] ) ) ? absint( $filter['offset'] ) : 0;

		if ( isset( $filter['role'] ) ) {
			global $wp_roles;

			if( ! isset ( $wp_roles ) )
				$wp_roles = new WP_Roles ();

			if( ! array_key_exists( $filter['role'], $wp_roles->get_names() ) )
				return new IXR_Error( 403, __( 'The role specified is not valid' ) );

			$query['role'] = $filter['role'];
		}

		$users = get_users( $query );

		$_users = array();
		foreach ( $users as $user_data ) {
			$_users[] = $this->prepare_user( get_userdata( $user_data->ID ) );
		}

		return $_users;
	}

	/**
	 * Create a new post
	 *
	 * @uses wp_insert_post()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - array     $content_struct.
	 *      The $content_struct must contain:
	 *      - 'post_type'
	 *      Also, it can optionally contain:
	 *      - 'post_status'
	 *      - 'wp_password'
	 *      - 'wp_slug
	 *      - 'wp_page_order'
	 *      - 'wp_page_parent_id'
	 *      - 'wp_page_template'
	 *      - 'wp_author_id'
	 *      - 'title'
	 *      - 'description'
	 *      - 'mt_excerpt'
	 *      - 'mt_allow_comments'
	 *      - 'mt_allow_pings'
	 *      - 'mt_text_more'
	 *      - 'mt_tb_ping_urls'
	 *      - 'date_created_gmt'
	 *      - 'dateCreated'
	 *      - 'sticky'
	 *      - 'custom_fields'
	 *      - 'terms'
	 *      - 'categories'
	 *      - 'mt_keywords'
	 *      - 'wp_post_format'
	 *  - boolean $publish optional. Defaults to true
	 * @return string post_id
	 */
	function wp_newPost( $args ) {
		$this->escape($args);

		$blog_id        = (int) $args[0]; // for future use
		$username       = $args[1];
		$password       = $args[2];
		$content_struct = $args[3];
		$publish        = isset( $args[4] ) ? $args[4] : false;

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		$post_type = get_post_type_object( $content_struct['post_type'] );
		if( ! ( (bool)$post_type ) )
			return new IXR_Error( 403, __( 'Invalid post type' ) );

		if( ! current_user_can( $post_type->cap->edit_posts ) )
			return new IXR_Error( 401, __( 'Sorry, you are not allowed to create posts in this post type' ));

		// this holds all the post data needed
		$post_data = array();
		$post_data['post_type'] = $content_struct['post_type'];

		$post_data['post_status'] = $publish ? 'publish' : 'draft';

		if( isset ( $content_struct["{$content_struct['post_type']}_status"] ) )
			$post_data['post_status'] = $content_struct["{$post_data['post_type']}_status"];

		switch ( $post_data['post_status'] ) {

			case 'draft':
				break;
			case 'pending':
				break;
			case 'private':
				if( ! current_user_can( $post_type->cap->publish_posts ) )
					return new IXR_Error( 401, __( 'Sorry, you are not allowed to create private posts in this post type' ));
				break;
			case 'publish':
				if( ! current_user_can( $post_type->cap->publish_posts ) )
					return new IXR_Error( 401, __( 'Sorry, you are not allowed to publish posts in this post type' ));
				break;
			default:
				return new IXR_Error( 401, __( 'Invalid post status' ) );
				break;

		}

		// Only use a password if one was given.
		if ( isset( $content_struct['wp_password'] ) ) {

			if( ! current_user_can( $post_type->cap->publish_posts ) )
				return new IXR_Error( 401, __( 'Sorry, you are not allowed to create password protected posts in this post type' ) );

			$post_data['post_password'] = $content_struct['wp_password'];

		}

		// Let WordPress generate the post_name (slug) unless one has been provided.
		$post_data['post_name'] = "";
		if ( isset( $content_struct['wp_slug'] ) )
			$post_data['post_name'] = $content_struct['wp_slug'];

		if ( isset( $content_struct['wp_page_order'] ) ) {

			if( ! post_type_supports( $content_struct['post_type'] , 'page-attributes' ) )
				return new IXR_Error( 401, __( 'This post type does not support page attributes.' ) );

			$post_data['menu_order'] = (int)$content_struct['wp_page_order'];

		}

		if ( isset( $content_struct['wp_page_parent_id'] ) ) {

			if( ! $post_type->hierarchical )
				return new IXR_Error( 401, __( 'This post type does not support post hierarchy.' ) );

			// validating parent ID
			$parent_ID = (int)$content_struct['wp_page_parent_id'];
			if( $parent_ID != 0 ) {

				$parent_post = (array)wp_get_single_post( $parent_ID );
				if ( empty( $parent_post['ID'] ) )
					return new IXR_Error( 401, __( 'Invalid parent ID.' ) );

				if ( $parent_post['post_type'] != $content_struct['post_type'] )
					return new IXR_Error( 401, __( 'The parent post is of different post type.' ) );

			}

			$post_data['post_parent'] = $content_struct['wp_page_parent_id'];

		}

		// page template is only supported only by pages
		if ( isset( $content_struct['wp_page_template'] ) ) {

			if( $content_struct['post_type'] != 'page' )
				return new IXR_Error( 401, __( 'Page templates are only supported by pages.' ) );

			// validating page template
			$page_templates = get_page_templates( );
			$page_templates['Default'] = 'default';

			if( ! array_key_exists( $content_struct['wp_page_template'], $page_templates ) )
				return new IXR_Error( 403, __( 'Invalid page template.' ) );

			$post_data['page_template'] = $content_struct['wp_page_template'];

		}

		$post_data['post_author '] = $user->ID;

		// If an author id was provided then use it instead.
		if( isset( $content_struct['wp_author_id'] ) && ( $user->ID != (int)$content_struct['wp_author_id'] ) ) {

			if( ! post_type_supports( $content_struct['post_type'] , 'author' ) )
				return new IXR_Error( 401, __( 'This post type does not support to set author.' ) );

			if( ! current_user_can( $post_type->cap->edit_others_posts ) )
				return new IXR_Error( 401, __( 'You are not allowed to create posts as this user.' ) );

			$author_ID = (int)$content_struct['wp_author_id'];

			$author = get_userdata( $author_ID );
			if( ! $author )
				return new IXR_Error( 404, __( 'Invalid author ID.' ) );

			$post_data['post_author '] = $author_ID;

		}

		if( isset ( $content_struct['title'] ) ) {

			if( ! post_type_supports( $content_struct['post_type'] , 'title' ) )
				return new IXR_Error( 401, __('This post type does not support title attribute.') );

			$post_data['post_title'] = $content_struct['title'];

		}

		if( isset ( $content_struct['description'] ) ) {

			if( ! post_type_supports( $content_struct['post_type'] , 'editor' ) )
				return new IXR_Error( 401, __( 'This post type does not support post content.' ) );

			$post_data['post_content'] = $content_struct['description'];

		}

		if( isset ( $content_struct['mt_excerpt'] ) ) {

			if( ! post_type_supports( $content_struct['post_type'] , 'excerpt' ) )
				return new IXR_Error( 401, __( 'This post type does not support post excerpt.' ) );

			$post_data['post_excerpt'] = $content_struct['mt_excerpt'];

		}

		if( post_type_supports( $content_struct['post_type'], 'comments' ) ) {

			$post_data['comment_status'] = get_option('default_comment_status');

			if( isset( $content_struct['mt_allow_comments'] ) ) {

				if( ! is_numeric( $content_struct['mt_allow_comments'] ) ) {

					switch ( $content_struct['mt_allow_comments'] ) {
						case 'closed':
							$post_data['comment_status']= 'closed';
							break;
						case 'open':
							$post_data['comment_status'] = 'open';
							break;
						default:
							return new IXR_Error( 401, __( 'Invalid comment option.' ) );
					}

				} else {

					switch ( (int) $content_struct['mt_allow_comments'] ) {
						case 0: // for backward compatiblity
						case 2:
							$post_data['comment_status'] = 'closed';
							break;
						case 1:
							$post_data['comment_status'] = 'open';
							break;
						default:
							return new IXR_Error( 401, __( 'Invalid comment option.' ) );
					}

				}

			}

		} else {

			if( isset( $content_struct['mt_allow_comments'] ) )
				return new IXR_Error( 401, __( 'This post type does not support comments.' ) );

		}

		if( post_type_supports( $content_struct['post_type'], 'trackbacks' ) ) {

			$post_data['ping_status'] = get_option('default_ping_status');

			if( isset( $content_struct['mt_allow_pings'] ) ) {

				if ( ! is_numeric( $content_struct['mt_allow_pings'] ) ) {

					switch ( $content_struct['mt_allow_pings'] ) {
						case 'closed':
							$post_data['ping_status']= 'closed';
							break;
						case 'open':
							$post_data['ping_status'] = 'open';
							break;
						default:
							return new IXR_Error( 401, __( 'Invalid ping option.' ) );
					}

				} else {

					switch ( (int) $content_struct['mt_allow_pings'] ) {
						case 0:
						case 2:
							$post_data['ping_status'] = 'closed';
							break;
						case 1:
							$post_data['ping_status'] = 'open';
							break;
						default:
							return new IXR_Error( 401, __( 'Invalid ping option.' ) );
					}

				}

			}

		} else {

			if( isset( $content_struct['mt_allow_pings'] ) )
				return new IXR_Error( 401, __( 'This post type does not support trackbacks.' ) );

		}

		$post_data['post_more'] = null;
		if( isset( $content_struct['mt_text_more'] ) ) {

			$post_data['post_more'] = $content_struct['mt_text_more'];
			$post_data['post_content'] = $post_data['post_content'] . '<!--more-->' . $post_data['post_more'];

		}

		$post_data['to_ping'] = null;
		if ( isset( $content_struct['mt_tb_ping_urls'] ) ) {

			$post_data['to_ping'] = $content_struct['mt_tb_ping_urls'];

			if ( is_array( $to_ping ) )
				$post_data['to_ping'] = implode(' ', $to_ping);

		}

		// Do some timestamp voodoo
		if ( ! empty( $content_struct['date_created_gmt'] ) )
			$dateCreated = str_replace( 'Z', '', $content_struct['date_created_gmt']->getIso() ) . 'Z'; // We know this is supposed to be GMT, so we're going to slap that Z on there by force
		elseif ( !empty( $content_struct['dateCreated']) )
			$dateCreated = $content_struct['dateCreated']->getIso();

		if ( ! empty( $dateCreated ) ) {
			$post_data['post_date'] = get_date_from_gmt( iso8601_to_datetime( $dateCreated ) );
			$post_data['post_date_gmt'] = iso8601_to_datetime( $dateCreated, 'GMT' );
		} else {
			$post_data['post_date'] = current_time('mysql');
			$post_data['post_date_gmt'] = current_time('mysql', 1);
		}

		// we got everything we need
		$post_ID = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_ID ) )
			return new IXR_Error( 500, $post_ID->get_error_message() );

		if ( ! $post_ID )
			return new IXR_Error( 401, __( 'Sorry, your entry could not be posted. Something wrong happened.' ) );

		// the default is to unstick
		if( $content_struct['post_type'] == 'post' ) {

			$sticky = $content_struct['sticky'] ? true : false;
			if( $sticky ) {

				if( $post_data['post_status'] != 'publish' )
					return new IXR_Error( 401, __( 'Only published posts can be made sticky.' ));

				if( ! current_user_can( $post_type->cap->edit_others_posts ) )
					return new IXR_Error( 401, __( 'Sorry, you are not allowed to stick this post.' ) );

				stick_post( $post_ID );

			} else {

				unstick_post( $post_ID );

			}

		} else {

			if( isset ( $content_struct['sticky'] ) )
				return new IXR_Error( 401, __( 'Sorry, only posts can be sticky.' ) );

		}

		if( isset ( $content_struct['custom_fields'] ) ) {

			if( ! post_type_supports( $content_struct['post_type'], 'custom-fields' ) )
				return new IXR_Error( 401, __( 'This post type does not support custom fields.' ) );

			$this->set_custom_fields( $post_ID, $content_struct['custom_fields'] );

		}

		$post_type_taxonomies = get_object_taxonomies( $content_struct['post_type'] );

		if( isset( $content_struct['terms'] ) ) {

			$terms = $content_struct['terms'];
			$taxonomies = array_keys( $terms );

			// validating term ids
			foreach( $taxonomies as $taxonomy ) {

				if( ! in_array( $taxonomy , $post_type_taxonomies ) )
					return new IXR_Error( 401, __( 'Sorry, one of the given taxonomy is not supported by the post type.' ));

				$term_ids = $terms[ $taxonomy ];
				foreach ( $term_ids as $term_id) {

					$term = get_term( $term_id, $taxonomy );

					if ( is_wp_error( $term ) )
						return new IXR_Error( 500, $term->get_error_message() );

					if ( ! $term )
						return new IXR_Error( 401, __( 'Invalid term ID' ) );

				}

			}

			foreach( $taxonomies as $taxonomy ) {

				$term_ids = $terms[ $taxonomy ];
				$term_ids = array_map( 'intval', $term_ids );
				$term_ids = array_unique( $term_ids );
				wp_set_object_terms( $post_ID , $term_ids, $taxonomy , $append);

			}

			return true;

		}

		// backward compatiblity
		if ( isset( $content_struct['categories'] ) ) {

			if( ! in_array( 'category', $post_type_taxonomies ) )
				return new IXR_Error( 401, __( 'Sorry, Categories are not supported by the post type' ));

			$category_names = $content_struct['categories'];

			foreach( $category_names as $category_name ) {
				$category_ID = get_cat_ID( $category_name );

				if( ! $category_ID )
					return new IXR_Error( 401, __( 'Sorry, one of the given categories does not exist!' ));

				$post_categories[] = $category_ID;
			}

			wp_set_post_categories ($post_ID, $post_categories );

		}

		if( isset( $content_struct['mt_keywords'] ) ) {

			if( ! in_array( 'post_tag' , $post_type_taxonomies ) )
				return new IXR_Error( 401, __( 'Sorry, post tags are not supported by the post type' ));

			wp_set_post_terms( $post_id, $tags, 'post_tag', false); // append is set false here

		}

		if( isset( $content_struct['wp_post_format'] ) ) {

			if( ! in_array( 'post_format' , $post_type_taxonomies ) )
				return new IXR_Error( 401, __( 'Sorry, post formats are not supported by the post type' ));

			wp_set_post_terms( $post_ID, array( 'post-format-' . $content_struct['wp_post_format'] ), 'post_format' );

		}

		// Handle enclosures
		$thisEnclosure = isset($content_struct['enclosure']) ? $content_struct['enclosure'] : null;
		$this->add_enclosure_if_new($post_ID, $thisEnclosure);
		$this->attach_uploads( $post_ID, $post_data['post_content'] );

		return strval( $post_ID );

	}

	/**
	 * Edit a  post
	 *
	 * @uses wp_update_post()
	 * @param array $args Method parameters. Contains:
	 *  - int     $post_id
	 *  - string  $username
	 *  - string  $password
	 *  - array     $content_struct
	 *  - boolean $publish optional. Defaults to true
	 * @return string post_id
	 */
	function wp_editPost($args) {
		$this->escape($args);

		$post_ID        = (int) $args[0];
		$username       = $args[1];
		$password       = $args[2];
		$content_struct = $args[3];
		$publish        = isset( $args[4] ) ? $args[4] : false;

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		$post = wp_get_single_post( $post_ID, ARRAY_A );
		if ( empty( $post['ID'] ) )
			return new IXR_Error( 404, __( 'Invalid post ID.' ) );

		$post_type = get_post_type_object( $post['post_type'] );
		if( ! current_user_can( $post_type->cap->edit_posts, $post_ID ) )
			return new IXR_Error( 401, __( 'Sorry, you are not allowed to create posts in this post type' ));

		// this holds all the post data needed
		$post_data = array();
		$post_data['ID'] = $post_ID;
		$post_data['post_status'] = $publish ? 'publish' : 'draft';

		if( isset ( $content_struct["{$content_struct['post_type']}_status"] ) )
			$post_data['post_status'] = $content_struct["{$post_data['post_type']}_status"];

		switch ( $post_data['post_status'] ) {

			case 'draft':
				break;
			case 'pending':
				break;
			case 'private':
				if( ! current_user_can( $post_type->cap->publish_posts, $post_ID ) )
					return new IXR_Error( 401, __( 'Sorry, you are not allowed to create private posts in this post type' ));
				break;
			case 'publish':
				if( ! current_user_can( $post_type->cap->publish_posts, $post_ID ) )
					return new IXR_Error( 401, __( 'Sorry, you are not allowed to publish posts in this post type' ));
				break;
			default:
				return new IXR_Error( 401, __( 'The post status specified is not valid' ) );
				break;

		}

		// Only use a password if one was given.
		if ( isset( $content_struct['wp_password'] ) ) {

			if( ! current_user_can( $post_type->cap->publish_posts ) )
				return new IXR_Error( 401, __( 'Sorry, you are not allowed to create password protected posts in this post type' ));

			$post_data['post_password'] = $content_struct['wp_password'];

		}

		if ( isset( $content_struct['wp_slug'] ) )
			$post_data['post_name'] = $content_struct['wp_slug'];

		if ( isset( $content_struct['wp_page_order'] ) ) {

			if( ! post_type_supports( $content_struct['post_type'] , 'page-attributes' ) )
				return new IXR_Error( 401, __( 'This post type does not support page attributes' ) );

			$post_data['menu_order'] = $content_struct['wp_page_order'];

		}

		if ( isset( $content_struct['wp_page_parent_id'] ) ) {

			if( ! $post_type->hierarchical )
				return new IXR_Error(401, __('This post type does not support post hierarchy'));

			// validating parent ID
			$parent_ID = (int)$content_struct['wp_page_parent_id'];
			if( $parent_ID != 0 ) {

				$parent_post = (array)wp_get_single_post( $parent_ID );
				if ( empty( $parent_post['ID'] ) )
					return new IXR_Error( 401, __( 'Invalid parent ID.' ) );

				if ( $parent_post['post_type'] != $content_struct['post_type'] )
					return new IXR_Error( 401, __( 'The parent post is of different post type' ) );

			}

			$post_data['post_parent'] = $content_struct['wp_page_parent_id'];

		}

		// page template is only supported only by pages
		if ( isset( $content_struct['wp_page_template'] ) ) {

			if( $content_struct['post_type'] != 'page' )
				return new IXR_Error(401, __('Page templates are only supported by pages'));

			// validating page template
			$page_templates = get_page_templates( );
			$page_templates['Default'] = 'default';

			if( ! array_key_exists( $content_struct['wp_page_template'], $page_templates ) )
				return new IXR_Error( 403, __( 'Invalid page template.' ) );

			$post_data['page_template'] = $content_struct['wp_page_template'];

		}

		// If an author id was provided then use it instead.
		if( isset( $content_struct['wp_author_id'] ) && ( $user->ID != $content_struct['wp_author_id'] ) ) {

			if( ! post_type_supports( $content_struct['post_type'] , 'author' ) )
				return new IXR_Error( 401, __( 'This post type does not support to set author.' ) );

			if( ! current_user_can( $post_type->cap->edit_others_posts ) )
				return new IXR_Error( 401, __( 'You are not allowed to create posts as this user.' ) );

			$author_ID = (int)$content_struct['wp_author_id'];

			$author = get_userdata( $author_ID );
			if( ! $author )
				return new IXR_Error( 404, __( 'Invalid author ID.' ) );

			$post_data['post_author '] = $author_ID;

		}

		if( isset ( $content_struct['title'] ) ) {

			if( ! post_type_supports( $content_struct['post_type'] , 'title' ) )
				return new IXR_Error( 401, __( 'This post type does not support title attribute.' ) );

			$post_data['post_title'] = $content_struct['title'];

		}

		if( isset ( $content_struct['post_content'] ) ) {

			if( ! post_type_supports( $content_struct['post_type'] , 'editor' ) )
				return new IXR_Error( 401, __( 'This post type does not support post content.' ) );

			$post_data['post_content'] = $content_struct['post_content'];

		}

		if( isset ( $content_struct['mt_excerpt'] ) ) {

			if( ! post_type_supports( $content_struct['post_type'] , 'excerpt' ) )
				return new IXR_Error( 401, __( 'This post type does not support post excerpt.' ) );

			$post_data['post_excerpt'] = $content_struct['mt_excerpt'];

		}

		if( isset( $content_struct['mt_allow_comments'] ) ) {

			if( ! post_type_supports( $content_struct['post_type'], 'comments' ) )
				return new IXR_Error( 401, __( 'This post type does not support comments.' ) );

			if ( ! is_numeric( $content_struct['mt_allow_comments'] ) ) {

				switch ( $content_struct['mt_allow_comments'] ) {
					case 'closed':
						$post_data['comment_status']= 'closed';
						break;
					case 'open':
						$post_data['comment_status'] = 'open';
						break;
					default:
						return new IXR_Error( 401, __ ( 'Invalid comment option' ) );
				}

			} else {

				switch ( (int) $content_struct['mt_allow_comments'] ) {
					case 0:
					case 2:
						$post_data['comment_status'] = 'closed';
						break;
					case 1:
						$post_data['comment_status'] = 'open';
						break;
					default:
						return new IXR_Error( 401, __ ( 'Invalid ping option.' ) );
				}

			}

		}

		if( isset( $content_struct['mt_allow_pings'] ) ) {

			if( ! post_type_supports( $content_struct['post_type'], 'trackbacks' ) )
				return new IXR_Error(401, __('This post type does not support trackbacks'));

			if ( ! is_numeric( $content_struct['mt_allow_pings'] ) ) {

				switch ( $content_struct['mt_allow_pings'] ) {
					case 'closed':
						$post_data['ping_status']= 'closed';
						break;
					case 'open':
						$post_data['ping_status'] = 'open';
						break;
					default:
						break;
				}

			} else {

				switch ( (int) $content_struct['mt_allow_pings'] ) {
					case 0:
					case 2:
						$post_data['ping_status'] = 'closed';
						break;
					case 1:
						$post_data['ping_status'] = 'open';
						break;
					default:
						break;
				}

			}

		}

		if( isset( $content_struct['mt_text_more'] ) ) {

			$post_data['post_more'] = $content_struct['mt_text_more'];
			$post_data['post_content'] = $post_data['post_content'] . '<!--more-->' . $post_data['post_more'];

		}

		if ( isset( $content_struct['mt_tb_ping_urls'] ) ) {

			$post_data['to_ping'] = $content_struct['mt_tb_ping_urls'];
			if ( is_array($to_ping) )
				$post_data['to_ping'] = implode(' ', $to_ping);

		}

		// Do some timestamp voodoo
		if ( ! empty( $content_struct['date_created_gmt'] ) )
			$dateCreated = str_replace( 'Z', '', $content_struct['date_created_gmt']->getIso() ) . 'Z'; // We know this is supposed to be GMT, so we're going to slap that Z on there by force
		elseif ( !empty( $content_struct['dateCreated']) )
			$dateCreated = $content_struct['dateCreated']->getIso();

		if ( ! empty( $dateCreated ) ) {

			$post_data['post_date'] = get_date_from_gmt(iso8601_to_datetime($dateCreated));
			$post_data['post_date_gmt'] = iso8601_to_datetime($dateCreated, 'GMT');

		}

		// we got everything we need
		$post_ID = wp_update_post( $post_data, true );

		if ( is_wp_error( $post_ID ) )
			return new IXR_Error(500, $post_ID->get_error_message());

		if ( ! $post_ID )
			return new IXR_Error(500, __('Sorry, your entry could not be posted. Something wrong happened.'));

		if( isset ( $content_struct['sticky'] ) ) {

			$sticky = $content_struct['sticky'] ? true : false;

			if( $sticky ) {

				if( $post_data['post_status'] != 'publish' )
					return new IXR_Error( 401, __( 'Only published posts can be made sticky.' ));

				if( ! current_user_can( $post_type->cap->edit_others_posts ) )
					return new IXR_Error( 401, __( 'Sorry, you are not allowed to stick this post.' ) );

				stick_post( $post_ID );

			} else {

				unstick_post( $post_ID );

			}

		}

		if( isset ( $content_struct['custom_fields'] ) ) {

			if( ! post_type_supports( $content_struct['post_type'], 'custom-fields' ) )
				return new IXR_Error(401, __('This post type does not support custom fields'));

			$this->set_custom_fields( $post_ID, $content_struct['custom_fields'] );

		}

		$post_type_taxonomies = get_object_taxonomies( $post['post_type'] );

		if( isset( $content_struct['terms'] ) ) {

			$terms = $content_struct['terms'];
			$taxonomies = array_keys( $terms );

			// validating term ids
			foreach( $taxonomies as $taxonomy ) {

				if( ! in_array( $taxonomy , $post_type_taxonomies ) )
					return new IXR_Error( 401, __( 'Sorry, one of the given taxonomy is not supported by the post type.' ));

				$term_ids = $terms[ $taxonomy ];
				foreach ( $term_ids as $term_id) {

					$term = get_term( $term_id, $taxonomy );

					if ( is_wp_error( $term ) )
						return new IXR_Error( 500, $term->get_error_message() );

					if ( ! $term )
						return new IXR_Error( 401, __( 'Invalid term ID' ) );

				}

			}

			foreach( $taxonomies as $taxonomy ) {

				$term_ids = $terms[ $taxonomy ];
				$term_ids = array_map( 'intval', $term_ids );
				$term_ids = array_unique( $term_ids );
				wp_set_object_terms( $post_ID , $term_ids, $taxonomy , $append);

			}

			return true;

		}

		// backward compatiblity
		if ( isset( $content_struct['categories'] ) ) {

			if( ! in_array( 'category', $post_type_taxonomies ) )
				return new IXR_Error( 401, __( 'Sorry, Categories are not supported by the post type' ));

			$category_names = $content_struct['categories'];

			foreach( $category_names as $category_name ) {
				$category_ID = get_cat_ID( $category_name );

				if( ! $category_ID )
					return new IXR_Error( 401, __( 'Sorry, one of the given categories does not exist!' ));

				$post_categories[] = $category_ID;
			}

			wp_set_post_categories ($post_ID, $post_categories );

		}

		if( isset( $content_struct['mt_keywords'] ) ) {

			if( ! in_array( 'post_tag' , $post_type_taxonomies ) )
				return new IXR_Error( 401, __( 'Sorry, post tags are not supported by the post type' ));

			wp_set_post_terms( $post_id, $tags, 'post_tag', false); // append is set false here
		}

		if( isset( $content_struct['wp_post_format'] ) ) {

			if( ! in_array( 'post_format' , $post_type_taxonomies ) )
				return new IXR_Error( 401, __( 'Sorry, post formats are not supported by the post type' ));

			wp_set_post_terms( $post_ID, array( 'post-format-' . $content_struct['wp_post_format'] ), 'post_format' );

		}

		// Handle enclosures
		$thisEnclosure = isset($content_struct['enclosure']) ? $content_struct['enclosure'] : null;
		$this->add_enclosure_if_new($post_ID, $thisEnclosure);
		$this->attach_uploads( $post_ID, $post_data['post_content'] );

		return strval( $post_ID );

	}

	/**
	 * Delete a  post
	 *
	 * @uses wp_delete_post()
	 * @param array $args Method parameters. Contains:
	 *  - int     $post_ids
	 *  - string  $username
	 *  - string  $password
	 * @return array post_ids
	 */
	function wp_deletePost( $args ) {
		$this->escape( $args );

		$post_IDs = $args[0]; // this could be an array
		$username = $args[1];
		$password = $args[2];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		if( ! is_array( $post_IDs ) )
			$post_IDs = array( (int)$post_IDs );

		foreach ( $post_IDs as $post_ID ) {

			$post_ID = (int)$post_ID;

			$post = wp_get_single_post( $post_ID, ARRAY_A );
			if ( empty( $post["ID"] ) )
				return new IXR_Error( 404, __( 'One of the post ID is invalid.' ) );

			$post_type = get_post_type_object( $post['post_type'] );
			if( ! current_user_can( $post_type->cap->delete_post, $post_ID ) )
				return new IXR_Error( 401, __( 'Sorry, you are not allowed to delete one of the posts.' ));

		}

		// this holds all the id of deleted posts and return it
		$deleted_posts = array();

		foreach( $post_IDs as $post_ID ) {

			$result = wp_delete_post( $post_ID );
			if ( $result )
				$deleted_posts[] = $post_ID;

		}

		return $deleted_posts;

	}

	/**
	 * Retrieve  post
	 *
	 * @uses wp_get_single_post()
	 * @param array $args Method parameters. Contains:
	 *  - int     $post_id
	 *  - string  $username
	 *  - string  $password
	 * @return array contains:
	 *  - 'postid'
	 *  - 'title'
	 *  - 'description'
	 *  - 'mt_excerpt'
	 *  - 'post_status'
	 *  - 'post_type'
	 *  - 'wp_slug'
	 *  - 'wp_password'
	 *  - 'wp_page_order'
	 *  - 'wp_page_parent_id'
	 *  - 'wp_author_id'
	 *  - 'mt_allow_comments'
	 *  - 'mt_allow_pings'
	 *  - 'dateCreated'
	 *  - 'date_created_gmt'
	 *  - 'userid'
	 *  - 'sticky'
	 *  - 'custom_fields'
	 *  - 'terms'
	 *  - 'link'
	 *  - 'permaLink'
	 *  - 'categories'
	 *  - 'mt_keywords'
	 *  - 'wp_post_format'
	 */
	function wp_getPost( $args ) {
		$this->escape( $args );

		$blog_id            = (int) $args[0];
		$username           = $args[1];
		$password           = $args[2];
		$post_id            = (int) $args[3];

		if ( isset( $args[4] ) )
			$fields = $args[4];
		else
			$fields = array( 'post', 'taxonomies', 'custom_fields' );

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		$post = wp_get_single_post( $post_id, ARRAY_A );

		if ( empty( $post["ID"] ) )
			return new IXR_Error( 404, __( 'Invalid post ID.' ) );

		$post_type = get_post_type_object( $post['post_type'] );
		if( ! current_user_can( $post_type->cap->edit_posts, $post_id ) )
			return new IXR_Error( 401, __( 'Sorry, you cannot edit this post.' ));

		return $this->prepare_post( $post, $fields );
	}

	/**
	 * Retrieve posts.
	 *
	 * Besides the common blog_id, username, and password arguments, it takes
	 * a filter array and a fields array.
	 *
	 * Accepted 'filter' keys are 'post_type', 'post_status', 'numberposts', 'offset',
	 * 'orderby', and 'order'.
	 *
	 * The 'fields' array specifies which post fields will be included in the response.
	 * Values can be either conceptual groups ('post', 'taxonomies', 'custom_fields')
	 * or specific field names. By default, all fields are returned.
	 *
	 * @uses wp_get_recent_posts()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - array   $filter optional
	 *  - array   $fields optional
	 * @return array. Contains a collection of posts.
	 */
	function wp_getPosts( $args ) {
		$this->escape( $args );

		$blog_ID    = (int) $args[0];
		$username   = $args[1];
		$password   = $args[2];

		if ( isset( $args[3] ) )
			$filter = $args[3];
		else
			$filter = array();

		if ( isset( $args[4] ) )
			$fields = $args[4];
		else
			$fields = array( 'post', 'taxonomies', 'custom_fields' );

		if ( !$user = $this->login( $username, $password ) )
			return $this->error;

		$query = array();

		if ( isset( $filter['post_type'] ) ) {
			$post_type = get_post_type_object( $filter['post_type'] );
			if( !( (bool)$post_type ) )
				return new IXR_Error( 403, __( 'The post type specified is not valid' ) );

			if( ! current_user_can( $post_type->cap->edit_posts ) )
				return new IXR_Error( 401, __( 'Sorry, you are not allowed to edit posts in this post type' ));
			$query['post_type'] = $filter['post_type'];
		}

		if ( isset( $filter['post_status'] ) ) {
			$query['post_status'] = $filter['post_status'];
		}

		if ( isset ( $filter['numberposts'] ) ) {
			$query['numberposts'] = absint( $filter['numberposts'] );
		}

		if ( isset ( $filter['offset'] ) ) {
			$query['offset'] = absint( $filter['offset'] );
		}

		if ( isset ( $filter['orderby'] ) ) {
			$query['orderby'] = $filter['orderby'];

			if ( isset ( $filter['order'] ) ) {
				$query['order'] = $filter['order'];
			}
		}

		do_action('xmlrpc_call', 'wp.getPosts');

		$posts_list = wp_get_recent_posts( $query );

		if ( !$posts_list )
			return array( );

		// holds all the posts data
		$struct = array();

		foreach ( $posts_list as $post ) {
			$post_type = get_post_type_object( $post['post_type'] );
			if( !current_user_can( $post_type->cap->edit_posts, $post['ID'] ) )
				continue;

			$struct[] = $this->prepare_post( $post, $fields );
		}

		return $struct;
	}

	/**
	 * Retrieve post terms
	 *
	 * @uses wp_get_object_terms()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - int     $post_id
	 * @return array term data
	 */
	function wp_getPostTerms( $args ) {
		$this->escape( $args );

		$blog_id            = (int) $args[0];
		$username           = $args[1];
		$password           = $args[2];
		$post_id            = (int) $args[3];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		$post = wp_get_single_post( $post_id, ARRAY_A );
		if ( empty( $post['ID'] ) )
			return new IXR_Error( 404, __( 'Invalid post ID.' ) );

		$post_type = get_post_type_object( $post['post_type'] );

		if( ! current_user_can( $post_type->cap->edit_post , $post_id ) )
			return new IXR_Error( 401, __( 'Sorry, you are not allowed to edit this post.' ) );

		$taxonomies = get_taxonomies( '' );

		$terms = wp_get_object_terms( $post_id , $taxonomies );

		if ( is_wp_error( $terms ) )
			return new IXR_Error( 500 , $terms->get_error_message() );

		return $terms;
	}

	/**
	 * Set post terms
	 *
	 * @uses wp_set_object_terms()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - int     $post_id
	 *  - array   $content_struct contains term_ids with taxonomy as keys
	 * @return boolean true
	 */
	function wp_setPostTerms( $args ) {
		$this->escape($args);

		$blog_id            = (int) $args[0];
		$username           = $args[1];
		$password           = $args[2];
		$post_ID            = (int) $args[3];
		$content_struct     = $args[4];
		$append             = $args[5] ? true : false;

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		$post = wp_get_single_post( $post_ID, ARRAY_A );
		if ( empty( $post['ID'] ) )
			return new IXR_Error( 404, __( 'Invalid post ID.' ) );

		$post_type = get_post_type_object( $post['post_type'] );

		if( ! current_user_can( $post_type->cap->edit_post , $post_ID ) )
			return new IXR_Error( 401, __( 'Sorry, You are not allowed to edit this post.' ));

		$post_type_taxonomies = get_object_taxonomies( $post['post_type'] );

		$taxonomies = array_keys( $content_struct );

		// validating term ids
		foreach( $taxonomies as $taxonomy ) {

			if( ! in_array( $taxonomy , $post_type_taxonomies ) )
				return new IXR_Error( 401, __( 'Sorry, one of the given taxonomy is not supported by the post type.' ));

			$term_ids = $content_struct[ $taxonomy ];
			foreach ( $term_ids as $term_id) {

				$term = get_term( $term_id, $taxonomy );

				if ( is_wp_error( $term ) )
					return new IXR_Error( 500, $term->get_error_message() );

				if ( ! $term )
					return new IXR_Error( 401, __( 'Invalid term ID' ) );

			}

		}

		foreach( $taxonomies as $taxonomy ) {

			$term_ids = $content_struct[ $taxonomy ];
			$term_ids = array_map( 'intval', $term_ids );
			$term_ids = array_unique( $term_ids );
			wp_set_object_terms( $post_ID , $term_ids, $taxonomy , $append);

		}

		return true;

	}

	/**
	 * Retrieves a post type
	 *
	 * @uses get_post_type_object()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - string  $post_type_name
	 * @return array contains:
	 *  - 'labels'
	 *  - 'description'
	 *  - 'capability_type'
	 *  - 'cap'
	 *  - 'map_meta_cap'
	 *  - 'hierarchical'
	 *  - 'menu_position'
	 *  - 'taxonomies'
	 */
	function wp_getPostType( $args ) {
		$this->escape( $args );

		$blog_id        = (int) $args[0];
		$username       = $args[1];
		$password       = $args[2];
		$post_type_name = $args[3];

		if ( !$user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.getPostType' );

		if( ! post_type_exists( $post_type_name ) )
			return new IXR_Error( 403, __( 'Invalid post type.' ) );

		$post_type = get_post_type_object( $post_type_name );

		if( ! current_user_can( $post_type->cap->edit_posts ) )
			return new IXR_Error( 401, __( 'Sorry, you are not allowed to edit this post type.' ) );

		return $this->prepare_post_type( $post_type );
	}

	/**
	 * Retrieves a post types
	 *
	 * @uses get_post_types()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 * @return array
	 */
	function wp_getPostTypes( $args ) {
		$this->escape( $args );

		$blog_id            = (int) $args[0];
		$username           = $args[1];
		$password           = $args[2];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.getPostTypes' );

		$post_types = get_post_types( '', 'objects' );

		$struct = array();

		foreach( $post_types as $post_type ) {
			if( ! current_user_can( $post_type->cap->edit_posts ) )
				continue;

			$struct[$post_type->name] = $this->prepare_post_type( $post_type );
		}

		return $struct;
	}

	/**
	 * Create a new term
	 *
	 * @uses wp_insert_term()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - array   $content_struct.
	 *      The $content_struct must contain:
	 *      - 'name'
	 *      - 'taxonomy'
	 *      Also, it can optionally contain:
	 *      - 'parent'
	 *      - 'description'
	 *      - 'slug'
	 * @return int term_id
	 */
	function wp_newTerm( $args ) {
		$this->escape( $args );

		$blog_id            = (int) $args[0];
		$username           = $args[1];
		$password           = $args[2];
		$content_struct     = $args[3];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.newTerm' );

		if ( ! taxonomy_exists( $content_struct['taxonomy'] ) )
			return new IXR_Error( 403, __( 'Invalid taxonomy.' ) );

		$taxonomy = get_taxonomy( $content_struct['taxonomy'] );

		if( ! current_user_can( $taxonomy->cap->manage_terms ) )
			return new IXR_Error( 401, __( 'You are not allowed to create terms in this taxonomy.' ) );

		$taxonomy = (array)$taxonomy;

		// hold the data of the term
		$term_data = array();

		$term_data['name'] = trim( $content_struct['name'] );
		if ( empty ( $term_data['name'] ) )
			return new IXR_Error( 403, __( 'The term name cannot be empty.' ) );

		if( isset ( $content_struct['parent'] ) ) {
			if( ! $taxonomy['hierarchical'] )
				return new IXR_Error( 403, __( 'This taxonomy is not hierarchical.' ) );

			$parent_term_id = (int) $content_struct['parent'];
			$parent_term = get_term( $parent_term_id , $taxonomy['name'] );

			if ( is_wp_error( $parent_term ) )
				return new IXR_Error( 500, $parent_term->get_error_message() );

			if ( ! $parent_term )
				return new IXR_Error( 500, __('Parent term does not exist.') );

			$term_data['parent'] = $content_struct['parent'];
		}

		$term_data['description'] = '';
		if( isset ( $content_struct['description'] ) )
			$term_data['description'] = $content_struct['description'];

		$term_data['slug'] = '';
		if( isset ( $content_struct['slug'] ) )
			$term_data['slug'] = $content_struct['slug'];

		$term = wp_insert_term( $term_data['name'] , $taxonomy['name'] , $term_data );

		if ( is_wp_error( $term ) )
			return new IXR_Error( 500, $term->get_error_message() );

		if ( ! $term )
			return new IXR_Error( 500, __('Sorry, your term could not be created. Something wrong happened.') );

		return $term['term_id'];
	}

	/**
	 * Edit a term
	 *
	 * @uses wp_update_term()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - int     $term_id
	 *  - array   $content_struct.
	 *      The $content_struct must contain:
	 *      - 'taxonomy'
	 *      Also, it can optionally contain:
	 *      - 'name'
	 *      - 'parent'
	 *      - 'description'
	 *      - 'slug'
	 *  - boolean $send_mail optional. Defaults to false
	 * @return int term_id
	 */
	function wp_editTerm( $args ) {
		$this->escape( $args );

		$blog_id            = (int) $args[0];
		$username           = $args[1];
		$password           = $args[2];
		$term_id            = (int) $args[3];
		$content_struct     = $args[4];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.editTerm' );

		if ( ! taxonomy_exists( $content_struct['taxonomy'] ) )
			return new IXR_Error( 403, __( 'Invalid taxonomy.' ) );

		$taxonomy = get_taxonomy( $content_struct['taxonomy'] );

		if( ! current_user_can( $taxonomy->cap->edit_terms ) )
			return new IXR_Error( 401, __( 'You are not allowed to edit terms in this taxonomy.' ) );

		$taxonomy = (array) $taxonomy;

		// hold the data of the term
		$term_data = array();

		$term = get_term( $term_id , $content_struct['taxonomy'] );

		if ( is_wp_error( $term ) )
			return new IXR_Error( 500, $term->get_error_message() );

		if ( ! $term )
			return new IXR_Error( 404, __('Invalid term ID.') );

		if( isset ( $content_struct['name'] ) ) {
			$term_data['name'] = trim( $content_struct['name'] );

			if( empty ( $term_data['name'] ) )
				return new IXR_Error( 403, __( 'The term name cannot be empty.' ) );
		}

		if( isset ( $content_struct['parent'] ) ) {
			if( ! $taxonomy['hierarchical'] )
				return new IXR_Error( 403, __( 'This taxonomy is not hierarchical.' ) );

			$parent_term_id = (int) $content_struct['parent'];
			$parent_term = get_term( $parent_term_id , $taxonomy['name'] );

			if ( is_wp_error( $parent_term) )
				return new IXR_Error( 500, $term->get_error_message() );

			if ( ! $parent_term )
				return new IXR_Error( 403, __('Invalid parent term ID.') );

			$term_data['parent'] = $content_struct['parent'];
		}

		if( isset ( $content_struct['description'] ) )
			$term_data['description'] = $content_struct['description'];

		if( isset ( $content_struct['slug'] ) )
			$term_data['slug'] = $content_struct['slug'];

		$term = wp_update_term( $term_id , $taxonomy['name'] , $term_data );

		if ( is_wp_error( $term ) )
			return new IXR_Error( 500, $term->get_error_message() );

		if ( ! $term )
			return new IXR_Error( 500, __('Sorry, editing the term failed.') );

		return $term['term_id'];
	}

	/**
	 * Delete a  term
	 *
	 * @uses wp_delete_term()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - int     $term_id
	 *  - string  $taxnomy_name
	 * @return boolean true
	 */
	function wp_deleteTerm( $args ) {
		$this->escape( $args );

		$blog_id            = (int) $args[0];
		$username           = $args[1];
		$password           = $args[2];
		$term_id            = (int) $args[3];
		$taxonomy_name      = $args[4];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.editTerm' );

		if ( ! taxonomy_exists( $taxonomy_name ) )
			return new IXR_Error( 403, __( 'Invalid taxonomy.' ) );

		$taxonomy = get_taxonomy( $taxonomy_name );

		if( ! current_user_can( $taxonomy->cap->delete_terms ) )
			return new IXR_Error( 401, __( 'You are not allowed to delete terms in this taxonomy.' ) );

		$term = get_term ( $term_id, $taxonomy_name );

		if ( is_wp_error( $term ) )
			return new IXR_Error( 500, $term->get_error_message() );

		if ( ! $term )
			return new IXR_Error( 404, __('Invalid term ID.') );

		$result = wp_delete_term( $term_id, $taxonomy_name );

		if ( is_wp_error( $result ) )
			return new IXR_Error( 500, $term->get_error_message() );

		if ( ! $result )
			return new IXR_Error( 500, __('Sorry, deleting the term failed.') );

		return $result;
	}

	/**
	 * Retrieve a term
	 *
	 * @uses get_term()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - string  $taxonomy_name
	 *  - int     $term_id
	 * @return array contains:
	 *  - 'term_id'
	 *  - 'name'
	 *  - 'slug'
	 *  - 'term_group'
	 *  - 'term_taxonomy_id'
	 *  - 'taxonomy'
	 *  - 'description'
	 *  - 'parent'
	 *  - 'count'
	 */
	function wp_getTerm( $args ) {
		$this->escape( $args );

		$blog_id            = (int) $args[0];
		$username           = $args[1];
		$password           = $args[2];
		$taxonomy_name      = $args[3];
		$term_id            = (int)$args[4];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.getTerm' );

		if ( ! taxonomy_exists( $taxonomy_name ) )
			return new IXR_Error( 403, __( 'Invalid taxonomy name.' ) );

		$taxonomy = get_taxonomy( $taxonomy_name );

		if( ! current_user_can( $taxonomy->cap->assign_terms ) )
			return new IXR_Error( 401, __( 'You are not allowed to assign terms in this taxonomy.' ) );

		$term = get_term( $term_id , $taxonomy_name );

		if ( is_wp_error( $term ) )
			return new IXR_Error( 500, $term->get_error_message() );

		if ( ! $term )
			return new IXR_Error( 404, __( 'Invalid term ID.' ) );

		return $this->prepare_term( $term );
	}

	/**
	 * Retrieve terms
	 *
	 * @uses get_terms()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - string   $taxonomy_name
	 * @return array terms
	 */
	function wp_getTerms( $args ) {
		$this->escape( $args );

		$blog_id        = (int) $args[0];
		$username       = $args[1];
		$password       = $args[2];
		$taxonomy_name  = $args[3];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.getTerms' );

		if ( ! taxonomy_exists( $taxonomy_name ) )
			return new IXR_Error( 403, __( 'Invalid taxonomy name.' ) );

		$taxonomy = get_taxonomy( $taxonomy_name );

		if( ! current_user_can( $taxonomy->cap->assign_terms ) )
			return new IXR_Error( 401, __( 'You are not allowed to assign terms in this taxonomy.' ) );

		$terms = get_terms( $taxonomy_name , array( 'get' => 'all' ) );

		if ( is_wp_error( $terms ) )
			return new IXR_Error( 500, $terms->get_error_message() );

		$struct = array();

		foreach ( $terms as $term ) {
			$struct[] = $this->prepare_term( $term );
		}

		return $struct;
	}

	/**
	 * Retrieve a taxonomy
	 *
	 * @uses get_taxonomy()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - string  $taxonomy_name
	 * @return array contains:
	 *  - 'labels'
	 *  - 'cap'
	 *  - 'hierarchical'
	 *  - 'object_type'
	 */
	function wp_getTaxonomy( $args ) {
		$this->escape( $args );

		$blog_id        = (int) $args[0];
		$username       = $args[1];
		$password       = $args[2];
		$taxonomy_name  = $args[3];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.getTaxonomy' );

		if( ! taxonomy_exists( $taxonomy_name ) )
			return new IXR_Error( 403, __( 'The taxonomy type specified is not valid' ) );

		$taxonomy = get_taxonomy( $taxonomy_name );

		if( ! current_user_can( $taxonomy->cap->edit_terms ) )
			return new IXR_Error( 401, __( 'Sorry, You are not allowed to edit this post type' ) );

		return $this->prepare_taxonomy( $taxonomy );
	}

	/**
	 * Retrieve taxonomies
	 *
	 * @uses get_taxonomies()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 * @return array taxonomies
	 */
	function wp_getTaxonomies($args) {
		$this->escape( $args );

		$blog_id            = (int) $args[0];
		$username           = $args[1];
		$password           = $args[2];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.getTaxonomies' );

		$taxonomies = get_taxonomies( '', 'objects' );

		// holds all the taxonomy data
		$struct = array();

		foreach( $taxonomies as $taxonomy ) {
			// capability check for post_types
			if( ! current_user_can( $taxonomy->cap->edit_terms ) )
				continue;

			$struct[ $taxonomy->name ] = $this->prepare_taxonomy( $taxonomy );
		}

		return $struct;
	}
}

?>