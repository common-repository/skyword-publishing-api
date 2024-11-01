<?php
/**
 * Created by PhpStorm.
 * User: bmcintyre
 * Date: 9/19/18
 * Time: 9:34 AM
 */

class Skyword_Taxonomies  extends Skyword_API_Publish {
    // Connect us with the REST API
    public function hook_rest_server() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    // Register our custom routes
    public function register_routes() {
        $this->namespace = $this->namespace . $this->version;

        /**
         * GET  - /skyword/v1/taxonomies - Get all taxonomies
         * GET  - /skyword/v1/taxonomies/:idString - Get a single taxonomy
         * GET  - /skyword/v1/taxonomies/:idString/terms - Get terms from a single taxonomy
         * POST - /skyword/v1/taxonomies - Create a new taxonomy
         * POST - /skyword/v1/taxonomies/:idString/terms - Create a new term for a single taxonomy
         */
        $taxonomies = '/taxonomies';
        $this->register_taxonomies( $taxonomies );
    }

    function register_taxonomies($taxonomies) {
        /**
         * Get all taxonomies
         * GET - /skyword/v1/taxonomies
         */
        register_rest_route($this->namespace, $taxonomies, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_taxonomies' ),
                'permission_callback' => '__return_true'
            )
        ) );

        /**
         * Get a single taxonomy
         * GET - /skyword/v1/taxonomies/:idString
         */
        register_rest_route($this->namespace, $taxonomies . $this->idStringParam, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_taxonomy' ),
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

        /**
         * Get terms from a single taxonomy
         * GET - /skyword/v1/taxonomies/:idString/terms
         */
        register_rest_route($this->namespace, $taxonomies . $this->idStringParam . '/terms', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_terms' ),
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

        /**
         * Create a new term for a single taxonomy
         * POST - /skyword/v1/taxonomies/:idString/terms
         */
        register_rest_route($this->namespace, $taxonomies . $this->idStringParam . '/terms', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_term' ),
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

    function get_taxonomies($request) {
        $login = $this->authenticate($request);
        if ( 'success' !== $login['status'] ) {
            return $login['message'];
        }

        $taxonomies = get_taxonomies(null, 'objects');

        $responseData = array();
        foreach ( $taxonomies as $taxonomy ) {

            $count = wp_count_terms( $taxonomy->name );

            $responseData[] = array(
                'id'    => $taxonomy->name,
                'name'  => $taxonomy->name,
                'description' => null !== $taxonomy->description ? $taxonomy->description : '',
                'numTerms' => $count
            );
        }

        if ( empty( $responseData ) ) {
            return new WP_REST_Response('no taxonomies found on cms', 404);
        }

        $response = new WP_REST_Response($responseData, 200);
        return $response;
    }

    function get_taxonomy($request) {
        $login = $this->authenticate($request);
        if ( 'success' !== $login['status'] ) {
            return $login['message'];
        }

        $taxonomyName = $request->get_param( 'id' );
        $taxonomy = get_taxonomy($taxonomyName);

        if ( false === $taxonomy ) {
            return new WP_REST_Response('taxonomy ' . $taxonomyName . ' not found', 404);
        }

        $count = wp_count_terms( $taxonomy->name );
        $responseData = array(
            'id'    => $taxonomy->name,
            'name'  => $taxonomy->name,
            'description' => null !== $taxonomy->description ? $taxonomy->description : '',
            'numTerms' => $count
        );

        return new WP_REST_Response($responseData, 200);
    }

    function get_terms($request) {
        $login = $this->authenticate($request);
        if( 'success' !== $login['status'] ) {
            return $login['message'];
        }

        $taxonomy = $request->get_param( 'id' );
        $countTerms = wp_count_terms($taxonomy);

        $args = array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'number' => 0
        );

        $paginator = $this->paginator($request, $args, $countTerms, function($p) { return get_terms($p); });

        $terms = $paginator['data'];

        $responseData = array();
        foreach ( $terms as $term ) {
            $responseData[] = array(
                'id' => $term->name,
                'value' => $term->name,
                'parent' => $term->parent
            );
        }

        if ( key_exists ( 'errors', $terms ) || empty( $responseData ) ) {
            return new WP_REST_Response('taxonomy ' . $taxonomy . ' not found or has no terms', 404);
        }

        $response = new WP_REST_Response($responseData, 201);
        foreach ( $paginator['headers'] as $headerName => $headerValue )
            $response->header($headerName, $headerValue);
        return $response;
    }

    function create_term($request) {
        $login = $this->authenticate($request);
        if ( 'success' !== $login['status'] ) {
            return $login['message'];
        }

        $taxonomy = $request->get_param( 'id' );

        if(!taxonomy_exists($taxonomy)) {
            return new WP_REST_Response($taxonomy . ' not found', 422);
        }

        $data = $request->get_json_params();
        $parent = 0;
        if ( key_exists('parent',$data ) )
            $parent = $data['parent'];

        $args = array(
            'slug' => $this->slugify(strtolower($data['value'])),
            'parent' => $parent
        );
        $termData = wp_insert_term($data['value'], $taxonomy, $args);

        if ( is_wp_error( $termData ) ) {
	        $error_string = $termData->get_error_message();
            return new WP_REST_Response('term ' . $data['value'] . ' already exists or error occurred: ' . $error_string, 409);
        }

        // Set metadata that contains the datetime this term was created
        add_term_meta($termData['term_id'], 'term_' . $termData['term_id'] . '_created', current_time( 'mysql' ));

        $responseData = array(
            'id' => $termData['term_id'],
            'value' => $data['value'],
            'parent' => $parent
        );

        $response = new WP_REST_Response($responseData, 200);
        $response->header("Link", "skyword/terms/" . $data['term_id']);
        return $response;
    }
}

global $skyword_taxonomies;
$skyword_taxonomies = new Skyword_Taxonomies;
$skyword_taxonomies->hook_rest_server();