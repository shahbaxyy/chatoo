<?php
/**
 * Knowledge Base class.
 *
 * Handles all database operations for the KB categories and articles tables.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class MHD_Knowledge_Base
 *
 * Provides create, read, update, and delete operations for knowledge base
 * categories stored in {prefix}mhd_kb_categories and articles stored in
 * {prefix}mhd_kb_articles.
 *
 * @since 1.0.0
 */
class MHD_Knowledge_Base {

	/**
	 * Full database table name for KB categories including WP prefix.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $categories_table;

	/**
	 * Full database table name for KB articles including WP prefix.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $articles_table;

	/**
	 * Constructor.
	 *
	 * Sets up the table names used by this class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;

		$this->categories_table = $wpdb->prefix . MHD_TABLE_PREFIX . 'kb_categories';
		$this->articles_table   = $wpdb->prefix . MHD_TABLE_PREFIX . 'kb_articles';
	}

	/**
	 * Retrieve all KB categories.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of category row objects ordered by name ascending.
	 */
	public function get_categories() {
		global $wpdb;

		return $wpdb->get_results( "SELECT * FROM {$this->categories_table} ORDER BY name ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get a single KB category by its ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Category ID.
	 * @return object|null Database row object on success, null if not found.
	 */
	public function get_category( $id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->categories_table} WHERE id = %d",
				(int) $id
			)
		);
	}

	/**
	 * Create a new KB category.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data {
	 *     Category data.
	 *
	 *     @type string $name Name of the category.
	 *     @type string $slug URL-safe slug. Auto-generated from name if empty.
	 *     @type string $icon Icon class or identifier.
	 * }
	 * @return int|false The new category ID on success, false on failure.
	 */
	public function create_category( $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$defaults = array(
			'name'       => '',
			'slug'       => '',
			'icon'       => '',
			'created_at' => $now,
		);

		$data = wp_parse_args( $data, $defaults );

		if ( empty( $data['slug'] ) ) {
			$data['slug'] = sanitize_title( $data['name'] );
		}

		// Force timestamp.
		$data['created_at'] = $now;

		$format = array(
			'%s', // name
			'%s', // slug
			'%s', // icon
			'%s', // created_at
		);

		$result = $wpdb->insert( $this->categories_table, $data, $format );

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing KB category.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $id   Category ID.
	 * @param array $data Associative array of column => value pairs to update.
	 * @return bool True on success, false on failure.
	 */
	public function update_category( $id, $data ) {
		global $wpdb;

		$format = array();
		foreach ( $data as $key => $value ) {
			$format[] = '%s';
		}

		$result = $wpdb->update(
			$this->categories_table,
			$data,
			array( 'id' => (int) $id ),
			$format,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a KB category.
	 *
	 * Articles belonging to this category will have their category_id set to NULL.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Category ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_category( $id ) {
		global $wpdb;

		// Nullify category_id on associated articles.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->articles_table} SET category_id = NULL WHERE category_id = %d",
				(int) $id
			)
		);

		$result = $wpdb->delete(
			$this->categories_table,
			array( 'id' => (int) $id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Retrieve a list of KB articles with optional filters.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *     Optional. Arguments to filter, sort, and paginate articles.
	 *
	 *     @type int    $category_id Filter by category ID.
	 *     @type string $status      Filter by status (published, draft).
	 *     @type string $search      Search term matched against title and content.
	 *     @type string $orderby     Column to sort by. Default 'created_at'.
	 *     @type string $order       Sort direction, ASC or DESC. Default 'DESC'.
	 *     @type int    $per_page    Number of results per page. Default 20.
	 *     @type int    $page        Page number (1-based). Default 1.
	 * }
	 * @return array {
	 *     @type array $articles Array of article row objects.
	 *     @type int   $total    Total number of matching articles.
	 * }
	 */
	public function get_articles( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'category_id' => 0,
			'status'      => '',
			'search'      => '',
			'orderby'     => 'created_at',
			'order'       => 'DESC',
			'per_page'    => 20,
			'page'        => 1,
		);

		$args   = wp_parse_args( $args, $defaults );
		$where  = array();
		$values = array();

		if ( ! empty( $args['category_id'] ) ) {
			$where[]  = 'category_id = %d';
			$values[] = (int) $args['category_id'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(title LIKE %s OR content LIKE %s)';
			$values[] = $like;
			$values[] = $like;
		}

		$where_clause = '';
		if ( ! empty( $where ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where );
		}

		$allowed_orderby = array( 'id', 'title', 'views', 'created_at', 'updated_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$per_page = max( 1, (int) $args['per_page'] );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		// Count total matching rows.
		$count_sql = "SELECT COUNT(*) FROM {$this->articles_table} {$where_clause}";
		if ( ! empty( $values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Fetch paginated rows.
		$sql       = "SELECT * FROM {$this->articles_table} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$values[]  = $per_page;
		$values[]  = $offset;

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'articles' => $results,
			'total'    => $total,
		);
	}

	/**
	 * Get a single KB article by its ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Article ID.
	 * @return object|null Database row object on success, null if not found.
	 */
	public function get_article( $id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->articles_table} WHERE id = %d",
				(int) $id
			)
		);
	}

	/**
	 * Get a single KB article by its slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Article slug.
	 * @return object|null Database row object on success, null if not found.
	 */
	public function get_article_by_slug( $slug ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->articles_table} WHERE slug = %s",
				sanitize_title( $slug )
			)
		);
	}

	/**
	 * Create a new KB article.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data {
	 *     Article data.
	 *
	 *     @type int    $category_id Category ID.
	 *     @type string $title       Article title.
	 *     @type string $slug        URL-safe slug. Auto-generated from title if empty.
	 *     @type string $content     Full article content.
	 *     @type string $excerpt     Short excerpt.
	 *     @type string $status      Article status. Default 'published'.
	 * }
	 * @return int|false The new article ID on success, false on failure.
	 */
	public function create_article( $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$defaults = array(
			'category_id' => null,
			'title'       => '',
			'slug'        => '',
			'content'     => '',
			'excerpt'     => '',
			'views'       => 0,
			'helpful_yes' => 0,
			'helpful_no'  => 0,
			'status'      => 'published',
			'created_at'  => $now,
			'updated_at'  => $now,
		);

		$data = wp_parse_args( $data, $defaults );

		if ( empty( $data['slug'] ) ) {
			$data['slug'] = sanitize_title( $data['title'] );
		}

		// Force timestamps.
		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		$format = array(
			'%d', // category_id
			'%s', // title
			'%s', // slug
			'%s', // content
			'%s', // excerpt
			'%d', // views
			'%d', // helpful_yes
			'%d', // helpful_no
			'%s', // status
			'%s', // created_at
			'%s', // updated_at
		);

		$result = $wpdb->insert( $this->articles_table, $data, $format );

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing KB article.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $id   Article ID.
	 * @param array $data Associative array of column => value pairs to update.
	 * @return bool True on success, false on failure.
	 */
	public function update_article( $id, $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );

		$format = array();
		foreach ( $data as $key => $value ) {
			if ( in_array( $key, array( 'category_id', 'views', 'helpful_yes', 'helpful_no' ), true ) ) {
				$format[] = '%d';
			} else {
				$format[] = '%s';
			}
		}

		$result = $wpdb->update(
			$this->articles_table,
			$data,
			array( 'id' => (int) $id ),
			$format,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a KB article.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Article ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_article( $id ) {
		global $wpdb;

		$result = $wpdb->delete(
			$this->articles_table,
			array( 'id' => (int) $id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Increment the view count for an article.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Article ID.
	 * @return int|false The number of rows updated, or false on failure.
	 */
	public function increment_views( $id ) {
		global $wpdb;

		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->articles_table} SET views = views + 1 WHERE id = %d",
				(int) $id
			)
		);
	}

	/**
	 * Record a helpful vote on an article.
	 *
	 * Increments helpful_yes when $helpful is true, or helpful_no when false.
	 *
	 * @since 1.0.0
	 *
	 * @param int  $id      Article ID.
	 * @param bool $helpful True to increment helpful_yes, false for helpful_no.
	 * @return int|false The number of rows updated, or false on failure.
	 */
	public function record_helpful( $id, $helpful ) {
		global $wpdb;

		$column = $helpful ? 'helpful_yes' : 'helpful_no';

		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->articles_table} SET {$column} = {$column} + 1 WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $id
			)
		);
	}

	/**
	 * Search KB articles by title and content.
	 *
	 * Only returns published articles.
	 *
	 * @since 1.0.0
	 *
	 * @param string $query Search query string.
	 * @param int    $limit Maximum number of results. Default 10.
	 * @return array Array of matching article row objects.
	 */
	public function search_articles( $query, $limit = 10 ) {
		global $wpdb;

		$like  = '%' . $wpdb->esc_like( $query ) . '%';
		$limit = max( 1, (int) $limit );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->articles_table} WHERE status = 'published' AND (title LIKE %s OR content LIKE %s) ORDER BY views DESC LIMIT %d",
				$like,
				$like,
				$limit
			)
		);
	}
}
