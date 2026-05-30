<?php

test('the home route redirects to setup when jira is not configured', function () {
    $response = $this->get('/');

    $response->assertRedirect();
});
