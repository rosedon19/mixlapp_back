@extends('layouts.passwordreset')

@section('content')
    @if (Session::has('error'))
        <p style="color: red;">{{ Session::get('error') }}</p>
    @endif
    
     {{ Form::open() }}
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            {{ Form::label('email', 'Email Address:') }}<br>
            {{ Form::text('email') }}
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
    <p>Please complete the form below to create a new password for your account.</p>
@stop