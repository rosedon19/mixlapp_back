<?php namespace App\Providers;

use Illuminate\Routing\Router;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Menumailer\Exceptions\MenuMailerException;
use App;
class RouteServiceProvider extends ServiceProvider {

	/**
	 * This namespace is applied to the controller routes in your routes file.
	 *
	 * In addition, it is set as the URL generator's root namespace.
	 *
	 * @var string
	 */
	protected $namespace = null;

	/**
	 * Define your route model bindings, pattern filters, etc.
	 *
	 * @param  \Illuminate\Routing\Router  $router
	 * @return void 
	 */
	public function boot(Router $router)
	{
		parent::boot($router);

		

        /*
        |--------------------------------------------------------------------------
        | Application & Route Filters
        |--------------------------------------------------------------------------
        |
        | Below you will find the "before" and "after" events for the application
        | which may be used to do any work before or after a request into your
        | application. Here you may also register your custom route filters.
        |
        */
/*
        App::before(function($request)
        {
        	//
        });


        App::after(function($request, $response)
        {
        	//
        });
*/
        /*
        |--------------------------------------------------------------------------
        | Authentication Filters
        |--------------------------------------------------------------------------
        |
        | The following filters are used to verify that the user of the current
        | session is logged into this application. The "basic" filter easily
        | integrates HTTP Basic authentication for quick, simple checking.
        |
        */

        Route::filter('auth', function()
        {
            if (Auth::guest()) return Redirect::guest('login');
        });

        Route::filter('auth.basic', function()
        {
        	return Auth::basic();
        });

        /*
        |--------------------------------------------------------------------------
        | Guest Filter
        |--------------------------------------------------------------------------
        |
        | The "guest" filter is the counterpart of the authentication filters as
        | it simply checks that the current user is not logged in. A redirect
        | response will be issued if they are, which you may freely change.
        |
        */

        Route::filter('guest', function()
        {
            if (Auth::check()) return Redirect::to('/');
        });

        /*
        |--------------------------------------------------------------------------
        | CSRF Protection Filter
        |--------------------------------------------------------------------------
        |
        | The CSRF filter is responsible for protecting your application against
        | cross-site request forgery attacks. If this special token in a user
        | session does not match the one given in this request, we'll bail.
        |
        */

        Route::filter('csrf', function()
        {
        	if (Session::token() != Input::get('_token'))
        	{
        		throw new Illuminate\Session\TokenMismatchException;
        	}
        });


        Route::filter('admin', function() {

            if(!Entrust::hasRole('Admin') ){
                return Redirect::to('/');
            }
        });

        Route::filter('usertype', function($route){

           $user = User::find(Auth::user()->id);

           $event = Event::fire('user.login', array($user));
           $action = Route::getCurrentRoute()->getPath();

           if($user->usrStatus == 'default' && $action != 'account'){
                return Redirect::to('/account');
           }

        });

        Route::filter('subscription', function($route){

            if (Entrust::hasRole('Admin') ){ 
               return;
            }
        	
            if(Session::get('subscriptions') && Entrust::can('can_read')){
                return;
            }

            $user = User::find(Auth::user()->id);
            $email = $user->email;

            //Log::error('-- user --');
            //Log::error($email);
            //Log::error('-- end user --');
            //for testing iSDK use:
            //$email = 'null@mcfarlan.ca';

            //for testing locally comment out $iSDK code and use the following account / info
            //id:7464 basic@theyintegrated.com || id:7465 premium@theyintegrated.com
            //$products = array(array('Status'=>'Active','ProductId'=>'1584'));
            //$products = array(array('Status'=>'Active','ProductId'=>'1582'));
            //$products = array(array('Status'=>'Active','ProductId'=>'1584'),array('Status'=>'Active','ProductId'=>'1582'));

            $products = array();

            $iSDK = new iSDK;
            $app_name = 'fk152';
            $api_key = '7ebde7af666d64b988f4756e9e83a797';

            if($iSDK->cfgCon($app_name, $api_key)) {
                $returnFields = array('Id','SubscriptionPlanId','ProductId','Status','ContactId','FirstName','LastName','Email','PaidThruDate');
                $query = array('Email' => $email);
                $products = $iSDK->dsQuery("RecurringOrderWithContact",10,0,$query,$returnFields);
            }else {
                Log::error('iSDK connection failed');
            }

            $user_products = array();

            foreach($products as $product){
                $status = $product['Status'];

                if($status != 'Active'){
                    continue;
                }

                $user->contactID = $product['ContactId'];
                $user->save();

                $product_id = $product['ProductId'];

                switch($product_id){
                  case '1586':
                    //Premium
                    $user_products[] = 3;
                    $user_products[] = 4;
                    break;
                  case '1575':
                    //Basic
                    $user_products[] = 4;
                    break;
                  case '1588':
                    //Basic
                    $user_products[] = 4;
                    break;
                  case '1577':
                    //Premium
                    $user_products[] = 3;
                    $user_products[] = 4;
                    break;
                }
            }

            //Log::error('-- products --');
            //Log::error($user_products);
            //Log::error('-- end products --');


            Session::put('subscriptions',
                array(
                    'infusionsoft_products'=> $products,
                    'user_roles'=> $user_products
            ));

            //update roles
            $user->roles()->sync($user_products);
        });
//
       
	}

	/**
	 * Define the routes for the application.
	 *
	 * @param  \Illuminate\Routing\Router  $router
	 * @return void
	 */
	public function map(Router $router)
	{
		$router->group(['namespace' => $this->namespace], function($router)
		{
			require app_path('Http/routes.php');
		});
	}



}
