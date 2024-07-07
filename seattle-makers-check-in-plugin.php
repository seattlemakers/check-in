<?php
    /*
    Plugin Name: Seattle Makers Check-In Plugin
    Plugin URI: https://github.com/seattlemakers/check-in/
    Description: To display at front desk to allow people to check into the space
    Version: 2.0
    Author: Adi
    Author URI: https://github.com/adkeswani/
     */

// This is a clone of the Interest + Waiver Form that redirects back to the check-in page when form is completed. Use _pp_form_6b326938daaffa3b443ad295f8168d61 for the dev site, _pp_form_fa63fcd59261ceaaa06157028432de5f for prod.
$VISITOR_REGISTRATION_FORM_EMBED = '[pauf id="_pp_form_fa63fcd59261ceaaa06157028432de5f"]';

$UNKNOWN_MEMBERSHIP_STATUS = 0;
$ACTIVE_MEMBERSHIP_STATUS = 1;
$EXPIRED_MEMBERSHIP_STATUS = 2;
$VISITOR_MEMBERSHIP_STATUS = 3;
$VOLUNTEER_MEMBERSHIP_STATUS = 4;
$PAUSED_MEMBERSHIP_STATUS = 5;

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
                <form action=\"/check-in/\" method=\"post\" onsubmit=\"check_in_submit.disabled = true; return true;\">
                <label for=\"check_in_searched_email\">Email address you registered with: </label>
                <input type=\"text\" id=\"check_in_searched_email\" name=\"check_in_searched_email\" oninput=\"check_in_searched_email_on_input()\">
                <input type=\"submit\" id=\"check_in_submit\" name=\"check_in_submit\" value=\"Check in\" disabled=\"true\">
                <br><br><br>
                <h6>Not already registered?</h6>
                <input type=\"submit\" id=\"check_in_visitor_registration\" name=\"check_in_visitor_registration\" value=\"Visitor Registration\">
                <button type=\"button\" onclick=\"window.open('/memberships/','_blank')\">Membership Sign-Up</button>
            </div>
            <div class = \"column\">
                <h3>Who's In The Space</h3>";

    $check_ins = check_in_db_get_todays_check_ins();
    $content = $content . 'Click on your name to check out.<br><br>';

    // Add Maketeers table
    $content = $content . '<h6>Maketeers:</h6>';
    $content = check_in_add_check_ins_table($content, $check_ins, true);

    // Add remaining members table
    $content = $content . '<h6>Members:</h6>';
    $content = check_in_add_check_ins_table($content, $check_ins, false);

    // Add key
    $content = $content . '<h6>Key:</h6>';
    $content = $content . '<span style="color:white; background-color:' . check_in_get_color_for_membership_status($GLOBALS['VOLUNTEER_MEMBERSHIP_STATUS']) . '">Maketeer</span>, ';
    $content = $content . '<span style="color:white; background-color:' . check_in_get_color_for_membership_status($GLOBALS['ACTIVE_MEMBERSHIP_STATUS']) . '">Active</span>, ';
    $content = $content . '<span style="color:white; background-color:' . check_in_get_color_for_membership_status($GLOBALS['EXPIRED_MEMBERSHIP_STATUS']) . '">Expired or Paused</span>, ';
    $content = $content . '<span style="color:white; background-color:' . check_in_get_color_for_membership_status($GLOBALS['VISITOR_MEMBERSHIP_STATUS']) . '">Visitor or Guest</span>';

    $content = $content . '</form></div></div>';

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
    $membership_status = check_in_get_membership_status_from_payment_plans($payment_plans);

    // Redirect volunteers to a page where they can select whether
    // they want to appear as a member or a volunteer on the check-in page.
    if ($membership_status == $GLOBALS['VOLUNTEER_MEMBERSHIP_STATUS'])
    {
        return check_in_success_volunteer_found($content, $user);
    }

    check_in_db_add_check_in($user->ID, $membership_status);

    $content = check_in_add_title($content);
    $content = "{$content}<br>Welcome, {$user->display_name}!";
    $redirect_time = 1;

    if ($membership_status == $GLOBALS['EXPIRED_MEMBERSHIP_STATUS'])
    {
        $content = "{$content}<br><div style=\"color:red; font-weight:bold;\">Your membership is expired. Please ensure that your payment details are correct and see the front desk.</div>";
        $redirect_time = 5;
    }
    elseif ($membership_status == $GLOBALS['PAUSED_MEMBERSHIP_STATUS'])
    {
        $content = "{$content}<br><div style=\"color:red; font-weight:bold;\">Your membership is paused. Please see the front desk to resume it.</div>";
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

function check_in_success_volunteer_found($content, $user)
{
    $content = check_in_add_title($content);
    $content = $content .
        '<form action="/check-in/" method="post" onsubmit="document.getElementById(\'check_in_volunteer_as_volunteer\').style.visibility = \'hidden\'; document.getElementById(\'check_in_volunteer_as_member\').style.visibility = \'hidden\'; document.getElementById(\'automatically_checking_in_message\').style.visibility = \'hidden\'; return true;"\>
            <input type="submit" id="check_in_volunteer_as_volunteer" name="check_in_volunteer_as_volunteer" value="Check in as Maketeer" style="color:white; background-color:' . check_in_get_color_for_membership_status($GLOBALS['VOLUNTEER_MEMBERSHIP_STATUS']) . '">&emsp;&emsp;
            <input type="submit" id="check_in_volunteer_as_member" name="check_in_volunteer_as_member" value="Check in as Member" style="color:white; background-color:' . check_in_get_color_for_membership_status($GLOBALS['ACTIVE_MEMBERSHIP_STATUS']) . '">
            <input type="hidden" id="volunteer_email" name="volunteer_email" value="' . $user->user_email . '">
        </form>
        <script> setTimeout(function() { document.querySelector(\'[name="check_in_volunteer_as_volunteer"]\').click(); }, 5000); </script>
        <br><br><div id="automatically_checking_in_message">Checking in as Maketeer in 5s...</div>';

    return $content;
}

function check_in_success_volunteer_add_selected_check_in($content, $volunteer_email, $check_in_as_volunteer)
{
    $content = check_in_add_title($content);

    // Find user by email. We should find one and only one. Bail out if we don't.
    $users = check_in_db_find_users_by_email($volunteer_email);
    if (count($users) != 1)
    {
        $content = "Something went wrong. Please try checking in again.";

        $redirect_time = 5;
        $content = check_in_add_redirect_to_home($content, $redirect_time);

        return $content;
    }

    $user = $users[0];
    $membership_status = $check_in_as_volunteer ? $GLOBALS['VOLUNTEER_MEMBERSHIP_STATUS'] : $GLOBALS['ACTIVE_MEMBERSHIP_STATUS'];
    check_in_db_add_check_in($user->ID, $membership_status);

    $content = "{$content}<br>Checking in {$user->display_name} as " . ($check_in_as_volunteer ? "Maketeer!" : "Member!");

    $redirect_time = 1;
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
    $users_count = count($users);

    if ($users_count == 0)
    {
        return check_in_failure_no_user_found($content, $user_email);
    }

    if ($users_count > 1)
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

function check_in_get_membership_status_from_payment_plans($payment_plans)
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
            if ($plan[$GLOBALS['ITEM_STATUS_KEY']] == 'active')
            {
                $membership_status = $GLOBALS['ACTIVE_MEMBERSHIP_STATUS'];

                // If the active payment plan is also a volunteer or paused plan, we know the status can stop iterating.
                // Otherwise we must keep iterating in case there is an active volunteer or paused plan.
                if ($plan[$GLOBALS['ITEM_ID_KEY']] == 23956)
                {
                    $membership_status = $GLOBALS['VOLUNTEER_MEMBERSHIP_STATUS'];
                    break;
                }
                elseif ($plan[$GLOBALS['ITEM_ID_KEY']] == 73733)
                {
                    $membership_status = $GLOBALS['PAUSED_MEMBERSHIP_STATUS'];
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

function check_in_get_color_for_membership_status($membership_status)
{
    switch ($membership_status)
    {
    case $GLOBALS['ACTIVE_MEMBERSHIP_STATUS']:
        return "#13723C"; // Dark green
    case $GLOBALS['EXPIRED_MEMBERSHIP_STATUS']:
        return "#FC7272"; // Pale red
    case $GLOBALS['VISITOR_MEMBERSHIP_STATUS']:
        return "#AD7CFC"; // Lavender
    case $GLOBALS['VOLUNTEER_MEMBERSHIP_STATUS']:
        return "#2DBD45"; // Bright green
    case $GLOBALS['PAUSED_MEMBERSHIP_STATUS']:
        return "#FC7272"; // Pale red
    default:
        // For unknown or invalid status, just pretend they are active. 
        // When we transition to using these colors, old check-ins will be set to unknown status.
        return "#13723C"; // Dark green
    }
}

function check_in_add_check_ins_table($content, $check_ins, $volunteers_only)
{
    $content = $content . '<table class="check-ins-table"><tbody>';

    $check_ins_counter = 0;
    foreach($check_ins as $check_in)
    {
        if ($volunteers_only != ($check_in->membership_status == $GLOBALS['VOLUNTEER_MEMBERSHIP_STATUS']))
        {
            continue;
        }

        // Show two buttons per line
        if ($check_ins_counter % 2 == 0)
        {
            $content = "{$content}<tr class=\"check-ins-table\">";
        }

       // Had to set the button style instead of using CSS class because it was being overridden by Wordpress theme
        $check_in_button_style = 'background-color:' . check_in_get_color_for_membership_status($check_in->membership_status);

        $content = "{$content}<td class=\"check-ins-table\" align=\"left\"><input style=\"{$check_in_button_style}\" type=\"submit\" id=\"check_out_{$check_in->user_id}\" name=\"check_out_{$check_in->user_id}\" value=\"{$check_in->display_name}\"></td>";

        // Show two buttons per line
        if ($check_ins_counter % 2 == 1)
        {
            $content = "{$content}</tr>";
        }

        $check_ins_counter += 1;
    }

    $content = $content . '</tbody></table>';
    return $content;
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
    if ($db_version != $GLOBALS['SM_CHECK_IN_PLUGIN_DB_VERSION'])
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

        // Handle the form that allows volunteers to check in as a volunteer or a member.
        if (($post_key == 'check_in_volunteer_as_volunteer') || ($post_key == 'check_in_volunteer_as_member'))
        {
            // volunteer_email should have been sent as part of the same form. If not, just fall through.
            if (array_key_exists('volunteer_email', $_POST))
            {
                return check_in_success_volunteer_add_selected_check_in($content, $_POST["volunteer_email"], ($post_key == 'check_in_volunteer_as_volunteer'));
            }
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
