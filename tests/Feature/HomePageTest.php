<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the home page renders the landing sections', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('ionvop Tech Solutions — Custom software for local businesses')
        ->assertSee('>ionvop Tech Solutions</span>', false)
        ->assertSee('ionvop Tech Solutions. All rights reserved.')
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
        ->assertSee('Chat — ionvop Tech Solutions')
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

test('the admin page requires sign-in', function () {
    $this->get(route('admin'))
        ->assertRedirect(route('admin.login'));
});
