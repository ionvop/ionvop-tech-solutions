<?php

test('the homepage presents services, portfolio projects, and contact links', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('Tagum City')
        ->assertSee('PHP 2,000')
        ->assertSee('USD 20')
        ->assertSee('GCash')
        ->assertSee('Nowcast')
        ->assertSee('Inventory Management System')
        ->assertSee('Attendance Monitoring System')
        ->assertSee('https://github.com/ionvop/nowcast')
        ->assertSee('https://github.com/ionvop/inventory-management-system')
        ->assertSee('https://github.com/ionvop/yet-another-attendance-monitoring-system')
        ->assertSee('mailto:ionvop@gmail.com')
        ->assertSee('https://facebook.com/ionvop.omadle')
        ->assertSee('https://discord.com/users/301203021608779776');
});

test('the chat route renders its placeholder page', function () {
    $this->get(route('chat'))
        ->assertOk()
        ->assertSee('Conversation space')
        ->assertSee(route('home'));
});

test('the admin route renders its placeholder page', function () {
    $this->get(route('admin'))
        ->assertOk()
        ->assertSee('Admin workspace')
        ->assertSee(route('home'));
});
