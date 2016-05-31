@extends('layouts.login')

@section('content')
    {{ Form::open(['route' => 'sessions.store', 'class' => 'custom','autocomplete'=>'off']) }}
            
        @if($error_message = Session::get('flash_message'))
            <div class="error ">
                <small class="error">{{ $error_message }}</small>
            </div>
        @endif    

        <div class="input_container">
            <input type="text" id="email" name="email"  class="input" placeholder="Email Address">
            <img src="{{asset('../images/icons/login/user_name_icon.png')}}" class="input_img" style='width:23px; height:20px;'>
        </div>

        <div class="input_container">
            <input type="password" id="password" name="password"  class="input" style='margin-bottom:10px;' placeholder="Password">
            <img src="{{asset('../images/icons/login/password_icon.png')}}" class="input_img" style='width:16px; height:20px;'>
        </div>
<!--
        <input type='text' id="txtUserName" name="txtUserName" class='loginInputUserName' />
        <input type='password' id="txtPassword" name="txtPassword" class='loginInputPassword'  />
-->             

        <div class='rememberMePanel'>
            <label for='chkRememberMe'>
                <input type='checkbox' name='chkRememberMe'>
                Remember Me
            </label>
            <a href='{{url('password/remind')}}'>Forgot Password?</a>
        </div>
        {{ Form::submit('SIGN IN',array('class'=>'button expand alert')) }}
        <div class='earlyReleasePanel'>
            This is an early release of our <strong>new Menu-Mailer</strong>.
            <a href="{{ Config::get('app.store_url') }}product/premium-menu-mailer" target="_blank">Sign up</a> and be one of the first to try it!
        </div>
        <input type="hidden" name="_token" value="{{ csrf_token() }}">
            


    {{ Form::close() }}


@stop
