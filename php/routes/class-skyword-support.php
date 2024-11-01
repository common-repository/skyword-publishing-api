<?php
/**
 * Created by PhpStorm.
 * User: bmcintyre
 * Date: 9/19/18
 * Time: 9:54 AM
 */

class Skyword_Support extends Skyword_API_Publish {
    // Connect us with the REST API
    public function hook_rest_server() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    // Register our custom routes
    public function register_routes() {
        $this->namespace = $this->namespace . $this->version;

        /**
         * GET  - /skyword/v1/support/phpinfo - Get the phpInfo for troubleshooting
         * GET  - /skyword/v1/support/tagdetails - Get meta data of tags for pruning
         */
        $support = '/support';
        $this->register_support( $support );
    }

    function register_support($support) {
        /**
         * Get the phpInfo
         * GET - /skyword/v1/support/phpinfo
         *
         * This route will return a php error due to the callback being commented out but it's
         *  more secure to only have this functionality available when necessary
         */
        register_rest_route($this->namespace, $support . '/phpinfo', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_phpinfo' ),
                'permission_callback' => '__return_true'
            )
        ) );

        /**
         * Get taxonomy details
         * GET - /skyword/v1/support/tagdetails
         *
         * This is for getting information about when tags were created and last used for potential pruning
         */
        register_rest_route($this->namespace, $support . '/tagdetails', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_tagDetails' ),
                'permission_callback' => '__return_true'
            )
        ) );
    }

    /*
     * Support function to get information about the user's configuration
     *
     * Uncomment if needed for troubleshooting
     *
    function get_phpinfo($request) {
        $login = $this->authenticate($request);
        if ( 'success' !== $login['status'] ) {
            return $login['message'];
        }

        if ( is_ssl() ) {
            return phpinfo();
        }

        return new WP_REST_Response('you must use a secure connection', 403);
    }
    */

    function get_tagDetails($request) {
        $login = $this->authenticate($request);
        if ( 'success' !== $login['status'] ) {
            return $login['message'];
        }

        $countTerms = wp_count_terms('post_tag');

        $args = array(
            'taxonomy' => 'post_tag',
            'hide_empty' => false,
            'number' => 0
        );
        $paginator = $this->paginator($request, $args, $countTerms, function($p) { return get_terms($p); } );
        $terms = $paginator['data'];

        $responseData = array();
        foreach ( $terms as $term ) {
            $termId = $term->term_id;
            $termUsedKey = 'term_' . $termId . '_used';
            $termCreatedKey = 'term_' . $termId . '_created';
            $termUsed = get_term_meta($termId, $termUsedKey, true);
            $termCreated = get_term_meta($termId, $termCreatedKey, true);
            $responseData[] = array(
                'id' => $termId,
                'name' => $term->name,
                'slug' => $term->slug,
                'parent' => $term->parent,
                'created' => $termCreated,
                'lastUsed' => $termUsed
            );
        }

        $response = new WP_REST_Response($responseData, 200);
        foreach ( $paginator['headers'] as $headerName => $headerValue )
            $response->header($headerName, $headerValue);

        return $response;
    }

    /**
     * Validate the apiKey
     */
    protected function authenticate($request) {
        $options  = get_option( 'skyword_plugin_options' );
        $storedHash   = md5($options['skyword_api_key']);
        $incomingHash = $request->get_header("Authentication");
        $response = array();

        if ( $incomingHash === $storedHash ) {
            $response['status'] = 'success';
        } else {
            $response['message'] = new WP_REST_Response('invalid api key', 403);
            $response['status'] = 'error';
        }

        return $response;
    }
}

global $skyword_support;
$skyword_support = new Skyword_Support;
$skyword_support->hook_rest_server();