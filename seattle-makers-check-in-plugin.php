<?php
    /*
    Plugin Name: Seattle Makers Check-In Plugin
    Plugin URI: https://github.com/seattlemakers/check-in/
    Description: To display at front desk to allow people to check into the space
    Version: 1.0
    Author: Adi
    Author URI: https://github.com/adkeswani/
     */

// This is a clone of the Interest + Waiver Form that redirects back to the check-in page when form is completed. Use _pp_form_6b326938daaffa3b443ad295f8168d61 for the dev site, _pp_form_fa63fcd59261ceaaa06157028432de5f for prod.
$VISITOR_REGISTRATION_FORM_EMBED = '[pauf id=\"_pp_form_fa63fcd59261ceaaa06157028432de5f\']';

$UNKNOWN_MEMBERSHIP_STATUS = 0;
$ACTIVE_MEMBERSHIP_STATUS = 1;
$EXPIRED_MEMBERSHIP_STATUS = 2;
$VISITOR_MEMBERSHIP_STATUS = 3;
$VOLUNTEER_MEMBERSHIP_STATUS = 4;

$ACTIVE_ITEM_STATUS = 'active';
$VOLUNTEER_ITEM_IDS = [1234, 5678, 91011];

$ITEM_ID_KEY = '_pp_item_id';
$ITEM_STATUS_KEY = '_pp_item_status';

$SM_CHECK_IN_PLUGIN_DB_VERSION = 2;
$SM_CHECK_IN_PLUGIN_DB_VERSION_OPTION_NAME = 'sm_check_in_plugin_db_version';

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

            // Automatically refresh at 1AM to clear out the previous day's check-ins.
            // Experimental. This may not work if machine or tab goes to sleep.
            var now = new Date();

            // 1AM the next day
            var refreshTime = new Date(
                now.getFullYear(),
                now.getMonth(),
                now.getDate() + 1, 
                1, 0, 0
            );

            var msTillRefresh = refreshTime.getTime() - now.getTime();
            setTimeout(() => { document.location.reload(); }, msTillRefresh);
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

            .check-ins-table {
                padding: 5px;
                border: none;
                text-align: left;
            }
        </style>

        <br>
        <div class = \"row\">
            <div class = \"column\">
                <h3>Check In</h3>
                <form action=\"/check-in/\" method=\"post\">
                <label for=\"check_in_searched_email\">Email address you registered with: </label>
                <input type=\"text\" id=\"check_in_searched_email\" name=\"check_in_searched_email\" oninput=\"check_in_searched_email_on_input()\">
                <input type=\"submit\" id=\"check_in_submit\" value=\"Check in\" disabled=\"true\">
                <br><br><br>
                <h6>Not already registered?</h6>
                <input type=\"submit\" id=\"check_in_visitor_registration\" name=\"check_in_visitor_registration\" value=\"Visitor Registration\">
                <button onclick=\"window.open('/memberships/','_blank')\">Membership Sign-Up</button>
            </div>
            <div class = \"column\">
                <h3>Who's In The Space</h3>
                Click on your name to check out.<br><br>
                <table class=\"check-ins-table\"><tbody>";

    $check_ins = check_in_db_get_todays_check_ins();
    $check_ins_counter = 0;
    foreach($check_ins as $check_in)
    {
        // Show two buttons per line
        if ($check_ins_counter % 2 == 0)
        {
            $content = "{$content}<tr class=\"check-ins-table\">";
        }

        // Had to set the button style instead of using CSS class because it was being overridden by Wordpress theme
        $check_in_button_style = 'background-color: #';
        if ($check_in->membership_status == $GLOBALS['ACTIVE_MEMBERSHIP_STATUS'])
        {
            $check_in_button_style = "{$check_in_button_style}13723C"; // Dark green
        }
        elseif ($check_in->membership_status == $GLOBALS['EXPIRED_MEMBERSHIP_STATUS'])
        {
            $check_in_button_style = "{$check_in_button_style}FC7272"; // Pale red
        }
        elseif ($check_in->membership_status == $GLOBALS['VISITOR_MEMBERSHIP_STATUS'])
        {
            $check_in_button_style = "{$check_in_button_style}AD7CFC"; // Lavender
}
        elseif ($check_in->membership_status == $GLOBALS['VOLUNTEER_MEMBERSHIP_STATUS'])
        {
            $check_in_button_style = "{$check_in_button_style}ABF7EB"; // Light turqouise
        }
        else
        {
            // For unknown or invalid status, just pretend they are active. 
            // When we transition to using these colors, old check-ins will be set to unknown status.
            $check_in_button_style = "{$check_in_button_style}13723C"; // Dark green
        }

        $content = "{$content}<td class=\"check-ins-table\" align=\"left\"><input style=\"{$check_in_button_style}\" type=\"submit\" id=\"check_out_{$check_in->user_id}\" name=\"check_out_{$check_in->user_id}\" value=\"{$check_in->display_name}\"></td>";

        // Show two buttons per line
        if ($check_ins_counter % 2 == 1)
        {
            $content = "{$content}</tr>";
        }

        $check_ins_counter += 1;
    }

    $content = "{$content}</tbody></table></form></div></div>";
        
    // TODO: Show events below form

    return $content;
}

