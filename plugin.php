<?php
/*
Plugin Name: LMS
Description: Works on single sites
Version: 1.0
Author: Gev
*/

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Customers_List extends WP_List_Table {

	/** Class constructor */
	public function __construct()
	{
		parent::__construct( [
			'singular' => __( 'Learner', 'sp' ), //singular name of the listed records
			'plural'   => __( 'Learners', 'sp' ), //plural name of the listed records
			'ajax'     => false //does this table support ajax?
		] );

	}


	/**
	 * Retrieve customers data from the database
	 *
	 * @param int $per_page
	 * @param int $page_number
	 *
	 * @return mixed
	 */
	public static function get_customers( $per_page = 5, $page_number = 1 )
	{
		global $wpdb;
		$user_info=array();
		$sql = "SELECT * FROM {$wpdb->prefix}users_courses";

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			$sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
			$sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
		}

		$sql .= " LIMIT $per_page";
		$sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;

		$result = $wpdb->get_results( $sql, 'ARRAY_A' );
		foreach($result as $r){
			$classname = '';
			$author_obj = get_user_by('id', $r['user_id']);
			if(isset($author_obj->display_name)) {
				$user_name = $author_obj->display_name;
			} else {
				$user_name = $author_obj['user_login'];
			}
			$course = current_user_can( 'edit_posts' )?'<a target="_blank" href="'. get_edit_post_link($r['course_id']) .'">'.get_the_title($r['course_id']).'</a>':get_the_title($r['course_id']);
			if($r['status']==1) $classname = "completed";
			$progress = '<div class="progres">
                                            <div class="inner-progress '.$classname.'" style="width:'.$r['progress'].'">'.$r['progress'].'</div>
                                        </div>  ' ;
			$status = $r['status'];
			$res = array(
				'ID'=>$r['ID'],
				'name'=> $user_name,
				'course'=> $course,
				'progress'=> $progress,
				'status' => $status
			);
			
			array_push($user_info,$res);
		}
		return $user_info;
	}


	/**
	 * Delete a customer record.
	 *
	 * @param int $id customer ID
	 */
	public static function delete_customer( $id )
	{
		global $wpdb;
		$wpdb->delete(
			"{$wpdb->prefix}users_courses",
			[ 'ID' => $id ],
			[ '%d' ]
		);
	}


	/**
	 * Returns the count of records in the database.
	 *
	 * @return null|string
	 */
	public static function record_count()
	{
		global $wpdb;
		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}users_courses";
		return $wpdb->get_var( $sql );
	}


	/** Text displayed when no customer data is available */
	public function no_items() 
	{
		_e( 'No customers avaliable.', 'sp' );
	}


	/**
	 * Render a column when no column specific method exist.
	 *
	 * @param array $item
	 * @param string $column_name
	 *
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) 
	{
		switch ( $column_name ) {
			case 'status': return $item[ $column_name ]?'completed':'in progress';
			default:
				return $item[ $column_name ];
		}
	}

	/**
	 * Render the bulk edit checkbox
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	public function column_cb( $item )
	{
		return sprintf(
			'<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['ID']
		);
	}


	/**
	 * Method for name column
	 *
	 * @param array $item an array of DB data
	 *
	 * @return string
	 */
	public function column_name( $item )
	{
		$delete_nonce = wp_create_nonce( 'sp_delete_customer' );
		$title = '<strong>' . $item['name'] . '</strong>';
		$actions = [
			'delete' => sprintf( '<a href="?page=%s&action=%s&customer=%s&_wpnonce=%s">Delete</a>', esc_attr( $_REQUEST['page'] ), 'delete', absint( $item['ID'] ), $delete_nonce )
		];
		return $title;// . $this->row_actions( $actions );
	}


	/**
	 *  Associative array of columns
	 *
	 * @return array
	 */
	public function get_columns()
	{
		$columns = [
 			'cb'      => '<input type="checkbox" />',
			'name'    => __( 'Name', 'sp' ),
			'course' => __( 'Course', 'sp' ),
			'progress'    => __( 'Progress', 'sp' ),
			'status' => __('Status','sp')
		];

		return $columns;
	}


	/**
	 * Columns to make sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns()
	{
		$sortable_columns = array(
			'status' => array( 'status', true ),
			'progress'=> array('progress',true)
		);

		return $sortable_columns;
	}

	/**
	 * Returns an associative array containing the bulk action
	 *
	 * @return array
	 */
	public function get_bulk_actions()
	{
		$actions = [
			'bulk-delete' => 'Delete'
		];

		return $actions;
	}


	/**
	 * Handles data query and filter, sorting, and pagination.
	 */
	public function prepare_items()
	{
		$this->_column_headers = $this->get_column_info();
		/** Process bulk action */
		$this->process_bulk_action();

		$per_page     = $this->get_items_per_page( 'customers_per_page', 5 );
		$current_page = $this->get_pagenum();
		$total_items  = self::record_count();

		$this->set_pagination_args( [
			'total_items' => $total_items, //WE have to calculate the total number of items
			'per_page'    => $per_page //WE have to determine how many items to show on a page
		] );

		$this->items = self::get_customers( $per_page, $current_page );
	}

	public function process_bulk_action()
	{
		//Detect when a bulk action is being triggered...
		if ( 'delete' === $this->current_action() ) {

			// In our file that handles the request, verify the nonce.
			$nonce = esc_attr( $_REQUEST['_wpnonce'] );

			if ( ! wp_verify_nonce( $nonce, 'sp_delete_customer' ) ) {
				die( 'Go get a life script kiddies' );
			}
			else {
				self::delete_customer( absint( $_GET['customer'] ) );

		                // esc_url_raw() is used to prevent converting ampersand in url to "#038;"
		                // add_query_arg() return the current url
		                wp_redirect( esc_url_raw(add_query_arg()) );
				exit;
			}

		}

		// If the delete bulk action is triggered
		if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-delete' )
		     || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-delete' )
		) {

			$delete_ids = esc_sql( $_POST['bulk-delete'] );

			// loop over the array of record IDs and delete them
			foreach ( $delete_ids as $id ) {
				self::delete_customer( $id );

			}

			// esc_url_raw() is used to prevent converting ampersand in url to "#038;"
		        // add_query_arg() return the current url
		        wp_redirect( esc_url_raw(add_query_arg()) );
			exit;
		}
	}

}

