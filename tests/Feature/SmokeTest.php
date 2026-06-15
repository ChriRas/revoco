<?php

it('serves the withdrawal form on the root route', function () {
    $this->withoutVite()->get('/')->assertOk();
});
