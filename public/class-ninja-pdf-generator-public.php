<?php


use Dompdf\Dompdf;
use Dompdf\Options;

class Ninja_Pdf_Generator_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $ninja_pdf_generator;

	/**
	 * The version of this plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    0.2.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct() {

		ob_start();

		$this->ninja_pdf_generator = 'ninja_pdf_generator';

	}


	/**
	 * Register shortcodes for the public-facing side of the site.
	 *
	 * @since	0.1.0
	 */
	public function register_shortcodes() {
		add_shortcode( $this->ninja_pdf_generator, array($this, 'ninja_pdf_generator_shortcode') );
	}

	/**
	 * Shortcode function for ninja-pdf-generator tag
	 *
	 * @since	0.4.0
	 *
	 */
	public function ninja_pdf_generator_shortcode( $atts = [] ) {
		$values = array(
					'download'			=> 1,
					'download_text' 	=> 'Download',
					'download_class' 	=> 'download-link',
					'send'				=> 1,
					'send_text' 		=> 'Send',
					'send_class' 		=> 'send-link',
					'name_label' 		=> 'Name',
					'name_placeholder' 	=> 'Full name',
					'email_label' 		=> 'E-mail',
					'email_placeholder' => 'E-mail',
					'success_message' 	=> 'E-mail has been sent.',
					'error_message' 	=> 'E-mail NOT sent.',
					'submit_label' 		=> 'Send',
					'view'				=> 1,
					'view_text' 		=> 'View',
					'view_class' 		=> 'view-link'
				);

		$content = "<ul id=\"ninja-pdf-generator\">";
var_dump($values);
		$download = $values->download;
		$send = $values->send;
		$view = $values->view;

		if( $view || $download || $send ) {
			$file_url = $this->generate_pdf();
		}

		if( $download ) {
			$download_text = $values->download_text;
			$download_class = $values->download_class;
			$content .= "<li><a class=\"$this->advanced_pdf_generator $download_class\" href=\"$file_url\" download>$download_text</a></li> ";
		}

		if( $send ) {
			$send_text = $values->send_text;
			$send_class = $values->send_class;
			$name_label = $values->name_label;
			$name_placeholder = $values->name_placeholder;
			$email_label = $values->email_label;
			$email_placeholder = $values->email_placeholder;
			$submit_label = $values->submit_label;
			$content .= "<li><a class=\"$this->advanced_pdf_generator $send_class\" href=\"javascript:;\" onclick=\"send_apdfg()\" data-toggle=\"modal\" data-target=\"#modal-send\">$send_text</a></li>
			<script>
				function send_apdfg() {
					swal({
						html: '<form id=\"apdfg-send-email\" action=\"" . esc_url( $_SERVER['REQUEST_URI'] ) . "\" method=\"POST\">'+
								'<div class=\"cont-input full\">'+
									'<label>$name_label</label>'+
									'<input name=\"names\" type=\"text\" placeholder=\"$name_placeholder\" required>'+
								'</div>'+
								'<div class=\"cont-input full\">'+
									'<label>$email_label</label>'+
									'<input name=\"email\" type=\"email\" placeholder=\"$email_placeholder\" required>'+
								'</div>'+
								'<input name=\"file_url\" type=\"hidden\" value=\"$file_url\">'+
								'<input class=\"submit\" type=\"submit\" name=\"send-apdfg\" value=\"$submit_label\">'+
							'</form>',
						showConfirmButton: false,
						showCloseButton: true
					});
				}
			</script> ";
		}

		if( $view ) {
			$view_text = $values->view_text;
			$view_class = $values->view_class;
			$content .= "<li><a class=\"$this->advanced_pdf_generator $view_class\" target=\"_blank\" href=\"$file_url\">$view_text</a></li> ";
		}

		if( $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send-apdfg']) ) {
			$email = $this->send_email();
			if( $email ) {
				$content .= "<script>
				window.onload = function() {
					swal({
						title: '$values->success_message',
						type: 'success'
					});
				 }
				</script>";
			} else {
				$content .= "<script>
				window.onload = function() {
					swal({
						title: '$values->error_message',
						type: 'error'
					});
				 }
				</script>";
			}
		}

		$content .= '</ul>';

		return $content;
	}

	/**
	 * Render PDF, save in uploads directory
	 *
	 * @return	$file_url
	 * @since	0.3.1
	 *
	 */
	public function generate_pdf() {
		$options = new Options();
		$options->set('isRemoteEnabled', true);
		$options->set('defaultFont', 'Helvetica');

		$dompdf = new Dompdf($options);

		$uploads = wp_upload_dir();
		if( !file_exists($uploads['basedir'].'/'.$this->ninja_pdf_generator) ) {
			mkdir($uploads['basedir'].'/'.$this->ninja_pdf_generator, 0755);
		}

		$template = $this->get_template_file( get_template_directory() . '/apg-templates/pdf.php' );
		if(!$template) {
			$template = "<p>Template file not found at theme_path.../$this->ninja_pdf_generator/apg-templates/pdf.php </p>";
		}

		$dompdf->loadHtml($template);
		$dompdf->render();
		$output = $dompdf->output();

		ob_start();
		if(!session_id()) {
			session_start();
		}
		$random_str = substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil(10/strlen($x)) )),1,10);
		$file_name = session_id(). '_' . $random_str .'.pdf';
		file_put_contents($uploads['basedir'].'/ninja-pdf-generator/'.$file_name, $output);
		$file_url = $uploads['baseurl'].'/ninja-pdf-generator/'.$file_name;
		return $file_url;
	}

	/**
	 * Load template file to generate PDF
	 *
	 * @since 0.1.0
	 *
	 */
	public function get_template_file($filename, $name=null, $file_url=null) {
		if (is_file($filename)) {
			ob_start();
			require $filename;
			return ob_get_clean();
		}
		return false;
	}

	/**
	 * Send email if send options is enabled and triggered
	 *
	 * @since	0.3.0
	 *
	 */
	public function send_email() {
		$name = sanitize_text_field( $_POST["names"] );
		$email = sanitize_email( $_POST["email"] );
		$file_url = esc_url( $_POST["file_url"] );

		$to = $email;
		$headers = array('Content-Type: text/html; charset=UTF-8');

		$template = $this->get_template_file( get_template_directory() . '/apg-templates/mail.php', $name, $file_url );
		if(!$template) {
			$template = "<p>Hello $name, <br> Here is your PDF file: $file_url</p>";
		}

		if( wp_mail($to, 'PDF', $template, $headers) ) {
			return true;
		} else {
			return false;
		}
	}

}