require_once plugin_dir_path(  __FILE__ )  . 'public/libs/dompdf/autoload.inc.php';
		use Dompdf\Dompdf;
		use Dompdf\Options;
class SP_Plugin {

	// class instance
	static $instance;

	// customer WP_List_Table object
	public $customers_obj;

	// class constructor
	public function __construct()
	{
		add_filter( 'set-screen-option', [ __CLASS__, 'set_screen' ], 10, 3 );
		add_action( 'admin_menu', [ $this, 'plugin_menu' ] );
		add_action('admin_head', [ $this,'admin_styles']);
		add_action( 'init', [$this,'custom_post_type'], 0 );
		add_action( 'init', [$this,'register_shortcodes'], 0 );
		add_filter( 'get_user_courses_filter', [ $this , 'get_user_courses' ], 10, 1 );
		add_filter( 'get_details_filter', [ $this , 'get_details' ], 10, 2 );
		add_filter( 'started_course', [ $this , 'started_course' ], 10, 2 );
		add_filter( 'trim_title_chars_filter', [ $this , 'trim_title_chars' ], 10, 3 );
		add_action('admin_head', [$this,'my_custom_fonts']);
		add_action( 'woocommerce_product_query', [$this,'custom_pre_get_posts_query'] );  
		add_action( 'woocommerce_thankyou', [$this,'bbloomer_redirectcustom']);
		add_action('wp_ajax_updateStatus', [$this,'updateStatus']);
		add_action('wp_ajax_nopriv_updateStatus', [$this,'updateStatus']);
		add_action('save_post', [$this,'my_save_post'],15,1);
		add_action( 'wp_enqueue_scripts', [$this,'enqueue_scripts'] );
		
	}
	public function enqueue_styles() {
	    
	}
		

