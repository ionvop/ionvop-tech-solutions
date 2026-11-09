<?php

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

test('the chat page renders a placeholder', function () {
    $this->get(route('chat'))
        ->assertOk()
        ->assertSee('Chat is on the way');
});

test('the admin page renders a placeholder', function () {
    $this->get(route('admin'))
        ->assertOk()
        ->assertSee('Admin dashboard is on the way');
});
