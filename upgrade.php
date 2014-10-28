<?php

if ( ! defined( 'WPMUDEV_DEV_DIR' ) )
    define( 'WPMUDEV_DEV_DIR', '/vagrant/www/wordpress-wpmudev/wp-content' );

if ( is_file( WPMUDEV_DEV_DIR . '/plugins/subscribe-by-email/subscribe-by-email.php' ) )
    include_once WPMUDEV_DEV_DIR . '/plugins/subscribe-by-email/subscribe-by-email.php';

class SBE_Init_Plugin extends WP_UnitTestCase {  
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


    /**
     * @group upgrade
     */
    function test_upgrade_to_29() {
        global $subscribe_by_email_plugin;
        update_option( 'incsub_sbe_version', '2.8.3' );

        // We need some data to upgrade
        global $wpdb;
        global $wp_filesystem;

        $log_table = $wpdb->prefix . 'subscriptions_log_table';

        $args = $this->factory->post->generate_args();
        $args['post_type'] = 'post';
        $post_id_1 = $this->factory->post->create_object( $args );

        $args = $this->factory->post->generate_args();
        $args['post_type'] = 'book';
        $post_id_2 = $this->factory->post->create_object( $args );

        $args = $this->factory->post->generate_args();
        $args['post_type'] = 'post';
        $post_id_3 = $this->factory->post->create_object( $args );

        $this->insert_data_tests();

        $confirmed_subscribers = wp_list_filter( $this->raw_subscribers, array( 'flag' => true ) );

        $max_subscriber_id = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->posts WHERE post_type='subscriber' AND post_status='publish'" );

        // First campaign (an immediately one)
        $settings = maybe_serialize( array( 'posts_ids' => array( $post_id_1 ) ) );

        $testing_logs = array(
            array(
                'mail_recipients' => 3,
                'max_subscriber_id' => $max_subscriber_id,
                'settings' => maybe_serialize( array( 'posts_ids' => array( $post_id_1 ) ) )
            ),
            array(
                'mail_recipients' => 1,
                'max_subscriber_id' => $max_subscriber_id - 2,
                'settings' => maybe_serialize( array( 'posts_ids' => array( $post_id_2, $post_id_3 ) ) )
            ),
            array(
                'mail_recipients' => count( $confirmed_subscribers ),
                'max_subscriber_id' => $max_subscriber_id - 1,
                'settings' => ''
            )
        );

        foreach ( $testing_logs as $key => $testing_log ) {
            extract( $testing_log );
            $wpdb->query( 
                "INSERT INTO $log_table (mail_subject, mail_recipients, mail_date, mail_settings, max_email_ID ) 
                VALUES ( 'New post', $mail_recipients, 1412775393, '$settings', $max_subscriber_id );"
            );

            $log_id = $wpdb->insert_id;

            // Delete the old log
            if ( ! is_dir( INCSUB_SBE_LOGS_DIR ) ) {
                wp_mkdir_p( INCSUB_SBE_LOGS_DIR );
            }

            $log_file = INCSUB_SBE_LOGS_DIR . '/sbe_log_' . $log_id . '.log';
            @unlink( $log_file );

            // Write the log (only already sent emails)
            reset( $confirmed_subscribers );
            for( $i = 0; $i < $mail_recipients; $i++ ) {
                $email = key( $confirmed_subscribers );
                next( $confirmed_subscribers );

                $date = current_time( 'timestamp' );
                $line = $email . '|' . time() . '|1';
                $fp = @fopen( $log_file, 'a+' );

                @fwrite( $fp, $line . "\n" );
                @fclose( $fp );
            }

            $testing_logs[ $key ]['log_id'] = $log_id;
        } 
        $queue_table = $wpdb->base_prefix . 'subscriptions_queue';

        // Testing the upgrade
        $subscribe_by_email_plugin->maybe_upgrade();        

        $queue_table = $wpdb->base_prefix . 'subscriptions_queue';
        $queue = $wpdb->get_results( "SELECT * FROM $queue_table" );

        $queue_items = array();
        foreach ( $queue as $item ) {
            $queue_items[] = incsub_sbe_get_queue_item( $item->id );
        }

        foreach ( $testing_logs as $testing_log ) {
            extract( $testing_log );
            $campaign_queue = wp_list_filter( $queue_items, array( 'campaign_id' => $log_id ) );
            $campaign_subscribers = $wpdb->get_var( "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type ='subscriber' AND post_status = 'publish' AND ID <= $max_subscriber_id" );
            $remain_campaign_subscribers = $campaign_subscribers - $mail_recipients;

            $sent_queue_items = wp_list_filter( $campaign_queue, array( 'sent_status' => 1 ) );
            $this->assertEquals( count( $sent_queue_items ), $mail_recipients );

            $pending_queue_items = wp_list_filter( $campaign_queue, array( 'sent_status' => 0 ) );
            $this->assertTrue( count( $pending_queue_items ) >= $campaign_subscribers - $mail_recipients );
        }

        foreach ( glob(INCSUB_SBE_LOGS_DIR . '/*') as $file ) {
            unlink( $file );
        }

        @rmdir( INCSUB_SBE_LOGS_DIR );

    }


}
