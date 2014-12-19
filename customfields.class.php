<?php

/**
 * Display custom fields on a wordpress content type.
 *
 * @author Raphael Goncalves <raphael@couleur-citron.com>
 */
class customfields {
    
    /**
     * All fields data stored here
     * 
     * @var array 
     */
    private $aFields;
    
    /**
     * Count of file's input displayed
     * 
     * @var integer 
     */
    private $nbFile;
    
    /**
     * False if our current form don't have an editor,
     * true if we displayed an Editor
     * I use it for display the javascript scrip
     * 
     * @var boolean 
     */
    private $displayEditor = false;
    
    /**
     * initialize all custom fields in a single array.
     * The array must be like :
     * 
     * array( 'post' => //content type
            array( //all array fields
                array(
                    'label'=> 'Image 1', //label
                    'desc'  => 'Image de fond du slider', //description
                    'id'    => $prefix.'file_1', //id field
                    'type'  => 'file' // field's type
                ),
                array(
                    'label'=> 'Image 2',
                    'desc'  => 'Image découpée pour l\'effet parralaxe',
                    'id'    => $prefix.'file_2',
                    'type'  => 'file'
                ),
                'title' => "Images" // metabox' title
            )
     * );
     * 
     * @param type $arrayFields
     */
    function __construct( $arrayFields ){
        
        $this->aFields = $arrayFields;
        
        add_action( 'add_meta_boxes', array( $this, 'addMetaBox' ) );
        add_action( 'save_post', array( $this, 'save' ) );
        
    }
    
    /**
     * Display Meta box in the content type's edit page.
     * The callback function is generated automatically, but you can
     * override it by creating a function in your functions.php file :
     * show_custom_meta_box_{ content_type }
     */
    public function addMetaBox(){
        
        foreach( $this->aFields as $content_type => $aFields ){
            
            $title = __( 'News fields' );
            
            if( isset( $aFields[ 'title' ] ) ){
                $title = $aFields[ 'title' ];
            }
            
            $callback = array( $this, 'show_custom_meta_box' );
            if(function_exists( 'show_custom_meta_box_' . $content_type ) ){
                $callback = 'show_custom_meta_box_' . $content_type;
            }
            
            
            add_meta_box(
                        'custom_meta_box', // $id
                        $title, // $title 
                        $callback,//'show_custom_meta_box_link', // $callback
                        $content_type, // $page
                        'normal', // $context
                        'high',
                        array(
                            $content_type
                        )
                    ); // $priority
            
        }
        
    }
    
    /**
     * display fields in our custom meta box area
     * 
     * @param object $post current post object
     * @return boolean true
     */
    public function show_custom_meta_box( $post ){

        if( !isset( $this->aFields[ $post->post_type ] ) || empty( $this->aFields[ $post->post_type ] ) ){
            return;
        }
        
        $custom_meta_fields = $this->aFields[ $post->post_type ]; // ATTENTION, à vérifier $post
        
        
        // Use nonce for verification
        echo '<input type="hidden" name="custom_meta_box_nonce" value="'.wp_create_nonce(basename(__FILE__)).'" />';

        // Begin the field table and loop
        echo '<table class="form-table">';
        foreach ($custom_meta_fields as $k => $field) {
            if( !is_array( $field ) ) continue;
            // get value of this field if it exists for this post
            $meta = get_post_meta($post->ID, $field['id'], true);
            // begin a table row with
            echo '<tr>
                    <th><label for="'.$field['id'].'">'.$field['label'].'</label></th>
                    <td>';
                    switch($field['type']) {
                        // text
                        case 'text':
                            $this->textFields($field, $meta);
                        break;

                        // textarea
                        case 'textarea':
                            $this->textareaFields($field, $meta);
                        break;

                    // textarea
                        case 'textarea_wysiwyg':
                            $this->textareaFields($field, $meta, true);
                        break;


                        // checkbox
                        case 'checkbox':
                            $this->checkBoxesFields($field, $meta);
                        break;


                        // select
                        case 'select':
                            $this->selectFields($field, $meta);
                        break;

                        // select
                        case 'file':
                            $this->fileFields($field, $post, $k);
                        break;


                    } //end switch
            echo '</td></tr>';
        } // end foreach
        echo '</table>'; // end table
        
        return true;
        
    }
    
