<?php


Route::get('/', 'HomeController@showWelcome');

Route::controller('api', 'APIController');
