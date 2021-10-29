<?php
/**
 * Plugin Name: Wordpress Webhook Posts
 * Plugin URI: https://github.com/ahsanwtc/wordpress-webook-posts
 * Description: A plugin to listen to a web hook to automatically create a post
 * Version: 1.0
 * Author: jsan
 * Author URI: https://iamahsan.dev
 */

class WordpressWebhookPosts {
  function __construct () {
    add_action('admin_menu', array($this, 'adminPage'));
    add_action('admin_init', array($this, 'settings'));
    add_action('rest_api_init', array($this, 'endpointInit'));
    // add_action('init', 'webhookListener');
  }

  public function adminPage () {
    add_options_page(
      'Wordpress Webhook Posts Settings',
      'WP Webhook',
      'manage_options',
      'wp-webhook-posts-settings-page',
      [$this, 'adminHTML']
    );
  }

  function adminHTML () { ?>
    <div class="wrap">
      <h1>Wordpress Webhook Posts Settings</h1>
      <form action="options.php" method="POST">
        <?php
          settings_fields('wpwhplugin');
          do_settings_sections('wp-webhook-posts-settings-page');
          submit_button();
        ?>
      </form>
    </div>
  <?php }

  function settings () {
    add_settings_section('wpwh_first_section', null, null, 'wp-webhook-posts-settings-page');

    add_settings_field('wpwh_category', 'Select category', array($this, 'categoryHTML'), 'wp-webhook-posts-settings-page', 'wpwh_first_section');
    register_setting('wpwhplugin', 'wpwh_category', ['sanitize_callback' => 'sanitize_text_field', 'default' => '0']);

    add_settings_field('wpwh_user', 'Select user to author posting', array($this, 'userHTML'), 'wp-webhook-posts-settings-page', 'wpwh_first_section');
    register_setting('wpwhplugin', 'wpwh_user', ['sanitize_callback' => 'sanitize_text_field', 'default' => '1']);

    add_settings_field('wpwh_status', 'Select Posts status', array($this, 'statusHTML'), 'wp-webhook-posts-settings-page', 'wpwh_first_section');
    register_setting('wpwhplugin', 'wpwh_status', ['sanitize_callback' => 'sanitize_text_field', 'default' => 'draft']);
  }

  function categoryHTML () {
    $categories = get_categories(['parent' => 0, 'hide_empty' => false]); ?>
    <select name="wpwh_category">
    <?php
      foreach($categories as $category) {?>
        <option value="<?php echo $category->term_id; ?>" <?php selected(get_option('wpwh_category'), $category->term_id); ?>>
          <?php echo esc_html($category->name); ?>
        </option>
      <?php } ?>
    </select>

  <?php
  }

  function userHTML () {
    $users = get_users(['role__in' => ['author']]); ?>
    <select name="wpwh_user">
    <?php
      foreach($users as $user) {?>
        <option value="<?php echo $user->ID; ?>" <?php selected(get_option('wpwh_user'), $user->ID); ?>>
          <?php echo esc_html($user->display_name); ?>
        </option>
      <?php } ?>
    </select>

  <?php
  }

  function statusHTML () { ?>
    <select name="wpwh_status">
      <option value="publish" <?php selected(get_option('wpwh_status'), 'publish'); ?>>Publish</option>
      <option value="draft" <?php selected(get_option('wpwh_status'), 'draft'); ?>>Draft</option>
    </select>

  <?php
  }

  public function webhookListener ($request) {
    $data = [];
    $namedValues = $request['namedValues'];

    $postType = 'post';
    $userID = get_option('wpwh_user');
    $categoryID = get_option('wpwh_category');
    $postStatus = get_option('wpwh_status');
    $leadContent = $namedValues['Please share story and/or impressions of your struggles with us'][0];
    $timeStamp = date('Y-m-d H:i:s');
    
    $leadContent = trim(preg_replace('/\s+/', ' ', $leadContent));
    $leadContent = esc_html($leadContent);
    $leadTitle = substr($leadContent, 0, 15);
    $leadContent = "<p>" . $leadContent . "</p>";

    $data['postType'] = $postType;
    $data['userID'] = $userID;
    $data['categoryID'] = $categoryID;
    $data['postStatus'] = $postStatus;
    $data['leadTitle'] = $leadTitle;
    $data['leadContent'] = $leadContent; 

    $new_post = [
      'post_title' => $leadTitle,
      'post_content' => $leadContent,
      'post_status' => $postStatus,
      'post_date' => $timeStamp,
      'post_author' => $userID,
      'post_type' => $postType,
      'post_category' => array($categoryID)
    ];

    $post_id = wp_insert_post($new_post);
    $data['post_id'] = $post_id;

    // print_r($json);
    $response = new WP_REST_Response($data);
    $response->set_status(200);
    return $response;

  }

  public function endpointInit () {
    register_rest_route('wpwhplugin/v1', 'stories', [
      'methods' => 'POST',
      'callback' => [$this, 'webhookListener']
    ]);
  }




}

$wordpressWebhookPosts = new WordpressWebhookPosts();