    /**
     * Save all custom fields adding by our methods.
     * 
     * @param integer $post_id the current post_id for saving meta data
     * @return boolean true
     */
    public function save( $post_id ){
        
        /* Can users be here? */
        if(strtolower($_POST['post_type']) === 'page') {
            if(!current_user_can('edit_page', $post_id)) {
                return $post_id;
            }
        }
        
        $post = get_post( $post_id );
        
        if( !isset( $this->aFields[ $post->post_type ] ) ) return;
        
        foreach( $this->aFields[ $post->post_type ] as $k => $field ){
            if( $field[ 'type' ] != 'file' ){
                
                if( isset( $_POST[ $field[ 'id' ] ] ) )
                    update_post_meta( $post_id, $field[ 'id' ], $_POST[ $field[ 'id' ] ] );
                
            } else {
                $this->uploadFile( $post_id, $field );
            }
            
        }
        
        return true;
        
    }
    
    /**
     * Upload all files adding by our methods.
     * 
     * We can validate every field by custom functions (returning true or false)
     * 
     * function validate_{ $field[ 'id' ] }( $_FILES ){
     *      // do something
     *      return true/false;
     * }
     * 
     * @param integer $post_id the current post_id for saving meta data
     * @return boolean
     */
    private function uploadFile( $post_id, $field ){
        
        /* Can users be here? */
        if(strtolower($_POST['post_type']) === 'page') {
            if(!current_user_can('edit_page', $post_id)) {
                return $post_id;
            }
        }
        else {
            if(!current_user_can('edit_post', $post_id)) {
                return $post_id;
            }
        }
        
        $k = 1;
        while( isset( $_FILES[ 'document_file_' . $k ]  ) ){
            if( !empty( $_FILES[ 'document_file_' . $k ] ) ){
                
                
    
                $attach_key = 'document_file_id_' . $k;
                
                if( $_POST['custom_delete_' .$k ] == 'on' ){
                    $existing_download = (int)get_post_meta($post_id, $attach_key, true);
                    
                    
                    if(is_numeric($existing_download)) {
                        wp_delete_attachment($existing_download);
                        update_post_meta($post_id, $attach_key, 0);
                    }
                }
                
                if(function_exists( 'validate_' . $field[ 'id' ] ) ){
                    $func_validation = 'validate_' . $field[ 'id' ];
                    $validation = $func_validation( $_FILES[ 'document_file_' . $k ] );
                } else {
                    $validation = true;
                }
                
                if( $validation ){
                
                    $file   = $_FILES[ 'document_file_' . $k ];
                    $upload = wp_handle_upload($file, array('test_form' => false));

                    if(!isset($upload['error']) && isset($upload['file'])) {
                        $filetype   = wp_check_filetype(basename($upload['file']), null);
                        $title      = $file['name'];
                        $ext        = strrchr($title, '.');
                        $title      = ($ext !== false) ? substr($title, 0, -strlen($ext)) : $title;

                        $wp_upload_dir = wp_upload_dir();

                        $attachment = array(
                            'guid'           => $wp_upload_dir['url'] . '/' . basename($upload['file']),
                            'post_mime_type'    => $wp_filetype['type'],
                            'post_title'        => addslashes($title),
                            'post_content'      => '',
                            'post_status'       => 'inherit',
                            'post_parent'       => $post_id
                        );


                        $attach_id  = wp_insert_attachment($attachment, $upload['file']);
                        $existing_download = (int) get_post_meta($post_id, $attach_key, true);

                        if(is_numeric($existing_download)) {
                            wp_delete_attachment($existing_download);
                            //var_dump("delete ".$post->ID);
                        }

                        update_post_meta($post_id, $attach_key, $attach_id);


                        // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
                        require_once( ABSPATH . 'wp-admin/includes/image.php' );

                        // Generate the metadata for the attachment, and update the database record.
                        $attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
                        wp_update_attachment_metadata( $attach_id, $attach_data );

                        //var_dump("update ".$post->ID); die();
                    }
                }
            }
            $k++;
        }
        
        return true;
    }
    