function check_in_visitor_registration($content)
{
    $content = check_in_add_idle_redirect($content);
    $content = "{$content}
        <h1>Visitor Registration</h1><br>
        <br><br><button onclick=\"window.open('/check-in/', '_self')\">Return to check-in page</button>
        {$GLOBALS['VISITOR_REGISTRATION_FORM_EMBED']}";
    return $content;
}

function check_in_success_user_found($content, $user)
{
    $payment_plans = check_in_db_get_user_payment_plans($user->ID);
    $membership_status = get_membership_status_from_payment_plans($payment_plans);

    check_in_db_add_check_in($user->ID, $membership_status);

    $content = check_in_add_title($content);
    $content = "{$content}<br>Welcome, {$user->display_name}!";
    $redirect_time = 1;

    if ($membership_status == $GLOBALS['EXPIRED_MEMBERSHIP_STATUS'])
    {
        $content = "{$content}<br><div style=\"color:red; font-weight:bold;\">Your membership is expired. Please ensure that your payment details are correct and see the front desk.</div>";
        $redirect_time = 5;
    }
    elseif ($membership_status == $GLOBALS['VISITOR_MEMBERSHIP_STATUS'])
    {
        $content = "{$content}<br><div style=\"font-weight:bold;\">Please note that membership or a guest pass is required for tool use.</div>";
        $redirect_time = 3;
    }

    $content = check_in_add_redirect_to_home($content, $redirect_time);
    return $content;
}

function check_in_failure_no_user_found($content, $user_email)
{
    $content = check_in_add_idle_redirect($content);
    $content = check_in_add_title($content);
    $content = "{$content}<br>The email address \"{$user_email}\" was not found. Please register as a visitor below or:<br><br>
        <button onclick=\"window.open('/memberships/', '_blank')\">Sign up as a member</button>
        <button onclick=\"window.open('/check-in/', '_self')\">Return to check-in page</button><br><br>
        {$GLOBALS['VISITOR_REGISTRATION_FORM_EMBED']}";
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

    $content = "{$content}<h1>Check Out</h1><br>";
    $content = "{$content}<br>Goodbye, {$display_name}!";
    $content = check_in_add_redirect_to_home($content, 1);
    return $content;
}

