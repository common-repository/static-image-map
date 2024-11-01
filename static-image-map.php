<?php

/* *
 * Plugin Name:       Static Image Map
 * Plugin URI:        https://fourmi-integree.com/wp-plugins/static-image-map
 * Description:       Static Image Map is an easy to use plugin to insert a static map on your site.
 * Text Domain:       static-image-map
 * Version:           0.5.0
 * License:           GPL2
 * */


class WPFIGMS
{
    const VERSION = '1.0.0';

    private static $name = "Static Image Map";
    private static $plugin_slug = "static-image-map"; 
    private static $text_domain = "static-image-map";
    private static $options = null;
    private static $upload_dir = null;
    private static $plugin_dir_name = null;
    private static $cache_folder = "gsm";

    
    public static $loaded = false;

    public static function init()
    {

        // do once
        SELF::$upload_dir = wp_upload_dir( null, false, false );
       
        // do once
        SELF::$plugin_dir_name = basename(dirname( __FILE__ ));

        add_action('admin_menu', [get_called_class(), 'add_settings_menu']);

        add_action('admin_init', [get_called_class(), 'init_settings_page']);

        add_action('plugins_loaded', [get_called_class(), 'load_textdomain']);

        add_shortcode('fi_smap', [get_called_class(), "insert_static_map_img"]);


        add_action("admin_enqueue_scripts", [get_called_class(), "admin_enqueue_scripts"]);

        
        register_activation_hook( __FILE__, [get_called_class(), "activate"]);

        register_deactivation_hook( __FILE__, [get_called_class(), "deactivation"]);

        register_uninstall_hook( __FILE__, [get_called_class(), "uninstall"]);

        SELF::$loaded = true;



        
        add_action('admin_notices',  [get_called_class(), "init_validation"]);
    }


    //
    // plugin setup #1
    public static function activate() 
    {
        if ( !file_exists( SELF::$upload_dir["basedir"] . "/". SELF::$cache_folder ."/" ) )
            mkdir(SELF::$upload_dir["basedir"] . "/" . SELF::$cache_folder . "/");
    }
    public static function deactivation() 
    {

    }
    public static function uninstall() 
    {
        delete_option( SELF::$plugin_slug.'_option' );
    }

    //
    // on plugins_loaded
    public static function load_textdomain()
    {
        $r = load_plugin_textdomain( SELF::$text_domain, false, SELF::$plugin_dir_name.'/languages' );
    }


