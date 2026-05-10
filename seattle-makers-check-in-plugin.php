<?php
    /*
    Plugin Name: Seattle Makers Check-In Plugin
    Plugin URI: https://github.com/seattlemakers/check-in/
    Description: To display at front desk to allow people to check into the space
    Version: 2.4
    Author: Adi
    Author URI: https://github.com/adkeswani/
     */

/*
Changelog:
### v2.5 - 2026-05-10
- Allow members to select up to 4 categories (multi-select with toggle buttons)
- Store multiple categories pipe-delimited in database
- Display multi-category ring using conic-gradient on check-in display buttons
- Volunteers with multiple mapped categories show conic-gradient ring
- Add stats dashboard with bar chart (check-ins per day) and pie chart (check-ins by space)
- Dashboard supports time range selection (1-21 days) and group filtering

### v2.4 - 2026-04-26
- Add category selection with icons when members check in
- Store selected category in database
- Show category color as ring on check-in display buttons

### v2.3 - 2026-04-25
- Redesign staff/volunteer buttons: white background, black border, category color dots
- Add category-to-color and email-to-category mappings for maketeers
- Update legend to show all category dots

### v2.2 - 2026-04-12
- Split guests into their own group below members on the check-in display
- Note: Use "nobody@nobody.nbd" as a test Guest user on the dev site

### v2.1 - 2025-06-08
- Detect volunteers and staff using user metadata instead of payment plans
- Display border to distinguish between staff and volunteers
*/

// This is a clone of the Interest + Waiver Form that redirects back to the check-in page when form is completed. Use _pp_form_6b326938daaffa3b443ad295f8168d61 for the dev site, _pp_form_fa63fcd59261ceaaa06157028432de5f for prod.
$VISITOR_REGISTRATION_FORM_EMBED = '[pauf id="_pp_form_fa63fcd59261ceaaa06157028432de5f"]';

$UNKNOWN_MEMBERSHIP_STATUS = 0;
$ACTIVE_MEMBERSHIP_STATUS = 1;
$EXPIRED_MEMBERSHIP_STATUS = 2;
$VISITOR_MEMBERSHIP_STATUS = 3;
$VOLUNTEER_MEMBERSHIP_STATUS = 4;
$PAUSED_MEMBERSHIP_STATUS = 5;
$STAFF_MEMBERSHIP_STATUS = 6;

$CHECKIN_GROUP_STAFF_VOLUNTEER = 'staff_volunteer';
$CHECKIN_GROUP_MEMBER = 'member';
$CHECKIN_GROUP_GUEST = 'guest';

$ITEM_ID_KEY = '_pp_item_id';
$ITEM_STATUS_KEY = '_pp_item_status';

$USER_METADATA_VOLUNTEER_STATUS_KEY = '007ccabca25c28e32048aaec5ecc18e0';
$USER_METADATA_VOLUNTEER_ROLES_KEY = 'ppu_roles_1506378218';

