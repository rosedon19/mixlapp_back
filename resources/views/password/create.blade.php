@extends('layouts.passwordcreate')

@section('content')
   
    
     {{ Form::open() }}
        <input type="hidden" name="email" value="{{ $email }}">

        <div>
            {{ Form::label('email', 'Email Address:') }}<br>
            {{ Form::text('email',$email) }}
        </div>

        <div>
            {{ Form::label('password', 'Password:') }}<br>
            {{ Form::password('password') }}
        </div>

        <div>
            {{ Form::label('password_confirmation', 'Password Confirmation:') }}<br>
            {{ Form::password('password_confirmation') }}
        </div>

        <div>{{ Form::submit('Create New Password') }}</div>
    {{ Form::close() }}

@stop

@section('status')
    <p>Please create a new password for your account.</p>
@stop