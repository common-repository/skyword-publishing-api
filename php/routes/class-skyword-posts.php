<?php
/**
 * Created by PhpStorm.
 * User: bmcintyre
 * Date: 9/19/18
 * Time: 9:40 AM
 */

class Skyword_Posts extends Skyword_API_Publish {
    // Error return if an included tag isn't in the system
    const TAG_NOT_FOUND = -1;

    // Connect us with the REST API
    public function hook_rest_server() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    // Register our custom routes
    public function register_routes() {
        $this->namespace = $this->namespace . $this->version;

        /**
         * GET  - /skyword/v1/posts - Get all posts
         * GET  - /skyword/v1/posts/:id - Get a single post
         * POST - /skyword/v1/posts - Create a new post
         */
        $posts = '/posts';
        $postCount = '/post-count';
        $postTypes = '/post-types';
        $this->register_posts( $posts, $postCount, $postTypes );
    }

    function register_posts($posts, $postCount, $postTypes) {
        /**
         * Get all posts
         * GET - /skyword/v1/posts
         *
         * OPTIONAL QUERY PARAMETERS
         *  - postType : String
         *      - slugified
         *      - defaults to 'post' if not specified
         *  - title : String
         *      - not sugified
         *  - titleRegex: String
         *      - cannot be checked in the query, done after getting results from other filters
         *          - X-Total-Count is incorrect because of this when using this query parameter
         *  - category : String
         *      - category name
         *  - tag : String
         *  - onOrBeforeDate : String
         *      - yyyy/MM/dd
         *  - onOrAfterDate : String
         *      - yyyy/MM/dd
         *  - author : Int
         *      - author ID
         *  - postStatus : String
         *      - 'publish', 'draft', etc
         *      - defaults to return posts in both 'publish' and 'draft' states
         *  - hasSkywordContentId : Boolean
         *  - detailed : Boolean
         *      - post returns more information in general
         *          - additional information for images
         *          - postStatus
         *          - postDate
         */
        register_rest_route($this->namespace, $posts, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_posts' ),
                'permission_callback' => '__return_true'
            )
        ) );

        /**
         * Get post count
         * GET - /skyword/v1/post-count
         *
         * OPTIONAL QUERY PARAMETERS
         *  - postType : String
         *      - slugified
         *      - defaults to 'post' if not specified
         *  - title : String
         *      - not sugified
         *  - titleRegex: String
         *      - cannot be checked in the query, done after getting results from other filters
         *          - X-Total-Count is incorrect because of this when using this query parameter
         *  - category : String
         *      - category name
         *  - tag : String
         *  - onOrBeforeDate : String
         *      - yyyy/MM/dd
         *  - onOrAfterDate : String
         *      - yyyy/MM/dd
         *  - author : Int
         *      - author ID
         *  - postStatus : String
         *      - 'publish', 'draft', etc
         *      - defaults to return posts in both 'publish' and 'draft' states
         *  - hasSkywordContentId : Boolean
         *  - detailed : Boolean
         *      - post returns more information in general
         *          - additional information for images
         *          - postStatus
         *          - postDate
         */
        register_rest_route($this->namespace, $postCount, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_postCount' ),
                'permission_callback' => '__return_true'
            )
        ) );

         /**
          * Get a post by id
          * GET - /skyword/v1/posts/:id
          */
         register_rest_route($this->namespace, $posts . $this->idParam, array(
             array(
                 'methods'             => WP_REST_Server::READABLE,
                 'callback'            => array( $this, 'get_post' ),
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
           * Create a new post
          * POST - /skyword/v1/posts
          */
         register_rest_route($this->namespace, $posts, array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_post' ),
                'permission_callback' => '__return_true'
            )
         ) );

        /**
         * Get all post types
         * GET - /skyword/v1/post-types
         */
        register_rest_route($this->namespace, $postTypes, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_postTypes' ),
                'permission_callback' => '__return_true'
            )
        ) );
    }

    function get_posts($request) {
        $login = $this->authenticate($request);
        if ( 'success' !== $login['status'] ) {
            return $login['message'];
        }

        $args = array(
            'post_type' => 'post',
            'post_status' => array('any'),
            'paged' => true,
            'posts_per_page' => 0,
        );

        $args = $this->handleQueryParameters($args, $request);

        $args['order'] = 'DESC';
        $args['orderby'] = 'ID';

        $paginator = $this->paginator($request, $args, null, function($p) { return new WP_Query($p); });

        $pagedPosts = $paginator['data']->posts;
        $responseData = array();

        $detailed = false;
        if ( $request->get_param('detailed') )
            $detailed = true;

        $titleRegexArg = $request->get_param('titleRegex');

        foreach ( $pagedPosts as $onePost ) {
            // Can't use regex in get_posts so the titleRegex query parameter needs to be handled here
            if ( isset($titleRegexArg) && key_exists('post_title', $onePost) ) {
                if ( 1 !== preg_match('/' . $titleRegexArg . '/', $onePost->post_title) ) {
                    continue;
                }
            }

            $id = $onePost->ID;
            $meta = get_post_meta( $id );
            $contentId = key_exists('skyword_content_id', $meta) ? $meta['skyword_content_id'][0] : '';

            $responseData[] = array(
                'id' => $id,
                'skywordId' => $contentId,
                'type' => $onePost->post_type,
                'title' => $onePost->post_title,
                // TODO: These can be included but need converted to Java.OffsetDateTime
                //'created' => $onePost->post_date,
                //'updated' => $onePost->post_modified,
                'url' => $onePost->guid,
                'author' => $onePost->post_author,
                'trackingTag' => '',
                'fields' => $this->getPostFields($id, $detailed)
            );
        }

        if ( empty( $responseData ) ) {
            return new WP_REST_Response('no posts found on cms', 404);
        }

        $response = new WP_REST_Response($responseData, 200);
        foreach ( $paginator['headers'] as $headerName => $headerValue )
            $response->header($headerName, $headerValue);

        return $response;
    }

    function get_postCount($request) {
        $login = $this->authenticate($request);
        if ( 'success' !== $login['status'] ) {
            return $login['message'];
        }

        $args = array(
            'post_type' => 'post',
            'post_status' => array('any')
        );

        $args = $this->handleQueryParameters($args, $request);

        $query = new WP_Query($args);

        $numPosts = '0';
        if ( array_key_exists('found_posts', $query) && isset($query->found_posts))
            $numPosts = $query->found_posts;

        return new WP_REST_Response($numPosts, 200);
    }

    function get_postTypes($request) {
        $login = $this->authenticate($request);
        if ( 'success' !== $login['status'] ) {
            return $login['message'];
        }

        return new WP_REST_Response(get_post_types(), 200);
    }

    function get_post($request) {
        $login = $this->authenticate($request);
        if ( 'success' !== $login['status'] ) {
            return $login['message'];
        }

        $id = $request->get_param( 'id' );
        $post = get_post( $id );

        if ( empty( $post ) ) {
            return new WP_REST_Response('post ' . $id . ' not found', 404);
        }

        $meta = get_post_meta( $id );
        $contentId = key_exists('skyword_content_id', $meta) ? $meta['skyword_content_id'][0] : '';

        $detailed = false;
        if ( $request->get_param('detailed') )
            $detailed = true;

        $responseData = array(
            'id' => $id,
            'skywordId' => $contentId,
            'type' => $post->post_type,
            'title' => $post->post_title,
            // TODO: These can be included but need converted to Java.OffsetDateTime
            //'created' => $post->post_date,
            //'updated' => $post->post_modified,
            'url' => $post->guid,
            'author' => $post->post_author,
            'trackingTag' => '',
            'fields' => $this->getPostFields($id, $detailed)
        );

        $response = new WP_REST_Response($responseData, 200);
        $response->header('Link', $post->guid);
        return $response;
    }

    function create_post($request) {
        $login = $this->authenticate($request);
        if ( 'success' !== $login['status'] ) {
            return $login['message'];
        }

        global $coauthors_plus;

        $data = $request->get_json_params();
        $postDate = current_time( 'mysql' );
        $state = $data['publishAsDraft'] ? 'draft' : 'publish';

        $skywordContentId = strval( $data['skywordId'] );
        $postType = sanitize_text_field( $data['type'] );

        if ( ! isset($data['id']) )
            $data['id'] = $this->checkContentExists( $skywordContentId, $postType );

        $fields = $this->addNameKeysToDataFields( $data['fields'] );

        $tags = '';
        $metaDescription = '';
        $seoTitle = '';

        // Meta_description and seo_title are hardcoded in the publish api, this will always™ be their names
        if ( key_exists( 'meta_description', $fields) ) {
            $metaDescription = $fields['meta_description']['value'];
        }
        if ( key_exists( 'seo_title', $fields ) ) {
            $seoTitle = $fields['seo_title']['value'];
        }
        // Featured images need apiNodeName 'featured'
        //      Featured alt - featured_imagealt
        //      Featured title - featured_imagetitle
        if ( key_exists( 'featured', $fields) ) {
            $fields['featured']['name'] = '_thumbnail_id';
        }
        if ( key_exists( 'tags', $fields ) ) {
            // Assign the tags
            $tags = $fields['tags']['value'];
            // Add meta that this tag has been used
            $tagNames = explode(',', $fields['tags']['value']);
            foreach ( $tagNames as $tagName ) {
                $termId = $this->getTagId( $tagName );
                if ( self::TAG_NOT_FOUND === $termId ) {
                    continue;
                }
                $termUsedKey = 'term_' . $termId . '_used';
                $termMeta = get_term_meta($termId, $termUsedKey, true);
                if ( false === $termMeta) {
                    add_term_meta($termId, $termUsedKey, current_time( 'mysql' ));
                } else {
                    update_term_meta($termId, $termUsedKey, current_time( 'mysql' ));
                }
            }
        }

        $title = sanitize_text_field( $data['title'] );

        $postName = $this->slugify( $title );

        if ( key_exists('urlSlug', $fields) )
            $postName = $this->slugify( $fields['urlSlug']['value'] );

        $newPost = array (
            'post_status' => $state,
            'post_name' => $postName,
            'ping_status' => 'open',
            'post_date' => $postDate,
            'post_excerpt' => $metaDescription,
            'post_type' => 'default' === $postType ? 'post' : $postType,
            'comment_status' => 'open',
            'post_title' => $title
        );

        if ( key_exists('body', $fields ) ) {
            $trackingTagShortcode = '';
            // Check to see which tracking tag we should add, if any
            if ( 'true' === $data['trackingTag'] ) {
                $trackingTagShortcode = '[skyword_tracking /]';
            } else if ( 'anonymous' === $data['trackingTag'] ) {
                $trackingTagShortcode = '[skyword_anonymous_tracking /]';
            }

            $options = get_option( 'skyword_api_plugin_options' );
            if ($options['skyword_align_class_conversion']) {
                // Adjust to WP align attributes
                $html = wp_kses_post($fields['body']['value']);
                // Make sure BR tags are balanced
                $html = str_replace("<br>", "<br/>", $html);
                $doc = new DomDocument();
                $doc->loadHTML($html, LIBXML_NOERROR);
                $list = $doc->getElementsByTagName("p");
                if ($list->length > 0) {
                    foreach ($list as $node) {
                        $classAttribute = $node->getAttribute("class");
                        forEach(array("left", "center", "right", "full", "wide") as $classValue) {
                            if(str_contains($classAttribute, $classValue)) {
                                //change node value
                                $node->setAttribute("class", str_replace($classAttribute, $classValue, "align" . $classValue));
                            }
                        }
                    }
                }
                $newBody = $doc->saveHTML();
            } else {
                // No change needed
                $newBody = $fields['body']['value'];
            }

            $newPost['post_content'] = wp_kses_post($newBody . $trackingTagShortcode);
        }

        if ( key_exists( 'category', $fields ) ) {
            $cats = explode(',', $fields['category']['value']);
            $catsById = array();
            foreach ( $cats as $cat )
                if ( !is_numeric($cat) )
                    $catsById[] = get_cat_ID( $cat );
                else $catsById[] = $cat;
            $newPost['post_category'] = $catsById;
        }
        if ( null !== $data['id'] ) {
            $newPost['ID'] = (int) $data['id'];
        }
        if ( null !== $data['author'] && null !== $coauthors_plus ) {
            $data['author'] = str_replace( 'guest-', '', $data['author'] );
            $author          = $coauthors_plus->guest_authors->get_guest_author_by( 'ID', $data['author'] );
            $authorTerm      = $coauthors_plus->update_author_term( $author );
            $newPost['post_author'] = (int)$data['author'];
        } else if ( null !== $data['author'] && is_numeric( trim( $data['author'] ) ) ) {
            $newPost['post_author'] = (int)$data['author'];
        }

        // If an ID is set on newPost it will update instead of insert
        $postId = wp_insert_post( $newPost );

        if ( null !== $data['author'] && null !== $coauthors_plus ) {
            if ( 'post' === $postType ) {
                wp_set_post_terms( $postId, $authorTerm->slug, $coauthors_plus->coauthor_taxonomy, true );
            } else {
                wp_set_object_terms( $postId, $authorTerm->slug, $coauthors_plus->coauthor_taxonomy, true );
            }
        }

        wp_set_post_tags( $postId, $tags );

        $imageFields = array_filter($fields, array($this, 'filterImages'));
        $imageIds = array();
        foreach ( $imageFields as $field ) {
            $imageIds[] = intval($field['value']);
        }

        // Get rid of all of the fields that we have the handle explicitly so we can handle the rest generically
        $fields = array_filter($fields, array($this, 'filterSpecialFields'));

        $fields = $this->addFieldToFields($fields,'skyword_content_id', $data['skywordId']);
        $fields = $this->addFieldToFields($fields,'skyword_seo_title', $seoTitle);
        $fields = $this->addFieldToFields($fields,'skyword_metadescription', $metaDescription);
        $fields = $this->addFieldToFields($fields,'_yoast_wpseo_title', $seoTitle);
        $fields = $this->addFieldToFields($fields,'_yoast_wpseo_metadesc', $metaDescription);
        $fields = $this->addFieldToFields($fields,'skyword_publication_type', 'post' === $postType ? 'evergreen' : $postType);
        $this->createCustomFields($postId, $fields);

        $this->attachAttachments($postId, $imageIds);

        // Provide a hook for customers to do any additional work with the post once it has been handled by us
        do_action( 'skyword_post_publish', $postId );

        $data['id'] = $postId;
        $data['url'] = get_permalink( $postId );
        $response = new WP_REST_Response($data, 201);
        $response->header('Link', '/skyword/post/' . $postId);
        return $response;
    }

    /**
     * Add meta for custom fields to the post
     */
    protected function createCustomFields($postId, $fields ) {
        foreach ( $fields as $field ) {
            delete_post_meta( $postId, urldecode($field['name'] ) );
            add_post_meta( $postId, urldecode($field['name']), urldecode($field['value'] ) );
        }
    }

    protected function convertTags($tags) {
        $converted = array();
        $tagNames = explode(',', $tags);
        foreach ( $tagNames as $tag ) {
            if (is_numeric($tag))
                array_push($converted, intval($tag));
            else
                array_push($converted, $tag);
        }
        return $converted;
    }

    /**
     * array_filter callback to remove fields that have special handling
     */
    protected function filterSpecialFields($field) {
        $specialCases = [ 'body', 'tags', 'meta_description', 'seo_title', 'author' ];
        return !in_array($field['name'], $specialCases);
    }

    /**
     * array_filter callback to get only the images
     */
    protected function filterImages($field) {
        return 'IMAGE' === $field['type'];
    }

    /**
     * Change image parent to the correct postId
     */
    protected function attachAttachments($postId, $imageIds) {
        global $wpdb;

        $args = array(
            'post__in' => $imageIds,
            'post_type' => 'attachment'
        );

        $attachments = get_posts( $args );

        if ( ! empty ( $attachments )  && ! empty ( $imageIds ) ) {
            foreach ( $attachments as $file ) {
				// Update is on a limited list of IDs
				// phpcs:ignore
                $wpdb->update( $wpdb->posts, array( 'post_parent' => $postId ), array( 'ID' => $file->ID ) );

                // Featured image has to be field name 'featured'
                if ( get_post_meta( $file->ID, 'featured', true ) === 'true' ) {
                    delete_post_meta( $file->ID, 'featured' );
                    delete_post_meta( $postId, '_thumbnail_id' );
                    add_post_meta( $postId, '_thumbnail_id', $file->ID, false );
                }
            }
        }
    }

    /**
     * Get all of the relevant data this post originally contained and rebuild the "fields" array
     */
    protected function getPostFields($postId, $detailed = false) {
        $post = get_post($postId);
        $tags = wp_get_post_tags($postId);
        $categories = wp_get_post_categories($postId, array('fields' => 'id=>name'));
        $index = 0;
        foreach ( $tags as $tag ) {
            $tags[$index++] = $tag->name;
        }
        $images = get_attached_media('image', $postId);
        $meta = get_post_meta( $postId );

        $fields = array(
            array (
                'name' => 'body',
                'value' => $post->post_content,
                'type' => 'HTML'
            )
        );

        if ( isset($tags) && ! empty($tags) )
            array_push($fields, array(
                'name' => 'tags',
                'value' => implode(',', $tags),
                'type' => 'TAXONOMY'
            ));

        if ( isset($categories) && ! empty($categories) ) {
            $list = Array();
            foreach ($categories as $key => $value) {
                $list[] = "$key|$value";
            }
            array_push($fields, array(
                'name' => 'categories',
                'value' => implode(',', $list),
                'type' => 'TAXONOMY'
            ));
        }

        // Include date and status if this is a detailed request
        if ( $detailed ) {
            if (isset($post->post_date)) {
                array_push($fields, array(
                    'name' => 'postDate',
                    'value' => $post->post_date,
                    'type' => 'DATETIME'
                ));
            }

            if (isset($post->post_status)) {
                array_push($fields, array(
                    'name' => 'postStatus',
                    'value' => $post->post_status,
                    'type' => 'METADATA'
                ));
            }
        }

        if ( key_exists('_thumbnail_id', $meta ) ) {
            $featuredId = $meta['_thumbnail_id'][0];
        }
        foreach ( $images as $image ) {
            $name = 'image';
            if ( isset( $featuredId ) ) {
                if ($image->ID == $featuredId) {
                    $name = 'featuredImage';
                }
            }

            if ( $detailed ) {
                $imageMeta = get_post_meta($image->ID);
                $alt = key_exists('_wp_attachment_image_alt', $imageMeta ) ? $imageMeta['_wp_attachment_image_alt'][0] : '';
                $fields[] = array(
                    'name' => $name,
                    'value' => $image->guid,
                    'details' => array(
                        'id' => $image->ID,
                        'fileName' => substr($image->guid, strrpos($image->guid, '/') + 1),
                        'url' => $image->guid,
                        'slug' => $image->post_name,
                        'title' => $image->post_title,
                        'alt' => $alt
                    ),
                    'type' => 'IMAGE'
                );
            } else {
                $fields[] = array(
                    'name' => $name,
                    'value' => $image->guid,
                    'type' => 'IMAGE'
                );
            }
        }
        return $fields;
    }

    function array_map_assoc( $callback, $array ){
        $r = array();
        foreach ($array as $key=>$value)
            $r[] = $callback($key,$value);
        return $r;
    }

    /**
     * Check if a piece of content already exists
     */
    protected function checkContentExists($skywordId, $postType ) {
        $query = array(
            'ignore_sticky_posts' => true,
            'meta_key'            => 'skywordId',
			// Meta query is necessary for the feature
			// phpcs:ignore
            'meta_value'          => $skywordId,
            'post_type'           => $postType,
            'posts_per_page'      => 1,
            'no_found_rows'       => true,
            'post_status'         => array(
                'publish',
                'pending',
                'draft',
                'auto-draft',
                'future',
                'private',
                'inherit',
                'trash'
            )
        );
        query_posts( $query );
        if ( have_posts() ) :
            while ( have_posts() ) : the_post();
                $str = get_the_ID();

                return $str;
            endwhile;
        else :
            $query = array(
                'ignore_sticky_posts' => true,
                'meta_key'            => 'skyword_content_id',
    			// Meta query is necessary for the feature
	    		// phpcs:ignore
                'meta_value'          => $skywordId,
                'post_type'           => $postType,
                'posts_per_page'      => 1,
                'no_found_rows'       => true,
                'post_status'         => array(
                    'publish',
                    'pending',
                    'draft',
                    'auto-draft',
                    'future',
                    'private',
                    'inherit',
                    'trash'
                )
            );
            query_posts( $query );
            if ( have_posts() ) :
                while ( have_posts() ) : the_post();
                    $str = get_the_ID();

                    return $str;
                endwhile;

                return null;
            else :
                return null;
            endif;
        endif;
    }

    /**
     * Add a new key => value to the passed in $fields array and return the modified version
     */
    protected function addFieldToFields($fields, $name, $value) {
        $fields[] = array(
            'name' => $name,
            'value' => $value
        );
        return $fields;
    }

    /**
     * Get the ID of a tag by name
     */
    protected function getTagId($tagName) {
        $tag = get_term_by('name', $tagName, 'post_tag');
        if ( $tag ) {
            return $tag->term_id;
        } else {
            return self::TAG_NOT_FOUND;
        }
    }

    /**
     * Look at query parameters and append (through returning) additional arguments for wp query
     */
    protected function handleQueryParameters($args, $request) {
        $postTypeArg = $request->get_param('postType');
        if ( isset($postTypeArg) )
            $args['post_type'] = $postTypeArg;

        $postStatusArg = $request->get_param('postStatus');
        if ( isset($postStatusArg) )
            $args['post_status'] = $postStatusArg;

        $postTagArg = $request->get_param('tag');
        if ( isset($postTagArg) ) {
             $theTags = $this->slugify($postTagArg);

            $args['tag'] = $theTags;
        }

        $authorArg = $request->get_param('author');
        if ( isset($authorArg) )
            $args['author_name'] = $authorArg;

        $beforeDateArg = $request->get_param('beforeDate');
        $afterDateArg = $request->get_param('afterDate');
        if ( isset($beforeDateArg) || isset($afterDateArg) ) {
            $beforeAndAfter = array();

            if ( isset($beforeDateArg) )
                $beforeAndAfter['before'] = $beforeDateArg;

            if ( isset($afterDateArg) )
                $beforeAndAfter['after'] = $afterDateArg;

            $args['date_query'] = array(
                $beforeAndAfter,
                'inclusive' => false,
            );
        }

        $postTitleArg = $request->get_param('title');
        if( isset($postTitleArg) )
            $args['title'] = $postTitleArg;

        $postCategoryArg = $request->get_param('category');
        if ( isset($postCategoryArg) )
            $args['category_name'] = $this->slugify($postCategoryArg);

        $metaQuery = array();

        $includeSkywordContentArg = $request->get_param('skywordContent');
        if ( ! isset($includeSkywordContentArg) ) {
            $compare = 'NOT EXISTS';

            array_push($metaQuery, array(
                    'key' => 'skyword_content_id',
                    'compare' => $compare,
                    'value' => ''
            ) );
        }

        if ( ! empty($metaQuery) )
			// Meta query is necessary for the feature
    		// phpcs:ignore
            $args['meta_query'] = $metaQuery;

        return $args;
    }
}

global $skyword_posts;
$skyword_posts = new Skyword_Posts;
$skyword_posts->hook_rest_server();
