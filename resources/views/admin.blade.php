@extends('layouts.app')

@section('title', 'Admin Workspace | Ionvop')
@section('description', 'Ionvop project administration workspace placeholder.')

@section('content')
    <main class="placeholder-page" id="main-content">
        <div class="site-container placeholder-content">
            <p class="eyebrow">Project management</p>
            <h1>Admin workspace</h1>
            <p>The project management workspace is not available yet.</p>
            <div class="placeholder-actions">
                <a class="placeholder-home" href="{{ route('home') }}">Return to home</a>
            </div>
        </div>
    </main>
@endsection