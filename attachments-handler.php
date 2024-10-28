<?php
/**
Plugin Name: Attachments Handler
Plugin Tag: attachments, check, validity, documents, images, duplicate, not used
Description: <p>Enables the supervision of your attachement, detects duplicates, detects unused files.</p><p>You may also create a list of all attached file in the page or in the child pages by using the following shorcode <code>[attach child=1 only_admin=1 title='Title you want' extension='pdf,doc,png']</code>.</p>
Version: 1.1.7
Framework: SL_Framework
Author: sedLex
Author URI: http://www.sedlex.fr/
Author Email: sedlex@sedlex.fr
Framework Email: sedlex@sedlex.fr
Plugin URI: http://wordpress.org/plugins/attachments-handler/
License: GPL3
*/

//Including the framework in order to make the plugin work

require_once('core.php') ; 

/** ====================================================================================================================================================
* This class has to be extended from the pluginSedLex class which is defined in the framework
*/
class attachments_handler extends pluginSedLex {
	
	/** ====================================================================================================================================================
	* Plugin initialization
	* 
	* @return void
	*/
	static $instance = false;

	protected function _init() {
		global $wpdb ; 

		// Name of the plugin (Please modify)
		$this->pluginName = 'Attachments Handler' ; 
		
		// The structure of the SQL table if needed (for instance, 'id_post mediumint(9) NOT NULL, short_url TEXT DEFAULT '', UNIQUE KEY id_post (id_post)') 
		$this->tableSQL = "id mediumint(9) NOT NULL, id_post mediumint(9) NOT NULL, url VARCHAR(400), path TEXT DEFAULT '', description TEXT DEFAULT '',  titre TEXT DEFAULT '', legende TEXT DEFAULT '', sha1 VARCHAR(40), attach_used_in TEXT DEFAULT '', is_exist BOOL, ignored BOOLEAN NOT NULL DEFAULT 0, id_media BIGINT NOT NULL AUTO_INCREMENT" ; 
		// The name of the SQL table (Do no modify except if you know what you do)
		$this->table_name = $wpdb->prefix . "pluginSL_" . get_class() ; 

		//Configuration of callbacks, shortcode, ... (Please modify)
		// For instance, see 
		//	- add_shortcode (http://codex.wordpress.org/Function_Reference/add_shortcode)
		//	- add_action 
		//		- http://codex.wordpress.org/Function_Reference/add_action
		//		- http://codex.wordpress.org/Plugin_API/Action_Reference
		//	- add_filter 
		//		- http://codex.wordpress.org/Function_Reference/add_filter
		//		- http://codex.wordpress.org/Plugin_API/Filter_Reference
		// Be aware that the second argument should be of the form of array($this,"the_function")
		// For instance add_action( "wp_ajax_foo",  array($this,"bar")) : this function will call the method 'bar' when the ajax action 'foo' is called
		
		add_shortcode( 'attach', array( $this, 'displayAttachments' ) );
		
		add_action( 'wp_ajax_nopriv_checkIfAttachmentsHandlerNeeded', array( $this, 'checkIfAttachmentsHandlerNeeded'));
		add_action( 'wp_ajax_checkIfAttachmentsHandlerNeeded', array( $this, 'checkIfAttachmentsHandlerNeeded'));
		
		add_action( "wp_ajax_stopAnalysisAttachments",  array($this,"stopAnalysisAttachments")) ; 
		add_action( "wp_ajax_forceAnalysisAttachments",  array($this,"forceAnalysisAttachments")) ; 
		add_action( "wp_ajax_cleanAnalysisAttachments",  array($this,"cleanAnalysisAttachments")) ; 
		
		add_action( "wp_ajax_stopRegenerateAttachments",  array($this,"stopRegenerateAttachments")) ; 
		add_action( "wp_ajax_forceRegenerateAttachments",  array($this,"forceRegenerateAttachments")) ; 
		
		add_action( "wp_ajax_doNotignoreAttachmentIssue",  array($this,"doNotignoreAttachmentIssue")) ; 
		add_action( "wp_ajax_ignoreAttachmentIssue",  array($this,"ignoreAttachmentIssue")) ; 

		add_action( 'save_post', array( $this, 'whenPostIsSaved') );
		add_action( 'delete_post', array( $this, 'whenPostIsSaved') );
		
		add_action( 'add_attachment', array( $this, 'whenAttachmentIsSaved') );
		add_action( 'edit_attachment', array( $this, 'whenAttachmentIsSaved') );
		add_action( 'delete_attachment', array( $this, 'whenAttachmentIsSaved') );

		// Important variables initialisation (Do not modify)
		$this->path = __FILE__ ; 
		$this->pluginID = get_class() ; 
		
		// activation and deactivation functions (Do not modify)
		register_activation_hook(__FILE__, array($this,'install'));
		register_deactivation_hook(__FILE__, array($this,'deactivate'));
		register_uninstall_hook(__FILE__, array('attachments_handler','uninstall_removedata'));
	}
	
	/** ====================================================================================================================================================
	* In order to uninstall the plugin, few things are to be done ... 
	* (do not modify this function)
	* 
	* @return void
	*/
	
	static public function uninstall_removedata () {
		global $wpdb ;
		
		// DELETE OPTIONS
		delete_option('attachments_handler'.'_options') ;
		if (is_multisite()) {
			delete_site_option('attachments_handler'.'_options') ;
		}
		
		// DELETE SQL
		if (function_exists('is_multisite') && is_multisite()){
			$old_blog = $wpdb->blogid;
			$old_prefix = $wpdb->prefix ; 
			// Get all blog ids
			$blogids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM ".$wpdb->blogs));
			foreach ($blogids as $blog_id) {
				switch_to_blog($blog_id);
				$wpdb->query("DROP TABLE ".str_replace($old_prefix, $wpdb->prefix, $wpdb->prefix . "pluginSL_" . 'attachments_handler')) ; 
			}
			switch_to_blog($old_blog);
		} else {
			$wpdb->query("DROP TABLE ".$wpdb->prefix . "pluginSL_" . 'attachments_handler' ) ; 
		}
		
		// DELETE FILES if needed
		//SLFramework_Utils::rm_rec(WP_CONTENT_DIR."/sedlex/my-plugin/"); 
		$plugins_all = 	get_plugins() ; 
		$nb_SL = 0 ; 	
		foreach($plugins_all as $url => $pa) {
			$info = pluginSedlex::get_plugins_data(WP_PLUGIN_DIR."/".$url);
			if ($info['Framework_Email']=="sedlex@sedlex.fr"){
				$nb_SL++ ; 
			}
		}
		if ($nb_SL==1) {
			SLFramework_Utils::rm_rec(WP_CONTENT_DIR."/sedlex/"); 
		}
	}
	
	/**====================================================================================================================================================
	* Function called when the plugin is activated
	* For instance, you can do stuff regarding the update of the format of the database if needed
	* If you do not need this function, you may delete it.
	*
	* @return void
	*/
	
	public function _update() {
		global $wpdb ; 
		SLFramework_Debug::log(get_class(), "Update the plugin." , 4) ; 
				
		// This update aims at adding the ignored fields 
		if ( !$wpdb->get_var("SHOW COLUMNS FROM ".$this->table_name." LIKE 'ignored'")  ) {
			$wpdb->query("ALTER TABLE ".$this->table_name." ADD ignored BOOLEAN NOT NULL DEFAULT 0;");
		}
		
				
		// This update aims at adding the id_media fields 
		if ( !$wpdb->get_var("SHOW COLUMNS FROM ".$this->table_name." LIKE 'id_media'")  ) {
			$wpdb->query("ALTER TABLE ".$this->table_name." ADD id_media BIGINT NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id_media) ;") ;
		}
		
		// We increase the size of the index
		$wpdb->query("ALTER TABLE ".$this->table_name." MODIFY id_media BIGINT NOT NULL AUTO_INCREMENT");
	}
	
	/**====================================================================================================================================================
	* Function called to return a number of notification of this plugin
	* This number will be displayed in the admin menu
	*
	* @return int the number of notifications available
	*/
	 
	public function _notify() {
		global $wpdb ; 
		global $post ; 
		
		$args = array(
			'numberposts'     => -1,
			'post_type'       => explode(",",$this->get_param('type_page')),
			'fields'        => 'ids',
			'nopaging' 		=> true,
			'post_status'     => 'publish' 
		);
		
		$myQuery = new WP_Query( $args ); 

		//Looping through the posts
		$total = 0 ; 
		$id_t = array() ; 
		//Looping through the posts
		$total = 0 ; 
		if ( $myQuery->have_posts() ) {
			foreach( $myQuery->posts as $id ) {
				$id_t[] = $id ; 
        		$total ++ ; 
   			}
		}

		// Reset Post Data
		wp_reset_postdata();
		
		$verified = $wpdb->get_var("SELECT COUNT(*) FROM ".$this->table_name." WHERE id_post!=0") ;
				
		// Si le nombre n'est pas cohérent, cela signifie que le nombre d'erreur n'est pas fiable
		if ($total<$verified) {
			// DELETE post that should not be here
			$ids = $wpdb->get_results("SELECT id_post FROM ".$this->table_name." WHERE id_post!=0") ;
			foreach($ids as $i) {
				if (in_array($i->id_post, $id_t)) {
					// nothing to do  
				} else {
					$wpdb->query("DELETE FROM ".$this->table_name." WHERE id_post='".$i->id_post."';") ; 
				}					
			}
			return 0 ; 
		}
		
		$nb=0 ; 
		$nb += $wpdb->get_var("SELECT COUNT(*) FROM ".$this->table_name." WHERE url!='' AND is_exist=0 AND ignored=0") ;
		$nb += $wpdb->get_var("SELECT COUNT(*) FROM ".$this->table_name." INNER JOIN (SELECT sha1 FROM ".$this->table_name." WHERE sha1!='?' AND sha1!='' AND ignored=0 GROUP BY sha1 HAVING count(id) > 1) dup ON ".$this->table_name.".sha1 = dup.sha1 ORDER BY ".$this->table_name.".sha1") ; 
		$nb += $wpdb->get_var("SELECT COUNT(*) FROM ".$this->table_name." WHERE attach_used_in='' AND id!=0  AND ignored=0") ;

		return $nb ; 
	}
	
	
	/** ====================================================================================================================================================
	* Init javascript for the public side
	* If you want to load a script, please type :
	* 	<code>wp_enqueue_script( 'jsapi', 'https://www.google.com/jsapi');</code> or 
	*	<code>wp_enqueue_script('attachments_handler_script', plugins_url('/script.js', __FILE__));</code>
	*	<code>$this->add_inline_js($js_text);</code>
	*	<code>$this->add_js($js_url_file);</code>
	*
	* @return void
	*/
	
