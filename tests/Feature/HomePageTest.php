<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the home page renders the landing sections', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Software built around how your business')
        ->assertSee('PHP 2,000')
        ->assertSee('PHP 1,000')
        ->assertSee('USD 20')
        ->assertSee('USD 10')
        ->assertSee('Nowcast')
        ->assertSee('Inventory Management System')
        ->assertSee('Attendance Monitoring System')
        ->assertSee('ionvop@gmail.com');
});

test('the chat page renders the conversation shell', function () {
    $this->get(route('chat'))
        ->assertOk()
        ->assertSee('Project conversation')
        ->assertSee('Project details')
        ->assertSee('Not started yet')
        ->assertSee('Describe your business problem')
        ->assertDontSee('Chat is on the way');
});

test('the chat page drops the site header and footer', function () {
    $this->get(route('chat'))
        ->assertOk()
        ->assertDontSee('All rights reserved')
        ->assertDontSee('Start a chat')
        ->assertSee('Back to site');
});

test('the admin page renders a placeholder', function () {
    $this->get(route('admin'))
        ->assertOk()
        ->assertSee('Admin dashboard is on the way');
});
