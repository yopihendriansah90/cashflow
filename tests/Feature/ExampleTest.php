<?php

test('legacy dashboard requires authentication', function () {
    $this->get('/ecommerce')->assertRedirect(route('login'));
});
