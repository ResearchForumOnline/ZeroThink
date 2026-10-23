<?php
// Send user to Google
$clientID = '570621942326-vdfh5qhmq0mnncovpbr6k2sqi77lhko1.apps.googleusercontent.com';
$redirect = 'https://zerothink.talktoai.org/auth/google/callback'; // Must match Google Console exactly
$url = "https://accounts.google.com/o/oauth2/v2/auth?client_id=$clientID&redirect_uri=$redirect&response_type=code&scope=email%20profile";
header("Location: $url");
exit;
