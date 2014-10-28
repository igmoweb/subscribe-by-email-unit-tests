<?php

if ( ! defined( 'WPMUDEV_DEV_DIR' ) )
    define( 'WPMUDEV_DEV_DIR', '/vagrant/www/wordpress-wpmudev/wp-content' );

if ( is_file( WPMUDEV_DEV_DIR . '/plugins/subscribe-by-email/subscribe-by-email.php' ) )
    include_once WPMUDEV_DEV_DIR . '/plugins/subscribe-by-email/subscribe-by-email.php';

class SBE_Settings extends WP_UnitTestCase {  
	function setUp() {  
		parent::setUp(); 

		if ( ! file_exists( WPMUDEV_DEV_DIR . '/plugins/subscribe-by-email/subscribe-by-email.php' ) ) {
			$this->markTestSkipped( 'SBE plugin is not installed.' );
		}

    } // end setup  


    function tearDown() {
    	parent::tearDown();
    }

    function test_default_settings() {
    	$settings = array(
    		'auto-subscribe',
			'subscribe_new_users',
			'from_sender',
			'subject',
			'frequency',
			'time',
			'day_of_week',
			'manage_subs_page',
			'get_notifications',
			'get_notifications_role',
			'follow_button',
			'follow_button_schema',
			'follow_button_position',
			'post_types',
			'taxonomies',
			'logo',
			'logo_width',
			'featured_image',
			'header_text',
			'show_blog_name',
			'footer_text',
			'header_color',
			'header_text_color',
			'send_full_post',
			'subscribe_email_content',
			'extra_fields',
			'from_email',
			'keep_logs_for',
			'mails_batch_size'
		);

		$default_settings = array_keys( incsub_sbe_get_default_settings() );
		foreach ( $settings as $setting ) {
			$this->assertTrue( in_array( $setting, $default_settings ) );
		}

		foreach ( $default_settings as $setting ) {
			$this->assertTrue( in_array( $setting, $settings ) );
		}

    }

    function test_multisite_settings() {
    	if ( ! is_multisite() )
    		return;

    	$settings = incsub_sbe_get_settings();

    	// Network setting
    	$settings['mails_batch_size'] = 1000;
    	incsub_sbe_update_settings( $settings );
    	$settings = incsub_sbe_get_settings();
    	$this->assertEquals( $settings['mails_batch_size'], 1000 );

    	// Blog setting
    	$settings['follow_button'] = true;
    	incsub_sbe_update_settings( $settings );
    	$settings = incsub_sbe_get_settings();
    	$this->assertEquals( $settings['follow_button'], true );

    }
    

}