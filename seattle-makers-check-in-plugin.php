<?php
    /*
    Plugin Name: Seattle Makers Check-In Plugin
    Plugin URI: https://seattlemakers.org/
    Description: To display at front desk to allow people to check into the space
    Version: 0.0.1
    Author: Adi
    Author URI: https://github.com/adkeswani/
     */

function check_in_home($content)
{
    $content = "{$content}
        <script>
            function check_in_searched_email_on_input() 
            {
                let check_in_searched_email = document.getElementById(\"check_in_searched_email\");
                let check_in_submit = document.getElementById(\"check_in_submit\");

                if (check_in_searched_email.value.trim().length === 0) 
                {
                    check_in_submit.disabled = true;
                } 
                else 
                {
                    check_in_submit.disabled = false;
                }
            }
        </script>

        <style>
            .row {
                display: flex;
            }

            /* Create two equal columns that sits next to each other */
            .column {
                flex: 50%;
                padding: 5%;
                border: solid;
            }
        </style>

        <br>
        <div class = \"row\">
            <div class = \"column\">
                <h3>Check In</h3>
                <form action=\"/check-in/\" method=\"post\">
                <label for=\"check_in_searched_email\">Email: </label>
                <input type=\"text\" id=\"check_in_searched_email\" name=\"check_in_searched_email\" oninput=\"check_in_searched_email_on_input()\">
                <input type=\"submit\" id=\"check_in_submit\" value=\"Check in\" disabled=\"true\">
                </form>
                <br><br><br>
                <h6>First time in the space?</h6>
                <button onclick=\"window.open('/interest/','_blank')\">Visitor Registration</button>
                <button onclick=\"window.open('/memberships/#options/','_blank')\">Membership Sign-Up</button>
            </div>
            <div class = \"column\">
                <h3>Check Out</h3>
                <form action=\"/check-in/\" method=\"post\">";

    $check_ins = check_in_db_get_todays_check_ins();
    foreach($check_ins as $check_in)
    {
        $content = "{$content}<br><input type=\"submit\" id=\"check_out_{$check_in->user_id}\" name=\"check_out_{$check_in->user_id}\" value=\"{$check_in->display_name}\">";
    }

    $content = "{$content}</form></div></div>";
        
    // TODO: Show events below form

    return $content;
}

function check_in_success_user_found($content, $user)
{
    $content = check_in_add_title($content);
    check_in_db_add_check_in($user->ID);
    $content = "{$content}<br>Welcome, {$user->display_name}!";
    $content = check_in_add_redirect_to_home($content, 1);
    return $content;
}

function check_in_failure_no_user_found($content, $user_email)
{
    $content = check_in_add_title($content);
    $content = "{$content}<br>The email address \"{$user_email}\" was not found. Please register as a visitor or sign up for a membership.<br><br>
        <button onclick=\"window.open('/interest/','_blank')\">Visitor Registration</button>
        <button onclick=\"window.open('/memberships/#options/','_blank')\">Membership Sign-Up</button>";
    $content = check_in_add_redirect_to_home($content, 10);
    return $content;
}

function check_in_failure_multiple_users_found($content, $user_email)
{
    $content = check_in_add_title($content);
    $content = "{$content}<br>The email address \"{$user_email}\" is associated with multiple users. Please talk to the front desk.";
    $content = check_in_add_redirect_to_home($content, 10);
    return $content;
}

function check_in_failure_already_checked_in($content, $user_email, $display_name)
{
    $content = check_in_add_title($content);
    $content = "{$content}<br>{$display_name} ({$user_email}) is already checked in.";
    $content = check_in_add_redirect_to_home($content, 5);
    return $content;
}

function check_in_check_out($content, $user_id, $display_name)
{
    check_in_db_add_check_out($user_id);
    $content = check_in_add_title($content);
    $content = "{$content}<br>Goodbye, {$display_name}!";
    $content = check_in_add_redirect_to_home($content, 1);
    return $content;
}

function check_in_handle_search($content, $user_email)
{
    $users = check_in_db_find_users_by_email($user_email);
    $usersCount = count($users);

    if ($usersCount == 1)
    {
        // Prevent multiple check-ins by the same user
        $check_ins = check_in_db_get_todays_check_ins();
        foreach($check_ins as $check_in)
        {
            if ($check_in->user_id == $users[0]->ID)
            {
                return check_in_failure_already_checked_in($content, $user_email, $users[0]->display_name);
            }
        }

        return check_in_success_user_found($content, $users[0]);
    }

    if ($usersCount > 1)
    {
        return check_in_failure_multiple_users_found($content, $user_email);
    }

    return check_in_failure_no_user_found($content, $user_email);
}

function check_in_stats($content)
{
    $check_ins = check_in_db_get_check_ins_all();
    foreach($check_ins as $check_in)
    {
        foreach($check_in as $check_in_field => $check_in_value)
        {
            $content = "{$content}    {$check_in_field}:{$check_in_value}";
        }

        $content = "{$content}<br>";
    }

    return $content;
}

// Helpers

function check_in_add_redirect_to_home($content, $interval_in_seconds)
{
    // Intentionally using single-quoted strings and concatenation instead of string interpolation here.
    // Interpolation did not like the interval calculation in the javascript.
    $content = $content . '<br><br>Redirecting to check-in page in ' . $interval_in_seconds . 's...' .
        '<script> var timer = setTimeout(function() {window.location="/check-in/"},' . $interval_in_seconds * 1000 . '); </script>';
    return $content;
}

function check_in_add_title($content)
{
    return "{$content}<h1>Check In</h1><br>";
}

// DB helpers

function check_in_db_create_table()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Casing and double-space below are intentional for dbDelta to work
    $sql = "CREATE TABLE {$wpdb->base_prefix}sm_check_ins (
       id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
       user_id bigint(20) unsigned NOT NULL,
       check_in_time datetime NOT NULL,
       check_out_time datetime,
       PRIMARY KEY  (id)) {$charset_collate};";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    return $wpdb->last_error;
}

