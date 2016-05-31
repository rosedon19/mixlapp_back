<?php namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\ClassLoader;
use Menumailer\Utilities\Util;
use Menumailer\Exceptions\MenuMailerException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;

class AppServiceProvider extends ServiceProvider {

	/**
	 * Bootstrap any application services.
	 *
	 * @return void
	 */
	public function boot()
	{
		//
	}

	/**
	 * Register any application services.
	 *
	 * This service provider is a great spot to register your various container
	 * bindings with the application. As you can see, we are registering our
	 * "Registrar" implementation here. You can add your own bindings too!
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->bind(
			'Illuminate\Contracts\Auth\Registrar',
			'App\Services\Registrar',
			'App\Http\composers'
		);


/*
|--------------------------------------------------------------------------
| Register The Laravel Class Loader
|--------------------------------------------------------------------------
|
| In addition to using Composer, you may use the Laravel class loader to
| load your controllers and models. This is useful for keeping all of
| your classes in the "global" namespace without Composer updating.
|
*/

ClassLoader::addDirectories(array(

	app_path().'/Commands',
	app_path().'/Controllers',
	app_path().'/Models',
	

));

/*
|--------------------------------------------------------------------------
| Application Error Logger
|--------------------------------------------------------------------------
|
| Here we will configure the error logger setup for the application which
| is built on top of the wonderful Monolog library. By default we will
| build a rotating log file setup which creates a new file each day.
|
*/

$logFile = 'log-'.php_sapi_name().'.txt';

Log::useDailyFiles(storage_path().'/logs/'.$logFile);

/*
|--------------------------------------------------------------------------
| Application Error Handler
|--------------------------------------------------------------------------
|
| Here you may handle any errors that occur in your application, including
| logging them or displaying custom views for specific errors. You may
| even register several error handlers to handle different types of
| exceptions. If nothing is returned, the default error view is
| shown, which includes a detailed stack trace during debug.
|
*/
/*
App::error(function(Exception $exception, $code)
{
	Log::error($exception);
});

*/
/*
|--------------------------------------------------------------------------
| Custom Error Handlers
|--------------------------------------------------------------------------
|
*/

/*
App::missing(function($exception){
    //return Response::view('missing', array(), 404);
});
*/
/*App::error(function(MenuMailerException $e){
    return '<h1>Error '.$e->getCode().'</h1><p>'.$e->getMessage().'</p>';
});
*/
/*
|--------------------------------------------------------------------------
| Maintenance Mode Handler
|--------------------------------------------------------------------------
|
| The "down" Artisan command gives you the ability to put an application
| into maintenance mode. Here, you will define what is displayed back
| to the user if maintenace mode is in effect for this application.
|
*/
/*
App::down(function()
{
	return Response::view('maintenance', array(), 503);
    //return Response::make("Be right back!", 503);
});
*/
/*
|--------------------------------------------------------------------------
| Require The Filters File
|--------------------------------------------------------------------------
|
| Next we will load the filters file for the application. This gives us
| a nice separate location to store our route and application filter
| definitions instead of putting them all in the main routes file.
|
*/

require app_path().'/Http/filters.php';


/*
|--------------------------------------------------------------------------
| Require The Composers
|--------------------------------------------------------------------------
|
*/


/*
|--------------------------------------------------------------------------
| Require The Events
|--------------------------------------------------------------------------
|
*/

\Blade::setRawTags('{{', '}}');
\Blade::setContentTags('{{{', '}}}');
\Blade::setEscapedContentTags('{{{', '}}}');	
	}

}