	public function enqueue_scripts() {
		wp_enqueue_style( 'ninja_dev_lms', plugin_dir_url( __FILE__ ) . 'assets/css/main.css', array(), time(), 'all' );
		
		if( is_single() && 'courses' == get_post_type() ) {
		    wp_enqueue_script( 'ninja_dev_lms_js', plugin_dir_url( __FILE__ ) . 'assets/js/main.js', array('jquery'), time(), false );
		}
	}

	public function register_shortcodes() {
		add_shortcode( 'ninja_pdf_generator', array($this, 'ninja_pdf_generator_shortcode') );
	}

	/**
	 * Shortcode function for ninja-pdf-generator tag
	 *
	 * @since	0.4.0
	 *
	 */
	public function ninja_pdf_generator_shortcode( $atts = [] ) 
	{
		global $wpdb;
		$course_id = $atts['course_id'];
		$certificate = '';
		if(isset($atts['certificate'])) $certificate = $atts['certificate'];
		$pdf_img = plugin_dir_url( __FILE__ ).'public/img/pdf.png';
		$values =(object) array(
					'download'			=> 1,
					'download_text' 	=> 'Download',
					'download_class' 	=> 'download-link',
					'view'				=> 1,
					'view_text' 		=> 'View',
					'view_class' 		=> 'view-link'
				);
		$content = '';
		// if( isset($_GET['lesson']) && $_GET['lesson'] == 'finished' ) {
		// 	$content .= '<div><h3>You are finished this course!</h3></div>';
		// }
		$content.='<div class="certificate center-xs"><h3>Download or view Certificate</h3><img src="'.$pdf_img.'">';
		$content .= "<ul id=\"ninja-pdf-generator\">";
		$download = $values->download;
		$view = $values->view;
		if( $certificate ) {
			$file_url = $certificate;			
		}
		else{
			$file_url = $this->generate_pdf(get_current_user_id(),$course_id);
			$sql = $wpdb->update($wpdb->prefix.'users_courses', array(
					              'certificate'=>$file_url
					          ),['course_id'=>$course_id,'user_id'=>get_current_user_id()]);
		}
		if( $download ) {
			$download_text = $values->download_text;
			$download_class = $values->download_class;
			$content .= "<li><a class=\"ninja-pdf-generator $download_class\" href=\"$file_url\" download>$download_text</a></li> ";
		}
		if( $view ) {
			$view_text = $values->view_text;
			$view_class = $values->view_class;
			$content .= "<li><a class=\"ninja-pdf-generator $view_class\" target=\"_blank\" href=\"$file_url\">$view_text</a></li> ";
		}
		$content .= '</ul></div>';
		return $content;
	}

	public function generate_pdf($user_id,$course_id) 
	{
		global $wpdb;
		$course_name = get_the_title($course_id);
		$title = str_replace(' ', '_', $course_name);
		$user_info = get_userdata($user_id);
      	$username = $user_info->user_login;
      	$display_name = $user_info->display_name;
      	$first_name = $user_info->first_name;
      	$last_name = $user_info->last_name;
      	if(empty($display_name)) {
      		if(!empty($username)) {
      			$display_name = $username;
      		} else {
      			$display_name = $first_name.' '.$last_name;
      		}
      	}
		$options = new Options();
		$options->set('isRemoteEnabled', true);
		$options->set('defaultFont', 'Helvetica');
		$dompdf = new Dompdf($options);
		$uploads = wp_upload_dir();
		if( !file_exists($uploads['basedir'].'/ninja_pdf_generator') ) {
			mkdir($uploads['basedir'].'/ninja_pdf_generator', 0755);
		}
		$template = $this->get_template_file( $uploads['basedir'].'/ninja_pdf_generator/pdf.php' );
		$data = [
			'username' 	=> $username,
			'title' 	=> $course_name,
			'firstName'	=>$display_name,
			'eventName'	=>'CCTP program Certification',
			'startDate'	=>date('d/m/Y')
		];
		foreach ($data as $key => $value) {
            $template = preg_replace('/\{\{\s*'.$key.'\s*\}\}/', trim($value), $template);
        }
        $dompdf->setPaper('landscape', 'A4');
		$dompdf->loadHtml($template);
		$dompdf->render();
		$output = $dompdf->output();
		if( !file_exists($uploads['basedir'].'/ninja_pdf_generator/'.$username) ) {
			mkdir($uploads['basedir'].'/ninja_pdf_generator/'.$username, 0755);
		}
		$file_name = $uploads['basedir'].'/ninja_pdf_generator/'.$username.'/'.$title.'.pdf';
		if(!file_exists($file_name)) {
			file_put_contents($file_name, $output);
			$headers = array('Content-Type: text/html; charset=UTF-8');
			$template = $this->get_template_file( $uploads['basedir'].'/ninja_pdf_generator/mail.php', $file_name );
			wp_mail($user_info->data->user_email, 'PDF', $template, $headers,array($file_name));
		}
		$file_url = $uploads['baseurl'].'/ninja_pdf_generator/'.$username.'/'.$title.'.pdf';
		return $file_url;
	} 

