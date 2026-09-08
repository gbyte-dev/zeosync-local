<?php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('admin_settings', function (Blueprint $table) {
        $table->id();
        $table->string('option_key')->unique();
        $table->text('option_value')->nullable();
        $table->timestamps();
    });
});

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
