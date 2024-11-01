<?php
/**
 * Created by PhpStorm.
 * User: bmcintyre
 * Date: 9/19/18
 * Time: 9:43 AM
 */

class Skyword_Categories extends Skyword_API_Publish {
    // Connect us with the REST API
    public function hook_rest_server() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    // Register our custom routes
    public function register_routes() {
        $this->namespace = $this->namespace . $this->version;

        /**
         * GET  - /skyword/v1/version - Get the version string
         */
        $version = '/categories';

        $this->register_categories( $version );
    }

    function register_categories($version) {
        /**
         * Get the version string
         * GET - /skyword/v1/version
         */
        register_rest_route($this->namespace, $version, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_categories' ),
                'permission_callback' => '__return_true'
            )
        ) );
    }

    function get_categories($request) {
        $login = $this->authenticate($request);
        if ( 'success' !== $login['status'] ) {
            return $login['message'];
        }

        $categories = get_categories(array(
            'hide_empty' => false
        ));

        $responseData = array();
        foreach ($categories as $category)
            $responseData[] = $category->name;

        return new WP_REST_Response($responseData, 200);
    }
}

global $skyword_categories;
$skyword_categories = new Skyword_Categories;
$skyword_categories->hook_rest_server();