    // validation folder writable
    public static function init_validation()
    {
        $screen = get_current_screen();
            
        if ($screen->id === 'settings_page_'.SELF::$plugin_slug) 
        {
		
            if ( ! is_writable( SELF::$upload_dir["basedir"] . "/" . SELF::$cache_folder ) )
            {
                $class = 'notice notice-error';
                $message =  __("Upload folder not writable", SELF::$text_domain);
                printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ); 
            }
        }
    }


    //
    // plugin setup #1 settings - gui
    public static function add_settings_menu() 
    {

        add_options_page(
            __( ucfirst(SELF::$name).' Settings', SELF::$text_domain),   // pg title
            __( ucfirst(SELF::$name), SELF::$text_domain),               // menu title
            'manage_options',                                            // capacity
            SELF::$plugin_slug,                                          // menu_slug
            [get_called_class(), 'create_settings_page'],                // callback
            null                                                         // position - int
        );

    }


    public static function create_settings_page() 
    {
        SELF::$options = get_option( SELF::$plugin_slug.'_option' );

    ?>
        <div class="wrap" id="<?php print SELF::$plugin_slug; ?>">
            
            <form method="post" action="options.php">
            <?php

                print  "<h1>" . SELF::$name ."</h1>";

                
                // print les champs de securité - Output nonce, action, and option_page 
                settings_fields( SELF::$plugin_slug.'_option_group' );


                // print les "settings_sections"                
                do_settings_sections( SELF::$plugin_slug.'_setting-pg' );

                // btn submit
                submit_button();
    ?>
            </form>
        </div>
        <?php
    }



    // helper pour tool tip
    private static function make_help_tag($id, $label, $help)
    {
        
        $label = __($label, SELF::$text_domain);
        $help = __($help, SELF::$text_domain);

        $str = <<<CODE
<div class='tt'><a title="{$help}"></a><label for="{$id}">{$label}</label></div>
CODE;

        return $str;
    }


    public static function init_settings_page()
    {
        register_setting(
            SELF::$plugin_slug.'_option_group',     // Option group
            SELF::$plugin_slug.'_option',           // Option name
            [get_called_class(), 'sanitize']        // call back Sanitize
        );


        // Section Carte
        $section_slug = SELF::$plugin_slug.'_section_img';
        add_settings_section(
            $section_slug,                             // ID
            __("Preview", SELF::$text_domain),         // Title - qui est affciher
            [get_called_class(), 'print_section'],     // callback 
            SELF::$plugin_slug.'_setting-pg'           // for page
        );  
        add_settings_field(
            'carte',                                
            // __("Map", SELF::$text_domain),       
            " ",
            [get_called_class() , 'carte_apercu'],  
            SELF::$plugin_slug .'_setting-pg',       
            $section_slug                         
        );      


        // Section parametres
        $section_slug = SELF::$plugin_slug.'_section_param';
        add_settings_section(
            $section_slug,        
            __("Settings", SELF::$text_domain),
            [get_called_class(), 'print_section'],
            SELF::$plugin_slug.'_setting-pg'      
        );  
        

        add_settings_field(
            'api_key', 
            SELF::make_help_tag("api_key", "Api key", "Api key: Google Maps Static API key is required. Follow the link at the right."),
            [get_called_class() , 'api_key_cb'], 
            SELF::$plugin_slug .'_setting-pg',
            $section_slug                  
            
        );      

        add_settings_field(
            'adr', 
            SELF::make_help_tag("adr", "Address", "Address: a unique addresses or a latitude,longitude value."),
            [get_called_class() , 'adr_cb'],   
            SELF::$plugin_slug .'_setting-pg', 
            $section_slug,
            ["label_for"=>"adr"]
        );      

           


        add_settings_field(
            'map_type', 
            SELF::make_help_tag("map_type", "Map type", "Map type: one of the four formats."),
            [get_called_class(), 'type_cb'],     // Callback
            SELF::$plugin_slug .'_setting-pg',   // for page
            $section_slug,                       // Section slug
            ["label_for"=>"map_type"]
        );

        
        
        $section_slug = SELF::$plugin_slug.'_section_dim';

        add_settings_section(
            $section_slug,                             // ID
            __('Dimension', SELF::$text_domain),       // Title 
            [get_called_class(), 'print_section'],     // callback 
            SELF::$plugin_slug.'_setting-pg'           // for page
        );  

        add_settings_field(
            'largeur', 
            SELF::make_help_tag("largeur", "Width", "Width: horizontal dimension of the map. Maximum value for 'Pay-As-You-Go' is 640."),
            [get_called_class(), 'largeur_cb'],   
            SELF::$plugin_slug .'_setting-pg',   
            $section_slug                 
        );

        add_settings_field(
            'hauteur', 
            SELF::make_help_tag("hauteur", "Height", "Height: vertical dimension of the map. Maximum value for 'Pay-As-You-Go' is 640."),
            [get_called_class(), 'hauteur_cb'],   
            SELF::$plugin_slug .'_setting-pg',   
            $section_slug                    
        );

        add_settings_field(
            'zoom', 
            SELF::make_help_tag("zoom", "Zoom level", "Zoom level: determines the magnification level of the map. The value is in between 0 and 21."),
            [get_called_class(), 'zoom_cb'], 
            SELF::$plugin_slug .'_setting-pg', 
            $section_slug,                    
            ["label_for"=>"zoom"]
        );

    }


   



    // 
    public static function sanitize( $input  )
    {
        $new_input = [];
        $message = false;

        

        if( !isset($input['api_key']) or empty($input['api_key']) )
        {
            $message = __( 'The api key is required', SELF::$text_domain);
        }
        else
        {
            $new_input['api_key'] = $input['api_key'];
        }
        if($message)
        {
            add_settings_error( 
                SELF::$plugin_slug.'_option_group',
                esc_attr( 'settings_updated' ),
                $message, 
                "error");
        }
        




        // drop down
        if( isset( $input['map_type'] ) )
            $new_input['map_type'] = $input['map_type'];


       


        $message = false;
        $input['adr'] = trim($input['adr']);
        if( !isset($input['adr']) or empty($input['adr']) )
        {
            $message = __('The civic address is required', SELF::$text_domain);
            // adresse civique est requise
        }
        else
        {
            $new_input['adr'] = $input['adr'];
        }
        if($message)
        {
            add_settings_error( 
                SELF::$plugin_slug.'_option_group',
                esc_attr( 'settings_updated' ),
                $message, 
                "error");
        }


        // entier positif
        $message = false;
        if( !isset($input['largeur']) or empty($input['largeur']) )
        {
            $message = __('Width is required', SELF::$text_domain);
        }
        elseif( !is_numeric( $input['largeur'] ))
        {
            $message = __( 'Width must be a positive integer', SELF::$text_domain);
        }
        elseif ( (int)$input['largeur'] < 0 ) 
        {
            $message = __( 'Width must be a positive integer', SELF::$text_domain);
        }
        $new_input['largeur'] = $input['largeur'];
        if($message)
        {
            add_settings_error( 
                SELF::$plugin_slug.'_option_group',
                esc_attr( 'settings_updated' ),
                $message, 
                "error");
        }
        
        // entier positif
        $message = false;
        if( !isset($input['hauteur']) or empty($input['hauteur']) )
        {
            $message = __( 'Height is required',  SELF::$text_domain);
        }
        elseif( !is_numeric( $input['hauteur'] ))
        {
            $message = __( 'Height must be a positive integer',  SELF::$text_domain);
        }
        elseif ( (int)$input['hauteur'] < 0 ) 
        {
            $message = __( 'Height must be a positive integer',  SELF::$text_domain);
        }
        $new_input['hauteur'] = $input['hauteur'];
        if($message)
        {
            add_settings_error( 
                SELF::$plugin_slug.'_option_group',
                esc_attr( 'settings_updated' ),
                $message, 
                "error");
        }

        $message = false;
        if( isset( $input['zoom'] ) )
        {
            if ( !is_numeric($input['zoom']) )
            {
                $message = __( 'The zoom level must be a positive number',  SELF::$text_domain);
                $input['zoom'] = 1;
            }
            $new_input['zoom'] = $input['zoom'];
        }
        else{
            $message = __( 'The zoom level is required', SELF::$text_domain);
        }
        if($message)
        {
            add_settings_error( 
                SELF::$plugin_slug.'_option_group',
                esc_attr( 'settings_updated' ),
                $message, 
                "error");
        }





        return $new_input;
    }


    public static function print_section()
    {
        // rien
    }
    

    private static function prn_dropdown_field($field_id, $values) 
    {
        $field_name = SELF::$plugin_slug."_option[{$field_id}]";
        $field_val = isset( SELF::$options[ $field_id ] ) ? esc_attr( SELF::$options[ $field_id ]) : '';
 
        echo "<select id='{$field_id}' name='{$field_name}'>";

        foreach($values as $item) 
        {
            $selected = ( $field_val == $item )  ? 'selected="selected"' : '';
            echo "<option value='$item' $selected>$item</option>";
        }

        echo "</select>";
    }

    public static function carte_apercu()
    {
        if ( ! is_writable( SELF::$upload_dir["basedir"] . "/" . SELF::$cache_folder ) )
        {
            return false;
        }

        $get = SELF::get_google_static_map();
        // get = true si y a eu une mise a jour  fichier


        $map_dir = SELF::make_map_dir();
        if ($map_dir)
        {
            if (file_exists($map_dir["path"]))
            {
                print <<<CODE
                <div class="apercu"><img src='{$map_dir["url"]}' alt='' style="border:1px solid #333;"></div>
                <p>
CODE;
                //  Parametres pour cette image ;
                // <code>{$map_dir["param"]}</code>
                

                $timezone   = wp_timezone();
                $date       = wp_date( "d F Y à H:i:s", filemtime($map_dir["path"]), $timezone);

                // Image créée
                print __("Image created: ",  SELF::$text_domain) . $date;

                print "</p><p>";
                
                print __( "See on google map: ", SELF::$text_domain) . SELF::get_google_map_url();

                print "</p>";
            }
            else
            {
                print __( "The image does not exist?",  SELF::$text_domain);
                print __( "Should be here: ", SELF::$text_domain) ;
                print $map_dir["path"] . ".";
            }
            
        }
        else
        {
            // Paramètres manquants pour générer une carte d'image!
            print __( 'Missing parameters to generate an image map!', SELF::$text_domain);
            
        }
         
    }

    public static function api_key_cb()
    {

        printf(
                '<input type="text" id="api_key" name="%s" value="%s" style="width:%s" />',
                SELF::$plugin_slug."_option" . "[api_key]",
                isset( SELF::$options['api_key'] ) ? esc_attr( SELF::$options['api_key']) : '',
                "80%"
            );


        $url_get_key = "https://developers.google.com/maps/documentation/maps-static/get-api-key";
        print "<a href='{$url_get_key }' target='_get_key'>" . __('Get API Key', SELF::$text_domain) . "<i class='api'></i></a>";
            
    }

    public static function type_cb()
    {
        $type_val = [
                "roadmap",
                "satellite",
                "terrain",
                "hybrid"
            ];

            SELF::prn_dropdown_field("map_type", $type_val);
    }

    public static function zoom_cb()
    {
        printf(
                '<input type="number" step="1" min="1" max="21" id="zoom" name="%s" value="%s" style="width:%s" />',
                SELF::$plugin_slug."_option" . "[zoom]",
                isset( SELF::$options['zoom'] ) ? esc_attr( SELF::$options['zoom']) : '14',
                "100%"
            );
    }


    public static function largeur_cb()
    {
        printf(
            '<input type="number" step="1" min="0" id="largeur" name="%s" value="%s" style="width:%s" class="small-text"/>',
            SELF::$plugin_slug."_option" . "[largeur]",
            isset( SELF::$options['largeur'] ) ? esc_attr( SELF::$options['largeur']) : '',
            "100%"
        );
    }

    public static function hauteur_cb()
    {
        printf(
            '<input type="number" step="1" min="0" id="hauteur" name="%s" value="%s" style="width:%s" class="small-text"/>',
            SELF::$plugin_slug."_option" . "[hauteur]",
            isset( SELF::$options['hauteur'] ) ? esc_attr( SELF::$options['hauteur']) : '',
            "100%"
        );
    }


    public static function adr_cb()
    {
        printf(
            '<input type="text"  id="adr" name="%s" value="%s" style="width:%s"/>',
            SELF::$plugin_slug."_option" . "[adr]",
            isset( SELF::$options['adr'] ) ? esc_attr( SELF::$options['adr']) : '',
            "100%"
        );
    }

    
    
 


    //
    // retour $cache_file = ["path"=>  , "url"=>  , "param" =>  "adr" =>   ] ou FALSE
    private static function make_map_dir()
    {
        
        // si pas init
        if (SELF::$options == NULL)
            SELF::$options = get_option( SELF::$plugin_slug.'_option' );

        $adr = isset(SELF::$options['adr']) ? SELF::$options['adr'] : FALSE;
        $zoom = isset(SELF::$options['zoom']) ? SELF::$options['zoom'] : FALSE;
        $largeur = isset(SELF::$options['largeur']) ? SELF::$options['largeur'] : FALSE;
        $hauteur = isset(SELF::$options['hauteur']) ? SELF::$options['hauteur'] : FALSE;
        $map_type = isset(SELF::$options['map_type']) ? SELF::$options['map_type'] : FALSE;

        $cache_file = FALSE;

        if ( $adr && $zoom && $map_type && $largeur && $hauteur )
        {
            $cache_file["adr"] = $adr;
            
            $adr_folder = base64_encode($adr);

            $rel_path = "/" . SELF::$cache_folder . "/" . $adr_folder;
            
            if ( !file_exists( SELF::$upload_dir["basedir"] .  $rel_path ) )
                mkdir(SELF::$upload_dir["basedir"] .  $rel_path );

            $img_parameters = "maptype=".$map_type."&zoom=".$zoom."&format=jpg&size=".$largeur."x".$hauteur;
            $cache_file["param"] = $img_parameters;

            $img_parameters = str_replace(["=","&","x","zoom","format","size","maptype"], "", $img_parameters);
            $filename = base64_encode(gzdeflate( $img_parameters, 9) );

            $cache_file["path"] = SELF::$upload_dir["basedir"] . $rel_path."/".$filename.".jpg";

            $cache_file["url"] = SELF::$upload_dir["baseurl"] . $rel_path ."/".$filename.".jpg";
        }
        
        return $cache_file;        
    }
 
     
    private static function get_google_static_map()
    {
        $map_dir = SELF::make_map_dir();


        if ( ! $map_dir ) return; // erreur

        $api_key = isset(SELF::$options['api_key']) ? SELF::$options['api_key'] : FALSE;

        //
        // si le fichier existe avoir la possibilit de forcer un refraiche !
        $get = false;
        // fichier existe pas
        if ( ! file_exists( $map_dir["path"] ) && $api_key )
        {
            
            $url = "https://maps.googleapis.com/maps/api/staticmap?";
            $markers = "markers=". urlencode($map_dir["adr"]);
            $parameters = "center=".urlencode($map_dir["adr"])."&".$map_dir["param"]."&".$markers."&key=" . $api_key;
            $url = $url . $parameters;


            $res          = wp_remote_get( $url );
            $http_code    = wp_remote_retrieve_response_code( $res );



            if ($http_code == 200)
            {
                $get = true;
                $file_content = wp_remote_retrieve_body( $res );
                file_put_contents($map_dir["path"], $file_content);
            }
            else
            {
                // google authe
                // ($http_code == 403)
                $message = __( 'Error. Http code = ', SELF::$text_domain) . $http_code;
                $get = false;
                add_settings_error( 
                    SELF::$plugin_slug.'_option_group',
                    esc_attr( 'settings_updated' ),
                    $message, 
                    "error");
            }
        }
        return $get;
    }



    private static function get_google_map_url($href=false)
    {
        $map_dir = SELF::make_map_dir();

        if (SELF::$options == NULL)
            SELF::$options = get_option( SELF::$plugin_slug.'_option' );


        if ( ! $map_dir ) return; // erreur

        $url = "https://www.google.com/maps/place/";
        $parameters = urlencode(SELF::$options["adr"]);
        $url = $url . $parameters;

        if ($href)
            return $url;
        else
            return "<a href='{$url}' target='_gmap'>{$parameters}</a>";
    }


    public static function insert_static_map_img($atts)
    {
        $atts = shortcode_atts( 
            [
                "link"=>0,
            ], 
            $atts
        );

        // ajouter flag line google 

        $map_dir = SELF::make_map_dir();
        $size = [
            "w" => SELF::$options["largeur"],
            "h" => SELF::$options["hauteur"]
        ];
        $str_code = "";
        if ($map_dir)
        {
            $str_code = <<<CODE
<img src='{$map_dir["url"]}' alt='{$map_dir["adr"]}' data-size='{$size["w"]}x{$size["h"]}' class="map">
CODE;
        }


        if ($atts["link"])
        {
            $href = SELF::get_google_map_url(true);
            $str_code = <<<CODE
<a href="{$href}" target="_blank">{$str_code}</a>
CODE;
        }


        return $str_code;
    }
    

    public static function admin_enqueue_scripts() 
    { 
        wp_enqueue_style(SELF::$plugin_slug.'-css', plugins_url('/css/styles.min.css', __FILE__), false,  SELF::VERSION, false);
        wp_enqueue_script(SELF::$plugin_slug, plugins_url('/js/script.js', __FILE__ ), array('jquery-ui-tooltip'), SELF::VERSION, true);
        
    }
}



if (! WPFIGMS::$loaded) WPFIGMS::init();

// fin