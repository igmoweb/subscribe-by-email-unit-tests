<?php


if ( ! defined( 'WPMUDEV_DEV_DIR' ) )
    define( 'WPMUDEV_DEV_DIR', '/vagrant/www/wordpress-wpmudev/wp-content' );

if ( is_file( WPMUDEV_DEV_DIR . '/plugins/subscribe-by-email/subscribe-by-email.php' ) )
    include_once WPMUDEV_DEV_DIR . '/plugins/subscribe-by-email/subscribe-by-email.php';


class SBE_Subscriptions extends WP_UnitTestCase {  
	function setUp() {  
		parent::setUp(); 

        if ( ! file_exists( WPMUDEV_DEV_DIR . '/plugins/subscribe-by-email/subscribe-by-email.php' ) ) {
            $this->markTestSkipped( 'SBE plugin is not installed.' );
        }

        $_SERVER['SERVER_NAME'] = 'example.com';
    } // end setup  

    function tearDown() {
    	parent::tearDown();
    }

    /**
     * @group subscriptions
     */
    function test_subscription() {
        global $subscribe_by_email_plugin;

        $args = array(
            'email' => 'test_email@example.org',
            'note' => 'Note',
            'type' => 'Type',
            'autopt' => false,
            'meta' => array( 'a-meta' => 'yes' )
        );

        extract( $args );
        $user_id = $subscribe_by_email_plugin::subscribe_user( $email, $note, $type, $autopt, $meta );

        $subscriber = incsub_sbe_get_subscriber( $user_id );

        $this->assertEquals( $subscriber->subscription_email, $email );
        $this->assertEquals( $subscriber->subscription_type, $type );
        $this->assertFalse( $subscriber->subscription_post_types );
        $this->assertFalse( $subscriber->is_confirmed() );
        $this->assertEquals( $subscriber->get_meta( 'a-meta' ), $meta['a-meta'] );

        incsub_sbe_confirm_subscription( $user_id );

        $subscriber = incsub_sbe_get_subscriber( $user_id );
        $this->assertTrue( $subscriber->is_confirmed() );

        incsub_sbe_cancel_subscription( $user_id );

        $subscriber = incsub_sbe_get_subscriber( $user_id );
        $this->assertFalse( $subscriber );
    }

    /**
     * @group subscriptions
     */
    function test_subscription_post_types() {
        global $subscribe_by_email_plugin;

        register_post_type( 'book' );
        register_post_type( 'product' );

        $settings = incsub_sbe_get_settings();
        $settings['post_types'] = array( 'post', 'product' );
        incsub_sbe_update_settings( $settings );

        $args = array(
            'email' => 'test_email@example.org',
            'note' => 'Note',
            'type' => 'Type',
            'autopt' => true,
        );
        extract( $args );
        $user_id = $subscribe_by_email_plugin::subscribe_user( $email, $note, $type, $autopt );

        $subscriber = incsub_sbe_get_subscriber( $user_id );
        
        $subscriber_post_types = $subscriber->subscription_post_types;
        $this->assertFalse( $subscriber_post_types );

        // First test
        $post_types = array( 'book', 'post' );
        $subscriber->set_post_types( $post_types );

        $subscriber_post_types = $subscriber->subscription_post_types;
        $expected_post_types = array( 'post' );

        sort( $subscriber_post_types );
        sort( $expected_post_types );
        $this->assertEquals( $expected_post_types, $subscriber_post_types );

        // Second test
        $post_types = array();
        $subscriber->set_post_types( $post_types );

        $subscriber_post_types = $subscriber->subscription_post_types;
        $expected_post_types = array();

        sort( $subscriber_post_types );
        sort( $expected_post_types );
        $this->assertEquals( $expected_post_types, $subscriber_post_types );

        // Third test
        $post_types = array( 'post', 'book', 'product' );
        $settings['post_types'] = $post_types;
        incsub_sbe_update_settings( $settings );

        $subscriber->set_post_types( $post_types );

        $subscriber_post_types = $subscriber->subscription_post_types;
        $expected_post_types = array( 'post', 'book', 'product' );

        sort( $subscriber_post_types );
        sort( $expected_post_types );
        $this->assertEquals( $expected_post_types, $subscriber_post_types );        
    }

}