	public function get_template_file($filename, $file_url=null) 
	{
		if (is_file($filename)) {
			ob_start();
			require $filename;
			return ob_get_clean();
		}
		return false;
	}
	
	public function custom_post_type()  
	{
		$labels = array(
			'name'                  => _x( 'Courses', 'Post Type General Name', 'text_domain' ),
			'singular_name'         => _x( 'Courses', 'Post Type Singular Name', 'text_domain' ),
			'menu_name'             => __( 'Courses', 'text_domain' ),
			'name_admin_bar'        => __( 'Courses', 'text_domain' ),
			'archives'              => __( 'Item Archives', 'text_domain' ),
			'attributes'            => __( 'Item Attributes', 'text_domain' ),
			'parent_item_colon'     => __( 'Parent Item:', 'text_domain' ),
			'all_items'             => __( 'All Courses', 'text_domain' ),
			'add_new_item'          => __( 'Add New Course', 'text_domain' ),
			'add_new'               => __( 'Add New', 'text_domain' ),
			'new_item'              => __( 'New Course', 'text_domain' ),
			'edit_item'             => __( 'Edit Course', 'text_domain' ),
			'update_item'           => __( 'Update Course', 'text_domain' ),
			'view_item'             => __( 'View Course', 'text_domain' ),
			'view_items'            => __( 'View Courses', 'text_domain' ),
			'search_items'          => __( 'Search Course', 'text_domain' ),
			'not_found'             => __( 'Not found', 'text_domain' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'text_domain' ),
			'featured_image'        => __( 'Featured Image', 'text_domain' ),
			'set_featured_image'    => __( 'Set featured image', 'text_domain' ),
			'remove_featured_image' => __( 'Remove featured image', 'text_domain' ),
			'use_featured_image'    => __( 'Use as featured image', 'text_domain' ),
			'insert_into_item'      => __( 'Insert into item', 'text_domain' ),
			'uploaded_to_this_item' => __( 'Uploaded to this item', 'text_domain' ),
			'items_list'            => __( 'Courses list', 'text_domain' ),
			'items_list_navigation' => __( 'Courses list navigation', 'text_domain' ),
			'filter_items_list'     => __( 'Filter Courses list', 'text_domain' ),
		);
		$args = array(
			'label'                 => __( 'Courses', 'text_domain' ),
			'description'           => __( 'Courses Description', 'text_domain' ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'editor', 'thumbnail' ),
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_position'         => 5,
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => true,
			'can_export'            => true,
			'has_archive'           => false,
			'exclude_from_search'   => false,
			'publicly_queryable'    => true,
			'capability_type'       => 'page',
			'rewrite' => array('slug' => 'course'), 
		);
		register_post_type( 'courses', $args );
	}
    