	function _public_js_load() {	
		ob_start() ; 
		?>
			function checkIfAttachmentsHandlerNeeded() {
				
				var arguments = {
					action: 'checkIfAttachmentsHandlerNeeded'
				} 
				var ajaxurl2 = "<?php echo admin_url()."admin-ajax.php"?>" ; 
				jQuery.post(ajaxurl2, arguments, function(response) {
					// We do nothing as the process should be as silent as possible
				});    
			}
			
			// We launch the callback
			if (window.attachEvent) {window.attachEvent('onload', checkIfAttachmentsHandlerNeeded);}
			else if (window.addEventListener) {window.addEventListener('load', checkIfAttachmentsHandlerNeeded, false);}
			else {document.addEventListener('load', checkIfAttachmentsHandlerNeeded, false);} 
						
		<?php 
		
		$java = ob_get_clean() ; 
		$this->add_inline_js($java) ; 	
	}
	
	/** ====================================================================================================================================================
	* Init css for the public side
	* If you want to load a style sheet, please type :
	*	<code>$this->add_inline_css($css_text);</code>
	*	<code>$this->add_css($css_url_file);</code>
	*
	* @return void
	*/
	
	function _public_css_load() {		
		$css = $this->get_param('css') ; 
		$this->add_inline_css($css) ;
	}
	
	/** ====================================================================================================================================================
	* Init javascript for the admin side
	* If you want to load a script, please type :
	* 	<code>wp_enqueue_script( 'jsapi', 'https://www.google.com/jsapi');</code> or 
	*	<code>wp_enqueue_script('attachments_handler_script', plugins_url('/script.js', __FILE__));</code>
	*	<code>$this->add_inline_js($js_text);</code>
	*	<code>$this->add_js($js_url_file);</code>
	*
	* @return void
	*/
	
	function _admin_js_load() {	

	}
	
	/** ====================================================================================================================================================
	* Init css for the admin side
	* If you want to load a style sheet, please type :
	*	<code>$this->add_inline_css($css_text);</code>
	*	<code>$this->add_css($css_url_file);</code>
	*
	* @return void
	*/
	
	function _admin_css_load() {

	}

	/** ====================================================================================================================================================
	* Called when the content is displayed
	*
	* @param string $content the content which will be displayed
	* @param string $type the type of the article (e.g. post, page, custom_type1, etc.)
	* @param boolean $excerpt if the display is performed during the loop
	* @return string the new content
	*/
	
	function _modify_content($content, $type, $excerpt) {	
		return $content; 
	}
		
	/** ====================================================================================================================================================
	* Add a button in the TinyMCE Editor
	*
	* To add a new button, copy the commented lines a plurality of times (and uncomment them)
	* 
	* @return array of buttons
	*/
	
	function add_tinymce_buttons() {
		$buttons = array() ; 
		$buttons[] = array(__('Attachments Handler', $this->pluginID), '[attach child=1 only_admin=1 title="Title you want" extension="pdf,doc,png"]', '', plugin_dir_url("/").'/'.str_replace(basename( __FILE__),"",plugin_basename( __FILE__)).'img/attach_button.png') ; 
		return $buttons ; 
	}
	
	/**====================================================================================================================================================
	* Function to instantiate the class and make it a singleton
	* This function is not supposed to be modified or called (the only call is declared at the end of this file)
	*
	* @return void
	*/
	
	public static function getInstance() {
		if ( !self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}
	
	/** ====================================================================================================================================================
	* Define the default option values of the plugin
	* This function is called when the $this->get_param function do not find any value fo the given option
	* Please note that the default return value will define the type of input form: if the default return value is a: 
	* 	- string, the input form will be an input text
	*	- integer, the input form will be an input text accepting only integer
	*	- string beggining with a '*', the input form will be a textarea
	* 	- boolean, the input form will be a checkbox 
	* 
	* @param string $option the name of the option
	* @return variant of the option
	*/
	public function get_default_option($option) {
		switch ($option) {
			// Alternative default return values (Please modify)
			case 'type_page' 		: return "page,post" 		; break ; 

			case 'list_post_id_to_check': return array()			; break ; 
			case 'nb_post_to_check'  : return 0 ; break ; 

			case 'list_img_id_to_regenerate': return array()			; break ; 
			case 'nb_img_to_regenerate'  : return 0 ; break ; 
			case 'info_img_to_regenerate'  : return "" ; break ; 

			
			
			case 'max_page_to_check'  : return 200 ; break ; 
			
			case 'titre'  : return "List of all attached files" ; break ; 

			case 'html'  : return "*<div class='attach_div'>
   <h2 class='attach_title'>%title%</h2>
   <div class='attach_list'>%list%</div>
</div>" ; break ; 
			case 'html_entry'  : return "*<p class='title'>%link%</p>
<p class='description'>%description%</p>" ; break ; 
			case 'css'  : return "*div.attach_div{
      border: 1px solid #AAAAAA;
      padding: 5px;
      padding-left: 20px;
      padding-right: 20px;
      padding-bottom: 10px;
      min-width:100px;
}
div.attach_div h2 {
	text-align: center;
	font-size: 13px;
        line-height: 15px ; 
	font-weight: bold;
	margin : 3px ; 
	padding-top : 5px ;
	padding-bottom : 5px ;
}
div.attach_list p{
	margin : 2px ; 
	padding : 2px ; 
        font-size: 11px;
        line-height: 11px ; 
}
div.attach_list p.description{
	padding-left:30px ; 
}" ; break ; 

			case 'last_request' : return 0 ; break ; 
			case 'between_two_requests' : return 5 ; break ; 

		}
		return null ;
	}

	/** ====================================================================================================================================================
	* The admin configuration page
	* This function will be called when you select the plugin in the admin backend 
	*
	* @return void
	*/
	