$SM_CHECK_IN_PLUGIN_DB_VERSION = 4;
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


    // Add Staff/Maketeers table
    $content .= '<h6 style="display:inline-block; margin-bottom: 0.692em">Staff</h6><h6 style="display:inline-block; margin-bottom: 0.692em">&nbsp;/&nbsp;<t></h6><h6 style="display:inline-block; margin-bottom: 0.692em">Maketeers:</h6>';
    $content = check_in_add_check_ins_table_group($content, $check_ins, $GLOBALS['CHECKIN_GROUP_STAFF_VOLUNTEER']);

    // Add Members table
    $content .= '<h6>Members:</h6>';
    $content = check_in_add_check_ins_table_group($content, $check_ins, $GLOBALS['CHECKIN_GROUP_MEMBER']);

    // Add Guests table
    $content .= '<h6>Guests:</h6>';
    $content = check_in_add_check_ins_table_group($content, $check_ins, $GLOBALS['CHECKIN_GROUP_GUEST']);

    // Add key
    $content .= '<h6>Key:</h6>';
    $category_colors = $GLOBALS['CATEGORY_COLORS'];
    foreach ($category_colors as $category_name => $category_hex) {
        if ($category_name === 'None') continue;
        $content .= '<span style="display:inline-block; white-space:nowrap; margin-right:1em;"><span style="display:inline-block; width:10px; height:10px; border-radius:50%; background-color:' . $category_hex . '; vertical-align:middle; margin-right:3px;"></span>' . $category_name . '</span>';
    }
    $content .= '<br>';
    $content .= '<span style="display:inline-block; white-space:nowrap; margin-right:1em;"><span style="display:inline-block; width:10px; height:10px; border-radius:50%; background-color:' . check_in_get_color_for_membership_status($GLOBALS['VISITOR_MEMBERSHIP_STATUS']) . '; vertical-align:middle; margin-right:3px;"></span>Visitor/Guest</span>';
    $content .= '<span style="display:inline-block; white-space:nowrap; margin-right:1em;"><span style="display:inline-block; width:10px; height:10px; border-radius:50%; background-color:white; border:1px solid black; vertical-align:middle; margin-right:3px;"></span>None</span>';
    $content .= '<span style="display:inline-block; white-space:nowrap; background-color:' . check_in_get_color_for_membership_status($GLOBALS['EXPIRED_MEMBERSHIP_STATUS']) . '; color:white; padding:2px 6px; border-radius:3px;">Expired/Paused</span>';

    $content .= '</form></div></div>';

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
    $roles_metadata = check_in_db_get_user_roles_metadata($user->ID);
    $membership_status = check_in_get_membership_status($payment_plans, $roles_metadata);

    // Redirect volunteers to a page where they can select whether
    // they want to appear as a member or a volunteer on the check-in page.
    if (($membership_status == $GLOBALS['STAFF_MEMBERSHIP_STATUS']) || ($membership_status == $GLOBALS['VOLUNTEER_MEMBERSHIP_STATUS']))
    {
        return check_in_success_volunteer_found($content, $user, $membership_status);
    }

    // Guests check in immediately without category selection
    if ($membership_status == $GLOBALS['VISITOR_MEMBERSHIP_STATUS'])
    {
        check_in_db_add_check_in($user->ID, $membership_status);

        $content = check_in_add_title($content);
        $content = "{$content}<br>Welcome, {$user->display_name}!";
        $content = "{$content}<br><div style=\"font-weight:bold;\">Please note that membership or a guest pass is required for tool use.</div>";
        $content = check_in_add_redirect_to_home($content, 3);
        return $content;
    }

    // All members (active, expired, paused) get a category selection page
    return check_in_success_member_select_category($content, $user, $membership_status);
}