    /**
     * Display a text input for our custom form.
     * 
     * @param array $field field data
     * @param string $meta
     */
    private function textFields( $field, $meta ){
        echo '<input type="text" name="'.$field['id'].'" id="'.$field['id'].'" value="'.$meta.'" size="30" />
                                <br /><span class="description">'.$field['desc'].'</span>';
    }
    
    /**
     * Display a textarea input for our custom form.
     * You also be able to display a custom wysiwyg editor
     * 
     * @param array $field
     * @param string $meta
     * @param boolean $wysiwyg true if you want to display a wysiwyg editor
     */
    private function textareaFields( $field, $meta, $wysiwyg = false ){
        echo '<textarea name="'.$field['id'].'" id="'.$field['id'].'" cols="60" rows="4"';
        
        if( $wysiwyg ){
            if( $this->displayEditor === false ){
                //die( 'false' );
                add_action('admin_print_footer_scripts',array( $this, 'add_editor_tinymce' ),99);
            }
            
            echo ' class="customEditor"';
            $this->displayEditor = true;
        }
        
        echo '>'.$meta.'</textarea>
                                <br /><span class="description">'.$field['desc'].'</span>';
        
        
        
        //rajouter le script JS.
    }
    
    /**
     * Display checkbox input for our custom form.
     * 
     * @param array $field
     * @param string $meta
     */
    private function checkBoxesFields( $field, $meta ){
        echo '<input type="checkbox" name="'.$field['id'].'" id="'.$field['id'].'" ',$meta ? '' : '','/>
                                <label for="'.$field['id'].'">'.$field['desc'].'</label>';
    }
    
    /**
     * Display select input for our custom form.
     * For this input, you have to specify in your field array options
     * an "options" entry like this :
     * $field = array(
     *      'options' => array( 
     *                      array( 
     *                          'value' => $value, 
     *                          'label' => $label
     *                      ), 
     *                      array( 
     *                          'value' => $value, 
     *                          'label' => $label ), 
     *                      etc... 
     *                      )
     * );
     * 
     * @param array $field
     * @param string $meta
     */
    private function selectFields( $field, $meta ){
        echo '<select name="'.$field['id'].'" id="'.$field['id'].'">';
                            foreach ($field['options'] as $option) {
                                echo '<option', $meta == $option['value'] ? ' selected="selected"' : '', ' value="'.$option['value'].'">'.$option['label'].'</option>';
                            }
                            echo '</select><br /><span class="description">'.$field['desc'].'</span>';
    }
    
    /**
     * Display file input for our custom form.
     * 
     * @param array $field
     * @param object $post the current post object
     */
    private function fileFields( $field, $post ){
        $this->nbFile += 1;
        $k = $this->nbFile;
        $custom         = get_post_custom($post->ID);
        $download_id    = get_post_meta($post->ID, 'document_file_id_' . $k, true);

        echo '<p><label for="document_file_' . $k . '">' . __('Upload document') .' :</label><br />';
        echo '<input type="file" name="document_file_' . $k . '" id="document_file_' . $k . '" /></p>';
        echo '</p>';

        if(!empty($download_id) && $download_id != '0') {
            echo '<p><a href="' . wp_get_attachment_url($download_id) . '">
                ' . __('View document') . '</a> <br />';
            /*'custom_delete_' .$k*/
            echo '<input type="checkbox" name="custom_delete_' . $k . '" id="custom_delete_' . $k . '" /> <label for="custom_delete_' . $k . '">' . __( 'delete this file' ) . '</label>';
            
            echo '</p>';
        }
    }
    
    /**
     * initiate tinyMCE Editor for cutom fields
     * @return boolean
     */
    public function add_editor_tinymce(){
        ?><script type="text/javascript">
            jQuery(function($)
            {
                $(document).ready(function(){
                    var i=1;
                    $('textarea.customEditor ').each(function(e)
                    {
                            var id = $(this).attr('id');
                            if (!id)
                            {
                                id = 'customEditor-' + i++;
                                $(this).attr('id',id);
                            }

                            tinyMCE.execCommand('mceAddEditor', false, id);
                    });

                });
            });
        </script><?php

        return true;
    }
    
}
