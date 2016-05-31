@extends('layouts.passwordreset')

@section('status')

   @if (Session::has('error'))
        <p style="color: red;">{{ Session::get('error') }}</p>
    @elseif (Session::has('status'))
        <p>{{ Session::get('status') }}</p>
    @else
         <p>{{$status}}</p>
    @endif
@stop

@section('content')
    {{ Form::open() }}
        <div>
            {{ Form::label('email', 'Email Address') }}<br>
            {{ Form::text('email', null, ['required' => true]) }}
        </div>

        <div>
            {{ Form::submit('Reset') }}
        </div>
    {{ Form::close() }}

@stop