function check_in_handle_search($content, $user_email)
{
    $users = check_in_db_find_users_by_email($user_email);
    $usersCount = count($users);

    if ($usersCount == 0)
    {
        return check_in_failure_no_user_found($content, $user_email);
    }

    if ($usersCount > 1)
    {
        return check_in_failure_multiple_users_found($content, $user_email);
    }

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

function check_in_stats($content)
{
    $check_ins = check_in_db_get_check_ins_all();
    $printed_fields = false;
    foreach($check_ins as $check_in)
    {
        if (!$printed_fields)
        {
            foreach($check_in as $check_in_field => $check_in_value)
            {
                $content = "{$content}{$check_in_field},";
            }

            $content = rtrim($content, ',');
            $content = "{$content}<br>\n";
            $printed_fields = true;
        }

        foreach($check_in as $check_in_field => $check_in_value)
        {
            $content = "{$content}{$check_in_value},";
        }

        $content = rtrim($content, ',');
        $content = "{$content}<br>\n";
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

function check_in_add_idle_redirect($content)
{
    // Copied from https://stackoverflow.com/questions/5631307/redirect-user-after-60-seconds-of-idling-inactivity
    // Used on check-in page interest form to redirect to check-in home page if user abandons registration.
    return "{$content}<script>
        (function() 
        {
            const idleDurationInSeconds = 30;    // X number of seconds
            const redirectUrl = '/check-in/';  // Redirect idle users to this URL
            let idleTimeout; // Variable to hold the timeout, do not modify

            const resetIdleTimeout = function() 
            {
                // Clears the existing timeout
                if(idleTimeout) clearTimeout(idleTimeout);

                // Set a new idle timeout to load the redirectUrl after idleDurationSecs
                idleTimeout = setTimeout(() => location.href = redirectUrl, idleDurationInSeconds * 1000);
            };

            // Init on page load
            resetIdleTimeout();

            // Reset the idle timeout on any of the events listed below
            ['click', 'touchstart', 'mousemove', 'keydown', 'wheel', 'DOMMouseScroll', 'mousewheel', 'mousedown', 'touchmove', 'MSPointerDown', 'MSPointerMove'].forEach(evt => 
                document.addEventListener(evt, resetIdleTimeout, false)
            );
        })();
    </script>";
}

function get_membership_status_from_payment_plans($payment_plans)
{
    $membership_status = $GLOBALS['UNKNOWN_MEMBERSHIP_STATUS'];

    // If no payment plans, they're a guest or visitor
    if (count($payment_plans) == 0)
    {
        $membership_status = $GLOBALS['VISITOR_MEMBERSHIP_STATUS'];
    }
    else
    {
        // Look through all payment plans for active ones
        foreach($payment_plans as $plan)
        {
            if ($plan[$GLOBALS['ITEM_STATUS_KEY']] == $GLOBALS['ACTIVE_ITEM_STATUS'])
            {
                $membership_status = $GLOBALS['ACTIVE_MEMBERSHIP_STATUS'];

                // If the active payment plan is also a volunteer plan, the member is a volunteer and we can stop iterating.
                // Otherwise we must keep iterating in case there is an active volunteer plan.
                if (array_key_exists($plan[$GLOBALS['ITEM_ID_KEY']], $GLOBALS['VOLUNTEER_ITEM_IDS']))
                {
                    $membership_status = $GLOBALS['VOLUNTEER_MEMBERSHIP_STATUS'];
                    break;
                }
            }
        }

        // No active payment plan was found. Membership has expired.
        if ($membership_status == $GLOBALS['UNKNOWN_MEMBERSHIP_STATUS'])
        {
            $membership_status = $GLOBALS['EXPIRED_MEMBERSHIP_STATUS'];
        }
    }

    return $membership_status;
}

// DB helpers

function check_in_db_create_or_update_table()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Casing and double-space below are intentional for dbDelta to work
    $sql = "CREATE TABLE {$wpdb->base_prefix}sm_check_ins (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        check_in_time datetime NOT NULL,
        check_out_time datetime,
        membership_status tinyint unsigned NOT NULL,
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

function check_in_db_add_check_in($user_id, $membership_status)
{
    global $wpdb;
    $sql = $wpdb->prepare("
        INSERT INTO {$wpdb->base_prefix}sm_check_ins (user_id, check_in_time, membership_status)
        VALUES ({$user_id}, UTC_TIMESTAMP(), {$membership_status});");

    $results = $wpdb->get_results($sql);
    return $results;
}

function check_in_db_get_check_ins_all()
{
    $limit = 1000;
    if (isset($_GET['limit'])) 
    {
        $limit = intval($_GET['limit']);
        if ($limit <= 0)
        {
            $limit = 1000;
        }
    }

    $offset = 0;
    if (isset($_GET['offset'])) 
    {
        $offset = intval($_GET['offset']);
        if ($offset < 0)
        {
            $offset = 0;
        }
    }

    global $wpdb;
    $sql = $wpdb->prepare("
        SELECT *
        FROM {$wpdb->base_prefix}sm_check_ins
        ORDER BY {$wpdb->base_prefix}sm_check_ins.id DESC
        LIMIT {$limit} OFFSET {$offset};");

    // Old query with user details included
    /*
    $sql = $wpdb->prepare("
        SELECT *
        FROM {$wpdb->base_prefix}sm_check_ins
        INNER JOIN {$wpdb->base_prefix}users 
        ON {$wpdb->base_prefix}sm_check_ins.user_id = {$wpdb->base_prefix}users.ID
        ORDER BY {$wpdb->base_prefix}sm_check_ins.id DESC
        LIMIT {$limit};");
     */

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
        SELECT {$wpdb->base_prefix}sm_check_ins.user_id, {$wpdb->base_prefix}users.display_name, {$wpdb->base_prefix}sm_check_ins.membership_status
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

function check_in_db_get_user_payment_plans($user_id)
{
    global $wpdb;
    $sql = $wpdb->prepare("
        SELECT {$wpdb->base_prefix}posts.ID, {$wpdb->base_prefix}postmeta.meta_key, {$wpdb->base_prefix}postmeta.meta_value
        FROM {$wpdb->base_prefix}posts
        INNER JOIN {$wpdb->base_prefix}postmeta
        ON {$wpdb->base_prefix}postmeta.post_id = {$wpdb->base_prefix}posts.ID
        WHERE {$wpdb->base_prefix}posts.post_author = {$user_id}
        AND {$wpdb->base_prefix}posts.post_type = 'pp_plan'
        AND ({$wpdb->base_prefix}postmeta.meta_key = '{$GLOBALS['ITEM_STATUS_KEY']}' OR {$wpdb->base_prefix}postmeta.meta_key = '{$GLOBALS['ITEM_ID_KEY']}');");

    $results = $wpdb->get_results($sql);

    $payment_plans = array();
    foreach($results as $result)
    {
        if (!array_key_exists($result->ID, $payment_plans))
        {
            $payment_plans[$result->ID] = array();
        }

        $payment_plans[$result->ID][$result->meta_key] = $result->meta_value;
    }

    return $payment_plans;
}

// Filter

function check_in_filter($content) 
{
    $url = strtok($_SERVER['REQUEST_URI'], '?');
    if (($url != '/check-in/') and ($url != '/check-in-stats/'))
    {
        return $content;
    }

    // Ensure database schema is up-to-date
    $db_version = get_option($GLOBALS['SM_CHECK_IN_PLUGIN_DB_VERSION_OPTION_NAME']);
    if ($dbVersion != $GLOBALS['SM_CHECK_IN_PLUGIN_DB_VERSION'])
    {
        check_in_db_create_or_update_table();
        update_option($GLOBALS['SM_CHECK_IN_PLUGIN_DB_VERSION_OPTION_NAME'], $GLOBALS['SM_CHECK_IN_PLUGIN_DB_VERSION']);
    }

    // Returns false if password hasn't been entered yet.
    // On false we return the original content, which includes the password entry box.
    // Password uses a cookie so we will only need to enter it once.
    if (post_password_required())
    {
        return $content;
    }

    if (!in_the_loop()) 
    {
        // Magic that prevents check-in code from being called more than once
        return $content;
    }

    if ($url == '/check-in-stats/')
    {
        return check_in_stats($content);
    }

    foreach($_POST as $post_key => $post_value)
    {
        if (preg_match('/^check_out_([0-9]+)$/', $post_key, $matches))
        {
            return check_in_check_out($content, $matches[1], $post_value);
        }

        if ($post_key == 'check_in_visitor_registration')
        {
            return check_in_visitor_registration($content);
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
    check_in_db_create_or_update_table();
}

function check_in_uninstall()
{
    // Do you really want to do this?
    //check_in_db_drop_table();
}

// Register hooks and filters

add_filter('the_content', 'check_in_filter');
register_activation_hook(__FILE__, 'check_in_activate');
register_uninstall_hook(__FILE__, 'check_in_uninstall');
