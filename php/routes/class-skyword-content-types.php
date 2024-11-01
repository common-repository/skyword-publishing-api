<?php
/**
 * Created by PhpStorm.
 * User: bmcintyre
 * Date: 9/19/18
 * Time: 9:41 AM
 */

class Skyword_ContentTypes extends Skyword_API_Publish {
    // Connect us with the REST API
    public function hook_rest_server() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    // Register our custom routes
    public function register_routes() {
        $this->namespace = $this->namespace . $this->version;

        /**
         * GET  - /skyword/v1/content-types - Get all content types (post types)
         * GET  - /skyword/v1/content-types/:idString - Get a single content type (post type)
         */
        $contentTypes = '/content-types';
        $this->register_contentTypes( $contentTypes );
    }

    function register_contentTypes($contentTypes) {
        /**
         * Get all content types (post types)
         * GET - /skyword/v1/content-types
         */
        register_rest_route($this->namespace, $contentTypes, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_contentTypes' ),
                'permission_callback' => '__return_true'
            )
        ) );

        /**
         * Get a single content type (post type)
         * GET - /skyword/v1/content-types/:idString
         */
        register_rest_route($this->namespace, $contentTypes . $this->idStringParam, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_contentType' ),
                'args'                => array(
                    'id' => array (
                        'validate_callback' => function($param, $request, $key) {
                            // This is already validated by the param regex
                            return true;
                        }
                    )
                ),
                'permission_callback' => '__return_true'
            )
        ) );
    }

    function get_contentTypes($request) {
        $login = $this->authenticate($request);
        if ( 'success' !== $login['status'] ) {
            return $login['message'];
        }

        $postTypes = get_post_types('', 'objects');

        $responseData = array();

        foreach ( $postTypes as $postType ) {
            $responseData[] = array(
                'id' => $postType->name,
                'name' => $postType->labels->singular_name,
                'description' => $postType->description,
                'fields' => array()
            );
        }

        return new WP_REST_Response($responseData, 200);
    }

    function get_contentType($request) {
        $login = $this->authenticate($request);
        if ( 'success' !== $login['status'] ) {
            return $login['message'];
        }

        $id = $request->get_param( 'id' );
        $args = array(
            'name' => $id
        );
        $postTypes = get_post_types($args, 'objects');

        if ( empty( $postTypes ) ) {
            return new WP_REST_Response('no valid content type with id ' . $id, 404);
        }

        $responseData = array();

        foreach ( $postTypes as $postType ) {
            $responseData[] = array(
                'id' => $postType->name,
                'name' => $postType->labels->singular_name,
                'description' => $postType->description,
                'fields' => array()
            );
        }

        return new WP_REST_Response($responseData, 200);
    }
}

global $skyword_content_types;
$skyword_content_types = new Skyword_ContentTypes;
$skyword_content_types->hook_rest_server();
