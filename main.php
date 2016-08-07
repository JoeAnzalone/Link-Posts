<?php
/*
Plugin Name: Link Posts
Plugin URI: https://github.com/JoeAnzalone/Link-Posts
Description: Adds a new meta box to post forms that allow you to import Open Graph data from external URLs into custom fields
Version: 0.0.1
Author: Joe Anzalone
Author URI: http://JoeAnzalone.com
License: GPL2
*/

class LinkPosts {
    function __construct() {
        $this->add_actions();
    }

    function add_actions() {
        add_action('add_meta_boxes', [$this, 'action_add_meta_boxes']);
        $this->add_action_save_post();
    }

    function action_add_meta_boxes() {
        add_meta_box(
            'link-posts-url',                  // Unique ID
            esc_html__( 'URL', 'link-posts' ), // Title
            [$this, 'meta_box_html'],          // Callback function
            'post',                            // Admin page (or post type)
            'side',                            // Context
            'default'                          // Priority
        );
    }

    function add_action_save_post() {
        add_action('save_post', [$this, 'action_save_post'], null, 2);
    }

    function remove_action_save_post() {
        remove_action('save_post', [$this, 'action_save_post'], null, 2);
    }

    function action_save_post($post_id, $post) {
        // Verify the nonce before proceeding
        if ( !isset($_POST['link_posts_nonce']) || !wp_verify_nonce( $_POST['link_posts_nonce'], basename(__FILE__))) {
            return $post_id;
        }

        // Get the post type object
        $post_type = get_post_type_object($post->post_type);

        // Check if the current user has permission to edit the post
        if (!current_user_can($post_type->cap->edit_post, $post_id)) {
            return $post_id;
        }

        $url = $_POST['link-posts-url'];

        if (!$url) {
            return $post_id;
        }

        $og_data = $this->get_og_data($url);

        $host = parse_url($url, PHP_URL_HOST);
        if (strpos($host, 'www') === 0) {
            $host = substr($host, 4);
        }

        $metadata = [
            'linked:url' => $url,
            'linked:og:image' => $og_data['og:image'],
            'linked:og:description' => $og_data['og:description'],
            'linked:host' => $host,
            'linked:og:title' => $og_data['og:title'],
        ];

        $this->remove_action_save_post();

        wp_update_post(['ID' => $post_id, 'post_title' => $og_data['og:title']]);

        $this->add_action_save_post();

        foreach ($metadata as $meta_key => $new_meta_value) {
            // Get the meta value of the custom field key
            $meta_value = get_post_meta($post_id, $meta_key, true);

            if ($new_meta_value && $meta_value === '') {
                // If a new meta value was added and there was no previous value, add it
                add_post_meta($post_id, $meta_key, $new_meta_value, true);
            } elseif ($new_meta_value && $new_meta_value !== $meta_value) {
                // If the new meta value does not match the old value, update it
                update_post_meta($post_id, $meta_key, $new_meta_value);
            } elseif ( '' == $new_meta_value && $meta_value) {
                // If there is no new meta value but an old value exists, delete it
                delete_post_meta($post_id, $meta_key, $meta_value);
            }
        }
    }

    function get_og_data($url) {
        $body = wp_remote_get($url)['body'];

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($body);
        $xpath = new DOMXPath($doc);
        $tag_list = $xpath->query('//meta[starts-with(@property, "og:")]');

        foreach($tag_list as $tag) {
            $og_data[$tag->getAttribute('property')] = $tag->getAttribute('content');
        }

        if (!$og_data['og:title']) {
            $og_data['og:title'] = $xpath->query('//title')[0]->nodeValue;
        }

        return $og_data;
    }

    // Display the post meta box
    function meta_box_html($object, $box) {

      wp_nonce_field(basename(__FILE__), 'link_posts_nonce'); ?>

      <p>
        <label for="link-posts-url"><?php _e('URL to import OG data from', 'link-posts' ); ?></label>
        <br>
        <input class="widefat" type="text" name="link-posts-url" id="link-posts-url" value="" placeholder="https://HTMLbyJoe.tumblr.com" size="30">
      </p>
      <?php
    }

}

new LinkPosts();
