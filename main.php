<?php

if ( ! defined( 'WPMUDEV_DEV_DIR' ) )
    define( 'WPMUDEV_DEV_DIR', '/vagrant/www/wordpress-wpmudev/wp-content' );

class SBE_Init_Plugin extends WP_UnitTestCase {  
	function setUp() {  
		parent::setUp(); 

		if ( ! file_exists( WPMUDEV_DEV_DIR . '/plugins/subscribe-by-email/subscribe-by-email.php' ) ) {
			$this->markTestSkipped( 'SBE plugin is not installed.' );
		}

         global $subscribe_by_email_plugin;

        if ( ! class_exists( 'Incsub_Subscribe_By_Email' ) ) {
            include WPMUDEV_DEV_DIR . '/plugins/subscribe-by-email/subscribe-by-email.php';
        }
        else {
           
            $subscribe_by_email_plugin = new Incsub_Subscribe_By_Email();
        }

        $_SERVER['SERVER_NAME'] = 'example.com';

        $subscribe_by_email_plugin->init_plugin();
    } // end setup  

    function tearDown() {
        parent::tearDown();
    }

    function insert_data_tests() {
        $note = 'Instant';
        $type = 'Manual Subscription';

        $this->raw_subscribers = array();

        $this->register_post_type( array() );
        $post_types = $this->get_post_types();

        $max_subscribers = mt_rand( 2, 40 );
        for ( $i = 1; $i <= $max_subscribers; $i++ ) {
            $email = 'subscriber' . $i . '@example.com';
            $autopt = (bool)mt_rand(0,1);
            $meta = array();

            // Get a random number of post types or nothing
            $set_post_types = (bool)mt_rand(0,1);
            if ( $set_post_types ) {
                $set_empty_array = (bool)mt_rand(0,1);
                if ( $set_empty_array ) {
                    $user_post_types = array();
                }
                else {
                    $user_post_types = array_rand( $post_types, mt_rand( 1, count( $post_types ) ) );
                    if ( ! is_array( $user_post_types ) )
                        $user_post_types = array( $user_post_types );
                }

                $meta['subscription_post_types'] = $user_post_types;
            }
            
            $this->raw_subscribers[ $email ] = array(
                'email' => $email,
                'note' => $note,
                'type' => $type,
                'flag' => $autopt,
                'meta' => $meta
            );
            Incsub_Subscribe_By_Email::subscribe_user( $email, $note, $type, $autopt, $meta );
        }

    }

    function register_post_type() {    
        register_post_type( 'book' );
    }

    function get_post_types() {
        $post_types = get_post_types();
        unset( $post_types['attachment'] );
        unset( $post_types['nav_menu_item'] );
        unset( $post_types['revision'] );
        unset( $post_types['subscriber'] );
        return $post_types;
    }

    function test_subscribe_users() {
        $this->insert_data_tests();

        foreach ( $this->raw_subscribers as $raw_subscriber_email => $raw_subscriber ) {
            $subscriber = incsub_sbe_get_subscriber( $raw_subscriber_email );
            
            $this->assertNotEmpty( $subscriber );
            $this->assertEquals( $subscriber->subscription_email, $raw_subscriber['email'] );
            $this->assertEquals( $subscriber->subscription_note, $raw_subscriber['note'] );
            $this->assertEquals( $subscriber->subscription_type, $raw_subscriber['type'] );

            if ( isset( $raw_subscriber['meta']['subscription_post_types'] ) )
                $this->assertEquals( $raw_subscriber['meta']['subscription_post_types'], $subscriber->subscription_post_types );
            else
                $this->assertEmpty( $subscriber->subscription_post_types );
        }
    }

