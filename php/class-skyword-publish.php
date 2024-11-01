<?php

class Skyword_API_Publish extends WP_REST_Controller {
    // Route namespace and version
    protected $version = '1';
    protected $namespace = 'skyword/v';
    // Route parameters
    protected $idParam = '/(?P<id>[\d]+)'; // An integer parameter
    protected $idStringParam = '/(?P<id>[_\w-]+)'; // A string parameter
    // Acceptable time difference for timestamp
    protected $timestampThreshold = 20000;

    /**
     * Get a slugified version of the passed string
     */
    protected function slugify($string) {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string), '-'));
    }

    /**
     * Validate the apiKey and timestamp
     */
    protected function authenticate($request) {
        $tempTime = time();
        $options  = get_option( 'skyword_api_plugin_options' );
        $apiKey   = $options['skyword_api_api_key'];
        $hash = $request->get_header("Authentication");
        $timestamp = $request->get_header("Timestamp");
        $response = array();
        if ( ( $tempTime - $timestamp <= $this->timestampThreshold ) && ( $tempTime - $timestamp >= - $this->timestampThreshold ) ) {
            if ( '' !== $apiKey ) {
                $tempHash = md5( $apiKey . $timestamp );
                if ( $tempHash === $hash ) {
                    $response['status'] = 'success';
                } else {
                    $response['message'] = new WP_REST_Response( 'could not match hash', 403 );
                    $response['status']  = 'error';
                }
            } else {
                $response['message'] = new WP_REST_Response( 'skyword API key not set', 403 );
                $response['status']  = 'error';
            }
        } else {
            $response['message'] = new WP_REST_Response( 'bad timestamp used ' . $hash . ' - timestamp sent: ' . $timestamp, 403 );
            $response['status']  = 'error';
        }

        return $response;
    }

    /**
     * Reorganize fields to be accessible by their name
     */
    protected function addNameKeysToDataFields($data) {
        $fields = [];
        foreach ($data as $field) {
            $fields[$field['name']] = $field;
        }

        return $fields;
    }

    /**
     * Provide pagination to the queries that would benefit from it
     *
     * @param $request
     *  The request
     * @param $args
     *  Arguments for the query
     * @param $total
     *  The total number of objects that the query would be handling without pagination
     * @param $queryFunc
     *  Function that handles the necessary query based on the passed and then amended $args
     * @return array
     *  Contains 'headers' to add to the response and 'data' result of the paginated query
     */
    protected function paginator($request, $args, $total, $queryFunc) {
        try {
            // Subtract 1 from the passed in 'page' to correctly calc the offset, if it wasn't passed assume '1' and set to 0
            $page = intval($request->get_param('page') ? $request->get_param('page') - 1 : 0);
            $perPage = intval($request->get_param('per_page') ? $request->get_param('per_page') : 250);

            // If there's no passed 'page' parameter assume '1'
            $next = $request->get_param('page') ? $request->get_param('page') + 1 : 2;
            $prev = $request->get_param('page') ? $request->get_param('page') - 1 : 0;

            $args['offset'] = $page * $perPage;

            // Look for which parameter was included, caller will need to include the one they need set in the $args
            //  - get_posts requires this arg to be 'numberposts'
            //  - get_terms terms requires this arg to be called 'number'
            //  - WP_Query requires this arg to be called 'posts_per_page'
            if (key_exists('number', $args)) {
                $args['number'] = $perPage;
            } else if (key_exists('numberposts', $args)) {
                $args['numberposts'] = $perPage;
            } else if (key_exists('posts_per_page', $args)) {
                $args['posts_per_page'] = $perPage;
            }

            $query = $queryFunc($args);

            // WP_Query as the callback provides found_posts as a total reflecting the filtered results
            if (key_exists('found_posts', $query) && isset($query->found_posts))
                $total = $query->found_posts;
            else {
                if (key_exists('taxonomy', $args)) {
                    $total = wp_count_terms($args['taxonomy']);
                } else {
                    $returnData = array(
                        'headers' => array(
                            'X-Total-Count' => '0'
                        ),
                        'data' => $query
                    );

                    return $returnData;
                }
            }

            $last = ceil($total / $perPage);
            $sanitized_host = esc_attr(empty($_SERVER['HTTP_HOST']));
            $sanitized_uri = strtok(esc_attr(empty($_SERVER['REQUEST_URI'])), '?');

            $url = (is_ssl() ? 'https:' : 'http:') . '//' . $sanitized_host . $sanitized_uri;

            $headerLink = array();

            if ($next <= $last) {
                $headerLink[] = "<{$url}?page={$next}&per_page={$perPage}>; rel=\"next\"";
            }

            $headerLink[] = "<{$url}?page=$last&per_page={$perPage}>; rel=\"last\"";
            $headerLink[] = "<{$url}?page=1&per_page={$perPage}>; rel=\"first\"";

            if ($prev > 0) {
                $headerLink[] = "<{$url}?page={$prev}&per_page={$perPage}>; rel=\"prev\"";
            }

            $returnData = array(
                'headers' => array(
                    'X-Total-Count' => $total,
                    'Link' => implode(',', $headerLink)
                ),
                'data' => $query
            );

            return $returnData;
        } catch (Exception $e) {
            return array($e->getLine() . ' -------- ' . $e->getMessage());
        }
    }
}