	public function my_save_post( $post_id)
	{
  		$course_type = get_field('course_type', $post_id);
	  	$price = '';
	  	$sale = '';
	  	$thumbnail_id = get_post_thumbnail_id( $post_id );
	  	$post_title = get_the_title( $post_id );
	  	
	  	$post_slug = get_post_field( 'post_name', $post_id);
	  	
	  	$post_content = apply_filters('the_content', get_post_field('post_content', $post_id));

	  	if( get_field('lesson_fields', $post_id))
	  	{
		    WP_Filesystem();
		    $destination = wp_upload_dir();
		    $destination_path = $destination['path'];
		    $destination_basedir = $destination['basedir'];
		    $dirname = get_home_path() . 'online-courses';
			$fields=get_field('lesson_fields', $post_id);
		    if( have_rows('lesson_fields') )
		    {
		    	global $wpdb;
		  		$result_all_courses = $wpdb->get_results ( "SELECT `details` FROM  ".$wpdb->prefix."users_courses WHERE course_id = ".$post_id );
	  			$aarr = [];
	  			$i = 0;
	  			$lessonbase_dir = $dirname.'/'.$post_slug;
	  			ninja_delete_files($lessonbase_dir); // before zip delete all files and folders
		      	while( have_rows('lesson_fields') )
		      	{
		          	the_row();
			        $sub_value = get_sub_field('upload_lesson');
			        $lessondir = $dirname.'/'.$post_slug.'/'.$sub_value["name"];
			        $lessonlink = get_bloginfo( 'url' ).'/online-courses/'.$post_slug.'/'.$sub_value["name"];
			        if(!file_exists($dirname)) wp_mkdir_p($dirname);
			        if(!file_exists($lessondir)) wp_mkdir_p($lessondir);
                    $custom_path = explode('/',$sub_value['url']);
				    $custom = $destination_basedir.'/'.$custom_path[5].'/'.$custom_path[6];
				    if(file_exists($custom.'/'.$sub_value["filename"])) {
				        $unzipfile = unzip_file( $custom.'/'.$sub_value["filename"], $lessondir);
				    }
			        update_sub_field('link_lesson', $lessonlink);
			        $str_name = strtolower(str_replace(' ','_',get_sub_field("name")));
			        $html = '<div class="resp-container" data-id="'.$str_name.'"><iframe src="'.$lessonlink.'" frameborder="0scrolling="no"></iframe></div>';
			        update_sub_field('content_lesson', $html);
			        update_sub_field('lesson_id', $str_name.'_'.get_row_index());
		          	$ch_lesson_id = $str_name.'_'.get_row_index();
		          	array_push($aarr,$ch_lesson_id);
			    } // end while
		      	$json = array();
		      	foreach($result_all_courses as $item) {
					foreach(json_decode($item->details) as $key => $val) {
						foreach($val as $mm => $kk) {
							if( isset($aarr[$i]) && $mm == $aarr[$i] ) {
								array_push($json,array($mm=>array('status'=>$kk->status)) );
							} else {
								if(isset($aarr[$i])) {
									array_push($json,array($aarr[$i]=>array('status'=>0)));
								}
							}
							$i++;
						}
					}
	  			} // end foreach
				$js = wp_json_encode($json);
		    	$sql = $wpdb->update($wpdb->prefix.'users_courses', array(
					              'details'=>$js
					          ),['course_id'=>$post_id]);
		    }
	  	}
	  	if($course_type === 'Paid')
	  	{
		    $price_info = get_field('price_info', $post_id);
		    $price = $price_info["regular_price"];
		    $sale = $price_info["sale_price"];
		    if($price!=''){
		      
		      	$term = term_exists( 'Courses', 'product_cat' );
		      	if ( $term !== 0 && $term !== null ) {
		          	echo __( "'Courses' category exists!", "textdomain" );
		      	}
		      	else{
			        wp_insert_term( 'Courses', 'product_cat', array(
			          'description' => 'Description for category', // optional
			          'parent' => 0, // optional
			          'slug' => 'course-product' // optional
			        ));
		      	}
		      	$post_ID='';
		      	$fount_post = post_exists( $post_title,'','','product');
		      	if(!$fount_post){
			        $post_ID = wp_insert_post(array(
			          'post_title' => $post_title,
			          'post_type' => 'product',
			          'post_content' =>$post_content,  
			          'post_status' => 'publish',      
			        ));
		      	} else{
		        	$post_ID = $fount_post;
		      	}
		      
		      	if($post_ID != ''){
			        $my_post = [
	          			'ID' => $post_ID,
			          	'post_content' => $post_content,
			        ];
			        wp_update_post( $my_post );
			        update_field('cours_id', $post_id,$post_ID);
			        set_post_thumbnail( $post_ID, $thumbnail_id );

			        wp_set_object_terms( $post_ID, 'Courses', 'product_cat' );
			        wp_set_object_terms($post_ID, 'simple', 'product_type');
			        update_post_meta( $post_ID, '_visibility', 'visible' );
			        update_post_meta( $post_ID, '_stock_status', 'instock');
			        update_post_meta( $post_ID, 'total_sales', '0');
			        update_post_meta( $post_ID, '_regular_price', $price );
			        update_post_meta( $post_ID, '_sale_price', $sale );
			        update_post_meta( $post_ID, '_purchase_note', "" );
			        update_post_meta( $post_ID, '_featured', "no" );
			        update_post_meta( $post_ID, '_weight', "" );
			        update_post_meta( $post_ID, '_length', "" );
			        update_post_meta( $post_ID, '_width', "" );
			        update_post_meta( $post_ID, '_height', "" );
			        update_post_meta( $post_ID, '_sku', "");
			        update_post_meta( $post_ID, '_product_attributes', array());
			        update_post_meta( $post_ID, '_sale_price_dates_from', "" );
			        update_post_meta( $post_ID, '_sale_price_dates_to', "" );
			        update_post_meta( $post_ID, '_price', $sale ? $sale: $price );
			        update_post_meta( $post_ID, '_sold_individually', true );
			        update_post_meta( $post_ID, '_manage_stock', "no" );
			        update_post_meta( $post_ID, '_backorders', "no" );
			        update_post_meta( $post_ID, '_stock', "" );
		      	} // exist post_ID
		    } // exists price
	  	} // if Paid
	}