    function test_update_subscribers() {
        $this->insert_data_tests();
        $post_types = $this->get_post_types();

        foreach ( $this->raw_subscribers as $raw_subscriber_email => $raw_subscriber ) {
            $subscriber = incsub_sbe_get_subscriber( $raw_subscriber_email );

            $set_empty_array = (bool)mt_rand(0,1);
            if ( $set_empty_array ) {
                $new_post_types = array();
            }
            else {
                $new_post_types = array_rand( $post_types, mt_rand( 1, count( $post_types ) ) );
                if ( ! is_array( $new_post_types ) )
                    $new_post_types = array( $new_post_types );
            }

            incsub_sbe_update_subscriber( $subscriber->ID, array( 'subscription_post_types' => $new_post_types ) );
            
            $subscriber = incsub_sbe_get_subscriber( $subscriber->ID );

            $this->assertEquals( $new_post_types, $subscriber->subscription_post_types );
        }
    }

    function test_enqueue_immediately_mails() {
        $this->insert_data_tests();

        $settings = incsub_sbe_get_settings();
        // Yep, there's a typo
        $settings['frequency'] = 'inmediately';
        incsub_sbe_update_settings( $settings );

        $settings = incsub_sbe_get_settings();

        $post_id = $this->factory->post->create_object( $this->factory->post->generate_args() );

        $model = incsub_sbe_get_model();
        $remaining_batch = $model->get_remaining_batch_mail();
        $log_id = $remaining_batch->campaign_id;

        $this->assertEquals( array( $post_id ), $remaining_batch->campaign_settings['posts_ids'] );

        $args = array( 
            'campaign_id' => $log_id, 
            'per_page' => -1
        );
        $queue_items = incsub_sbe_get_queue_items( $args );

        $confirmed_subscribers = wp_list_filter( $this->raw_subscribers, array( 'flag' => true ) );
        $this->assertCount( count( $confirmed_subscribers ), $queue_items['items'] );

        foreach ( $queue_items['items'] as $item ) {
            $item->get_subscriber_posts();
            $raw_subscriber = $this->raw_subscribers[ $item->subscriber_email ];
            if ( ! isset( $raw_subscriber['subscription_post_types'] ) )
                $user_post_types = $this->get_post_types();
            else
                $user_post_types = $raw_subscriber['subscription_post_types'];

            $queue_item_post_types = wp_list_pluck( $item->get_queue_item_posts(), 'post_type' );

            foreach ( $queue_item_post_types as $post_type )
                $this->assertTrue( in_array( $post_type, $user_post_types ) );
            
        }

    }

    function test_enqueue_daily_mails() {
        global $subscribe_by_email_plugin;

        $this->insert_data_tests();

        $settings = incsub_sbe_get_settings();
        $settings['frequency'] = 'daily';

        // This does not matter
        $settings['time'] = 3;

        // Let's select post types randomly
        $settings['post_types'] = array_rand( $this->get_post_types(), mt_rand( 1, count( $this->get_post_types() ) ) );
        if ( ! is_array( $settings['post_types'] ) )
            $settings['post_types'] = array( $settings['post_types'] );

        $settings['post_types'] = array( 'post', 'page', 'book' );
        
        // Set the next day schedules
        Incsub_Subscribe_By_Email::set_next_day_schedule_time( $settings['time'] );

        incsub_sbe_update_settings( $settings );

        $settings = incsub_sbe_get_settings();

        // Create a few posts of random post types
        $posts_no = rand( 1, 5 );
        $posts_ids = array();
        for ( $i = 1; $i <= $posts_no ; $i++ ) { 
            $args = $this->factory->post->generate_args();
            $args['post_type'] = array_rand( $this->get_post_types() );
            $posts_ids[] = $this->factory->post->create_object( $args );
        }

        // We are going to create an old post too
        $args = $this->factory->post->generate_args();
        $args['post_date'] = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 259200 ); 
        $args['post_date_gmt'] = date( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - 259200 );
        $args['post_type'] = array_rand( $this->get_post_types() ); 
        $old_post_id = $this->factory->post->create_object( $args );

        // Clean the daily option that makes the daily digest to be triggered
        update_option( Incsub_Subscribe_By_Email::$freq_daily_transient_slug, 1 );

        // This should enqueue all the emails
        $subscribe_by_email_plugin->process_scheduled_subscriptions();

        $model = incsub_sbe_get_model();

        // Here's the campagin
        $remaining_batch = $model->get_remaining_batch_mail();
        if ( empty( $remaining_batch ) ) {
            // This could happen in one case because any of the posts created are in the settings['post_types']
            // It's better if we repeat the test
            $this->test_enqueue_daily_mails();
        }

