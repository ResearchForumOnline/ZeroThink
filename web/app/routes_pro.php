<?php
// Pro upgrade + PayPal IPN
$r->get('/upgrade', fn()=> require_auth(fn()=> view('upgrade')));
$r->get('/upgrade/success', fn()=> require_auth(fn()=> view('upgrade_success')));
$r->post('/ipn/paypal', function(){
  require_once __DIR__ . '/core/ipn_paypal.php';
  ipn_paypal_handle();
});
