<?php
/**
 * Created by PhpStorm.
 * User: bmcintyre
 * Date: 9/19/18
 * Time: 9:42 AM
 */

class Skyword_Authors extends Skyword_API_Publish {
    // Connect us with the REST API
    public function hook_rest_server() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    // Register our custom routes
    public function register_routes() {
        $this->namespace = $this->namespace . $this->version;

        /**
         * GET  - /skyword/v1/authors - Get all Authors
         * GET  - /skyword/v1/authors/:idString - Get a single author
         * POST - /skyword/v1/authors - Create a new Author
         */
        $authors = '/authors';
        $this->register_authors( $authors );
    }

    function register_authors($authors) {
        /**
         * Get a single Author
         * GET - /skyword/v1/authors/:idString
         */
        register_rest_route($this->namespace, $authors . $this->idStringParam, array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_author'),
                'args' => array(
                    'id' => array(
                        'validate_callback' => function($param, $request, $key) {
                            // This is already validated by the param regex
                            return true;
                        }
                    )
                ),
                'permission_callback' => '__return_true'
            )
        ));

        /**
         * Get all Authors
         * GET - /skyword/v1/authors
         */
        register_rest_route($this->namespace, $authors, array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_authors'),
                'permission_callback' => '__return_true'
            )
        ));

        /**
         * Create a new Author
         * POST - /skyword/v1/authors
         */
        register_rest_route($this->namespace, $authors, array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_author'),
                'permission_callback' => '__return_true'
            )
        ));
    }

    function get_author($request) {
        $login = $this->authenticate($request);
        if ( 'success' !== $login['status'] ) {
            return $login['message'];
        }
        global $coauthors_plus;
        $id = $request->get_param('id');

        // Co-Authors Plus users are stored differently
        if( null !== $coauthors_plus && false !== strpos( $id, 'guest-' ) ) {
            $icon = null;
            $id = substr($id, strpos($id, '-' ) + 1);
            $author = $coauthors_plus->guest_authors->get_guest_author_by( 'id', $id );

            if ( empty( $author ) ) {
                return new WP_REST_Response('author guest-' . $id . ' not found', 404);
            }

            $meta = get_post_meta( $id );

            if ( key_exists( '_thumbnail_id', $meta ) ) {
                $attachment = get_post($meta['_thumbnail_id'][0]);
                $icon = $attachment->guid;
            }

            $responseData = array(
                'id' => $author->ID,
                'firstName' => $author->first_name,
                'lastName' => $author->last_name,
                'email' => $author->user_email,
                'byline' => $author->display_name,
                'icon' => null !== $icon ? $icon : ''
            );
        } else {
            $author = get_user_by('id', $id);

            if ( empty( $author ) ) {
                return new WP_REST_Response('author ' . $id . ' not found', 404);
            }

            $icon = get_avatar_url($id);

            $responseData = array(
                'id' => $author->ID,
                'firstName' => $author->first_name,
                'lastName' => $author->last_name,
                'email' => $author->user_email,
                'byline' => $author->display_name,
                // get_avatar_url returns false if it doesn't exist
                'icon' => false !== $icon ? $icon : ''
            );
        }

        $response = new WP_REST_Response($responseData, 200);
        $response->header('Link', 'skyword/authors/' . $id);
        return $response;
    }

    function create_author($request) {
        $login = $this->authenticate($request);
        if ( 'success' !== $login['status'] ) {
            return $login['message'];
        }

        global $coauthors_plus;

        $data = $request->get_json_params();
        $userId = username_exists( $data['email'] );

        if ( !$userId ) {
            $newUsername = uniqid("sw-",false);
            $options = get_option( 'skyword_api_plugin_options' );
            if ( null !== $coauthors_plus ) {
                $guest_author                   = array();
                $guest_author['ID']             = '';
                $guest_author['display_name']   = $data['byline'];
                $guest_author['first_name']     = $data['firstName'];
                $guest_author['last_name']      = $data['lastName'];
					if ($options['skyword_coauthors_friendly_slugs']) {
                        $guest_author['user_login']     = $this->generate_author_slug($data);
                    } else {
                        $guest_author['user_login']     = $newUsername;
                    }
                $guest_author['user_email']     = $newUsername . "@skyword.com";
                $guest_author['description']    = array_key_exists('bio', $data) ? $data['bio'] : 'None';
                $guest_author['jabber']         = '';
                $guest_author['yahooim']        = '';
                $guest_author['aim']            = '';
                $guest_author['website']        = '';
                $guest_author['linked_account'] = '';
                $guest_author['company']        = '';
                $guest_author['title']          = '';
                $guest_author['google']         = '';
                $guest_author['twitter']        = '';

                $retval = $coauthors_plus->guest_authors->create( $guest_author );
                if ( is_wp_error( $retval ) ) {
    				if ($options['skyword_coauthors_friendly_slugs']) {
    					$author = $coauthors_plus->guest_authors->get_guest_author_by( 'user_login', $this->generate_author_slug($data));
    				} else {
    					$author = $coauthors_plus->guest_authors->get_guest_author_by( 'user_login', $newUsername );
    				}
                    if ( null !== $author ) {
                        $userId = $author->ID;
                    }
                } else {
                    $userId = $retval;
                }

                $upload = null;
                if ( array_key_exists('profileImage', $data) ) {
                    include_once( ABSPATH . 'wp-admin/includes/image.php' );
                    $fields = $data['profileImage'];
                    $fields = $this->addNameKeysToDataFields($fields);

                    $upload = wp_upload_bits($fields['fileName']['value'], null, base64_decode($fields['imageData']['value']));
                    if ( ! empty( $upload['error'] ) ) {
                        $errorString = sprintf( __( 'Could not write file %1$s (%2$s)' ), 'author-'.$userId, $upload['error'] );
                        return new WP_REST_Response($errorString, 500);
                    }

                    $attachment = array(
                        'guid' => $upload['url'],
                        'post_status' => 'inherit',
                        'ping_status' => 'closed',
                        'post_type' => 'attachment',
                        'post_content' => '',
                        'post_name' => 'author-'.$userId,
                        'post_title' => 'author-'.$userId,
                        'post_mime_type' => $fields['mimeType']['value']
                    );

                    $postId = wp_insert_attachment($attachment);
                    wp_update_attachment_metadata( $postId, wp_generate_attachment_metadata( $postId, $upload['file'] ) );
                    add_post_meta( $postId, '_wp_attached_file', $upload['file'] );
                    add_post_meta( $userId, '_thumbnail_id', $postId );
                    apply_filters( 'wp_handle_upload', array(
                        'file' => $fields['fileName']['value'],
                        'url'  => $upload['url'],
                    ), 'upload' );
                }

                $userId = 'guest-' . $userId;
            } else if ( $options['skyword_api_generate_new_users_automatically'] ) {
                // Generate a random password
                $random_password = wp_generate_password(20, false);
                $userId = wp_insert_user(array(
                    'first_name' => $data['firstName'],
                    'last_name' => $data['lastName'],
                    'user_nicename' => $data['byline'],
                    'display_name' => $data['byline'],
                    'user_email' => $newUsername . "@skyword.com",
                    'role' => 'author',
                    'user_login' => $newUsername,
                    'user_pass' => $random_password,
                    'description' => array_key_exists('bio', $data) ? $data['bio'] : 'None'
                ));
            } else {
                return new WP_REST_Response('could not create new author - install co-authors plus or enable new author creation through Skyword', 500);
            }
        } else {
            $response = new WP_REST_Response('author ' . $userId . ' already exists', 400);
            $response->header('Link', 'skyword/authors/' . $userId);
            return $response;
        }

        $responseData = array(
            'id' => $userId,
            'firstName' => $data['firstName'],
            'lastName' => $data['lastName'],
            'email' => $data['email'],
            'byline' => $data['byline'],
            'icon' => !empty($upload) ? $upload['url'] : "None"
        );

        $response = new WP_REST_Response($responseData, 201);
        $response->header('Link', 'skyword/authors/' . $userId);
        return $response;
    }

    function get_authors($request) {
        $login = $this->authenticate($request);
        if ( 'success' !== $login['status'] ) {
            return $login['message'];
        }

        $responseData = array();
        foreach ( get_users( array('fields' => 'all', 'role_in' => array( 'author', 'editor' ) ) ) as $user ) {
            $responseData[] = array(
                'id'      => $user->ID,
                'firstName'    => $user->role,
                'lastName'   => $user->user_login,
                'byline' => $user->display_name,
                'email' => $user->user_login
            );
        }

        if ( empty($responseData ) ) {
            return new WP_REST_Response('either no authors exist or there is no author role for Skyword to use', 404);
        }

        return new WP_REST_Response($responseData, 200);
    }
    /**
     * Generate a slug based on the author byline
     */
	private function generate_author_slug( $data ) {
        return sanitize_text_field(mb_strtolower(str_replace(' ', '-', $data['byline'])));
    }

}

global $skyword_authors;
$skyword_authors = new Skyword_Authors;
$skyword_authors->hook_rest_server();