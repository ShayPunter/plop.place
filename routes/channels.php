<?php

use Illuminate\Support\Facades\Broadcast;

// Public canvas channel - no auth required
Broadcast::channel('canvas', function () {
    return true;
});

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