        // The old post should not be there
        $this->assertFalse( in_array( $old_post_id, $remaining_batch->campaign_settings['posts_ids'] ) );

        foreach ( $posts_ids as $post_id ) {
            if ( ! in_array( get_post_type( $post_id ), $settings['post_types'] ) ) // If the post type is not in settings, it should not be in the campaign
                $this->assertFalse( in_array( $post_id, $remaining_batch->campaign_settings['posts_ids'] ) );
            else
                $this->assertTrue( in_array( $post_id, $remaining_batch->campaign_settings['posts_ids'] ) );
        }

        // Get all queue items pending
        $log_id = $remaining_batch->campaign_id;
        $args = array( 
            'campaign_id' => $log_id, 
            'per_page' => -1
        );
        $queue_items = incsub_sbe_get_queue_items( $args );

        // Filter only confirmed users
        $confirmed_subscribers = wp_list_filter( $this->raw_subscribers, array( 'flag' => true ) );

        // Confirmed users should be in the queue
        $this->assertCount( count( $confirmed_subscribers ), $queue_items['items'] );

        // Check that we are sending only posts IDs that the subscriber wants
        foreach ( $queue_items['items'] as $item ) {
            $item->get_subscriber_posts();
            $raw_subscriber = $this->raw_subscribers[ $item->subscriber_email ];
            if ( ! isset( $raw_subscriber['subscription_post_types'] ) )
                $user_post_types = $this->get_post_types();
            else
                $user_post_types = $raw_subscriber['subscription_post_types'];

            $queue_item_post_types = wp_list_pluck( $item->get_queue_item_posts(), 'post_type' );
            foreach ( $queue_item_post_types as $post_type )
                $this->assertTrue( in_array( $post_type, $user_post_types ) );
            
        }

    }

    function insert_weekly_posts() {
        // Create a few posts of random post types
        $posts_no = rand( 1, 5 );
        $posts_ids = array();
        for ( $i = 1; $i <= $posts_no ; $i++ ) { 
            $args = $this->factory->post->generate_args();
            $args['post_type'] = array_rand( $this->get_post_types() );
            // One week in seconds as max
            $seed = mt_rand( 1, 604799 );
            $args['post_date'] = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $seed );
            $args['post_date_gmt'] = date( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - $seed );
            $posts_ids[] = $this->factory->post->create_object( $args );
        }

        // We are going to create an old post too
        $args = $this->factory->post->generate_args();
        $args['post_date'] = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 2592000 ); 
        $args['post_date_gmt'] = date( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - 2592000 );
        $args['post_type'] = array_rand( $this->get_post_types() ); 
        $old_post_id = $this->factory->post->create_object( $args );

        return compact( 'posts_ids', 'old_post_id' );
    }

    function set_weekly_settings() {
        $settings = incsub_sbe_get_settings();
        $settings['frequency'] = 'weekly';
        $settings['mails_batch_size'] = 100;

        // This does not matter
        $settings['day_of_week'] = 3;
        $settings['time'] = 3;

        // Let's select post types randomly
        $settings['post_types'] = array_rand( $this->get_post_types(), mt_rand( 1, count( $this->get_post_types() ) ) );
        if ( ! is_array( $settings['post_types'] ) )
            $settings['post_types'] = array( $settings['post_types'] );

        $settings['post_types'] = array( 'post', 'page', 'book' );
        
        // Set the next day schedules
        Incsub_Subscribe_By_Email::set_next_week_schedule_time( 3, 2 );

        incsub_sbe_update_settings( $settings );
    }

    function test_enqueue_weekly_mails() {
        global $subscribe_by_email_plugin;

        $this->insert_data_tests();
        $this->set_weekly_settings();
        $posts = $this->insert_weekly_posts();
        extract( $posts );

        $settings = incsub_sbe_get_settings();

        // Clean the weekly option that makes the weekly digest to be triggered
        update_option( Incsub_Subscribe_By_Email::$freq_weekly_transient_slug, 1 );

        // This should enqueue all the emails
        $subscribe_by_email_plugin->process_scheduled_subscriptions();

        $model = incsub_sbe_get_model();

        // Here's the campagin
        $remaining_batch = $model->get_remaining_batch_mail();
        if ( empty( $remaining_batch ) ) {
            // This could happen in one case because any of the posts created are in the settings['post_types']
            // It's better if we repeat the test
            $this->test_enqueue_weekly_mails();
        }

        // The old post should not be there
        $this->assertFalse( in_array( $old_post_id, $remaining_batch->campaign_settings['posts_ids'] ) );

        foreach ( $posts_ids as $post_id ) {
            if ( ! in_array( get_post_type( $post_id ), $settings['post_types'] ) ) // If the post type is not in settings, it should not be in the campaign
                $this->assertFalse( in_array( $post_id, $remaining_batch->campaign_settings['posts_ids'] ) );
            else
                $this->assertTrue( in_array( $post_id, $remaining_batch->campaign_settings['posts_ids'] ) );
        }

        // Get all queue items pending
        $log_id = $remaining_batch->campaign_id;
        $args = array( 
            'campaign_id' => $log_id, 
            'per_page' => -1
        );
        $queue_items = incsub_sbe_get_queue_items( $args );

        // Filter only confirmed users
        $confirmed_subscribers = wp_list_filter( $this->raw_subscribers, array( 'flag' => true ) );

        // Confirmed users should be in the queue
        $this->assertCount( count( $confirmed_subscribers ), $queue_items['items'] );

        // Check that we are sending only posts IDs that the subscriber wants
        foreach ( $queue_items['items'] as $item ) {
            $item->get_subscriber_posts();
            $raw_subscriber = $this->raw_subscribers[ $item->subscriber_email ];
            if ( ! isset( $raw_subscriber['subscription_post_types'] ) )
                $user_post_types = $this->get_post_types();
            else
                $user_post_types = $raw_subscriber['subscription_post_types'];

            $queue_item_post_types = wp_list_pluck( $item->get_queue_item_posts(), 'post_type' );
            foreach ( $queue_item_post_types as $post_type )
                $this->assertTrue( in_array( $post_type, $user_post_types ) );
            
        }

    }

    function test_send_weekly_digest() {
        global $subscribe_by_email_plugin;

        $this->insert_data_tests();
        $this->set_weekly_settings();
        $posts = $this->insert_weekly_posts();
        extract( $posts );

        $settings = incsub_sbe_get_settings();

        // Clean the weekly option that makes the weekly digest to be triggered
        update_option( Incsub_Subscribe_By_Email::$freq_weekly_transient_slug, 1 );

        // This should enqueue all the emails
        $subscribe_by_email_plugin->process_scheduled_subscriptions();

        $model = incsub_sbe_get_model();
        $remaining_batch = $model->get_remaining_batch_mail();
        if ( empty( $remaining_batch ) ) {
            // This could happen in one case because any of the posts created are in the settings['post_types']
            // It's better if we repeat the test
            $this->test_send_weekly_digest();
        }
        
        $log_id = $remaining_batch->campaign_id;
        $args = array( 
            'campaign_id' => $log_id, 
            'per_page' => -1
        );
        $queue_items = incsub_sbe_get_queue_items( $args );

        delete_transient( Incsub_Subscribe_By_Email::$pending_mails_transient_slug );

        $result = $subscribe_by_email_plugin->maybe_send_pending_emails();

        // Filter only confirmed users
        $confirmed_subscribers = wp_list_filter( $this->raw_subscribers, array( 'flag' => true ) );

        $this->assertCount( count( $confirmed_subscribers ), $result );

        foreach ( $queue_items['items'] as $item ) {
            $item->get_subscriber_posts();
            $raw_subscriber = $this->raw_subscribers[ $item->subscriber_email ];
            $sent_status = $result[ $item->subscriber_email ];

            if ( ! isset( $raw_subscriber['subscription_post_types'] ) )
                $user_post_types = $this->get_post_types();
            else
                $user_post_types = $raw_subscriber['subscription_post_types'];

        }

    }

}