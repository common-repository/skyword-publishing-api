<?php
/**
 * Created by PhpStorm.
 * User: bmcintyre
 * Date: 9/19/18
 * Time: 9:43 AM
 */

class Skyword_Tags extends Skyword_API_Publish {
    // Connect us with the REST API
    public function hook_rest_server() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    // Register our custom routes
    public function register_routes() {
        $this->namespace = $this->namespace . $this->version;

        /**
         * GET  - /skyword/v1/tags - Get all existing tags
         */
        $tags = '/tags';

        $this->register_tags( $tags );
    }

    function register_tags($tags) {
        /**
         * Get the version string
         * GET - /skyword/v1/tags
         */
        register_rest_route($this->namespace, $tags, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_allTags' ),
                'permission_callback' => '__return_true'
            )
        ) );
    }

    function get_allTags($request) {
        $login = $this->authenticate($request);
        if ( 'success' !== $login['status'] ) {
            return $login['message'];
        }

        $tags = get_tags(array(
            'hide_empty' => false
        ));

        $responseData = array();
        foreach ($tags as $tag)
            $responseData[] = $tag->name;
        return new WP_REST_Response($responseData, 200);
    }
}

global $skyword_tags;
$skyword_tags = new Skyword_Tags;
$skyword_tags->hook_rest_server();