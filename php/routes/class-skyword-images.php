<?php
/**
 * Created by PhpStorm.
 * User: bmcintyre
 * Date: 9/19/18
 * Time: 9:42 AM
 */

class Skyword_Images extends Skyword_API_Publish {
    // Connect us with the REST API
    public function hook_rest_server() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    // Register our custom routes
    public function register_routes() {
        $this->namespace = $this->namespace . $this->version;

        /**
         * GET  - /skyword/v1/images
         * GET  - /skyword/v1/images/:id/metadata - Get the metadata for a single image
         * GET  - /skyword/v1/images/:id - Get a single image
         * POST - /skyword/v1/images - Create a new image
         * POST - /skyword/v1/images/:id/metadata - Create a new metadata entry for a single image
         */
        $images = '/images';
        $this->register_images( $images );
    }

    function register_images($images) {
        /**
         * Get all images
         * GET - /skyword/v1/images
         */
        register_rest_route($this->namespace, $images, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_images' ),
                'permission_callback' => '__return_true'
            )
        ) );

        /**
         * Create an image
         * POST - /skyword/v1/images
         */
        register_rest_route($this->namespace, $images, array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_image' ),
                'permission_callback' => '__return_true'
            )
        ) );

        /**
         * Get a single image
         * GET - /skyword/v1/images/:id
         */
        register_rest_route($this->namespace, $images . $this->idParam, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_image' ),
                'args'                => array(
                    'id' => array (
                        'validate_callback' => function($param, $request, $key) {
                            return is_numeric( $param );
                        }
                    )
                ),
                'permission_callback' => '__return_true'
            )
        ) );

        /**
         * Get the metadata for a single image
         * GET - /skyword/v1/images/:id/metadata
         */
        register_rest_route($this->namespace, $images . $this->idParam . '/metadata', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_image_metadata' ),
                'args'                => array(
                    'id' => array (
                        'validate_callback' => function($param, $request, $key) {
                            return is_numeric( $param );
                        }
                    )
                ),
                'permission_callback' => '__return_true'
            )
        ) );

        /**
         * Create a new metadata entry for a single image
         * POST - /skyword/v1/images/:id/metadata
         */
        register_rest_route($this->namespace, $images . $this->idParam . '/metadata', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_image_metadata' ),
                'args'                => array(
                    'id' => array (
                        'validate_callback' => function($param, $request, $key) {
                            return is_numeric( $param );
                        }
                    )
                ),
                'permission_callback' => '__return_true'
            )
        ) );
    }

    function get_images($request) {
        $login = $this->authenticate($request);
        if ( 'success' !== $login['status'] ) {
            return $login['message'];
        }

        $args = array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => 0,
        );

        $paginator = $this->paginator($request, $args, null, function($p) { return new WP_Query($p); } );
        $images = $paginator['data']->posts;

        foreach ( $images as $image ) {
            $id = $image->ID;
            $type = get_post_mime_type( $id );
            $url = wp_get_attachment_url( $id );
            $meta = get_post_meta( $id );

            $responseData[] = [
                'id' => $id,
                'type' => $type,
                'url' => $url,
                'metadata' => [
                    'alt' => key_exists('_wp_attachment_image_alt', $meta) ? $meta['_wp_attachment_image_alt'][0] : null,
                    'title' => $image->post_title,
                ],
            ];
        }

        if ( empty($responseData ) ) {
            return new WP_REST_Reponse('no images found in cms', 404);
        }

        $response = new WP_REST_Response($responseData, 200);
        foreach ( $paginator['headers'] as $headerName => $headerValue )
            $response->header($headerName, $headerValue);
        return $response;
    }

    function create_image($request) {
        global $coauthors_plus;
        $login = $this->authenticate($request);
        if ( 'success' !== $login['status'] ) {
            return $login['message'];
        }

        include_once( ABSPATH . 'wp-admin/includes/image.php' );
        $data = $request->get_json_params();
        $name        = sanitize_file_name( $data['filename'] );
        $type        = 'image/' . substr($data['filename'], strpos($data['filename'], '.') + 1);
        // Yoast SEO plugin requires 'image/jpeg' and will not accept 'image/jpg'
        if (is_multisite()) {
            $activePlugins = (array) get_site_option('active_sitewide_plugins', array());
            $activePlugins = array_keys($activePlugins);
        } else {
            $activePlugins = (array) get_option('active_plugins', array());
        }
        if ( in_array( 'wordpress-seo/wp-seo.php', $activePlugins, true ) ) {
            if ($type === 'image/jpg') {
                $type = 'image/jpeg';
            }
        }
        $bits        = base64_decode($data['file']);
        $title       = $name;

        $upload = wp_upload_bits( $name, null, $bits );

        if ( ! empty( $upload['error'] ) ) {
            $errorString = sprintf( __( 'could not write file %1$s (%2$s)' ), $name, $upload['error'] );
            return new WP_REST_Response($errorString, 500);
        }

        $postId    = 0;
        $attachment = array(
            'post_title'     => $title,
            'post_content'   => '',
            'post_type'      => 'attachment',
            'post_parent'    => $postId,
            'post_mime_type' => $type,
            'guid'           => $upload['url']
        );

        if ( null !== $data['author'] && is_numeric( trim( $data['author'] ) ) ) {
            $attachment['post_author'] = (int)$data['author'];
        } else if ( null !== $data['author'] && null !== $coauthors_plus ) {
            $data['author'] = str_replace( 'guest-', '', $data['author'] );
            $author          = $coauthors_plus->guest_authors->get_guest_author_by( 'ID', $data['author'] );
            $authorTerm      = $coauthors_plus->update_author_term( $author );
            $attachment['post_author'] = (int)$data['author'];
        }

        $id = wp_insert_attachment( $attachment, $upload['file'], $postId );
        if ( null !== $data['author'] && null !== $coauthors_plus ) {
            wp_set_object_terms( $id, $authorTerm->slug, $coauthors_plus->coauthor_taxonomy, true );
        }
        wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );

        $responseData = array(
            'id' => $id,
            'type' => $type,
            'url' => $upload['url']
        );
        apply_filters( 'wp_handle_upload', array(
            'file' => $name,
            'url'  => $upload['url'],
            'type' => $type
        ), 'upload' );

        $response = new WP_REST_Response($responseData, 201);
        $response->header('Link', $upload['url']);
        return $response;
    }

    function get_image($request) {
        $login = $this->authenticate($request);
        if ( 'success' !== $login['status'] ) {
            return $login['message'];
        }

        $id = $request->get_param( 'id' );
        $query_images_args = array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'p'             => $id
        );

        $data = new WP_Query( $query_images_args );

        if ( empty( $data->posts ) )
            return new WP_REST_Response('image ' . $id . ' was not found', 404);

        $data = $data->posts[0];
        $meta = get_post_meta( $id );
        $responseData = array(
            'id' => $id,
            'type' => $data->post_mime_type,
            'url' => $data->guid,
            'metadata' => array(
                'title' => $data->post_title,
                'alt' => key_exists('_wp_attachment_image_alt', $meta) ? $meta['_wp_attachment_image_alt'][0] : null
            )
        );

        $response = new WP_REST_Response($responseData, 200);
        $response->header('Link', $data->guid);
        return $response;
    }

    function create_image_metadata($request) {
        $login = $this->authenticate($request);
        if ( 'success' !== $login['status'] ) {
            return $login['message'];
        }

        $id = $request->get_param( 'id' );

        $data = $request->get_json_params();

        if( !empty( $data ) ) {
            $imageMeta = array('ID' => $id);

            if (key_exists('alt', $data ) )
                add_post_meta($id, "_wp_attachment_image_alt", $data['alt'], false);

            if (key_exists( 'title', $data ) ) {
                $title = array('post_title' => $data['title']);
                $imageMeta += $title;
            }

            if (key_exists('caption', $data)) {
                $caption = array('post_excerpt' => $data['caption']);
                $imageMeta += $caption;
            }

            if(key_exists('description', $data)) {
                $description = array('post_content' => $data['description']);
                $imageMeta += $description;
            }

            wp_update_post( $imageMeta );
        }

        $response = new WP_REST_Response($data, 201);
        $response->header( 'Link', get_post( $id )->guid);
        return $response;
    }
}

global $skyword_images;
$skyword_images = new Skyword_Images;
$skyword_images->hook_rest_server();