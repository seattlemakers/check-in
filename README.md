**Setup instructions:**
1. Sign in with administrator Wordpress account.
2. Go to Plugins > Add New > Upload Plugin.
3. Create a zip file containing the seattle-makers-check-in-plugin.php file. It should be called seattle-makers-check-in-plugin.zip.
4. Select the zip file, upload, install, and Activate it.
5. Go to Pages > Add New.
6. Set the title to "Check-In".
7. In the right pane, under Restrict Content Options > Allow, set it to Anyone (Unrestricted), and under Publish > Visibility, set it to Password Protected, enter a password and hit OK.
8. Scroll down to Page Options > Title/Description > Set Title/Description Visibility to Hidden.
9. Hit Publish. This page should now only be accessible after the password is entered.
10. Go to Pages > Add New.
11. Set the title to Check-In Stats.
12. In the right pane, under Publish > Visibility, set it to Private and hit OK.
13. Hit Publish. This page should now only be accessible to administrators. It currently displays the database contents for the last 1000 check-ins.

**Uninstall instructions:**
1. Sign in with administrator Wordpress account.
2. Go to Plugins > Installed Plugins > Seattle Makers Check In Plugin > Deactivate.
3. Go to Plugins > Installed Plugins > Seattle Makers Check In Plugin > Delete, and confirm. This will also delete the database table of check-ins.

**Stats instructions:**
1. Sign into https://seattlemakers.org with AVStudios or other admin account.
2. Go to https://seattlemakers.org/check-in-stats. By default this shows the 1000 most recent check-ins.
3. Go to https://seattlemakers.org/check-in-stats?offset=100&limit=200 to skip the first 100 most recent check-ins and then show the next 200.
