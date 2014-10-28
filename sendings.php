<?php

if ( ! defined( 'WPMUDEV_DEV_DIR' ) )
    define( 'WPMUDEV_DEV_DIR', '/vagrant/www/wordpress-wpmudev/wp-content' );

if ( is_file( WPMUDEV_DEV_DIR . '/plugins/subscribe-by-email/subscribe-by-email.php' ) )
    include_once WPMUDEV_DEV_DIR . '/plugins/subscribe-by-email/subscribe-by-email.php';

class SBE_Sendings extends WP_UnitTestCase {  
	function setUp() {  
		parent::setUp(); 

		if ( ! file_exists( WPMUDEV_DEV_DIR . '/plugins/subscribe-by-email/subscribe-by-email.php' ) ) {
			$this->markTestSkipped( 'SBE plugin is not installed.' );
		}

        $_SERVER['SERVER_NAME'] = 'example.com';

        global $subscribe_by_email_plugin;
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
        $this->raw_subscribers = array(
            'settings_not_touched@example.com' => array(
                'email' => 'settings_not_touched@example.com',
                'note' => $note,
                'type' => $type,
                'flag' => true,
                'meta' => array()
            ),
            'he_wants_only_posts@example.com' => array(
                'email' => 'he_wants_only_posts@example.com',
                'note' => $note,
                'type' => $type,
                'flag' => true,
                'meta' => array(
                    'subscription_post_types' => array( 'post' )
                )
            ),
            'he_wants_only_books@example.com' => array(
                'email' => 'he_wants_only_books@example.com',
                'note' => $note,
                'type' => $type,
                'flag' => true,
                'meta' => array(
                    'subscription_post_types' => array( 'book' )
                )
            ),
            'he_does_not_want_anything@example.com' => array(
                'email' => 'he_does_not_want_anything@example.com',
                'note' => $note,
                'type' => $type,
                'flag' => true,
                'meta' => array(
                    'subscription_post_types' => array()
                )
            ),
            'not_confirmed@example.com' => array(
                'email' => 'not_confirmed@example.com',
                'note' => $note,
                'type' => $type,
                'flag' => false,
                'meta' => array()
            ),
            'he_wants_everything@example.com' => array(
                'email' => 'he_wants_everything@example.com',
                'note' => $note,
                'type' => $type,
                'flag' => true,
                'meta' => array(
                    'subscription_post_types' => $post_types
                )
            ),
            'he_wants_posts_and_pages@example.com' => array(
                'email' => 'he_wants_posts_and_pages@example.com',
                'note' => $note,
                'type' => $type,
                'flag' => true,
                'meta' => array(
                    'subscription_post_types' => array( 'post', 'page' )
                )
            ),
            'he_wants_everything_but_is_not_confirmed@example.com' => array(
                'email' => 'he_wants_everything_but_is_not_confirmed@example.com',
                'note' => $note,
                'type' => $type,
                'flag' => false,
                'meta' => array(
                    'subscription_post_types' => $post_types
                )
            ),
        );

        foreach ( $this->raw_subscribers as $email => $raw_subscriber ) {
            Incsub_Subscribe_By_Email::subscribe_user( $email, $raw_subscriber['note'], $raw_subscriber['type'], $raw_subscriber['flag'], $raw_subscriber['meta'] );
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

    function get_queue_items( $log_id ) {
        $args = array( 
            'campaign_id' => $log_id, 
            'per_page' => -1
        );
        return incsub_sbe_get_queue_items( $args );
    }

    /**
     * @group subscriptions
     */
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

    /**
     * @group subscriptions
     */
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

    function insert_immediately_posts() {
        return $this->factory->post->create_object( $this->factory->post->generate_args() );
    }

    function set_immediately_settings() {
        $settings = incsub_sbe_get_settings();
        // Yep, there's a typo
        $settings['frequency'] = 'inmediately';
        incsub_sbe_update_settings( $settings );
    }

    /**
     * @group immediately
     */
    function test_enqueue_immediately_mails() {
        $this->insert_data_tests();
        $this->set_immediately_settings();
        $post_id = $this->insert_immediately_posts();

        $settings = incsub_sbe_get_settings();
        $model = incsub_sbe_get_model();

        // Remaining campaign
        $remaining_batch = $model->get_remaining_batch_mail();
        $log_id = $remaining_batch->campaign_id;

        $this->assertEquals( array( $post_id ), $remaining_batch->campaign_settings['posts_ids'] );

        $queue_items = $this->get_queue_items( $log_id );

        $confirmed_subscribers = wp_list_filter( $this->raw_subscribers, array( 'flag' => true ) );
        $this->assertCount( count( $confirmed_subscribers ), $queue_items['items'] );

        foreach ( $queue_items['items'] as $item ) {
            $user_posts = $item->get_subscriber_posts();

            $raw_subscriber = $this->raw_subscribers[ $item->subscriber_email ];

            if ( ! isset( $raw_subscriber['meta']['subscription_post_types'] ) )
                $user_post_types = $this->get_post_types();
            else
                $user_post_types = $raw_subscriber['meta']['subscription_post_types'];

            if ( empty( $user_posts ) ) {
                $this->assertFalse( in_array( get_post_type( $post_id ), $user_post_types ) );
            }
            else {
                $this->assertTrue( in_array( get_post_type( $post_id ), $user_post_types ) );
            }
            
        }

    }

    /**
     * @group immediately
     */
    function test_send_immediately_mails() {
        global $subscribe_by_email_plugin;

        $this->insert_data_tests();
        $this->set_immediately_settings();
        $post_id = $this->insert_immediately_posts();

        $settings = incsub_sbe_get_settings();

        $post_id = $this->insert_immediately_posts();
        $post_type = get_post_type( $post_id );

        delete_transient( Incsub_Subscribe_By_Email::$pending_mails_transient_slug );

        $result = $subscribe_by_email_plugin->maybe_send_pending_emails();

        $confirmed_subscribers = wp_list_filter( $this->raw_subscribers, array( 'flag' => true ) );

        $this->assertCount( count( $result ), $confirmed_subscribers );
        
        foreach ( $result as $email => $status ) {
            $raw_subscriber = $this->raw_subscribers[ $email ];

            if ( ! isset( $raw_subscriber['meta']['subscription_post_types'] ) )
                $user_post_types = $this->get_post_types();
            else
                $user_post_types = $raw_subscriber['meta']['subscription_post_types'];

            if ( in_array( $post_type, $user_post_types ) )
                $this->assertEquals( 1, $status ); // Sending OK
            else
                $this->assertEquals( 3, $status ); // Empty user content
        }
    }

    function insert_daily_posts() {
        $posts_ids = array();
        
        $args = $this->factory->post->generate_args();
        $args['post_type'] = 'post';
        $posts_ids[] = $this->factory->post->create_object( $args );

        $args = $this->factory->post->generate_args();
        $args['post_type'] = 'page';
        $posts_ids[] = $this->factory->post->create_object( $args );

        $args = $this->factory->post->generate_args();
        $args['post_type'] = 'book';
        $posts_ids[] = $this->factory->post->create_object( $args );

        return $posts_ids;
    }

    function set_daily_settings() {
        $settings = incsub_sbe_get_settings();
        $settings['frequency'] = 'daily';

        // This does not matter
        $settings['time'] = 3;
        $settings['post_types'] = array( 'post', 'page' );
        
        // Set the next day schedules
        Incsub_Subscribe_By_Email::set_next_day_schedule_time( $settings['time'] );

        incsub_sbe_update_settings( $settings );
    }

    /**
     * @group daily
     */
    function test_enqueue_daily_mails() {
        global $subscribe_by_email_plugin;

        $this->insert_data_tests();
        $this->set_daily_settings();
        $posts_ids = $this->insert_daily_posts();

        $settings = incsub_sbe_get_settings();

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

        // The old post should not be there
        $this->assertFalse( in_array( $old_post_id, $remaining_batch->campaign_settings['posts_ids'] ) );

        foreach ( $posts_ids as $post_id ) {
            if ( ! in_array( get_post_type( $post_id ), $settings['post_types'] ) ) // If the post type is not in settings, it should not be in the campaign
                $this->assertFalse( in_array( $post_id, $remaining_batch->campaign_settings['posts_ids'] ) );
            else
                $this->assertTrue( in_array( $post_id, $remaining_batch->campaign_settings['posts_ids'] ) );
        }

        // Get all queue items pending
        $queue_items = $this->get_queue_items( $remaining_batch->campaign_id );

        // Filter only confirmed users
        $confirmed_subscribers = wp_list_filter( $this->raw_subscribers, array( 'flag' => true ) );

        // Confirmed users should be in the queue
        $this->assertCount( count( $confirmed_subscribers ), $queue_items['items'] );

        // Check that we are sending only posts IDs that the subscriber wants
        foreach ( $queue_items['items'] as $item ) {
            $user_posts = $item->get_subscriber_posts();

            $raw_subscriber = $this->raw_subscribers[ $item->subscriber_email ];

            if ( ! isset( $raw_subscriber['meta']['subscription_post_types'] ) )
                $user_post_types = $this->get_post_types();
            else
                $user_post_types = $raw_subscriber['meta']['subscription_post_types'];

            foreach ( $user_posts as $user_post ) {
                $this->assertTrue( in_array( get_post_type( $user_post->ID ), $user_post_types ) );
            }
            
        }

    }

    /**
     * @group daily
     */
    function test_send_daily_posts() {
        global $subscribe_by_email_plugin;

        $this->insert_data_tests();
        $this->set_daily_settings();
        $posts_ids = $this->insert_daily_posts();

        $settings = incsub_sbe_get_settings();

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

        delete_transient( Incsub_Subscribe_By_Email::$pending_mails_transient_slug );

        $result = $subscribe_by_email_plugin->maybe_send_pending_emails();

        // Here are all post types that we have sent
        $sent_post_types = array();
        foreach ( $posts_ids as $post_id ) {
            $post_type = get_post_type( $post_id );
            if ( in_array( $post_type, $settings['post_types'] ) )
                $sent_post_types[] = get_post_type( $post_id );    
        }
        $sent_post_types = array_unique( $sent_post_types );

        // Now check the status of every sending
        foreach ( $result as $email => $status ) {
            $raw_subscriber = $this->raw_subscribers[ $email ];

            if ( ! isset( $raw_subscriber['meta']['subscription_post_types'] ) )
                $user_post_types = $this->get_post_types();
            else
                $user_post_types = $raw_subscriber['meta']['subscription_post_types'];

            if ( ! empty( array_intersect( $user_post_types, $sent_post_types ) ) ) {
                $this->assertEquals( 1, $status );
            }
            else {
                $this->assertEquals( 3, $status );   
            }
        }


    }


    function insert_weekly_posts() {
        $posts_ids = array();
        
        $args = $this->factory->post->generate_args();
        $args['post_type'] = 'post';
        $posts_ids[] = $this->factory->post->create_object( $args );

        $args = $this->factory->post->generate_args();
        $args['post_type'] = 'page';
        $args['post_date'] = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 259200 );
        $posts_ids[] = $this->factory->post->create_object( $args );

        $args = $this->factory->post->generate_args();
        $args['post_type'] = 'book';
        $posts_ids[] = $this->factory->post->create_object( $args );

        return $posts_ids;
    }

    function set_weekly_settings() {
        $settings = incsub_sbe_get_settings();
        $settings['frequency'] = 'weekly';

        // This does not matter
        $settings['time'] = 3;
        $settings['day_of_the_week'] = 3;

        $settings['post_types'] = array( 'post', 'page' );
        
        // Set the next day schedules
        Incsub_Subscribe_By_Email::set_next_week_schedule_time( $settings['day_of_the_week'], $settings['time'] );

        incsub_sbe_update_settings( $settings );
    }

    /**
     * @group weekly
     */
    function test_enqueue_weekly_mails() {
        global $subscribe_by_email_plugin;

        $this->insert_data_tests();
        $this->set_weekly_settings();
        $posts_ids = $this->insert_weekly_posts();

        $settings = incsub_sbe_get_settings();

        // We are going to create an old post too
        $args = $this->factory->post->generate_args();
        $args['post_date'] = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 2259200 ); 
        $args['post_date_gmt'] = date( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - 2259200 );
        $args['post_type'] = array_rand( $this->get_post_types() ); 
        $old_post_id = $this->factory->post->create_object( $args );

        // Clean the weekly option that makes the weekly digest to be triggered
        update_option( Incsub_Subscribe_By_Email::$freq_weekly_transient_slug, 1 );

        // This should enqueue all the emails
        $subscribe_by_email_plugin->process_scheduled_subscriptions();

        $model = incsub_sbe_get_model();

        // Here's the campagin
        $remaining_batch = $model->get_remaining_batch_mail();

        // The old post should not be there
        $this->assertFalse( in_array( $old_post_id, $remaining_batch->campaign_settings['posts_ids'] ) );

        foreach ( $posts_ids as $post_id ) {
            if ( ! in_array( get_post_type( $post_id ), $settings['post_types'] ) ) // If the post type is not in settings, it should not be in the campaign
                $this->assertFalse( in_array( $post_id, $remaining_batch->campaign_settings['posts_ids'] ) );
            else
                $this->assertTrue( in_array( $post_id, $remaining_batch->campaign_settings['posts_ids'] ) );
        }

        // Get all queue items pending
        $queue_items = $this->get_queue_items( $remaining_batch->campaign_id );

        // Filter only confirmed users
        $confirmed_subscribers = wp_list_filter( $this->raw_subscribers, array( 'flag' => true ) );

        // Confirmed users should be in the queue
        $this->assertCount( count( $confirmed_subscribers ), $queue_items['items'] );

        // Check that we are sending only posts IDs that the subscriber wants
        foreach ( $queue_items['items'] as $item ) {
            $user_posts = $item->get_subscriber_posts();

            $raw_subscriber = $this->raw_subscribers[ $item->subscriber_email ];

            if ( ! isset( $raw_subscriber['meta']['subscription_post_types'] ) )
                $user_post_types = $this->get_post_types();
            else
                $user_post_types = $raw_subscriber['meta']['subscription_post_types'];

            foreach ( $user_posts as $user_post ) {
                $this->assertTrue( in_array( get_post_type( $user_post->ID ), $user_post_types ) );
            }
            
        }

    }

