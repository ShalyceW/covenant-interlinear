<?php

use App\Models\User;

test('guests cannot access the Filament administration panel', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

test('ordinary users cannot access the Filament administration panel', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

test('administrators can access the Filament administration panel', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get('/admin')->assertOk();
});
