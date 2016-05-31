<?php

use Carbon\Carbon;
use App\Models\User;

class HomeController extends BaseController {

  public function showWelcome()
  {

        return View::make('home');
  }


  public function weeklymm()
  {

      /* Do a product check for this page */
      $user = User::find(Auth::user()->id);

      $dt = new \DateTime($user->last_login);
      $last_login = Carbon::instance($dt);

      $dt = new \DateTime($user->created_at);
      $created_at = Carbon::instance($dt);

      $days = $last_login->diffInDays($created_at);
  
      $roles = Session::get('subscriptions')['user_roles'];

 

      //Log::info($roles);

      //Log::info($days, array('context' => Auth::user()->id));
      //print"<pre>";
      //print_r($roles);
      //exit;

      if($days >=1){

        Log::info($roles, array('context' => Auth::user()->id));
        

        if(is_array($roles) && !in_array(4,$roles)){ 
           if(Auth::user()->id != 5263){
            return Redirect::to('/memberships?upgrade=basic');
           }
          
        }
      }


      $data = DB::table('menuMailerContent')
                        ->where('mmcActive','=','1')
                        ->orderBy('mmcDate','DESC')
                        ->get();


       $find = ['/images/','width="110%"'];
       $replace = ['//menumailer.savingdinner.com/images/','width="100%"'];

       $content = str_replace($find, $replace, $data[0]->mmcContent);

       $data[0]->content = $content;

       //
       $text = DB::table('sitetext')
                        ->select('content')
                        ->where('id','=','1')
                        ->get();
       //

       return View::make('weeklymm.index',array('data'=>$data[0],'text'=>$text[0]));
  }

  //
  public static function myRecipes($start_date = null){

    $cal = new CalendR\Calendar;
    $start_date == null ? $date = date("Y-m-d H:i:s", strtotime(date('Y-m-d 00:00:00')))  : $date = date("Y-m-d H:i:s", strtotime($start_date)) ;

    $date = new DateTime($date);

    $week = $cal->getWeek($date);
    $day_begin = clone $week->getBegin();
    $day_end = clone $week->getEnd();
    $day_end = $day_end->modify('-1 day');

    $planner_items = array();
    $shopping_items = array();
    $planner_items = Planner::getItems($day_begin->format('Y-m-d'),$day_end->format('Y-m-d'))->get();
    $shopping_items = Shopping::all()->recipes;
    $custom_items = Planner::getCustomItems()->get();

    //var_dump($custom_items);


    return View::make('layouts.partials.myrecipes',array('planner_items'=>$planner_items
                      ,'shopping_items'=>$shopping_items
                      ,'custom_items'=>$custom_items));

  }

  public function showStatus()
  {
    Auth::user();
    return View::make('status');
  }
  
  public function infusionpay()
  {
	  echo "testinggggggggg";
  }

   public function tour(){
    $toggle = Input::get('toggle');
    $user = User::find(Auth::user()->id);

    if($toggle == 'off'){
       $user->tour = 'off';
    }else{
      $user->tour = 'on';
    }

    $user->save();
    return $toggle;
  }




    public function arb()
    {


        // Get the subscription ID if it is available.
        // Otherwise $subscription_id will be set to zero.
        $subscription_id = (int) Input::get('x_subscription_id');

        // Check to see if we got a valid subscription ID.
        // If so, do something with it.
        if ($subscription_id){
            // Get the response code. 1 is success, 2 is decline, 3 is error
            $response_code = (int) Input::get('x_response_code');

            // Get the reason code. 8 is expired card.
            $reason_code = (int) Input::get('x_response_reason_code');

            if ($response_code == 1){
                $response_status = 'approved';
            }else if ($response_code == 2){
                $response_status = 'declined';
            }else if ($response_code == 3 && $reason_code == 8){
                $response_status = 'expired';
            }else {
                $response_status = 'other';
            }

            $timestamp = Carbon::now();

            ARB::insertGetId(array('subscription_id' => Input::get('x_subscription_id')
                                   ,'response_code' => Input::get('x_response_code')
                                   ,'response_status' => $response_status
                                   ,'email'=> Input::get('x_email')
                                   ,'data'=>json_encode($_POST)
                                   ,'created_at'=> $timestamp
                                   )
            );
        }

        return;

    }

    public function help()
    {
        return View::make('help');
    }

    public function is(){
        //Event::fire('user.signup_success',[array('test'=>'works'),array('nice'=>'yes')]);
	$email = Input::get('email');
	
        $user = User::where('email','=',$email)->first();
	
		
	
        $param = true;
        if (!count($user)) { 
          $param = false;
        }
         
        
            $iSDK = new iSDK;
            $app_name = 'fk152';
            $api_key = '7ebde7af666d64b988f4756e9e83a797';
            

            if($iSDK->cfgCon($app_name, $api_key)) {
            	
            
                $returnFields = array('Id','SubscriptionPlanId','ProductId','Status','ContactId','FirstName','LastName','Email','PaidThruDate');
                $query = array('Email' => $email);
                $products = $iSDK->dsQuery("RecurringOrderWithContact",10,0,$query,$returnFields);
		
		//print"<pre>";
		//print_r($products);
		//exit;
		
                //var_dump($products);
            }else {
                Log::error('iSDK connection failed');
            }

            
            $roles = array();

            if(isset($user)){

              $reset = Input::get('reset');
		
              if(isset($reset)){
                  

                  $user->login_status = 'default';
                  $user->password = 'reset';
                  $user->usrStatus = 'active';
                  $user->usrPass = '@reset';
                  $user->last_login = NULL;
                  $user->save();

                  // return Redirect::to('/admin/users')->with('flash_message', 'User Details Has Been Updated');
              }

              $roles = DB::table('roles')
                      ->select(array('name','roles.id'))->distinct()
                      ->leftJoin('assigned_roles', 'roles.id', '=', 'assigned_roles.role_id')
                      ->where('user_id','=',$user->id)
                      ->get();
            }
            

          return View::make('admin.users.debug',array('email'=>$email,'roles'=>$roles,'user'=>$user,'returnFields'=>$returnFields,'products'=>$products,'param'=>$param));

    }
}