    /**
     * @group weekly
     */
    function test_send_weekly_posts() {
        global $subscribe_by_email_plugin;

        $this->insert_data_tests();
        $this->set_weekly_settings();
        $posts_ids = $this->insert_weekly_posts();

        $settings = incsub_sbe_get_settings();

        // We are going to create an old post too
        $args = $this->factory->post->generate_args();
        $args['post_date'] = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 259200 ); 
        $args['post_date_gmt'] = date( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - 259200 );
        $args['post_type'] = array_rand( $this->get_post_types() ); 
        $old_post_id = $this->factory->post->create_object( $args );

        // Clean the weekly option that makes the weekly digest to be triggered
        update_option( Incsub_Subscribe_By_Email::$freq_weekly_transient_slug, 1 );

        // This should enqueue all the emails
        $subscribe_by_email_plugin->process_scheduled_subscriptions();

        delete_transient( Incsub_Subscribe_By_Email::$pending_mails_transient_slug );

        $result = $subscribe_by_email_plugin->maybe_send_pending_emails();

        // Here are all post types that we have sent
        $sent_post_types = array();
        foreach ( $posts_ids as $post_id ) {
            $post_type = get_post_type( $post_id );
            if ( in_array( $post_type, $settings['post_types'] ) )
                $sent_post_types[] = get_post_type( $post_id );    
        }
        $sent_post_types = array_unique( $sent_post_types );

