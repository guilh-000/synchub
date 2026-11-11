<?php

test('the home page leads to the dashboard', function () {
    $this->get(route('home'))->assertRedirect(route('dashboard'));
});