	public function configuration_page() {
		global $wpdb;
		global $blog_id ; 
	
		$this->set_param('info_img_to_regenerate', "") ; 
				
		SLFramework_Debug::log(get_class(), "Print the configuration page." , 4) ; 
		?>

		<div class="plugin-titleSL">
			<h2><?php echo $this->pluginName ?></h2>
		</div>
		
		<div class="plugin-contentSL">		
			<?php echo $this->signature ; ?>
	
			<?php
			//===============================================================================================
			// After this comment, you may modify whatever you want
			
			// We check rights
			$this->check_folder_rights( array(array(WP_CONTENT_DIR."/sedlex/test/", "rwx")) ) ;
			
			$tabs = new SLFramework_Tabs() ; 
			
			ob_start() ; 
			
				echo "<div id='table_formatting'>"  ; 
				$this->displayTable(0) ;
				echo "</div>" ; 
			
				echo "<p>" ; 
				echo "<img id='wait_analysisAH' src='".WP_PLUGIN_URL."/".str_replace(basename(__FILE__),"",plugin_basename( __FILE__))."core/img/ajax-loader.gif' style='display: none;'>" ; 
				echo "<input type='button' id='forceAnalysisAH' class='button-primary validButton' onClick='forceAnalysisAH()'  value='". __('Force analysis',$this->pluginID)."' />" ; 
				echo "<script>jQuery('#forceAnalysisAH').removeAttr('disabled');</script>" ; 
				echo " <input type='button' id='stopAnalysisAH' class='button validButton' onClick='stopAnalysisAH()'  value='". __('Stop analysis',$this->pluginID)."' />" ; 
				echo "<script>jQuery('#stopAnalysisAH').attr('disabled', 'disabled');</script>" ; 
				echo " <input type='button' id='cleanAnalysisAH' class='button validButton' onClick='cleanAnalysisAH()'  value='". __('Reset all entries',$this->pluginID)."' />" ; 
				echo "</p>" ; 
				
			$tabs->add_tab(__('Attachments issues',  $this->pluginID), ob_get_clean()) ; 	
			
			ob_start() ; 
			
				echo "<div id='table_formatting'>"  ; 
				$this->displayTable(1) ;
				echo "</div>" ;  
				
			$tabs->add_tab(__('Ignored issues',  $this->pluginID), ob_get_clean()) ; 	
			
			ob_start() ; 
				$maxnb = 20 ; 	
				
				$table = new SLFramework_Table(0, $maxnb, true, true) ;
				$table->title(array(__('Title of the file', $this->pluginID),__('Description of the file', $this->pluginID), __('File in used', $this->pluginID))) ; 
				
				// We order the posts page according to the choice of the user
				$order = " ORDER BY " ; 
				if ($table->current_ordercolumn()==1) {
					$order .= "description" ;  
				} else { 
					$order .= "titre" ;  
				}				
				if ($table->current_orderdir()=="DESC") {
					$order .= " DESC" ;  
				} else { 
					$order .= " ASC" ;  
				}
				
				$nb = $wpdb->get_var("SELECT COUNT(*) FROM ".$this->table_name." WHERE url!='' AND (titre like '%".str_replace("'","",$table->current_filter())."%' OR description like '%".str_replace("'","",$table->current_filter())."%')".$order) ; 
				$table->set_nb_all_Items($nb) ; 
				
				$limit = "" ; 
				if ($nb>$maxnb) {
					$limit =  " LIMIT ".(($table->current_page()-1)*$maxnb).",".$maxnb ; 
				}

				$results = $wpdb->get_results("SELECT * FROM ".$this->table_name." WHERE url!='' AND (titre like '%".str_replace("'","",$table->current_filter())."%' OR description like '%".str_replace("'","",$table->current_filter())."%')".$order.$limit) ; 
				
				$ligne=0 ; 
				foreach ($results as $r) {
					$ligne++ ; 
					if ($r->titre=="") {
						$r->titre = $r->url ; 
					}
					$all_id = explode(',',$r->attach_used_in) ; 
					$post_used = "" ; 
					foreach ($all_id as $ai) {
						if ($ai!="") {
							$tit_pos = get_the_title($ai); 
							if ($tit_pos=="") {
								$tit_pos = __("No title", $this->pluginID) ; 
							}
							if (get_edit_post_link($ai)!="") {
								$post_used .= "<p><a href=\"".(get_permalink($ai))."\">".$tit_pos."</a> <span style='font-size:75%'>(<a href=\"".(get_edit_post_link($ai))."\">".__('Edit', $this->pluginID)."</a>)</span></p>" ; 
							} else {
								$post_used .= "<p><a href=\"".(get_permalink($ai))."\">".$tit_pos."</a></p>" ; 
							}
						}
					}
					if ($post_used == "") {
						$post_used = "<p>".__("(Not used)", $this->pluginID)."</p>" ; 
					}
					if ($r->id==0) {
						$cel1 = new adminCell("<p><b><a href=\"".($r->url)."\">".$r->titre."</a></b> </p>") ; 		
					} else {
						if (get_edit_post_link($r->id)!="") {
							$cel1 = new adminCell("<p><b><a href=\"".($r->url)."\">".$r->titre."</a></b> <span style='font-size:75%'>(<a href=\"".(get_edit_post_link($r->id))."\">".__('Edit', $this->pluginID)."</a>)</span></p>") ; 		
						} else {
							$cel1 = new adminCell("<p><b><a href=\"".($r->url)."\">".$r->titre."</a></b></p>") ; 		
						}
					}
					$cel2 = new adminCell("<p><em>".$r->description."</em></p>") ; 				
					$cel3 = new adminCell($post_used) ; 				
				
					$table->add_line(array($cel1, $cel2, $cel3), $ligne) ; 
				}
				echo $table->flush() ; 

			$tabs->add_tab(__('All links',  $this->pluginID), ob_get_clean()) ; 

			ob_start() ; 
			
				$maxnb = 20 ; 	
				
				$table = new SLFramework_Table(0, $maxnb, true, true) ;
				$table->title(array(__('Title of the image', $this->pluginID),__('Image', $this->pluginID),__('Metadata', $this->pluginID), __('Image in...', $this->pluginID))) ; 
				
				// We order the posts page according to the choice of the user
				$order = " ORDER BY " ; 
				if ($table->current_ordercolumn()==1) {
					$order .= "description" ;  
				} else { 
					$order .= "titre" ;  
				}				
				if ($table->current_orderdir()=="DESC") {
					$order .= " DESC" ;  
				} else { 
					$order .= " ASC" ;  
				}
				
				$limit_to_img = "(url LIKE '%.png' OR url LIKE '%.jpg' OR url LIKE '%.bmp' OR url LIKE '%.gif')" ; 
				
				$nb = $wpdb->get_var("SELECT COUNT(*) FROM ".$this->table_name." WHERE ".$limit_to_img." AND (titre like '%".str_replace("'","",$table->current_filter())."%' OR description like '%".str_replace("'","",$table->current_filter())."%')".$order) ; 
				$table->set_nb_all_Items($nb) ; 
				
				$limit = "" ; 
				if ($nb>$maxnb) {
					$limit =  " LIMIT ".(($table->current_page()-1)*$maxnb).",".$maxnb ; 
				}

				$results = $wpdb->get_results("SELECT * FROM ".$this->table_name." WHERE ".$limit_to_img." AND (titre like '%".str_replace("'","",$table->current_filter())."%' )".$order.$limit) ; 
				
				$ligne=0 ; 
				foreach ($results as $r) {
					$ligne++ ; 
					if ($r->titre=="") {
						$r->titre = $r->url ; 
					}
					$all_id = explode(',',$r->attach_used_in) ; 
					$post_used = "" ; 
					foreach ($all_id as $ai) {
						if ($ai!="") {
							$tit_pos = get_the_title($ai); 
							if ($tit_pos=="") {
								$tit_pos = __("No title", $this->pluginID) ; 
							}
							if (get_edit_post_link($ai)!="") {
								$post_used .= "<p><a href=\"".(get_permalink($ai))."\">".$tit_pos."</a> <span style='font-size:75%'>(<a href=\"".(get_edit_post_link($ai))."\">".__('Edit', $this->pluginID)."</a>)</span></p>" ; 
							} else {
								$post_used .= "<p><a href=\"".(get_permalink($ai))."\">".$tit_pos."</a></p>" ; 
							}
						}
					}
					if ($post_used == "") {
						$post_used = "<p>".__("(Not used)", $this->pluginID)."</p>" ; 
					}
					if ($r->id==0) {
						$cel1 = new adminCell("<p><b><a href=\"".($r->url)."\">".$r->titre."</a></b> </p>") ; 		
					} else {
						if (get_edit_post_link($r->id)!="") {
							$cel1 = new adminCell("<p><b><a href=\"".($r->url)."\">".$r->titre."</a></b> <span style='font-size:75%'>(<a href=\"".(get_edit_post_link($r->id))."\">".__('Edit', $this->pluginID)."</a>)</span></p>") ; 		
						} else {
							$cel1 = new adminCell("<p><b><a href=\"".($r->url)."\">".$r->titre."</a></b></p>") ; 		
						}
					}
					$cel2 = new adminCell("<p>".wp_get_attachment_image( $r->id, "thumbnail")."</p>") ;
					 
					$meta = wp_get_attachment_metadata($r->id)	;
					$meta_toprint = "" ; 
					if (($meta!==false)&&(isset($meta['width']))) {
						$meta_toprint .= "<p>".sprintf(__("Dimension: %s", $this->pluginID), $meta['width']."x".$meta['height'])."</p>" ; 
						$meta_toprint .= "<p>".__("Sizes:", $this->pluginID)."</p>" ; 
						foreach ($meta['sizes'] as $nms => $ms) {
							$meta_toprint .= "<p style='padding-left:20px'>".$nms.": ".$ms['width']."x".$ms['height']."</p>" ; 
						}
						$meta_toprint .= "<p>".__("Meta data:", $this->pluginID)."</p>" ; 
						foreach ($meta['image_meta'] as $nim => $im) {
							if (($im!="")&&($im!=0)) {
								if ($nim=="created_timestamp") {
									$meta_toprint .= "<p style='padding-left:20px'>".$nim.": ".date_i18n( get_option( 'date_format' ), $im)."</p>" ; 
								} else {
									$meta_toprint .= "<p style='padding-left:20px'>".$nim.": ".$im."</p>" ; 
								}
							}
						}
					}
					$cel3 = new adminCell($meta_toprint) ; 				
					$cel4 = new adminCell($post_used) ; 				
				
					$table->add_line(array($cel1, $cel2, $cel3, $cel4), $ligne) ; 
				}
				echo $table->flush() ; 
				
				echo "<div id='table_regenerate'></div>" ; 
				
				echo "<p>" ; 
				echo "<img id='wait_regenerateAH' src='".WP_PLUGIN_URL."/".str_replace(basename(__FILE__),"",plugin_basename( __FILE__))."core/img/ajax-loader.gif' style='display: none;'>" ; 
				echo "<input type='button' id='forceRegenerateAH' class='button-primary validButton' onClick='forceRegenerateAH()'  value='". __('Regenerate the Thumbnails',$this->pluginID)."' />" ; 
				echo "<script>jQuery('#forceRegenerateAH').removeAttr('disabled');</script>" ; 
				echo " <input type='button' id='stopRegenerateAH' class='button validButton' onClick='stopRegenerateAH()'  value='". __('Stop Regeneration',$this->pluginID)."' />" ; 
				echo "<script>jQuery('#stopRegenerateAH').attr('disabled', 'disabled');</script>" ; 
				echo "</p>" ; 

			
			$tabs->add_tab(__('Images',  $this->pluginID), ob_get_clean()) ; 
			
			ob_start() ; 

				$params = new SLFramework_Parameters($this, "tab-parameters") ; 
				$params->add_title(__('Analysis of',  $this->pluginID)) ; 
				$params->add_param('type_page', __('Type of page to be analysed:',  $this->pluginID)) ; 
				
				$params->add_title(__('Appearence',  $this->pluginID)) ; 
				$params->add_param('titre', __('Default title:',  $this->pluginID)) ; 
				$params->add_param('html', __('The HTML displayed for the shortcode:',  $this->pluginID)) ; 
				$params->add_comment(__("The default value is:",  $this->pluginID)) ; 
				$params->add_comment_default_value('html') ; 
				$params->add_param('html_entry', __('The HTML displayed for each entry of the list:',  $this->pluginID)) ; 
				$params->add_comment(__("The default value is:",  $this->pluginID)) ; 
				$params->add_comment_default_value('html_entry') ; 
				$params->add_param('css', __('The CSS used for the shortcode:',  $this->pluginID)) ; 
				$params->add_comment(__("The default value is:",  $this->pluginID)) ; 
				$params->add_comment_default_value('css') ; 
				
				$params->add_title(__('Advanced',  $this->pluginID)) ; 
				$params->add_param('max_page_to_check', __('Max number of post to be checked when an analysis is forced:',  $this->pluginID)) ; 
				$params->add_param('between_two_requests', __('Number of minutes between two background check:',  $this->pluginID)) ; 
				
				$params->flush() ; 
				
			$tabs->add_tab(__('Parameters',  $this->pluginID), ob_get_clean() , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_param.png") ; 	
			
			// HOW To
			ob_start() ;
				echo "<p>".__("There is two different ways to analyze the attachments:", $this->pluginID)."</p>" ; 
				echo "<ul style='list-style-type: disc;padding-left:40px;'>" ; 
					echo "<li><p>".__("an automatic process (namely background process):", $this->pluginID)."</p></li>" ; 
						echo "<ul style='list-style-type: circle;padding-left:40px;'>" ; 
							echo "<li><p>".__("Every time a user visits a page of the frontside of your website, a verification of the attachments related to this page is performed;", $this->pluginID)."</p></li>" ; 
							echo "<li><p>".__("In order to limit the load of the website, you may configure in the parameter tab the minimum number of minutes bewteen to background check;", $this->pluginID)."</p></li>" ; 
							echo "<li><p>".__("Note that if you have very few visits, the complete check of all attachments may be quite long.", $this->pluginID)."</p></li>" ; 
						echo "</ul>" ;
					echo "<li><p>".__("a forced process:", $this->pluginID)."</p></li>" ; 
						echo "<ul style='list-style-type: circle;padding-left:40px;'>" ; 
							echo "<li><p>".__("The button that triggers this forced process may be found in the Attachments issues tabs;", $this->pluginID)."</p></li>" ; 
							echo "<li><p>".__("You have to stay on that page for processing all attachments: if you go on another page (or if you reload the page), the process will be stopped.", $this->pluginID)."</p></li>" ; 
						echo "</ul>" ;				
				echo "</ul>" ; 
			$howto1 = new SLFramework_Box (__("How the attachments are checked?", $this->pluginID), ob_get_clean()) ; 
			ob_start() ;
				echo "<p>".__("Some attachments may be listed in the Attachments issues tab but you are sure that the attachments are used somewhere.", $this->pluginID)."</p>" ; 
				echo "<p>".__("This could happen if the attachments are not used in a page or a post but, for instance, in your theme or in a plugin.", $this->pluginID)."</p>" ; 
				echo "<p>".__("If so, just ignore the warning with the appropriate link.", $this->pluginID)."</p>" ; 
			$howto2 = new SLFramework_Box (__("False positive?", $this->pluginID), ob_get_clean()) ; 
			ob_start() ;
				echo "<p>".__("This plugin enables the supervision of your attachements.", $this->pluginID)."</p>" ; 
				echo "<p>".__("You may supervize your attachements, detect duplicates files with different names, detect unused files...", $this->pluginID)."</p>" ; 
			$howto3 = new SLFramework_Box (__("Purpose of that plugin", $this->pluginID), ob_get_clean()) ; 
			ob_start() ;
				echo "<p>".sprintf(__("To add the list of used attachments in the post/page or in the child posts/pages, you may used a shortcode like %s.", $this->pluginID), "<code>[attach child=1 only_admin=1 title='Title you want' extension='pdf,doc,png']</code>")."</p>" ; 
				echo "<p>".__("A button that add this code is available in the post/page editor.", $this->pluginID)."</p>" ; 
				echo "<ul style='list-style-type: disc;padding-left:40px;'>" ; 
					echo "<li><p>".sprintf(__("%s: set this option to 1 if you want to display all attachments used in this page and in all child pages, otherwise set this option to 0.", $this->pluginID), "<code>child</code>")."</p></li>" ; 
					echo "<li><p>".sprintf(__("%s: set this option to 1 if you want to display the list only for the admin (this can be used for configuring the plugin without any impact on the users).", $this->pluginID), "<code>only_admin</code>")."</p></li>" ; 
					echo "<li><p>".sprintf(__("%s: list the attachments extension you want to list.", $this->pluginID), "<code>extension</code>")."</p></li>" ; 
				echo "</ul>" ; 
			$howto4 = new SLFramework_Box (__("Create a list of used attachments", $this->pluginID), ob_get_clean()) ; 
			ob_start() ; 
				 echo $howto3->flush() ; 
				 echo $howto4->flush() ; 
				 echo $howto1->flush() ; 
				 echo $howto2->flush() ; 
			$tabs->add_tab(__('How To',  $this->pluginID), ob_get_clean() , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_how.png") ; 	

			$frmk = new coreSLframework() ;  
			if (((is_multisite())&&($blog_id == 1))||(!is_multisite())||($frmk->get_param('global_allow_translation_by_blogs'))) {
				ob_start() ; 
					$plugin = str_replace("/","",str_replace(basename(__FILE__),"",plugin_basename( __FILE__))) ; 
					$trans = new SLFramework_Translation($this->pluginID, $plugin) ; 
					$trans->enable_translation() ; 
				$tabs->add_tab(__('Manage translations',  $this->pluginID), ob_get_clean() , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_trad.png") ; 	
			}

			ob_start() ; 
				$plugin = str_replace("/","",str_replace(basename(__FILE__),"",plugin_basename( __FILE__))) ; 
				$trans = new SLFramework_Feedback($plugin, $this->pluginID) ; 
				$trans->enable_feedback() ; 
			$tabs->add_tab(__('Give feedback',  $this->pluginID), ob_get_clean() , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_mail.png") ; 	
			
			ob_start() ; 
				// A list of plugin slug to be excluded
				$exlude = array('wp-pirate-search') ; 
				// Replace sedLex by your own author name
				$trans = new SLFramework_OtherPlugins("sedLex", $exlude) ; 
				$trans->list_plugins() ; 
			$tabs->add_tab(__('Other plugins',  $this->pluginID), ob_get_clean() , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_plug.png") ; 	
			
			echo $tabs->flush() ; 
			
			
			// Before this comment, you may modify whatever you want
			//===============================================================================================
			?>
			<?php echo $this->signature ; ?>
		</div>
		<?php
	}
	
	/** ====================================================================================================================================================
	* Shortcode to display attachments
	* 
	* @return void
	*/

	function displayAttachments( $_atts, $text ) {
		global $post ;
		global $wpdb ; 
				
		extract( shortcode_atts( array(
			'title' => $this->get_param('titre'),
			'extension' => '',
			'only_admin' => false,
			'child' => false,
			'zip' => false
		), $_atts ) );
		
		if (($only_admin)||($only_admin===1)||($only_admin=="1")) {
			if (!current_user_can('manage_options' )) {
				return "" ; 
			}
		}
		
		$pid = array($post->ID) ; 
		if (($child)||($child===1)||($child=="1")) {
			$pid = array_merge(array($post->ID), $this->get_posts_children($post->ID)) ; 
		}
		$out = "" ; 
		
		// On cherche le lien vers des PJ de ce 
		$res = $wpdb->get_results("SELECT id,titre,description,url,attach_used_in FROM ".$this->table_name." WHERE id!=0 ORDER BY titre") ;
		foreach ( $res as $r ) {
			$array_used_in = explode(",", $r->attach_used_in) ; 
			$already_display = false ; 
			foreach($array_used_in as $aiu) {
				if ((!$already_display)&&(in_array($aiu, $pid))) {
					if ($extension==""){
						$admin = "" ; 
						if (current_user_can('manage_options' )) {
							if (get_edit_post_link($r->id)!="") {
								$admin = " <span style='font-size:75%'>(<a href='".get_edit_post_link($r->id)."'>".__("Edit",$this->pluginID)."</a>)</span>" ; 
							} else {
								$admin = "" ; 
							}
						}
						if ($r->titre=="") {
							$r->titre = $r->url ; 
						}
						$out .= str_replace("%description%",$r->description,str_replace("%link%","<a href='".$r->url."'>".$r->titre."</a>".$admin, $this->get_param('html_entry'))) ; 
						$already_display = true ; 
					} else {
						$ext = explode(",",$extension) ; 
						$found = false ; 
						foreach ($ext as $e) {
							// Si ca se termine comme l'extension, alors on a trouvé
							if (($e!="")&&(mb_substr($r->url,-mb_strlen($e))==$e)) {
								$found = true ; 
							}
						}
						if ($found) {
							if (current_user_can( 'manage_options' )) {
								if (get_edit_post_link($r->id)!="") {
									$admin = " <span style='font-size:75%'>(<a href='".get_edit_post_link($r->id)."'>".__("Edit",$this->pluginID)."</a>)</p>" ; 
								} else {
									$admin = "" ; 
								}
							}
							if ($r->titre=="") {
								$r->titre = $r->url ; 
							}
							$out .= str_replace("%description%",$r->description,str_replace("%link%","<a href='".$r->url."'>".$r->titre."</a>".$admin, $this->get_param('html_entry'))) ; 
							$already_display = true ; 
						}
					}
				}
			}
		}
		
		if ($out!=""){
			return str_replace("%title%",$title,str_replace("%list%",$out,$this->get_param('html'))); 
		} else {
			return "" ; 
		}
	}
	
	function get_posts_children($parent_id){
	    $children = array();
	    // grab the posts children
	    $posts = get_children( array( 
			'post_type' => explode(",",$this->get_param('type_page')), 
			'post_parent' => $parent_id 
		));
	    // now grab the grand children
	    foreach( $posts as $child ){
			$children = array_merge($children, array($child->ID), $this->get_posts_children($child->ID)) ; 
	    }
	    return $children;
	}
	
	/** ====================================================================================================================================================
	* Callback for processing
	*
	* @return void
	*/
	
	function checkIfAttachmentsHandlerNeeded() {
		echo $this->check_post_for_attachments() ; 
		die() ; 
	}
	
	/** ====================================================================================================================================================
	* Check a post
	* 
	* @return void
	*/
	
	function check_post_for_attachments($id='rand',$tempo=true){
		global $post ; 
		global $wpdb;
		
		// We check that the last request has not been emitted since a too short period of time
		$now = time() ; 
		if ($tempo) {
			$last = $this->get_param('last_request') ; 
			if ($now-$last<=60*$this->get_param('between_two_requests')) {
				return sprintf(__('Only %s seconds since the last computation: please wait!', $this->pluginID), ($now-$last)."" ) ; 
			}
		}
		$this->set_param('last_request',$now); 
				
		if ($id=='rand') {
			// Exclude post that has already been analyzed
			$exclude_ids = array() ; 
			$res = $wpdb->get_results("SELECT DISTINCT id_post FROM ".$this->table_name) ;
			foreach ( $res as $r ) {
				$exclude_ids[] = $r->id_post;
			}
			
			$args = array(
				'posts_per_page'     => 1,
				'post_type'       => explode(",",$this->get_param('type_page')),
				'post__not_in'    => $exclude_ids, 
				'orderby'         => 'rand',
				'post_status'     => 'publish' 
			);
		} else {
			// We get the post
			$args = array(
				'p'=>$id,
				'post_type' => 'any'
			) ; 
		}
		
		$myQuery = new WP_Query( $args ); 

		//Looping through the posts
		$post_temp = array() ; 
		while ( $myQuery->have_posts() ) {
			$myQuery->the_post();
			$post_temp[] = $post;
		}
		
		// Si c'est empty alors que l'on a tout verifié et si c'est rand, alors on prend le premier de $exclude_ids
		if ((empty($post_temp))&&($id=='rand')&&(isset($exclude_ids[0]))) {
			// We get the post
			$args = array(
				'p'=>$exclude_ids[0],
				'post_type' => 'any'
			) ; 
			$myQuery = new WP_Query( $args ); 

			//Looping through the posts
			$post_temp = array() ; 
			while ( $myQuery->have_posts() ) {
				$myQuery->the_post();
				$post_temp[] = $post;
			}
		}
		
		// Reset Post Data
		wp_reset_postdata();
		
		$return_string = "" ; 
		
		// On regarde les attachments attaché à ce post
        foreach($post_temp as $p){	
        
        	$return_string .= $p->ID."-" ; 
         
        	// ON INDIQUE QUE CE POST A ETE ANALYSE
        	// ====================================	
	       	$nb_res = $wpdb->get_var("SELECT COUNT(*) FROM ".$this->table_name." WHERE id_post='".$p->ID."'") ;
			if ($nb_res==0) {
				$wpdb->query("INSERT INTO ".$this->table_name." (id,id_post,path,url,is_exist,description,legende,titre,sha1,attach_used_in) VALUES ('0', '".$p->ID."', '', '', '0', '', '', '', '', '')") ;
			}

        	// ON SUPPRIME TOUS LES ENDROITS OU CE POST EST MENTIONNE
        	// =========================================================
        	$result_to_clean = $wpdb->get_results("SELECT url,attach_used_in FROM ".$this->table_name." WHERE url!=''") ;
			foreach ($result_to_clean as $rtc) {
				$list_id_to_clean = explode(",",$rtc->attach_used_in) ; 
        		if (in_array($p->ID,$list_id_to_clean)) {
        			$new_list_id_to_clean = array() ; 
        			foreach ($list_id_to_clean as $litc) {
        				if ($litc != $p->ID) {
        					$new_list_id_to_clean[] = $litc ; 
        				}
        			}
        			$new_list_id_to_clean = implode(",",$new_list_id_to_clean) ; 
					if ($new_list_id_to_clean!="") {
						$wpdb->query("UPDATE ".$this->table_name." SET attach_used_in='".$new_list_id_to_clean."'  WHERE url='".$rtc->url."'") ;
					} else {
						$wpdb->query("DELETE FROM ".$this->table_name." WHERE url='".$rtc->url."'") ;
					}
        		}
        	}
        
        	// ON ANALYSE LES PIECES JOINTES
        	// ====================================	
        	$attachments = get_posts( array(
            	'post_type' => 'attachment',
                'posts_per_page' => -1,
                'post_parent' => $p->ID
        	) );
        	
            foreach ($attachments as $a) {
            	$file_path = get_attached_file($a->ID); 
            	$return_string .= $a->ID."," ; 
         
				if(file_exists($file_path)){
                	$resul_file = array('exist'=>true, 'path'=>$file_path, 'id_post'=>$p->ID, 'description'=>$a->post_content, 'titre'=>$a->post_title, 'legende'=>$a->post_excerpt, 'url'=>wp_get_attachment_url($a->ID), 'sha1'=>sha1_file($file_path)) ; 
                } else {
                	$resul_file = array('exist'=>false, 'path'=>$file_path, 'id_post'=>$p->ID, 'description'=>$a->post_content, 'titre'=>$a->post_title, 'legende'=>$a->post_excerpt, 'url'=>wp_get_attachment_url($a->ID), 'sha1'=>"?") ; 
                }
 				$nb_res = $wpdb->get_var("SELECT COUNT(*) FROM ".$this->table_name." WHERE url='".addslashes($resul_file['url'])."'") ;
				if ($nb_res>0) {
					$wpdb->query("UPDATE ".$this->table_name." SET id='".$a->ID."',is_exist='".$resul_file['exist']."',description='".addslashes($resul_file['description'])."',titre='".addslashes($resul_file['titre'])."',legende='".addslashes($resul_file['legende'])."',sha1='".$resul_file['sha1']."'  WHERE url='".addslashes($resul_file['url'])."'") ;
				} else {
					$wpdb->query("INSERT INTO ".$this->table_name." (id,id_post,path,url,is_exist,description,legende,titre,sha1,attach_used_in) VALUES ('".$a->ID."', '0', '".addslashes($resul_file['path'])."', '".addslashes($resul_file['url'])."', '".$resul_file['exist']."', '".addslashes($resul_file['description'])."', '".addslashes($resul_file['legende'])."', '".addslashes($resul_file['titre'])."', '".$resul_file['sha1']."', '')") ;
				}
            }
            
            // ON ANALYSE LES PIECES JOINTES NON-ATTACHE A AUCUN POST
        	// ===========================================================
        	$attachments = get_posts( array(
            	'post_type' => 'attachment',
                'posts_per_page' => -1,
                'post_parent' => 0
        	) );
        	
            foreach ($attachments as $a) {
            	$file_path = get_attached_file($a->ID); 
            	$return_string .= $a->ID."," ; 
         
				if(file_exists($file_path)){
                	$resul_file = array('exist'=>true, 'path'=>$file_path, 'id_post'=>0, 'description'=>$a->post_content, 'titre'=>$a->post_title, 'legende'=>$a->post_excerpt, 'url'=>wp_get_attachment_url($a->ID), 'sha1'=>sha1_file($file_path)) ; 
                } else {
                	$resul_file = array('exist'=>false, 'path'=>$file_path, 'id_post'=>0, 'description'=>$a->post_content, 'titre'=>$a->post_title, 'legende'=>$a->post_excerpt, 'url'=>wp_get_attachment_url($a->ID), 'sha1'=>"?") ; 
                }
 				$nb_res = $wpdb->get_var("SELECT COUNT(*) FROM ".$this->table_name." WHERE url='".addslashes($resul_file['url'])."'") ;
				if ($nb_res>0) {
					$wpdb->query("UPDATE ".$this->table_name." SET id='".$a->ID."',is_exist='".$resul_file['exist']."',description='".addslashes($resul_file['description'])."',titre='".addslashes($resul_file['titre'])."',legende='".addslashes($resul_file['legende'])."',sha1='".$resul_file['sha1']."'  WHERE url='".addslashes($resul_file['url'])."'") ;
				} else {
					$wpdb->query("INSERT INTO ".$this->table_name." (id,id_post,path,url,is_exist,description,legende,titre,sha1,attach_used_in) VALUES ('".$a->ID."', '0', '".addslashes($resul_file['path'])."', '".addslashes($resul_file['url'])."', '".$resul_file['exist']."', '".addslashes($resul_file['description'])."', '".addslashes($resul_file['legende'])."', '".addslashes($resul_file['titre'])."', '".$resul_file['sha1']."', '')") ;
				}
            }

          	// ON ANALYSE L'IMAGE MISE EN AVANT (FEATURED IMAGE)
        	// =======================================================
        	
        	$post_thumbnail_id = get_post_thumbnail_id( $p->ID );

        	$attachments = get_posts( array(
            	'post_type' => 'attachment',
                'posts_per_page' => -1,
                'post__in' => array($post_thumbnail_id)
        	) );
        	
            foreach ($attachments as $a) {
				$file_path = get_attached_file($a->ID); 
				$return_string .= $a->ID."," ; 
					 
				if(file_exists($file_path)){
					$resul_file = array('exist'=>true, 'path'=>$file_path, 'id_post'=>$p->ID, 'description'=>$a->post_content, 'titre'=>$a->post_title, 'legende'=>$a->post_excerpt, 'url'=>wp_get_attachment_url($a->ID), 'sha1'=>sha1_file($file_path)) ; 
				} else {
					$resul_file = array('exist'=>false, 'path'=>$file_path, 'id_post'=>$p->ID, 'description'=>$a->post_content, 'titre'=>$a->post_title, 'legende'=>$a->post_excerpt, 'url'=>wp_get_attachment_url($a->ID), 'sha1'=>"?") ; 
				}
								
				$nb_res = $wpdb->get_var("SELECT COUNT(*) FROM ".$this->table_name." WHERE url='".addslashes($resul_file['url'])."'") ;
				if ($nb_res>0) {
					$ui = $wpdb->get_var("SELECT attach_used_in FROM ".$this->table_name." WHERE url='".addslashes($resul_file['url'])."' LIMIT 1") ;
					$ui = explode(",", $ui) ; 
					$already_in_ui = false ; 
					foreach ($ui as $uie) {
						if ($uie==$p->ID) {
							$already_in_ui = true ;
						}
					}
					if (!$already_in_ui) {
						$ui[] = $p->ID ; 
					}
					$wpdb->query("UPDATE ".$this->table_name." SET id='".$a->ID."',attach_used_in='".implode(",",$ui)."',is_exist='".$resul_file['exist']."',description='".addslashes($resul_file['description'])."',titre='".addslashes($resul_file['titre'])."',legende='".addslashes($resul_file['legende'])."',sha1='".$resul_file['sha1']."'  WHERE url='".addslashes($resul_file['url'])."'") ;
				} else {
					$wpdb->query("INSERT INTO ".$this->table_name." (id,id_post,path,url,is_exist,description,legende,titre,sha1,attach_used_in) VALUES ('".$a->ID."', '0', '".addslashes($resul_file['path'])."', '".addslashes($resul_file['url'])."', '".$resul_file['exist']."', '".addslashes($resul_file['description'])."', '".addslashes($resul_file['legende'])."', '".addslashes($resul_file['titre'])."', '".$resul_file['sha1']."', '".$p->ID."')") ;
				}
			}
            
            $return_string .= "-" ; 
            
        	// ON ANALYSE LE TEXTE
        	// ====================================	
            
            $text = $p->post_content ; 
            
            // Detect all url
			// 0 - All a tag
			// 1 - the URL
			$match = array() ; 
			$match2 = array() ; 
			$match3 = array() ; 
			$match4 = array() ; 
			$match5 = array() ; 
			$match6 = array() ; 
			preg_match_all('/<a [^>]*href=["]([^>"]*)["][^>]*>/u', $text, $match, PREG_SET_ORDER) ;
			preg_match_all('/<a [^>]*href=[\']([^>\']*)[\'][^>]*>/u', $text, $match2, PREG_SET_ORDER) ;
			preg_match_all('/<img [^>]*src=["]([^>"]*)["][^>]*>/u', $text, $match3, PREG_SET_ORDER) ;
			preg_match_all('/<img [^>]*src=[\']([^>\']*)[\'][^>]*>/u', $text, $match4, PREG_SET_ORDER) ;
			preg_match_all('/\[video [^\]]*mp4=["]([^\]"]*)["][^\]]*\]/u', $text, $match5, PREG_SET_ORDER) ;
 			preg_match_all('/\[video [^\]]*mp4=[\']([^\]\']*)[\'][^\]]*\]/u', $text, $match6, PREG_SET_ORDER) ;

			$match = array_merge($match, $match2, $match3, $match4, $match5, $match6) ; 
			
			foreach ($match as $m) {
				// Check if this url is a declared attachment
				$upload_dir = wp_upload_dir();
				$upload_url = $upload_dir['baseurl'];
				$upload_dir = $upload_dir['basedir'];
				$url = $m[1] ; 

				// si le lien est en fait un lien vers la page de l'attachement
				// on le converti vers le liebn vers la pièce jointe et non vers la page contenant l'attachment
				$id_attach = url_to_postid($url) ; 
				if ($id_attach!=0) {
				
					$attachments = get_posts( array(
						'post__in' => array($id_attach)
					) );
					
					foreach ($attachments as $a) {
						if ($a->type=='attachment') {
							$url = wp_get_attachment_url( $id_attach );
						}
					}
				}
				
				$return_string .= $url ; 
				
				if (strpos($url,$upload_url)!==false) {
					
					// Si le lien est en fait une miniature on modifie le lien vers la vrai url
					$new_url = preg_replace( '/-\d+x\d+(?=\.(jpg|jpeg|png|gif)$)/i', '', $url);
					if ($url!=$new_url){
						$new_path = $upload_dir.str_replace($upload_url, "", $new_url) ;
						if (file_exists($new_path)){
							$url = $new_url ; 
						}
					}
					
				
					$path = $upload_dir.str_replace($upload_url, "", $url) ; 
					if (file_exists($path)){
						$exist = 1 ; 
						$sha1 = sha1_file($path) ; 
					} else {
						$exist = 0 ; 
						$sha1 = "?" ; 
					}
					
					$nb_res = $wpdb->get_var("SELECT COUNT(*) FROM ".$this->table_name." WHERE  url='".addslashes($url)."'") ;
					if ($nb_res==0) {                
						$wpdb->query("INSERT INTO ".$this->table_name." (id,id_post,path,url,is_exist,description,legende,titre,sha1,attach_used_in) VALUES ('0', '0', '".addslashes($path)."', '".addslashes($url)."', '".$exist."', '', '', '', '".$sha1."', '".$p->ID."')") ;
						$return_string .= "(new)," ; 
					} else {
						$used_in = $wpdb->get_var("SELECT attach_used_in FROM ".$this->table_name." WHERE  url='".addslashes($url)."'") ;
						$array_used_in = explode(",",$used_in) ; 
						if ($used_in!="") {
							$used_in = $used_in."," ; 
						}
						if (!in_array($p->ID, $array_used_in)) {
							$wpdb->query("UPDATE ".$this->table_name." SET attach_used_in='".$used_in.$p->ID."'  WHERE url='".addslashes($url)."'") ;
						} else {
							
						}
						
						$return_string .= "(update)," ; 
					}
					
				}
			}
			
            // Detect gallery
			// 0 - All a tag
			// 1 - list of ids
			$match = array() ; 
			$match2 = array() ; 

			preg_match_all('/\[gallery [^\]]*ids=["]([^\]"]*)["][^\]]*\]/u', $text, $match, PREG_SET_ORDER) ;
 			preg_match_all('/\[gallery [^\]]*ids=[\']([^\]\']*)[\'][^\]]*\]/u', $text, $match2, PREG_SET_ORDER) ;
 			
			$match = array_merge($match, $match2) ; 
			
			foreach ($match as $m) {
				
				$list_ids = explode(",",$m[1]) ; 
				
				foreach ($list_ids as $li) {
	            	$file_path = get_attached_file($li); 
					$a = get_post($li) ; 
					
					if ($a!=null) {
						if(file_exists($file_path)){
		                	$resul_file = array('exist'=>true, 'path'=>$file_path, 'id_post'=>0, 'description'=>$a->post_content, 'titre'=>$a->post_title, 'legende'=>$a->post_excerpt, 'url'=>wp_get_attachment_url($li), 'sha1'=>sha1_file($file_path)) ; 
		                } else {
		                	$resul_file = array('exist'=>false, 'path'=>$file_path, 'id_post'=>0, 'description'=>$a->post_content, 'titre'=>$a->post_title, 'legende'=>$a->post_excerpt, 'url'=>wp_get_attachment_url($li), 'sha1'=>"?") ; 
		                }
		 				$nb_res = $wpdb->get_var("SELECT COUNT(*) FROM ".$this->table_name." WHERE url='".addslashes($resul_file['url'])."'") ;
						if ($nb_res>0) {
							$used_in = $wpdb->get_var("SELECT attach_used_in FROM ".$this->table_name." WHERE  url='".addslashes($resul_file['url'])."'") ;
							$array_used_in = explode(",",$used_in) ; 
							if ($used_in!="") {
								$used_in  = $used_in."," ; 
							}
							if (!in_array($p->ID, $array_used_in)) {
								$wpdb->query("UPDATE ".$this->table_name." SET attach_used_in='".$used_in.$p->ID."'  WHERE url='".addslashes($resul_file['url'])."'") ;	
							} 
						} else {
							$wpdb->query("INSERT INTO ".$this->table_name." (id,id_post,path,url,is_exist,description,legende,titre,sha1,attach_used_in) VALUES ('".$li."', '0', '".addslashes($resul_file['path'])."', '".addslashes($resul_file['url'])."', '".$resul_file['exist']."', '".addslashes($resul_file['description'])."', '".addslashes($resul_file['legende'])."', '".addslashes($resul_file['titre'])."', '".$resul_file['sha1']."', '".$p->ID."')") ;
						}
					} 
				}
			}
        }
        
        // ON SUPPRIME LES LIENS FANTOMES
    	// =========================================================
		$wpdb->query("DELETE FROM ".$this->table_name." WHERE url!='' AND id=0 AND attach_used_in='' AND is_exist=0") ;

        if ($return_string == "") {
        	return __("No post/article to check.", $this->pluginID) ; 
        } else {
        	return $return_string ; 
        }

    }
    	
	/** ====================================================================================================================================================
	* Display table
	* 
	* @return void
	*/
	function displayTable($ignored=0) {
		global $wpdb, $post ; 
		$maxnb = 20 ; 
			
		$args = array(
			'numberposts'     => -1,
			'post_type'       => explode(",",$this->get_param('type_page')),
			'fields'        => 'ids',
			'nopaging' 		=> true,
			'post_status'     => 'publish' 
		);
		
		$myQuery = new WP_Query( $args ); 
		
		//Looping through the posts
		$total = 0 ; 
		$list_posts = array() ; 
		if ( $myQuery->have_posts() ) {
			foreach( $myQuery->posts as $id ) {
        		$list_posts[] = $id ; 
        		$total ++ ; 
   			}
		}
		

		// Reset Post Data
		wp_reset_postdata();
		
		$verified = $wpdb->get_var("SELECT COUNT(*) FROM ".$this->table_name." WHERE id_post!=0") ;
		$nb_links = $wpdb->get_var("SELECT COUNT(*) FROM ".$this->table_name." WHERE url!=''") ;
		
		if (($total!=$verified)&&($ignored!=1)) {
			echo "<p style='font-weight:bold;color:#8F0000;'>".sprintf(__('%s posts/articles have been analysed while there is %s posts/articles to be analysed (%s links found).', $this->pluginID), "<b>".$verified."</b>", "<b>".$total."</b>" , "<b>".$nb_links."</b>" )."</p>"  ; 
			echo "<p style='font-weight:bold;color:#8F0000;'>".__('If all posts/articles have not been analysed, the results cannot be displayed ... Thus, wait or force the verification!', $this->pluginID)."</p>"  ; 
		
			// DELETE post that should not be here
			if ($total<$verified) {
				$ids = $wpdb->get_results("SELECT id_post FROM ".$this->table_name." WHERE id_post!=0") ;
				$all_id = array() ; 
				foreach($ids as $i) {
					if (in_array($i->id_post, $list_posts)) {
						// nothing to do  
					} else {
						$wpdb->query("DELETE FROM ".$this->table_name." WHERE id_post='".$i->id_post."';") ; 
						echo "DELETE FROM ".$this->table_name." WHERE id_post='".$i->id_post."';" ; 
					}					
				}
			}
		} else {
			if ($ignored!=1) {
				echo "<p>".sprintf(__('Each of the %s posts/articles has been analysed (%s links found).', $this->pluginID), "<b>".$total."</b>" , "<b>".$nb_links."</b>" )."</p>" ; 
			} 
			
			// DETECT MISSING FILES ON HARD DISK
			//----------------------------------------
			echo "<h3>".__('Missing files', $this->pluginID)."</h3>" ; 
			
			$res = $wpdb->get_results("SELECT id_media,id,titre,url,attach_used_in,sha1 FROM ".$this->table_name." WHERE url!='' AND is_exist=0 AND ignored=".$ignored) ;
			
			if (count($res)>0) {
				if ($ignored!=1) {
					echo "<p>".__('The following files have been deleted on the hard disk but is still registered by Wordpress.', $this->pluginID)."</p>" ; 
					echo "<p>".__('You have to modify the post/page and thus to remove this file from the media manager.', $this->pluginID)."</p>" ; 
				}
				
				$table = new SLFramework_Table() ;
				$table->title(array(__('Title of the file', $this->pluginID), __('This file is used in', $this->pluginID))) ; 
	 
				$ligne = 0 ; 
				foreach ($res as $r) {
					$ligne++ ; 
					if ($r->titre=="") {
						$r->titre = $r->url ; 
					}
					$all_id = explode(',',$r->attach_used_in) ; 
					$post_used = "" ; 
					foreach ($all_id as $ai) {
						if ($ai!="") {
							$tit_pos = get_the_title($ai); 
							if ($tit_pos=="") {
								$tit_pos = __("No title", $this->pluginID) ; 
							}
							if (get_edit_post_link($ai)!="") {
								$post_used .= "<p><a href=\"".(get_permalink($ai))."\">".$tit_pos."</a> <span style='font-size:75%'>(<a href=\"".(get_edit_post_link($ai))."\">".__('Edit', $this->pluginID)."</a>)</span></p>" ; 
							} else {
								$post_used .= "<p><a href=\"".(get_permalink($ai))."\">".$tit_pos."</a></p>" ; 
							}
						}
					}
					if ($post_used == "") {
						$post_used = "<p>".__("(Not used)", $this->pluginID)."</p>" ; 
					}
					if ($r->id!=0){
						if (get_edit_post_link($r->id)!="") {
							$cel1 = new adminCell("<p><b><a href=\"".($r->url)."\">".$r->titre."</a></b> <span style='font-size:75%'>(<a href=\"".(get_edit_post_link($r->id))."\">".__('Edit', $this->pluginID)."</a>)</span></p>") ; 	
						} else {
							$cel1 = new adminCell("<p><b><a href=\"".($r->url)."\">".$r->titre."</a></b></p>") ; 
						}
					} else {
						$cel1 = new adminCell("<p><b><a href=\"".($r->url)."\">".$r->titre."</a></b></p>") ; 
					}	
					$cel2 = new adminCell($post_used) ; 	
					
					if ($ignored!=1) {
						$cel1->add_action(__("Ignore", $this->pluginID), "ignoreAttachmentIssue('".$r->id_media."','".$r->sha1."', '".addslashes(__("Do you want to ignore this entry?", $this->pluginID))."')") ; 
					} else {
						$cel1->add_action(__("Do not Ignore", $this->pluginID), "doNotignoreAttachmentIssue('".$r->id_media."', '".$r->sha1."', '".addslashes(__("Do you want not to ignore this entry?", $this->pluginID))."')") ; 
					}
				
					$table->add_line(array($cel1, $cel2), $r->id_media) ; 
				}
				echo $table->flush() ;
			} else {
				if ($ignored!=1) {
					echo "<p>".__('No missing files.', $this->pluginID)."</p>" ; 
				} else {
					echo "<p>".__('No missing files ignored.', $this->pluginID)."</p>" ; 
				}
			}
			
			// DETECT DUPLICATE FILES
			// -------------------------------------
			echo "<h3>".__('Duplicate files', $this->pluginID)."</h3>" ; 
			
			$res = $wpdb->get_results( "SELECT * FROM ".$this->table_name." INNER JOIN (SELECT sha1 FROM ".$this->table_name." WHERE sha1!='?' AND sha1!='' AND ignored=".$ignored." GROUP BY sha1 HAVING count(id) > 1) dup ON ".$this->table_name.".sha1 = dup.sha1 ORDER BY ".$this->table_name.".sha1") ; 
			
			if (count($res)>0) {
				if ($ignored!=1) {
					echo "<p>".__('The following files are duplicated on the hard disk.', $this->pluginID)."</p>" ; 
					echo "<p>".__('You have to modify the post/page and thus to remove the extras files from the media manager.', $this->pluginID)."</p>" ; 
				}
				$ligne = 0 ; 
				$old_sha1 = "" ; 
				$nb_entr = 0 ; 
				foreach ($res as $r) {

					if ($r->sha1!=$old_sha1) {
						if ($nb_entr!=0) {
							echo $table->flush() ;
							echo "<p>&nbsp;</p>" ; 
						}
						$table = new SLFramework_Table() ;
						$table->title(array(__('Title of the file', $this->pluginID), __('This file is used in', $this->pluginID))) ;
						$nb_entr = 0 ; 
						$old_sha1 = $r->sha1 ; 
					} 
					
					$nb_entr ++ ;
	 
					$ligne++ ; 
					if ($r->titre=="") {
						$r->titre = $r->url ; 
					}
					$all_id = explode(',',$r->attach_used_in) ; 
					$post_used = "" ; 
					foreach ($all_id as $ai) {
						if ($ai!="") {
							$tit_pos = get_the_title($ai); 
							if ($tit_pos=="") {
								$tit_pos = __("No title", $this->pluginID) ; 
							}
							if (get_edit_post_link($ai)!="") {
								$post_used .= "<p><a href=\"".(get_permalink($ai))."\">".$tit_pos."</a> <span style='font-size:75%'>(<a href=\"".(get_edit_post_link($ai))."\">".__('Edit', $this->pluginID)."</a>)</span></p>" ; 
							} else {
								$post_used .= "<p><a href=\"".(get_permalink($ai))."\">".$tit_pos."</a></p>" ; 
							}
						}
					}
					if ($post_used == "") {
						$post_used = "<p>".__("(Not used)", $this->pluginID)."</p>" ; 
					}
					if ($r->id!=0){
						if (get_edit_post_link($r->id)!="") {
							$cel1 = new adminCell("<p><b><a href=\"".($r->url)."\">".$r->titre."</a></b> <span style='font-size:75%'>(<a href=\"".(get_edit_post_link($r->id))."\">".__('Edit', $this->pluginID)."</a>)</span></p>") ; 	
						} else {
							$cel1 = new adminCell("<p><b><a href=\"".($r->url)."\">".$r->titre."</a></b></p>") ; 
						}
					} else {
						$cel1 = new adminCell("<p><b><a href=\"".($r->url)."\">".$r->titre."</a></b></p>") ; 
					}					
					$cel2 = new adminCell($post_used) ; 	
					
					if ($ignored!=1) {
						$cel1->add_action(__("Ignore", $this->pluginID), "ignoreAttachmentIssue('".$r->id_media."','".$r->sha1."', '".addslashes(__("Do you want to ignore this entry?", $this->pluginID))."')") ; 
					} else {
						$cel1->add_action(__("Do not Ignore", $this->pluginID), "doNotignoreAttachmentIssue('".$r->id_media."', '".$r->sha1."', '".addslashes(__("Do you want not to ignore this entry?", $this->pluginID))."')") ; 
					}
			
				
					$table->add_line(array($cel1, $cel2), $r->id_media) ; 
				}
				
				if ($nb_entr!=0) {
					echo $table->flush() ;
				}
			} else {
				if ($ignored!=1) {
					echo "<p>".__('No duplicate files.', $this->pluginID)."</p>" ; 
				} else {
					echo "<p>".__('No duplicate files ignored.', $this->pluginID)."</p>" ; 
				}
			}
			
			// DETECT FILES THAT ARE NOT USED IN ANY PAGES		
			//-------------------------------------------------------
			echo "<h3>".__('Files in media manager by not used', $this->pluginID)."</h3>" ; 
	
			$res = $wpdb->get_results("SELECT id,titre,url,sha1,id_media FROM ".$this->table_name." WHERE attach_used_in='' AND id!=0 AND ignored=".$ignored) ;
			
			if (count($res)>0) {
			
				if ($ignored!=1) {
					echo "<p>".__('The following files exists on the disk but does not seems to be used ...', $this->pluginID)."</p>" ; 
					echo "<p>".__('You may remove them from the media manager.', $this->pluginID)."</p>" ; 
				}
				
				$table = new SLFramework_Table() ;
				$table->title(array(__('Title of the file', $this->pluginID))) ; 
	 
				$ligne = 0 ; 
				foreach ($res as $r) {
					$ligne++ ; 
					if ($r->titre=="") {
						$r->titre = $r->url ; 
					}
					if (get_edit_post_link($r->id)!="") {
						$cel1 = new adminCell("<p><b><a href=\"".($r->url)."\">".$r->titre."</a></b> <span style='font-size:75%'>(<a href=\"".(get_edit_post_link($r->id))."\">".__('Edit', $this->pluginID)."</a>)</span></p>") ; 	
					} else {
						$cel1 = new adminCell("<p><b><a href=\"".($r->url)."\">".$r->titre."</a></b></p>") ; 	
					}
					
					if ($ignored!=1) {
						$cel1->add_action(__("Ignore", $this->pluginID), "ignoreAttachmentIssue('".$r->id_media."','".$r->sha1."', '".addslashes(__("Do you want to ignore this entry?", $this->pluginID))."')") ; 
					} else {
						$cel1->add_action(__("Do not Ignore", $this->pluginID), "doNotignoreAttachmentIssue('".$r->id_media."', '".$r->sha1."', '".addslashes(__("Do you want not to ignore this entry?", $this->pluginID))."')") ; 
					}

				
					$table->add_line(array($cel1), $r->id_media) ; 
				}
				echo $table->flush() ;
			} else {

				if ($ignored!=1) {
					echo "<p>".__('No unused files.', $this->pluginID)."</p>" ; 				
				} else {
					echo "<p>".__('No unused files ignored.', $this->pluginID)."</p>" ; 
				}
			}
				
			// DETECT FILES THAT ARE ON THE DISK BUT NOT HANDLED BY THE MEDIA MANAGER	
		}
	}
	
	/** ====================================================================================================================================================
	* Ajax Callback to force attachment anaysis
	* @return void
	*/
	function forceAnalysisAttachments() {
		global $post, $wpdb ; 
		
		// Initialize the list
		$at = $this->get_param('list_post_id_to_check') ; 
		if (empty($at)) {
			// We get the post 
			$args = array(
				'posts_per_page'     => intval($this->get_param('max_page_to_check')),
				'orderby'         => 'rand',
				'post_type'       => explode(",",$this->get_param('type_page')),
				'fields'        => 'ids',
				'post_status'     => 'publish' 
			);
			
			$myQuery = new WP_Query( $args ); 

			//Looping through the posts
			$post_temp = array() ; 
			$total = 0 ; 
			if ( $myQuery->have_posts() ) {
				foreach( $myQuery->posts as $id ) {
					$post_temp[] = $id ; 
   		     		$total ++ ; 
   				}
			}

			// Reset Post Data
			wp_reset_postdata();
			$this->set_param('list_post_id_to_check', $post_temp) ; 
			$this->set_param('nb_post_to_check', count($post_temp)) ; 
		}
		
		// Get the first post of the list
		$post_temp = $this->get_param('list_post_id_to_check') ; 
		$pid = array_pop($post_temp) ; 
		$this->set_param('list_post_id_to_check', $post_temp) ; 
		
		$this->check_post_for_attachments($pid,false) ; 
		
		$this->displayTable() ; 	
		
		$at = $this->get_param('list_post_id_to_check') ; 
		if (empty($at)) {
			$this->set_param('nb_post_to_check', 0) ; 
		} else {
			$pc = floor(100*($this->get_param('nb_post_to_check')-count($this->get_param('list_post_id_to_check')))/$this->get_param('nb_post_to_check')) ; 
			
			$pb = new SLFramework_Progressbar(500, 20, $pc, "PROGRESS - ".($this->get_param('nb_post_to_check')-count($this->get_param('list_post_id_to_check')))." / ".$this->get_param('nb_post_to_check')) ; 
			echo "<br>" ; 
			$pb->flush() ;	
		}
		
		die() ; 
	}
	
	/** ====================================================================================================================================================
	* Ajax Callback to stop analysis
	* @return void
	*/
	function stopAnalysisAttachments() {
		global $post, $wpdb ; 
		
		$this->set_param('list_post_id_to_check', array()) ; 
		$this->set_param('nb_post_to_check', 0) ; 

		echo "OK" ; 
		
		die() ; 
	}
	
	/** ====================================================================================================================================================
	* Ajax Callback to force attachment regeneration
	* @return void
	*/
	function forceRegenerateAttachments() {
		global $post, $wpdb ; 
		
		// Initialize the list
		$at = $this->get_param('list_img_id_to_regenerate') ; 
		if (empty($at)) {
			// We get the image 
			$images = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%' ORDER BY ID DESC" ) ;
			$li = array() ; 
			foreach ($images as $i) {
				$li[] = $i->ID ; 
			}
			
			$this->set_param('list_img_id_to_regenerate', $li) ; 
			$this->set_param('nb_img_to_regenerate', count($li)) ; 
			$this->set_param('info_img_to_regenerate', "") ; 
		}
		
		// Get the first img of the list
		$img_temp = $this->get_param('list_img_id_to_regenerate') ; 
		$iid = array_pop($img_temp) ; 
		$this->set_param('list_img_id_to_regenerate', $img_temp) ; 
		
		// REGENERATE 
		$fullsizepath = get_attached_file( $iid );
		if ( false === $fullsizepath || ! file_exists( $fullsizepath ) ) {
			$this->set_param('info_img_to_regenerate', $this->get_param('info_img_to_regenerate').sprintf(__("The file %s does not exist for the image with id %s.", $this->pluginID),"<code>".esc_html( $fullsizepath )."</code>","<code>".esc_html( $iid )."</code>")."<br>") ; 
		} else {
			$metadata = wp_generate_attachment_metadata($iid, $fullsizepath) ; 
			if ( is_wp_error( $metadata ) ) {
				$this->set_param('info_img_to_regenerate', $this->get_param('info_img_to_regenerate').sprintf(__("The regeneration for %s fails with the following message: %s.", $this->pluginID),"<code>".esc_html( $fullsizepath )."</code>","<code>".esc_html( $metadata->get_error_message() )."</code>")."<br>") ; 
			} else if ( empty( $metadata ) ) {
				$this->set_param('info_img_to_regenerate', $this->get_param('info_img_to_regenerate').sprintf(__("The regeneration for %s fails with an unknown error.", $this->pluginID),"<code>".esc_html( $fullsizepath )."</code>")."<br>") ; 
			} else {
				$this->set_param('info_img_to_regenerate', $this->get_param('info_img_to_regenerate').sprintf(__("The file %s is regenerated.", $this->pluginID),"<code>".esc_html( $fullsizepath )."</code>")."<br>") ; 
			}
		}
		
		echo $this->get_param('info_img_to_regenerate') ; 
				
		$at = $this->get_param('list_img_id_to_regenerate') ; 
		if (empty($at)) {
			$this->set_param('nb_img_to_regenerate', 0) ; 
		} else {
			$pc = floor(100*($this->get_param('nb_img_to_regenerate')-count($this->get_param('list_img_id_to_regenerate')))/$this->get_param('nb_img_to_regenerate')) ; 
			
			$pb = new SLFramework_Progressbar(500, 20, $pc, "PROGRESS - ".($this->get_param('nb_img_to_regenerate')-count($this->get_param('list_img_id_to_regenerate')))." / ".$this->get_param('nb_img_to_regenerate')) ; 
			echo "<br>" ; 
			$pb->flush() ;	
		}
		
		die() ; 
	}
	
	/** ====================================================================================================================================================
	* Ajax Callback to stop regeneration
	* @return void
	*/
	function stopRegenerateAttachments() {
		global $post, $wpdb ; 
		
		$this->set_param('list_img_id_to_regenerate', array()) ; 
		$this->set_param('nb_img_to_regenerate', 0) ; 

		echo "OK" ; 
		
		die() ; 
	}
	
	/** ====================================================================================================================================================
	* Ajax Callback to stop analysis
	* @return void
	*/
	function cleanAnalysisAttachments() {
		global $wpdb ; 
		
		$this->set_param('list_post_id_to_check', array()) ; 
		$this->set_param('nb_post_to_check', 0) ; 

		$wpdb->query("DELETE FROM ".$this->table_name." WHERE ignored!=1") ; 
		
		echo "OK" ; 
		
		die() ; 
	}
	
	/** =====================================================================================================
	* Callback to ignore
	*
	* @return string
	*/
	
	function ignoreAttachmentIssue(){
		global $wpdb ;
		$sha1 = $_POST['sha1'] ; 
		$id_media = $_POST['id'] ;
		 
		if ($sha1!="?") {
			if (FALSE===$wpdb->query("UPDATE ".$this->table_name." SET ignored = TRUE WHERE sha1='".$sha1."'")) {
				echo "error" ; 
			} else {
				echo "ok" ; 
			}
		} else {
			if (FALSE===$wpdb->query("UPDATE ".$this->table_name." SET ignored = TRUE WHERE id_media='".$id_media."'")) {
				echo "error" ; 
			} else {
				echo "ok" ; 
			}		
		}
		die() ; 
	}
	
	/** =====================================================================================================
	* Callback to ignore
	*
	* @return string
	*/
	
	function doNotignoreAttachmentIssue(){
		global $wpdb ;
		$sha1 = $_POST['sha1'] ; 
		$id_media = $_POST['id'] ; 
		if ($sha1!="?") {
			if (FALSE===$wpdb->query("UPDATE ".$this->table_name." SET ignored = FALSE WHERE sha1='".$sha1."'")) {
				echo "error" ; 
			} else {
				echo "ok" ; 
			}
		} else {
			if (FALSE===$wpdb->query("UPDATE ".$this->table_name." SET ignored = FALSE WHERE id_media='".$id_media."'")) {
				echo "error" ; 
			} else {
				echo "ok" ; 
			}		
		}

		die() ; 
	}
	
	/** ====================================================================================================================================================
	* Ajax Callback to save post
	* @return void
	*/
	function whenPostIsSaved($id) {
		global $wpdb ; 
		
		// We delete the entries if the id is a post
		$wpdb->query("DELETE FROM ".$this->table_name." WHERE id_post='".$id."'") ; 
		
		// We delete all possible used in the attach if the id is a post
		$res = $wpdb->get_results("SELECT id,attach_used_in FROM ".$this->table_name."") ;
		foreach ( $res as $r ) {
			$array_used_in = explode(",", $r->attach_used_in) ; 
			if (in_array($id, $array_used_in)) {
				// We have to delete it from the array
				$new_array_used_in = array() ; 
				foreach ($array_used_in as $a) {
					if ($a!=$id) {
						$new_array_used_in[] = $a ; 
					}
				}
				$wpdb->query("UPDATE ".$this->table_name." SET attach_used_in='".implode(',', $new_array_used_in)."'  WHERE id='".($r->id)."'") ;
			}
		}
		// On reteste immediatement le post
		$this->check_post_for_attachments($id, false) ; 
	}
	
	/** ====================================================================================================================================================
	* Ajax Callback when the attachments is saved/deleted
	* @return void
	*/
	function whenAttachmentIsSaved($id) {
		global $wpdb ; 
		
		$res = $wpdb->get_var("SELECT attach_used_in FROM ".$this->table_name." WHERE id='".$id."'") ;
		
		// On nettoie les posts dans lesquels cet attachement est utilisé
		if ($res!=null) {
			$res = explode(",",$res) ; 
			foreach ($res as $r) {
				$wpdb->query("DELETE FROM ".$this->table_name." WHERE id_post='".$r."'") ; 
			}
		} 
		
		// On nettoie cet attachement 
		$wpdb->query("DELETE FROM ".$this->table_name." WHERE id='".$id."'") ; 
	}
}



$attachments_handler = attachments_handler::getInstance();

?>