function check_in_db_drop_table()
{
    global $wpdb;
    $sql = $wpdb->prepare("DROP TABLE {$wpdb->base_prefix}sm_check_ins;");
    $results = $wpdb->get_results($sql);
    return $results;
}

function check_in_db_find_users_by_email($user_email)
{
    global $wpdb;
    $sql = $wpdb->prepare("
        SELECT ID, display_name, user_email
        FROM {$wpdb->base_prefix}users
        WHERE user_email = \"{$user_email}\"");

    $results = $wpdb->get_results($sql);
    return $results;
}

function check_in_db_add_check_in($user_id)
{
    global $wpdb;
    $sql = $wpdb->prepare("
        INSERT INTO {$wpdb->base_prefix}sm_check_ins (user_id, check_in_time)
        VALUES ({$user_id}, UTC_TIMESTAMP());");

    $results = $wpdb->get_results($sql);
    return $results;
}

function check_in_db_get_check_ins_all()
{
    global $wpdb;
    $sql = $wpdb->prepare("
        SELECT *
        FROM {$wpdb->base_prefix}sm_check_ins
        INNER JOIN {$wpdb->base_prefix}users 
        ON {$wpdb->base_prefix}sm_check_ins.user_id = {$wpdb->base_prefix}users.ID
        ORDER BY {$wpdb->base_prefix}sm_check_ins.id DESC
        LIMIT 1000;");

    $results = $wpdb->get_results($sql);
    return $results;
}

function check_in_db_get_todays_check_ins()
{
    // Gets the midnight that has already happened, e.g. if it is Monday 5PM it will return Monday 12AM
    $today_midnight = new DateTime('today midnight', new DateTimeZone('America/Los_Angeles'));
    $today_midnight->setTimezone(new DateTimeZone('UTC'));
    $today_midnight_utc_string = $today_midnight->format('Y-m-d H:i:s');

    // Get all users after midnight today. Double percentage signs escapes percentage signs for the prepare call.
    global $wpdb;
    $sql = $wpdb->prepare("
        SELECT {$wpdb->base_prefix}sm_check_ins.user_id, {$wpdb->base_prefix}users.display_name
        FROM {$wpdb->base_prefix}sm_check_ins
        INNER JOIN {$wpdb->base_prefix}users 
        ON {$wpdb->base_prefix}sm_check_ins.user_id = {$wpdb->base_prefix}users.ID
        WHERE {$wpdb->base_prefix}sm_check_ins.check_out_time IS NULL
        AND {$wpdb->base_prefix}sm_check_ins.check_in_time IS NOT NULL
        AND {$wpdb->base_prefix}sm_check_ins.check_in_time > STR_TO_DATE('{$today_midnight_utc_string}', '%%Y-%%m-%%d %%h:%%i:%%s')
        ORDER BY {$wpdb->base_prefix}users.display_name;");

    $results = $wpdb->get_results($sql);
    return $results;
}

function check_in_db_add_check_out($user_id)
{
    global $wpdb;
    $sql = $wpdb->prepare("
        UPDATE {$wpdb->base_prefix}sm_check_ins
        SET check_out_time = UTC_TIMESTAMP()
        WHERE check_out_time IS NULL
        AND user_id = {$user_id}
        ORDER BY check_in_time DESC
        LIMIT 1;");

    $results = $wpdb->get_results($sql);
    return $results;
}

// Filter

function check_in_filter($content) 
{
    if (($_SERVER['REQUEST_URI'] != '/check-in/') and ($_SERVER['REQUEST_URI'] != '/check-in-stats/'))
    {
        return $content;
    }

    if (!in_the_loop()) 
    {
        // Magic that prevents check-in code from being called more than once
        return $content;
    }

    if ($_SERVER['REQUEST_URI'] == '/check-in-stats/')
    {
        return check_in_stats($content);
    }

    foreach($_POST as $post_key => $post_value)
    {
        if (preg_match('/^check_out_([0-9]+)$/', $post_key, $matches))
        {
            return check_in_check_out($content, $matches[1], $post_value);
        }
    }

    if (array_key_exists('check_in_searched_email', $_POST))
    {
        return check_in_handle_search($content, $_POST['check_in_searched_email']);
    }

    return check_in_home($content);
}

// Activation, uninstallation

function check_in_activate()
{
    check_in_db_create_table();
}

function check_in_uninstall()
{
    check_in_db_drop_table();
}

// Register hooks and filters

add_filter('the_content', 'check_in_filter');
register_activation_hook(__FILE__, 'check_in_activate');
register_uninstall_hook(__FILE__, 'check_in_uninstall');