        // Now check the status of every sending
        foreach ( $result as $email => $status ) {
            $raw_subscriber = $this->raw_subscribers[ $email ];

            if ( ! isset( $raw_subscriber['meta']['subscription_post_types'] ) )
                $user_post_types = $this->get_post_types();
            else
                $user_post_types = $raw_subscriber['meta']['subscription_post_types'];

            if ( ! empty( array_intersect( $user_post_types, $sent_post_types ) ) ) {
                $this->assertEquals( 1, $status );
            }
            else {
                $this->assertEquals( 3, $status );   
            }
        }


    }

    /**
     * @group immediately
     */
    function test_delete_subscriber_after_enqueueing() {
        global $subscribe_by_email_plugin;

        $this->insert_data_tests();
        $this->set_immediately_settings();
        $post_id = $this->insert_immediately_posts();

        $settings = incsub_sbe_get_settings();

        $post_id = $this->insert_immediately_posts();
        $post_type = get_post_type( $post_id );

        delete_transient( Incsub_Subscribe_By_Email::$pending_mails_transient_slug );

        // Let's delete the first subscriber
        reset( $this->raw_subscribers );
        $email_deleted = key( $this->raw_subscribers );
        incsub_sbe_cancel_subscription( $email_deleted );
        reset( $this->raw_subscribers );


        $result = $subscribe_by_email_plugin->maybe_send_pending_emails();

        $confirmed_subscribers = wp_list_filter( $this->raw_subscribers, array( 'flag' => true ) );

        $this->assertCount( count( $result ), $confirmed_subscribers );
        
        foreach ( $result as $email => $status ) {
            if ( $email === $email_deleted ) {
                $this->assertEquals( 5, $status );
                continue;
            }

            $raw_subscriber = $this->raw_subscribers[ $email ];

            if ( ! isset( $raw_subscriber['meta']['subscription_post_types'] ) )
                $user_post_types = $this->get_post_types();
            else
                $user_post_types = $raw_subscriber['meta']['subscription_post_types'];

            if ( in_array( $post_type, $user_post_types ) )
                $this->assertEquals( 1, $status ); // Sending OK
            else
                $this->assertEquals( 3, $status ); // Empty user content
        }
    }


}
