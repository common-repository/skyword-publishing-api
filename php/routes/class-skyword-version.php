<?php
/**
 * Created by PhpStorm.
 * User: bmcintyre
 * Date: 9/19/18
 * Time: 9:43 AM
 */

class Skyword_Version extends Skyword_API_Publish {
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
        $version = '/version';

        $this->register_version( $version );
    }

    function register_version($version) {
        /**
         * Get the version string
         * GET - /skyword/v1/version
         */
        register_rest_route($this->namespace, $version, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_version' ),
                'permission_callback' => '__return_true'
            )
        ) );
    }

    function get_version($request) {
        $login = $this->authenticate($request);
        if ( 'success' !== $login['status'] ) {
            return $login['message'];
        }

        global $wp_version;
        $responseData = array(
            'plugin' => array(
                'version' => SKYWORD_REST_API_VERSION,
                'language' => array(
                    'name' => 'PHP',
                    'version' => phpversion()
                )
            ),
            'cms' => array(
                'name' => 'Wordpress',
                'version' => $wp_version
            )
        );
        return new WP_REST_Response($responseData, 200);
    }
}

global $skyword_version;
$skyword_version = new Skyword_Version;
$skyword_version->hook_rest_server();