function check_in_success_volunteer_found($content, $user, $membership_status)
{
    $content = check_in_add_title($content);
    $content = $content .
        '<form action="/check-in/" method="post" onsubmit="document.getElementById(\'check_in_volunteer_as_volunteer\').style.visibility = \'hidden\'; document.getElementById(\'check_in_volunteer_as_member\').style.visibility = \'hidden\'; document.getElementById(\'automatically_checking_in_message\').style.visibility = \'hidden\'; return true;"\>
            <input type="submit" id="check_in_volunteer_as_volunteer" name="check_in_volunteer_as_volunteer" value="Check in as Staff/Maketeer" style="color:white; background-color:' . check_in_get_color_for_membership_status($GLOBALS['VOLUNTEER_MEMBERSHIP_STATUS']) . '">&emsp;&emsp;
            <input type="submit" id="check_in_volunteer_as_member" name="check_in_volunteer_as_member" value="Check in as Member" style="color:white; background-color:' . check_in_get_color_for_membership_status($GLOBALS['ACTIVE_MEMBERSHIP_STATUS']) . '">
            <input type="hidden" id="volunteer_email" name="volunteer_email" value="' . $user->user_email . '">
            <input type="hidden" id="membership_status" name="membership_status" value="' . $membership_status . '">
        </form>
        <script> setTimeout(function() { document.querySelector(\'[name="check_in_volunteer_as_volunteer"]\').click(); }, 5000); </script>
        <br><br><div id="automatically_checking_in_message">Checking in as Staff/Maketeer in 5s...</div>';

    return $content;
}

function check_in_success_volunteer_add_selected_check_in($content, $volunteer_email, $membership_status, $check_in_as_volunteer)
{
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
    if ($check_in_as_volunteer) {
        $content = check_in_add_title($content);

        // Store all mapped categories pipe-delimited, or 'None'. Staff always get 'None'.
        $email_to_categories = $GLOBALS['EMAIL_TO_CATEGORIES'];
        $category = 'None';
        if ($membership_status != $GLOBALS['STAFF_MEMBERSHIP_STATUS']
            && $user->user_email && array_key_exists($user->user_email, $email_to_categories)) {
            $category = implode('|', $email_to_categories[$user->user_email]);
        }
        check_in_db_add_check_in($user->ID, $membership_status, $category);

        $content = "{$content}<br>Checking in {$user->display_name} as Staff/Maketeer!";
        $content = check_in_add_redirect_to_home($content, 1);
        return $content;
    } else {
        // Checking in as member — send to category selection
        $membership_status = $GLOBALS['ACTIVE_MEMBERSHIP_STATUS'];
        return check_in_success_member_select_category($content, $user, $membership_status);
    }
}

function check_in_success_member_select_category($content, $user, $membership_status)
{
    $content = check_in_add_title($content);
    $content = "{$content}Welcome, {$user->display_name}! What space will you be working in today? (You can select up to 4!)<br>";

    if ($membership_status == $GLOBALS['EXPIRED_MEMBERSHIP_STATUS'])
    {
        $content = "{$content}<div style=\"color:red; font-weight:bold;\">Your membership is expired. Please ensure that your payment details are correct and see the front desk.</div>";
        $content = "{$content}<div style=\"font-size:14px; font-style:italic; font-weight:bold;\">If today is within a day or two of your renewal date, your payment may still be processing and you can ignore this.</div><br>";
    }
    elseif ($membership_status == $GLOBALS['PAUSED_MEMBERSHIP_STATUS'])
    {
        $content = "{$content}<div style=\"color:red; font-weight:bold;\">Your membership is paused. Please see the front desk to resume it.</div><br>";
    }

    $content .= '<script>
        var selectedCategories = [];
        var maxCategories = 4;

        function hexToRgba(hex, alpha) {
            var r = parseInt(hex.slice(1, 3), 16);
            var g = parseInt(hex.slice(3, 5), 16);
            var b = parseInt(hex.slice(5, 7), 16);
            return "rgba(" + r + "," + g + "," + b + "," + alpha + ")";
        }

        function toggleCategory(button, categoryName, categoryColor) {
            var index = selectedCategories.indexOf(categoryName);
            if (index > -1) {
                selectedCategories.splice(index, 1);
                button.style.backgroundColor = "white";
            } else {
                if (selectedCategories.length >= maxCategories) {
                    return;
                }
                selectedCategories.push(categoryName);
                button.style.backgroundColor = hexToRgba(categoryColor, 0.25);
            }
            document.getElementById("check_in_member_category").value =
                selectedCategories.length > 0 ? selectedCategories.join("|") : "None";
            resetAutoSubmitTimer();
        }

        var autoSubmitTimer;
        function resetAutoSubmitTimer() {
            if (autoSubmitTimer) clearTimeout(autoSubmitTimer);
            autoSubmitTimer = setTimeout(function() {
                submitCategories();
            }, 10000);
            updateAutoSubmitMessage();
        }

        function updateAutoSubmitMessage() {
            var msg = document.getElementById("auto_category_message");
            if (msg) {
                if (selectedCategories.length > 0) {
                    msg.textContent = "Auto-submitting in 10s...";
                } else {
                    msg.textContent = "Auto-selecting None in 10s...";
                }
            }
        }

        function submitCategories() {
            var input = document.getElementById("check_in_member_category");
            if (selectedCategories.length === 0) {
                input.value = "None";
            }
            document.getElementById("check_in_member_category_submit_btn").click();
        }
    </script>';

    $content .= '<form action="/check-in/" method="post">';
    $content .= '<input type="hidden" name="check_in_member_email" value="' . $user->user_email . '">';
    $content .= '<input type="hidden" name="check_in_member_membership_status" value="' . $membership_status . '">';
    $content .= '<input type="hidden" id="check_in_member_category" name="check_in_member_category" value="None">';

    $icons_url = plugin_dir_url(__FILE__) . 'icons/';
    $category_icons = $GLOBALS['CATEGORY_ICONS'];
    $category_colors = $GLOBALS['CATEGORY_COLORS'];

    $content .= '<div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; max-width:700px;">';
    foreach ($category_icons as $category_name => $icon_file) {
        $color = $category_colors[$category_name];
        $icon_url = $icons_url . $icon_file;
        $content .= '<button type="button" onclick="toggleCategory(this, \'' . $category_name . '\', \'' . $color . '\')" style="display:flex; align-items:center; gap:8px; padding:10px; background-color:white; color:black; border:3px solid ' . $color . '; border-radius:8px; cursor:pointer; font-size:16px; font-weight:bold;">';
        $content .= '<img src="' . $icon_url . '" alt="" style="width:40px; height:40px;">';
        $content .= $category_name;
        $content .= '</button>';
    }
    $content .= '</div>';

    $content .= '<br><input type="submit" id="check_in_member_category_submit_btn" value="Submit" style="font-size:18px; padding:10px 30px; cursor:pointer;">';
    $content .= '</form>';

    $content .= '<br><div id="auto_category_message">Auto-selecting None in 10s...</div>';
    $content .= '<script> resetAutoSubmitTimer(); </script>';

    return $content;
}

function check_in_success_member_category_selected($content, $member_email, $membership_status, $category)
{
    $content = check_in_add_title($content);

    // Parse and validate pipe-delimited categories (max 4)
    $valid_categories = array_keys($GLOBALS['CATEGORY_COLORS']);
    $submitted = explode('|', $category);
    $validated = array();
    foreach ($submitted as $cat) {
        $cat = trim($cat);
        if (in_array($cat, $valid_categories) && $cat !== 'None' && !in_array($cat, $validated)) {
            $validated[] = $cat;
        }
    }
    $validated = array_slice($validated, 0, 4);
    $category = empty($validated) ? 'None' : implode('|', $validated);

    // Re-lookup user by email (same pattern as volunteer flow — needed because this is a new HTTP request)
    $users = check_in_db_find_users_by_email($member_email);
    if (count($users) != 1)
    {
        $content = "{$content}<br>Something went wrong. Please try checking in again.";
        $content = check_in_add_redirect_to_home($content, 5);
        return $content;
    }

    $user = $users[0];
    check_in_db_add_check_in($user->ID, $membership_status, $category);

    $content = "{$content}<br>Checked in {$user->display_name}!";
    $content = check_in_add_redirect_to_home($content, 1);
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

    $content = check_in_stats_dashboard($content, $check_ins);
    $content .= '<hr><h3>Raw Data</h3>';
    $content .= '<div style="font-weight:bold;">Note: Timestamps are in UTC.</div><br>';

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

function check_in_stats_dashboard($content, $check_ins)
{
    $check_ins_json = json_encode($check_ins);
    $category_colors_json = json_encode($GLOBALS['CATEGORY_COLORS']);

    $chart_js_url = plugin_dir_url(__FILE__) . 'chart.umd.min.js';
    $content .= '<script src="' . $chart_js_url . '"></script>';

    $content .= '<h3>Dashboard</h3>';

    // Time range selector
    $content .= '<div style="margin-bottom:10px;">';
    $content .= '<label>Time range: <input type="range" min="1" max="21" value="7" id="dashboardDaysRange" oninput="updateDashboard()"> <span id="dashboardDaysLabel">7 days</span></label>';
    $content .= '</div>';

    // Radio buttons
    $content .= '<div style="margin-bottom:10px;">';
    $content .= '<label style="margin-right:15px;"><input type="radio" name="dashboardGroup" value="Members" checked onchange="updateDashboard()"> Members</label>';
    $content .= '<label style="margin-right:15px;"><input type="radio" name="dashboardGroup" value="Volunteers" onchange="updateDashboard()"> Volunteers</label>';
    $content .= '<label style="margin-right:15px;"><input type="radio" name="dashboardGroup" value="Staff" onchange="updateDashboard()"> Staff</label>';
    $content .= '<label><input type="radio" name="dashboardGroup" value="Guests" onchange="updateDashboard()"> Guests</label>';
    $content .= '</div>';

    // Chart containers
    $content .= '<div style="display:flex; gap:20px; margin-bottom:20px;">';
    $content .= '<div style="flex:1; min-height:300px;"><canvas id="dailyChart"></canvas></div>';
    $content .= '<div style="flex:1; min-height:300px;"><canvas id="categoryChart"></canvas></div>';
    $content .= '</div>';

    // JavaScript
    $content .= '<script>
        var allCheckIns = ' . $check_ins_json . ';
        var categoryColors = ' . $category_colors_json . ';

        var statusGroups = {
            "Members": [1, 2, 5],
            "Volunteers": [4],
            "Staff": [6],
            "Guests": [3]
        };

        var groupBarColors = {
            "Members": "#13723C",
            "Volunteers": "#2DBD45",
            "Staff": "#FC6A03",
            "Guests": "#AD7CFC"
        };

        var dailyChart = null;
        var categoryChart = null;

        function dateToPSTLabel(d) {
            return d.toLocaleDateString("en-US", {
                timeZone: "America/Los_Angeles",
                month: "short",
                day: "numeric"
            });
        }

        function updateDashboard() {
            var daysRange = document.getElementById("dashboardDaysRange");
            var daysLabel = document.getElementById("dashboardDaysLabel");
            var selectedDays = parseInt(daysRange.value);
            daysLabel.textContent = selectedDays + (selectedDays === 1 ? " day" : " days");

            var selectedGroup = document.querySelector("input[name=dashboardGroup]:checked").value;
            var statuses = statusGroups[selectedGroup];

            var cutoff = new Date();
            cutoff.setDate(cutoff.getDate() - selectedDays);
            cutoff.setHours(0, 0, 0, 0);

            var filtered = allCheckIns.filter(function(ci) {
                var d = new Date(ci.check_in_time.replace(" ", "T") + "Z");
                return statuses.indexOf(Number(ci.membership_status)) !== -1 && d >= cutoff;
            });

            // Bar chart: checkins per day
            var dayLabels = [];
            var dayCounts = {};
            for (var i = selectedDays - 1; i >= 0; i--) {
                var d = new Date();
                d.setDate(d.getDate() - i);
                var label = dateToPSTLabel(d);
                if (!(label in dayCounts)) {
                    dayLabels.push(label);
                    dayCounts[label] = 0;
                }
            }
            filtered.forEach(function(ci) {
                var d = new Date(ci.check_in_time.replace(" ", "T") + "Z");
                var label = dateToPSTLabel(d);
                if (label in dayCounts) {
                    dayCounts[label]++;
                }
            });

            // Pie chart: checkins per category (split pipe-delimited)
            var catCounts = {};
            filtered.forEach(function(ci) {
                var cats = (ci.category || "None").split("|");
                cats.forEach(function(cat) {
                    cat = cat.trim();
                    if (!catCounts[cat]) catCounts[cat] = 0;
                    catCounts[cat]++;
                });
            });

            // Render bar chart
            if (dailyChart) dailyChart.destroy();
            dailyChart = new Chart(document.getElementById("dailyChart"), {
                type: "bar",
                data: {
                    labels: dayLabels,
                    datasets: [{
                        label: selectedGroup + " Check-ins",
                        data: dayLabels.map(function(l) { return dayCounts[l]; }),
                        backgroundColor: groupBarColors[selectedGroup]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        title: { display: true, text: selectedGroup + " Check-ins Per Day (PST)" }
                    },
                    scales: {
                        x: { ticks: { maxRotation: 45 } },
                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                    }
                }
            });

            // Render pie chart
            if (categoryChart) categoryChart.destroy();
            var catLabels = Object.keys(catCounts).sort();
            var catData = catLabels.map(function(l) { return catCounts[l]; });
            var catBgColors = catLabels.map(function(l) {
                if (l === "None") return "rgba(0,0,0,0)";
                return categoryColors[l] || "#CCCCCC";
            });
            categoryChart = new Chart(document.getElementById("categoryChart"), {
                type: "pie",
                data: {
                    labels: catLabels,
                    datasets: [{
                        data: catData,
                        backgroundColor: catBgColors
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: { display: true, text: selectedGroup + " Check-ins By Space" },
                        legend: { position: "right" }
                    }
                }
            });
        }

        updateDashboard();
    </script>';

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

function check_in_get_membership_status($payment_plans, $roles_metadata)
{
    $membership_status = $GLOBALS['UNKNOWN_MEMBERSHIP_STATUS'];

    // Check if they are a volunteer
    if (count($roles_metadata) > 0)
    {
        if (array_key_exists($GLOBALS['USER_METADATA_VOLUNTEER_STATUS_KEY'], $roles_metadata))
        {
            // Status must be Active
            if ($roles_metadata[$GLOBALS['USER_METADATA_VOLUNTEER_STATUS_KEY']] == 'Active')
            {
                // Role must be either Staff (67) or Maketeer (66)
                foreach ($roles_metadata as $meta_key => $meta_value)
                {
                    if ($meta_key == $GLOBALS['USER_METADATA_VOLUNTEER_ROLES_KEY'])
                    {
                        // Staff takes precedence over Maketeer
                        if (str_contains($meta_value, 's:2:"67"'))
                        {
                            $membership_status = $GLOBALS['STAFF_MEMBERSHIP_STATUS'];
                            return $membership_status;
                        }
                        elseif (str_contains($meta_value, 's:2:"66"'))
                        {
                            $membership_status = $GLOBALS['VOLUNTEER_MEMBERSHIP_STATUS'];
                            return $membership_status;
                        }
                    }
                }
            }
        }
    }

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

                // If the active payment plan is a paused plan, we can stop iterating.
                // Otherwise we must keep iterating in case there is a paused plan.
                if ($plan[$GLOBALS['ITEM_ID_KEY']] == 73733)
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
    case $GLOBALS['STAFF_MEMBERSHIP_STATUS']:
        return "#FC6A03"; // Tiger
    default:
        // For unknown or invalid status, just pretend they are active. 
        // When we transition to using these colors, old check-ins will be set to unknown status.
        return "#13723C"; // Dark green
    }
}

// Category color definitions
$CATEGORY_COLORS = array(
    '3D Printing'    => '#FF7F00',
    'A/V Studio'     => '#0033CC',
    'Arts & Crafts'  => '#D5E680',
    'Ceramics'       => '#E8A0C0',
    'CNC Routing'    => '#C07800',
    'Electronics'    => '#009640',
    'Lapidary'       => '#800020',
    'Laser Cutting'  => '#E60012',
    'Metalworking'   => '#B8B8B8',
    'Screen Printing'=> '#C0DDE8',
    'Sewing'         => '#800080',
    'Woodshop'       => '#FFC830',
    'Staff'          => '#000000',
    'None'           => '#FFFFFF',
);

// Category icon filenames (relative to plugin icons/ directory)
$CATEGORY_ICONS = array(
    '3D Printing'    => '3d_printing.png',
    'A/V Studio'     => 'av_studio.png',
    'Arts & Crafts'  => 'arts_and_crafts.png',
    'Ceramics'       => 'ceramics.png',
    'CNC Routing'    => 'cnc_routing.png',
    'Electronics'    => 'electronics.png',
    'Lapidary'       => 'lapidary.png',
    'Laser Cutting'  => 'laser_cutting.png',
    'Metalworking'   => 'metalworking.png',
    'Screen Printing'=> 'screen_printing.png',
    'Sewing'         => 'sewing.png',
    'Woodshop'       => 'woodshop.png',
);

// Email-to-categories mappings (add overrides here)
$EMAIL_TO_CATEGORIES = array(
    // 'someone@example.com' => array('3D Printing', 'Laser Cutting'),
);

// Returns the list of category colors for a staff/volunteer user.
function check_in_get_category_colors_for_user($user_email, $is_staff)
{
    $email_to_categories = $GLOBALS['EMAIL_TO_CATEGORIES'];
    $category_colors = $GLOBALS['CATEGORY_COLORS'];

    $colors = array();

    // Staff always get the Staff dot first
    if ($is_staff) {
        $colors[] = $category_colors['Staff'];
    }

    // Add any category dots from the email mapping
    if ($user_email && array_key_exists($user_email, $email_to_categories)) {
        foreach ($email_to_categories[$user_email] as $category) {
            if (array_key_exists($category, $category_colors)) {
                $colors[] = $category_colors[$category];
            }
        }
    }

    // Volunteers with no categories get the Volunteer dot
    if (empty($colors)) {
        $colors[] = $category_colors['None'];
    }

    return $colors;
}

// Returns a CSS background value for the category ring.
// Single category: returns a solid hex color.
// Multiple categories (pipe-delimited): returns a conic-gradient.
function check_in_get_ring_background($category_string) {
    $categories = explode('|', $category_string);
    $colors = array();
    foreach ($categories as $cat) {
        $cat = trim($cat);
        if (array_key_exists($cat, $GLOBALS['CATEGORY_COLORS']) && $cat !== 'None') {
            $color = $GLOBALS['CATEGORY_COLORS'][$cat];
            if (!in_array($color, $colors)) {
                $colors[] = $color;
            }
        }
    }

    if (empty($colors)) {
        return $GLOBALS['CATEGORY_COLORS']['None'];
    }
    if (count($colors) == 1) {
        return $colors[0];
    }

    $angle_per = 360 / count($colors);
    $stops = array();
    for ($i = 0; $i < count($colors); $i++) {
        $start = round($i * $angle_per);
        $end = round(($i + 1) * $angle_per);
        $stops[] = $colors[$i] . ' ' . $start . 'deg ' . $end . 'deg';
    }
    return 'conic-gradient(' . implode(', ', $stops) . ')';
}

// New function to handle three groups: staff/volunteers, members, guests
function check_in_add_check_ins_table_group($content, $check_ins, $group)
{
    $content .= '<table class="check-ins-table"><tbody>';
    $check_ins_counter = 0;
    foreach($check_ins as $check_in)
    {
        $is_staff = ($check_in->membership_status == $GLOBALS['STAFF_MEMBERSHIP_STATUS']);
        $is_volunteer = ($check_in->membership_status == $GLOBALS['VOLUNTEER_MEMBERSHIP_STATUS']);
        $is_member = ($check_in->membership_status == $GLOBALS['ACTIVE_MEMBERSHIP_STATUS'] || $check_in->membership_status == $GLOBALS['EXPIRED_MEMBERSHIP_STATUS'] || $check_in->membership_status == $GLOBALS['PAUSED_MEMBERSHIP_STATUS']);
        $is_guest = ($check_in->membership_status == $GLOBALS['VISITOR_MEMBERSHIP_STATUS']);

        $show = false;
        if ($group === $GLOBALS['CHECKIN_GROUP_STAFF_VOLUNTEER'] && ($is_staff || $is_volunteer)) {
            $show = true;
        } elseif ($group === $GLOBALS['CHECKIN_GROUP_MEMBER'] && $is_member) {
            $show = true;
        } elseif ($group === $GLOBALS['CHECKIN_GROUP_GUEST'] && $is_guest) {
            $show = true;
        }
        if (!$show) {
            continue;
        }

        // Show two buttons per line
        if ($check_ins_counter % 2 == 0) {
            $content .= "<tr class=\"check-ins-table\">";
        }

        // Had to set the button style instead of using CSS class because it was being overridden by Wordpress theme
        if ($is_staff || $is_volunteer) {
            if ($is_staff) {
                $ring_bg = $GLOBALS['CATEGORY_COLORS']['Staff'];
            } else {
                $category = isset($check_in->category) ? $check_in->category : 'None';
                $ring_bg = check_in_get_ring_background($category);
            }
            $wrapper_style = 'display:inline-block; background:' . $ring_bg . '; padding:1px 4px; border-radius:999px; outline:1px solid black;';
            $button_style = 'background-color:white; color:black; border:1px solid black; border-radius:999px;';
            $content .= "<td class=\"check-ins-table\" align=\"left\"><div style=\"{$wrapper_style}\"><button style=\"{$button_style}\" type=\"submit\" id=\"check_out_{$check_in->user_id}\" name=\"check_out_{$check_in->user_id}\" value=\"{$check_in->display_name}\">{$check_in->display_name}</button></div></td>";
        } else {
            // Guests use membership status color for ring; members use stored category
            if ($is_guest) {
                $ring_bg = check_in_get_color_for_membership_status($check_in->membership_status);
            } else {
                $category = isset($check_in->category) ? $check_in->category : 'None';
                $ring_bg = check_in_get_ring_background($category);
            }
            $is_expired = ($check_in->membership_status == $GLOBALS['EXPIRED_MEMBERSHIP_STATUS'] || $check_in->membership_status == $GLOBALS['PAUSED_MEMBERSHIP_STATUS']);
            $bg_color = $is_expired ? check_in_get_color_for_membership_status($check_in->membership_status) : 'white';
            $text_color = $is_expired ? 'white' : 'black';
            $wrapper_style = 'display:inline-block; background:' . $ring_bg . '; padding:1px 4px; border-radius:999px; outline:1px solid black;';
            $button_style = 'background-color:' . $bg_color . '; color:' . $text_color . '; border:1px solid black; border-radius:999px;';
            $content .= "<td class=\"check-ins-table\" align=\"left\"><div style=\"{$wrapper_style}\"><input style=\"{$button_style}\" type=\"submit\" id=\"check_out_{$check_in->user_id}\" name=\"check_out_{$check_in->user_id}\" value=\"{$check_in->display_name}\"></div></td>";
        }

        // Show two buttons per line
        if ($check_ins_counter % 2 == 1) {
            $content .= "</tr>";
        }

        $check_ins_counter += 1;
    }
    $content .= '</tbody></table>';
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
        category varchar(255),
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

function check_in_db_add_check_in($user_id, $membership_status, $category = 'None')
{
    global $wpdb;
    $wpdb->insert("{$wpdb->base_prefix}sm_check_ins", array(
        'user_id' => $user_id,
        'check_in_time' => current_time('mysql', true),
        'membership_status' => $membership_status,
        'category' => $category,
    ));
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
        INNER JOIN {$wpdb->base_prefix}usermeta
        ON {$wpdb->base_prefix}sm_check_ins.user_id = {$wpdb->base_prefix}usermeta.user_id
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
        SELECT {$wpdb->base_prefix}sm_check_ins.user_id, {$wpdb->base_prefix}users.display_name, {$wpdb->base_prefix}users.user_email, {$wpdb->base_prefix}sm_check_ins.membership_status, {$wpdb->base_prefix}sm_check_ins.category
        FROM {$wpdb->base_prefix}sm_check_ins
        INNER JOIN {$wpdb->base_prefix}users 
        ON {$wpdb->base_prefix}sm_check_ins.user_id = {$wpdb->base_prefix}users.ID
        WHERE {$wpdb->base_prefix}sm_check_ins.check_out_time IS NULL
        AND {$wpdb->base_prefix}sm_check_ins.check_in_time IS NOT NULL
        AND {$wpdb->base_prefix}sm_check_ins.check_in_time > STR_TO_DATE('{$today_midnight_utc_string}', '%%Y-%%m-%%d %%h:%%i:%%s')
        ORDER BY {$wpdb->base_prefix}sm_check_ins.membership_status DESC, {$wpdb->base_prefix}users.display_name;");

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

function check_in_db_get_user_roles_metadata($user_id)
{
    global $wpdb;
    $sql = $wpdb->prepare("
        SELECT {$wpdb->base_prefix}usermeta.meta_key, {$wpdb->base_prefix}usermeta.meta_value
        FROM {$wpdb->base_prefix}usermeta
        WHERE {$wpdb->base_prefix}usermeta.user_id = {$user_id}
        AND ({$wpdb->base_prefix}usermeta.meta_key = '{$GLOBALS['USER_METADATA_VOLUNTEER_STATUS_KEY']}' OR {$wpdb->base_prefix}usermeta.meta_key LIKE '{$GLOBALS['USER_METADATA_VOLUNTEER_ROLES_KEY']}');");

    $results = $wpdb->get_results($sql);

    $roles_metadata = array();
    foreach($results as $result)
    {
        $roles_metadata[$result->meta_key] = $result->meta_value;
    }

    return $roles_metadata;
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
            // volunteer_email and membership_status should have been sent as part of the same form. If not, just fall through.
            if (array_key_exists('volunteer_email', $_POST) && array_key_exists('membership_status', $_POST))
            {
                return check_in_success_volunteer_add_selected_check_in($content, $_POST["volunteer_email"], $_POST["membership_status"], ($post_key == 'check_in_volunteer_as_volunteer'));
            }
        }

        // Handle the form that allows members to select a category/space.
        if ($post_key == 'check_in_member_category')
        {
            if (array_key_exists('check_in_member_email', $_POST) && array_key_exists('check_in_member_membership_status', $_POST))
            {
                return check_in_success_member_category_selected($content, $_POST['check_in_member_email'], $_POST['check_in_member_membership_status'], $post_value);
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
