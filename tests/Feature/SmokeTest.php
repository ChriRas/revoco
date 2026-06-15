<?php

it('serves the welcome page', function () {
    $this->withoutVite()->get('/')->assertOk();
});
