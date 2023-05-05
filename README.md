**Setup instructions:**
1. Sign in with administrator Wordpress account.
2. Go to Plugins > Add New > Upload Plugin.
3. Create a zip file containing the seattle-makers-check-in-plugin.php file. It should be called seattle-makers-check-in-plugin.zip.
4. Select the zip file, upload, install, and Activate it.
5. In the top bar, hover over "+New" and click on New User. 
  a. This will be the non-administrator user who has access to the check in page. It does not need a valid email address, but it does need a unique one.
  b. In the user profile page, replace the Email field with the desired email address.
  c. Click on Account and enter a Password. Uncheck the box for displaying the admin bar and making the account public. Hit Save, back out, hit Save on the Profile too.
  d. If these login details are forgotten, you will need to repeat some of these instructions to reset the user who has access.
6. Go to Dashboard > Presspoint > Dashboard > Advanced Search.
7. Search for the new user created above by their email address.
8. Click the triangle next to Search > Save As.
9. Give it a sensible name like "check-in-admin-2023-04-22-1504" and allow Presspoint admins to view and edit.
10. Go to Pages > Add New.
11. Set the title to "Check-In".
12. In the right pane, under Restrict Content Options > Allow, select the "check-in-admin-2023-04-22-1504" search (or whatever name you used).
13. Scroll down to Page Options > Title/Description > Set Title/Description Visibility to Hidden.
14. Hit Publish. This page should now only be accessible to the privilege-less user.
15. Go to Pages > Add New.
16. Set the title to Check-In Stats.
17. In the right pane, under Publish > Visibility, set it to Private and hit OK.
18. Hit Publish. This page should now only be accessible to administrators. It currently displays the database contents for the last 1000 check-ins.

**Uninstall instructions:**
1. Sign in with administrator Wordpress account.
2. Go to Plugins > Installed Plugins > Seattle Makers Check In Plugin > Deactivate.
3. Go to Plugins > Installed Plugins > Seattle Makers Check In Plugin > Delete, and confirm. This will also delete the database table of check-ins.