	public function my_custom_fonts()
	{
	  	echo '<style>
	    .dNone,.acf-th[data-name="content_lesson"]{display:none !important;}
	  </style>';
	}

	public function started_course($user_id,$product_id)
	{
		global $wpdb;
	  	$result = $wpdb->get_results ( "
      		SELECT course_id,progress,status 
	      		FROM  ".$wpdb->prefix."users_courses
          	WHERE user_id = ".$user_id." and product_id=".$product_id);
	  	if(empty($result)) return false;
	  	return (array) $result[0];
	}

	public function trim_title_chars($title,$count, $after)
	{
	  	if (mb_strlen($title) > $count) $title = mb_substr($title,0,$count);
	  	else $after = '';
	  	echo $title . $after;
	}

	public function custom_pre_get_posts_query( $q )
	{
	    $tax_query = (array) $q->get( 'tax_query' );
	    $tax_query[] = array(
	           'taxonomy' => 'product_cat',
	           'field' => 'slug',
	           'terms' => array( 'course-product' ),
	           'operator' => 'NOT IN'
	    );
	    $q->set( 'tax_query', $tax_query );
	}
	
	public function bbloomer_redirectcustom( $order_id )
	{
	    $order = new WC_Order($order_id);
	    if (!empty($order) ) {
	        global $wpdb;
	        
	        $items=$order->get_items();
	        $user_id = $order->get_customer_id();
	        $status = 0;
	        
	        if(!$order->has_status( 'failed' )){
	          $status = 1;
	        }
	        
	        foreach ($items as $key => $item) {
	          	$pr_id = $item->get_product_id();
	         	if( has_term( 'course-product', 'product_cat', $pr_id )){
		          	$course_id = get_field('cours_id',$pr_id);
		          	$json = array();
		          	$fields = get_field('lesson_fields',$course_id);
		          
	                foreach($fields as $k => $field):
                    	array_push($json,array($field['lesson_id']=>array('status'=>0)) );
                  	endforeach;

			        $js = wp_json_encode($json);
			        
			        $check_exists = $wpdb->get_results("SELECT `id` FROM ".$wpdb->prefix."users_courses WHERE `course_id` = $course_id AND `user_id` = $user_id");
			        if(!$check_exists) {
			            $order->update_status( 'completed' );
			            $sql = $wpdb->insert($wpdb->prefix.'users_courses', array(
    			            'course_id' => $course_id,
    			            'user_id' => $user_id,
    			            'product_id' => $pr_id, 
    			            'status'=>0,
    			            'progress'=>'0',
    			            'pay_status'=>1,
    			            'details'=>$js
			            ));
			        } else {
			            wp_redirect(get_bloginfo('url'));
			        }
	         	} 
	        }
	        exit;
	    }
	}

	public function get_user_courses($user_id)
	{
  		global $wpdb;
	  	$result = $wpdb->get_results ( 'SELECT * FROM  '.$wpdb->prefix.'users_courses WHERE user_id = '.$user_id );
	  	return $result;
	}

	public function get_details($user_id,$course_id)
	{
		global $wpdb;
	  	$result = $wpdb->get_results ( "
      		SELECT details,certificate 
	      		FROM  ".$wpdb->prefix."users_courses
          	WHERE user_id = ".$user_id." and course_id=".$course_id );
	  	return $result;
	}

	public function updateStatus()
	{
		global $wpdb;
  		$data = $_POST['lesson'];
  		$lesson_id = $data["lessonId"];
	    $course_id = $data["courseId"];
	    $user_id = get_current_user_id();
	    
	  	$result = $wpdb->get_results ( "
	      	SELECT details 
	      		FROM  ".$wpdb->prefix."users_courses
          	WHERE user_id = ".$user_id." and course_id=".$course_id );
	  
	  	$result = json_decode($result[0]->details);
	  	$count = count($result);
	  	$status_count = 0;
	  	$course_status=0;
	  	foreach($result as $lesson){
		    foreach ($lesson as $k => $v) {
		      	if($k===$lesson_id){
		        	$v->status = 1;
		      	}
		      	if($v->status === 1) $status_count++;
		    }
	  	}
	  	$progress = ($status_count/$count)*100;
	  	$progress=(string)$progress.'%';
	  	if($status_count==$count) $course_status=1;
	  	$result = wp_json_encode($result);
	  	$r = $wpdb->update($wpdb->prefix."users_courses",array('details'=>$result,'progress'=>$progress,'status'=>$course_status),array('user_id' => $user_id,'course_id' =>$course_id));
	  	echo $course_status;
	    wp_die();
	}

	/**
	 * Adds a submenu page under a custom post type parent.
	 */
	public function reports_register_page()
	{
	    add_submenu_page(
	        'edit.php?post_type=courses',
	        __( 'Reports', 'textdomain' ),
	        __( 'Reports', 'textdomain' ),
	        'manage_options',
	        'reports',
	        'reports_page_callback'
	    );
	}
 	//add_action('admin_menu', 'reports_register_page');
	/**
	 * Display callback for the submenu page.
	 */
	public function reports_page_callback()
	{ 
	    ?>
	    <div class="wrap">
	        <h1><?php _e( 'Reports', 'textdomain' ); ?></h1>
	        <p><?php _e( 'Helpful stuff here', 'textdomain' ); ?></p>
	    </div>
	    <?php
	} 

	public static function set_screen( $status, $option, $value )
	{
		return $value;
	}

	public function plugin_menu()
	{

		/*$hook = add_menu_page(
			'Sitepoint WP_List_Table Example',
			'SP WP_List_Table',
			'manage_options',
			'wp_list_table_class',
			[ $this, 'plugin_settings_page' ]
		);*/
		$hook = add_submenu_page(
			'edit.php?post_type=courses',
			__( 'Reports', 'textdomain' ),
			__( 'Reports', 'textdomain' ),
			'manage_options',
			'reports',
			[ $this, 'plugin_settings_page' ]
		);
		add_action( "load-$hook", [ $this, 'screen_option' ] );
	}

	/**
	 * Plugin settings page
	 */
	public function plugin_settings_page()
	{
		?>
		<div class="wrap">
			<h2>Reports</h2>

			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-2">
					<div id="post-body-content">
						<div class="meta-box-sortables ui-sortable">
							<form method="post">
								<?php
								$this->customers_obj->prepare_items();
								$this->customers_obj->display(); ?>
							</form>
						</div>
					</div>
				</div>
				<br class="clear">
			</div>
		</div>
	<?php
	}

	/**
	 * Screen options
	 */
	public function screen_option()
	{
		$option = 'per_page';
		$args   = [
			'label'   => 'Customers',
			'default' => 5,
			'option'  => 'customers_per_page'
		];

		add_screen_option( $option, $args );

		$this->customers_obj = new Customers_List();
	}

	public function admin_styles()
	{	
		  echo '<style>
		  .progres {
			height: 20px;
			position: relative;
			background: #eee;
			width: 100%;
		}
		.inner-progress {
		  height: 20px;
			display: inline-block;
			position: relative;
			overflow: hidden;
			background-color: rgb(43,194,83);
			background-image: -webkit-gradient( linear, left bottom, left top, color-stop(0, rgb(43,194,83)), color-stop(1, rgb(84,240,84)) );
			background-image: -moz-linear-gradient( center bottom, rgb(43,194,83) 37%, rgb(84,240,84) 69% );
			color: #fff;
			letter-spacing: 0.5px;
			font-weight: bold;
			font-size: 13px;
			text-align: center;
			line-height: initial;
		}
		.inner-progress:after {
			content: "";
			position: absolute;
			top: 0;
			left: 0;
			bottom: 0;
			right: 0;
			background-image: -webkit-gradient(linear, 0 0, 100% 100%, color-stop(.25, rgba(255, 255, 255, .2)), color-stop(.25, transparent), color-stop(.5, transparent), color-stop(.5, rgba(255, 255, 255, .2)), color-stop(.75, rgba(255, 255, 255, .2)), color-stop(.75, transparent), to(transparent) );
			background-image: -moz-linear-gradient( -45deg, rgba(255, 255, 255, .2) 25%, transparent 25%, transparent 50%, rgba(255, 255, 255, .2) 50%, rgba(255, 255, 255, .2) 75%, transparent 75%, transparent );
			z-index: 1;
			-webkit-background-size: 50px 50px;
			-moz-background-size: 50px 50px;
			background-size: 50px 50px;
			-webkit-animation: move 2s linear infinite;
			-moz-animation: move 2s linear infinite;    
			overflow: hidden;
		}
		.inner-progress.completed:after {
			display: none;
		}
		@-webkit-keyframes move {
			0% {
			   background-position: 0 0;
			}
			100% {
			   background-position: 50px 50px;
			}
		}

		@-moz-keyframes move {
			0% {
			   background-position: 0 0;
			}
			100% {
			   background-position: 50px 50px;
			}
		}
		
		  </style>';
	}

	/** Singleton instance */
	public static function get_instance()
	{
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}

add_action( 'plugins_loaded', function () {
	SP_Plugin::get_instance();
} );

if(!function_exists('activate_ninja_plugin')) {
    function activate_ninja_plugin()
    {
        global $wpdb;
        $wpdb->query("CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}users_courses`(
          `ID` int(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
          `course_id` int(11) NOT NULL,
          `user_id` bigint(20) UNSIGNED NOT NULL,
          `product_id` int(11) NOT NULL,
          `details` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
          `status` tinyint(4) NOT NULL DEFAULT '0',
          `progress` varchar(191) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '0',
          `pay_status` tinyint(4) NOT NULL DEFAULT '0',
          `certificate` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL
        ) {$wpdb -> get_charset_collate()};");
    }
} register_activation_hook( __FILE__, 'activate_ninja_plugin' );