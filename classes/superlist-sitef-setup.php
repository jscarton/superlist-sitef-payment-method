<?php

class SuperlistSitefSetup{
	const PREFIX = 'superlist_sitef_';
	const STATUS_ACTIVE = 1;
	const STATUS_PAUSED = 0;
	const STATUS_INACTIVE = -1;
	
	public static function activate() {
		$settings = self::default_settings();
		foreach ( $settings as $name => $value ) {
			add_option( $name, $value );
		}
		self::_create_tables();
		//self::unschedule_autoship();
		//self::schedule_autoship();
	}
	
	public static function deactivate() {
		//self::unschedule_autoship();
	}
	
	public static function uninstall() {
		// Delete tables
		self::_delete_tables();
		// Delete autoship metadata
		$settings = self::default_settings();
		foreach ( $settings as $name => $value ) {
			delete_option( $name );
		}
	}
	public static function default_settings() {
		$default_settings = array(
			
		);
		return $default_settings;
	}
	
	private static function _create_tables()
    {
        global $wpdb;   
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        
        $prefix = $wpdb->prefix . SuperlistSitefSetup::PREFIX;
        
        $wpdb->hide_errors();
        $create_sql=
        "CREATE TABLE {$prefix}creditcards (
        id 						integer auto_increment,
        credit_card_token_id 	varchar(100) not null,
        payer_id 				integer not null,
        payer_dni 				varchar(20) not null,
        payment_method			varchar(20) not null,
        payment_maskednumber	varchar(20) not null,
        nita 					varchar(255) not null,
        PRIMARY KEY(id)
        );";
        dbDelta($create_sql);
        $wpdb->show_errors();

        $create_sql="CREATE TABLE `wp_superlist_sitef_ws_posts` (
					  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					  `nita` varchar(512) DEFAULT NULL,
					  `nsu` varchar(256)  DEFAULT NULL,
					  `merchantUSN` int(11) DEFAULT NULL,
					  `status` varchar(128) DEFAULT NULL,
					  `authorizer_id` int(11) DEFAULT NULL,
					  `cancelStore` int(11) DEFAULT NULL,
					  `full_post_response` longtext,
					  `post_date` datetime DEFAULT NULL,
					  PRIMARY KEY (`id`)
					);";
		dbDelta($create_sql);
        $wpdb->show_errors();
		update_option( 'superlist_sitef_db_version', SUPERLIST_SITEF_PAYMENT_METHOD_VERSION );
	}
	
	private static function _delete_tables() {
		global $wpdb;
	
		$prefix = $wpdb->prefix . SuperlistSitefSetup::PREFIX;
		$wpdb->query( "DROP TABLE {$prefix}creditcards" );
	}	
}