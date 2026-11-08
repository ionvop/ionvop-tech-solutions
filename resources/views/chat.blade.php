@extends('layouts.app')

@section('title', 'Conversation Space | Ionvop')
@section('description', 'Contact Ionvop about a software or website project.')

@section('content')
    <main class="placeholder-page" id="main-content">
        <div class="site-container placeholder-content">
            <p class="eyebrow">Client conversations</p>
            <h1>Conversation space</h1>
            <p>Project chat is being prepared. For now, send a note by email and tell me a little about the work you have in mind.</p>
            <div class="placeholder-actions">
                <a class="btn btn-secondary" href="mailto:ionvop@gmail.com">Email Ionvop</a>
                <a class="placeholder-home" href="{{ route('home') }}">Return to home</a>
            </div>
        </div>
    </main>
@endsection